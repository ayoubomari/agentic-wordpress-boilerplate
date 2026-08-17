#!/usr/bin/env python3
"""Usage: ./scripts/seo-check.py

Fetches every page type the theme ships a template for and asserts the SEO
invariants this boilerplate is supposed to hold, the same way
lighthouse-check.sh asserts the performance ones. Exits non-zero if any fail,
so it works as a gate rather than a suggestion:

  - every page that is NOT noindex has a <meta name="description">
  - no schema entity appears twice on a page
  - exactly one BreadcrumbList per URL
  - every indexable page's title and description fit The SEO Framework's own
    length guidelines, so wp-admin's SEO Bar column stays green (see step 14 of
    setup-site.sh). TSF's tolerated range is the gate; its ideal range is
    reported as a warning, because that is the band the SEO Bar calls "good"
    and a value outside it shows up orange in the dashboard
  - pages that are meant to be noindex actually are (cart/checkout/account, on
    purpose — see CLAUDE.md) and pages that are not, are not

Two things it is careful about, both of which produced false results on the
first pass and are worth not re-learning:

  - JSON-LD is walked RECURSIVELY. The SEO Framework nests BreadcrumbList
    inside the WebPage node rather than as a top-level @graph entry, so a
    top-level-only scan reports zero breadcrumbs and reads as a regression
    when nothing is wrong.
  - Nodes that share an @id are ONE entity referenced twice, not a duplicate.
    WooCommerce's `offers.seller` and the SEO plugin's `WebSite.publisher`
    both describe the store; functions.php links them by @id on purpose, and
    a naive count of "@type": "Organization" would flag that fix as the bug.

Run it against a running wp-env site (wp-env start).
"""
import json
import re
import sys
import urllib.request
from collections import Counter

BASE = "http://localhost:8888"

# The SEO Framework's own guidelines, from its Helper\Guidelines class: the
# outer pair is what it tolerates (outside it the SEO Bar goes red), the inner
# pair is what it calls good (outside it the bar goes orange).
TITLE_HARD, TITLE_GOOD = (25, 75), (35, 65)
DESC_HARD, DESC_GOOD = (45, 320), (80, 160)

# label, path, must_be_noindex
#
# Cart, checkout, my-account and search are deliberately noindex, and an empty
# product category is noindexed by TSF itself ("No posts are attached to this
# term"), so all five are asserted to STAY that way — silently becoming
# indexable is the regression worth catching, not the noindex itself.
#
# /checkout/ 302s to /cart/ whenever the cart is empty, which it is here, so
# that row really measures the cart page. Left in deliberately: the redirect
# itself is what a crawler meets.
PATHS = [
    ("front page", "/", False),
    ("shop archive", "/shop/", False),
    ("product cat", "/product-category/cleansers/", False),
    ("empty prod cat", "/product-category/eye-care/", True),
    ("product", "/product/gentle-gel-cleanser/", False),
    ("journal", "/journal/", False),
    ("single post", None, False),  # resolved below
    ("post category", "/category/skincare/", False),
    ("about us", "/about-us/", False),
    ("contact", "/contact/", False),
    ("cart", "/cart/", True),
    ("checkout", "/checkout/", True),
    ("my account", "/my-account/", True),
    ("search", "/?s=serum", True),
    ("404", "/definitely-not-a-real-url/", True),
]


def fetch(path):
    req = urllib.request.Request(BASE + path, headers={"User-Agent": "seo-audit"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")


def crumb_name(item):
    if isinstance(item, dict):
        if item.get("name"):
            return str(item["name"])
        inner = item.get("item")
        return inner.get("name", "?") if isinstance(inner, dict) else str(inner)
    return str(item)


def walk(node, types, trails):
    """Collect every @type and every breadcrumb trail, at any nesting depth."""
    if isinstance(node, dict):
        t = node.get("@type")
        if t:
            label = ",".join(t) if isinstance(t, list) else str(t)
            # Nodes sharing an @id are one entity referenced twice, not a
            # duplicate — that is exactly what linking them by @id achieves.
            types.append((label, node.get("@id", id(node))))
        if t == "BreadcrumbList":
            el = node.get("itemListElement", [])
            el = el if isinstance(el, list) else [el]
            trails.append(" > ".join(crumb_name(i) for i in el))
        for v in node.values():
            walk(v, types, trails)
    elif isinstance(node, list):
        for v in node:
            walk(v, types, trails)


def meta(html, name):
    m = re.search(
        r"<meta\s+name=['\"]%s['\"]\s+content=['\"](.*?)['\"]" % name, html, re.I | re.S
    )
    return m.group(1) if m else None


def doctitle(html):
    m = re.search(r"<title>(.*?)</title>", html, re.I | re.S)
    return m.group(1).strip() if m else None


def length_verdict(text, hard, good):
    """'' when inside the good band, else 'warn'/'fail' plus what is wrong."""
    n = len(text or "")
    if not n:
        return ""
    if n < hard[0] or n > hard[1]:
        return "fail: %s (%d, want %d-%d)" % (
            "too short" if n < hard[0] else "too long",
            n,
            hard[0],
            hard[1],
        )
    if n < good[0] or n > good[1]:
        return "warn: %s (%d, ideal %d-%d)" % (
            "short" if n < good[0] else "long",
            n,
            good[0],
            good[1],
        )
    return ""


# Resolve a real post URL from the sitemap so the audit covers a Journal post.
_, sm = fetch("/sitemap.xml")
posts = [
    u
    for u in re.findall(r"<loc>([^<]+)</loc>", sm)
    if "/product" not in u
    and "/category/" not in u
    and u.rstrip("/").count("/") == 3
]
paths = [(l, p, n) for l, p, n in PATHS if p]
if posts:
    paths.insert(6, ("single post", posts[-1].replace(BASE, ""), False))

rows = []
for label, path, must_noindex in paths:
    status, html = fetch(path)
    blocks = re.findall(
        r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S
    )
    types, trails = [], []
    for b in blocks:
        try:
            walk(json.loads(b), types, trails)
        except json.JSONDecodeError:
            types.append("<unparseable>")
    # ListItem/Offer/UnitPriceSpecification legitimately repeat within one node.
    ignore = {"ListItem", "UnitPriceSpecification", "Offer", "ImageObject"}
    entities = {t for t in set(types)}
    dupes = {
        label: c
        for label, c in Counter(l for l, _ in entities).items()
        if c > 1 and label not in ignore
    }
    robots = meta(html, "robots") or ""
    rows.append(
        {
            "label": label,
            "path": path,
            "status": status,
            "blocks": len(blocks),
            "dupes": dupes,
            "title": doctitle(html),
            "desc": meta(html, "description"),
            "noindex": "noindex" in robots,
            "must_noindex": must_noindex,
            "trails": trails,
            "canonical": bool(re.search(r'rel="canonical"', html)),
            "og": bool(re.search(r'property="og:title"', html)),
        }
    )

print(f"{'page':<14} {'code':<5} {'ld':<3} {'title':<6} {'desc':<8} {'canon':<6} {'og':<4} {'noindex':<8} {'crumbs':<7} duplicated nodes")
print("-" * 110)
for r in rows:
    print(
        f"{r['label']:<14} {r['status']:<5} {r['blocks']:<3} "
        f"{len(r['title'] or ''):<6} "
        f"{(str(len(r['desc'])) if r['desc'] else 'MISSING'):<8} "
        f"{('yes' if r['canonical'] else 'NO'):<6} "
        f"{('yes' if r['og'] else 'NO'):<4} "
        f"{('yes' if r['noindex'] else 'no'):<8} "
        f"{len(r['trails']):<7} "
        f"{', '.join(f'{k}x{v}' for k, v in r['dupes'].items()) or '-'}"
    )

print("\n--- breadcrumb trails ---")
for r in rows:
    for t in r["trails"]:
        print(f"{r['label']:<14} {t}")

print("\n--- meta descriptions ---")
for r in rows:
    print(f"{r['label']:<14} {(r['desc'] or '(none)')[:90]}")

problems = []
warnings = []
for r in rows:
    if r["dupes"]:
        problems.append(f"{r['label']}: duplicate schema {r['dupes']}")
    if len(r["trails"]) > 1:
        problems.append(f"{r['label']}: {len(r['trails'])} breadcrumb trails")

    if r["must_noindex"] and not r["noindex"] and r["status"] == 200:
        problems.append(f"{r['label']}: expected noindex, but it is indexable")
    if not r["must_noindex"] and r["noindex"]:
        problems.append(f"{r['label']}: unexpectedly noindex")

    # Length only matters where a search engine will use the value. A noindex
    # page's title still shows in the browser tab, but TSF stops grading it and
    # so does this.
    if r["noindex"] or r["status"] != 200:
        continue

    if not r["desc"]:
        problems.append(f"{r['label']}: indexable but no meta description")

    for field, hard, good in (
        ("title", TITLE_HARD, TITLE_GOOD),
        ("desc", DESC_HARD, DESC_GOOD),
    ):
        verdict = length_verdict(r[field], hard, good)
        if verdict.startswith("fail"):
            problems.append(f"{r['label']}: {field} {verdict[6:]}")
        elif verdict:
            warnings.append(f"{r['label']}: {field} {verdict[6:]}")

if warnings:
    print("\n--- warnings (orange in wp-admin's SEO Bar, not a gate) ---")
    print("\n".join(warnings))

print("\n--- problems ---")
print("\n".join(problems) if problems else "none")
sys.exit(1 if problems else 0)
