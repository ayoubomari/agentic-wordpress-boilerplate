# ROADMAP — Agentic WordPress Boilerplate

**Audience:** future Claude Code sessions (Sonnet) picking this repo up.
**Status at time of writing:** working skeleton. `wp-env start` brings up a
real, browsable WooCommerce store with SEO in one command, verified end to
end. What remains is breadth — blocks, templates, and hardening.

---

## Environment gotchas (will waste your time otherwise)

- **`wp-env start` intermittently fails with `ETIMEDOUT` / `RequestError`
  during "Reading configuration."** This is a host networking fault, not a
  repo defect — `wp-env` (Node) fails to reach `api.wordpress.org` while
  `curl` to the same host succeeds. It is transient. Just retry; it usually
  succeeds within a minute or two. Do not "fix" the repo in response to it.
- **`wp-env` swallows `afterStart` output on success.** You will not see
  `setup-site.sh`'s step log unless it fails. To confirm it ran, check the
  resulting state (options, plugins, seeded product) rather than the console.
- **`wp-env run` takes no leading flags.** `wp-env run --quiet cli wp ...`
  parses the container name as empty and fails. Use `wp-env run cli wp ...`.

---

## 0. Read this before touching anything

Read `CLAUDE.md` first and follow it exactly. The two rules that matter most,
restated because they are the ones most likely to be abandoned under time
pressure:

**Rule 1 — Everything is built by writing files.** Pages, layouts, blocks,
design tokens, and site settings are all code. wp-admin, the block editor,
and Playwright are for *verifying* what code produced, never for producing
it. If you catch yourself about to change a setting in wp-admin, add a
`wp option update` line to `scripts/setup-site.sh` instead.

**Rule 2 — Verify by running, not by reading.** A change is not done because
the code looks right. It is done when you have loaded the page, taken a
screenshot, and run the Lighthouse gate. Several bugs found in the initial
audit of this repo (a silently dropped block bundle, a block registered with
no editor script, an empty product grid, a storefront hidden behind
WooCommerce's Coming Soon splash) were *invisible to code review* and only
surfaced by actually running things.

**Do not "simplify" the block build back to a glob.** See the
"How the JS wiring works" section of `CLAUDE.md`. `wp-scripts build
blocks/*/index.js --output-path=build` silently drops every block but the
first. This was the single worst defect in the original repo.

**No shortcuts that trade the rules for speed.** If a phase is taking too
long, ship fewer items from it — never the same items built by clicking.

---

## End goal

A repository that can be cloned and, with **one command** (`wp-env start`),
produces a fully working WooCommerce storefront:

- WooCommerce + Yoast SEO installed, activated, and sanely configured
- A custom block theme with full template coverage — no PHP fallbacks
- A library of minimal custom blocks covering what a typical store needs
- No page builder, no premium plugins, no bloat
- Lighthouse ≥ 90 across all four categories on every template
- Zero manual wp-admin steps beyond the documented owner-only exceptions
  (payment credentials, live shipping/tax accounts, domain/SSL)

---

## Baseline already in place (do not redo)

| Area | State |
|---|---|
| `wp-env start` | Works from a destroyed env with no `node_modules`; single command |
| `scripts/setup-site.sh` | Idempotent; builds blocks, installs WC + Yoast + WP Mail SMTP + UpdraftPlus, permalinks, disables Coming Soon, seeds 4 products |
| Analytics/pixels | GA4 + Meta Pixel — code, not a plugin, in `functions.php`; empty `AGENTIC_GA4_ID`/`AGENTIC_META_PIXEL_ID` constants until the owner sets them; purchase event on `woocommerce_thankyou`, double-fire-guarded via order meta |
| Block build | `webpack.config.js`, one entry per block → `build/<block>/index.js` + `index.asset.php` |
| Design tokens | `theme.json` — Dawn-like palette, fluid type scale, spacing scale, button/link styles |
| Sections | 14 blocks (see Phase 1) — all render, all registered |
| Templates | 17 templates, **all resolving to the theme**, all with header + footer |
| Parts | `header` (announcement bar, nav, account, mini-cart), `footer` (4-column + newsletter) |
| Scaffolding | `scripts/new-block.sh`, `scripts/new-template.sh` |
| Playwright MCP | Configured in `.mcp.json` at project scope |
| `scripts/lighthouse-check.sh` | Threshold gate, selectable categories, audits 404s |
| Brand inputs | `../system-design/` folder + README (outside the repo, on purpose) |

### How to add a section (the pattern to follow)
```bash
./scripts/new-block.sh logo-list              # or --with-js for editor controls
# edit blocks/logo-list/{block.json,render.php,style.css}
cd agentic-blocks && npm run build            # only needed if --with-js
# add <!-- wp:agentic/logo-list /--> to a template
```
Sections are **attribute-driven** (like Shopify section settings), not
InnerBlocks-driven. That is deliberate: it is what lets a template compose a
page entirely from a file, with no editor interaction. Keep it.

---

## Phase 1 — Core block library — **done**

Built and verified (registered, non-empty render, no PHP fatals — checked via
WP-CLI and curl, 2026-08-01): `hero-banner`, `featured-collection`,
`image-with-text`, `multicolumn`, `testimonials`, `faq-accordion`,
`newsletter-signup`, `collection-list`, `rich-text`, `logo-list`,
`video-section`, `countdown-banner`, `product-badge`, `announcement-bar`.

Notes on the last batch:
- `collection-list`, `rich-text` are wired into `front-page.html`. `logo-list`
  is built but deliberately **not** wired into any template — it needs real
  press/brand logo images, and `../system-design/` is still empty, so
  inserting it now would mean inventing brand assets. Wire it in once real
  logos exist.
- `video-section` and `countdown-banner` are built and verified but not yet
  placed in a template (no real video asset or real promotion to point them
  at). `countdown-banner`'s ticking is a plain `view.js` (no build step,
  no framework) enqueued only on pages where the block is present.
- `product-badge` is wired into `archive-product.html`,
  `taxonomy-product_cat.html`, `taxonomy-product_tag.html`, and
  `single-product.html`, and also reused inline inside
  `featured-collection`'s own product-card loop via a shared
  `agentic_product_badge_markup()` helper in `agentic-blocks.php` — needed
  because featured-collection builds its own card markup rather than nesting
  blocks. Its CSS lives in the theme's global `style.css` (now enqueued via
  `functions.php`, which it previously was not) rather than a per-block
  stylesheet, since it has to work in both places.
- `announcement-bar` replaced the hard-coded markup in `parts/header.html`.

Cart, checkout, and My Account also got a pass in this session: WooCommerce's
own block/classic markup ships rounded corners, drop shadows, and blue
accents that don't match theme.json. `assets/css/woocommerce.css` re-points
the stable WC class names at the theme's tokens, enqueued only on
cart/checkout/account (`functions.php`); the mini-cart drawer gets a small
always-on inline override since it can open from the header anywhere.
**Not yet verified visually** — checked via WP-CLI/curl only in this session,
per instruction to skip the browser. Take screenshots of `/cart/`,
`/checkout/`, and `/my-account/` before calling this fully done.

**Done when, for every block:**
```bash
cd agentic-blocks && npm run build      # exits 0
ls build/<name>/index.js build/<name>/index.asset.php   # both exist
```
```bash
# registers, and (if it has index.js) has a non-empty editor script array
wp-env run cli wp eval 'var_dump(WP_Block_Type_Registry::get_instance()->is_registered("agentic/<name>"));'
wp-env run cli wp eval 'print_r(WP_Block_Type_Registry::get_instance()->get_registered("agentic/<name>")->editor_script_handles);'
```
```bash
# renders non-empty output with default attributes
wp-env run cli wp eval 'echo strlen(trim(do_blocks("<!-- wp:agentic/<name> /-->")));'
```
Plus: a screenshot of a page using the block, and
`./scripts/lighthouse-check.sh <path>` exits 0.

> Prove multi-block builds still work after adding the second block — that is
> exactly where the original pipeline failed silently.

---

## Phase 2 — Template coverage — **done, with follow-ups**

All 17 templates exist, resolve to the theme, and carry header + footer.
Verified with the command below and by loading each page.

Remaining follow-ups:
- `parts/sidebar-filters.html` — price/attribute/stock filter blocks, wired
  into `archive-product` and `taxonomy-product_cat`.
- Product gallery is the plain WooCommerce gallery; a Dawn-style thumbnail
  strip would be closer to the target.
- `single-product` has no variable-product coverage — the seeded products are
  all simple. Add a variable product to the seed and confirm the variation
  picker renders before calling this finished.
- The breadcrumb on `single-product` shows the product's primary category;
  confirm it reflects the intended taxonomy once real categories exist.

**Done when:**
```bash
# every listed template resolves to the theme, not a plugin fallback
wp-env run cli wp eval '
foreach (["single-product","archive-product","taxonomy-product_cat","page-cart",
          "page-checkout","order-confirmation","404","search","archive"] as $s) {
  $t = get_block_template( "agentic-theme//" . $s );
  printf("%-24s %s\n", $s, $t ? $t->source : "MISSING");
}'
```
Every row must print `custom` or `theme` — never `MISSING`.

And each of these returns 200 with real content (not the Coming Soon splash,
not an empty `<main>`):
`/`, `/shop/`, `/product/sample-product/`, `/cart/`, `/checkout/`,
`/my-account/`, `/?s=sample`, `/this-page-does-not-exist/` (must be 404).

Screenshot each one.

---

## Phase 3 — SEO hardening

**Goal:** correct metadata, schema, and sitemaps for **product** URLs, not
just posts and pages.

Known state from the audit (verify, don't assume it still holds):
- Yoast free emits `WebPage` / `BreadcrumbList` / `WebSite` graphs.
- **Product schema comes from WooCommerce core, not Yoast free.** Yoast's
  Product schema lives in the paid WooCommerce SEO addon, which this
  boilerplate will not use. So confirm WooCommerce's `Product` JSON-LD is
  present, valid, and not duplicated by anything else.
- `product-sitemap.xml` only appears once at least one product exists.

Tasks:
1. Confirm `Product` JSON-LD has `name`, `offers.price`, `offers.priceCurrency`,
   `offers.availability`, and `image` populated on a real product.
2. Ensure exactly one `BreadcrumbList` — the audit saw one from Yoast and one
   from WooCommerce. Deduplicate.
3. Add canonical/OG/Twitter checks for product URLs.
4. Set Yoast title/meta templates for products and product archives in code
   via `wp option update wpseo_titles ...` in `setup-site.sh`.
5. Confirm `noindex-product` and `noindex-ptarchive-product` stay `false`.
6. Ensure `robots.txt` does not disallow product URLs.

**Done when:**
```bash
curl -s http://localhost:8888/sitemap_index.xml | grep -q product-sitemap && echo OK
curl -s http://localhost:8888/product-sitemap.xml | grep -c '<loc>'   # >= product count
```
```bash
# exactly one Product node and exactly one BreadcrumbList across the page
curl -s http://localhost:8888/product/sample-product/ \
  | grep -o '"@type":"Product"' | wc -l      # must be 1
curl -s http://localhost:8888/product/sample-product/ \
  | grep -o '"@type":"BreadcrumbList"' | wc -l   # must be 1
```
And SEO category ≥ 90 in `./scripts/lighthouse-check.sh /product/sample-product/`.

---

## Phase 4 — Performance pass

**Goal:** every template ≥ 90 in all four Lighthouse categories.

```bash
# Indexable pages — all four categories must pass
for p in / /shop/ /product/sample-product/; do
  ./scripts/lighthouse-check.sh "$p" 90 || echo "FAILED: $p"
done

# Intentionally-noindex pages — skip the seo category
for p in /cart/ /my-account/ /no-such-page-xyz/; do
  ./scripts/lighthouse-check.sh "$p" 90 performance,accessibility,best-practices \
    || echo "FAILED: $p"
done
```

**Baseline measured after the Dawn pass (2026-07-30):**

| Page | Perf | A11y | Best practices | SEO |
|---|---|---|---|---|
| `/` | 91–92 | 94 | 100 | 100 |
| `/shop/` | 78–91 (noisy) | 94 | 100 | 92 |
| `/product/sample-product/` | **86** | 97 | 100 | 92 |
| `/cart/` | **51** | 100 | 100 | n/a (noindex) |
| `/my-account/` | 90 | 92 | 100 | n/a (noindex) |
| `/404` | **87** | 94 | 96 | n/a (noindex) |

> Cart, checkout, my-account, and 404 are intentionally noindex. Their SEO
> score is *correctly* low — audit them without the seo category rather than
> trying to make them indexable.

**Known issues to resolve (measured, not guessed):**
- **`/cart/` performance is 51 — by far the worst page.** WooCommerce's Cart
  block is a full React app; the cost is upstream, not in this theme. Options
  worth measuring: the classic `[woocommerce_cart]` shortcode instead of the
  block, or deferring cart scripts. Do not accept 51 silently.
- **`/product/sample-product/` is 86**, even on the new block template — so
  the earlier assumption that the PHP fallback was the cause was wrong.
  Re-measure and find the real cost before optimizing.
- **`/404` is 87.** Likely the same shared asset stack; it should be the
  cheapest page on the site, so this is worth a look.
- Accessibility sits at **92–97** because of `aria-hidden-focus`. The
  offending node is WooCommerce's own `wc-block-mini-cart__drawer`, which sets
  `aria-hidden="true"` on a container holding focusable children. This is
  upstream, not theme code. Either work around it or, if the cost is too
  high, document it as a known upstream limitation — do not silently ignore it.
- **Local performance scores are extremely noisy — do not trust a single
  run.** Three consecutive audits of the identical `/shop/` page returned
  **78, 91, 89**. That is ±13 points of pure measurement variance on an
  unchanged page. `WP_DEBUG` also means assets are unminified and uncached
  here, which production would not be.

  So: before "fixing" a performance number, run the audit **at least three
  times and compare medians**. Chasing a single low sample will waste a
  session and can lead to changes that make the code worse for no real gain.
  The accessibility, best-practices, and SEO categories are stable — treat
  those as reliable signals.
- Consider dequeuing unused WooCommerce block assets on templates that do not
  need them (via a small `functions.php` in the theme).

**Done when:** the loop above prints no `FAILED:` lines.

---

## Phase 4.5 — Brand system wiring

**Goal:** make a clone stop looking like the baseline and start looking like a
specific brand, entirely through code.

The `../system-design/` folder (next to this repo) is where the store owner
drops brand material. It is currently empty, so the theme uses neutral,
Dawn-like defaults.

When it has content:
1. Read `identity/` and `styles/`; derive the palette, type scale, and spacing.
2. Write those into `theme/agentic-theme/theme.json` — never into per-block
   CSS. Blocks must consume `var(--wp--preset--*)` so a token change
   restyles the whole site at once.
3. Copy only the fonts actually used into
   `theme/agentic-theme/assets/fonts/` and register them in `theme.json`
   under `settings.typography.fontFamilies[].fontFace`. WordPress can only
   serve fonts from inside the theme.
4. Check the licence before committing any font into a published boilerplate.
5. Replace placeholder copy (`testimonials`, `faq-accordion`, the announcement
   bar, hero text) with real text from `copy/`.

**Done when:** changing only `theme.json` visibly rebrands every template, and
`grep -rE '#[0-9a-fA-F]{6}' theme/agentic-theme/templates agentic-blocks/blocks`
returns nothing — no hard-coded colours outside the token layer.

> The 4 seeded products ("Rejuvenating Night Oil", "Rose Quartz Facial
> Polish", "Hydrating Body Serum", "Gentle Gel Cleanser" — see
> `scripts/setup-site.sh`) and their two categories (Skincare, Bath & Body)
> are development fixtures. Delete them before launch. The testimonials
> block also ships with placeholder quotes — replace them with real,
> attributable feedback; never publish invented reviews.

---

## Phase 5 — Documentation pass

**Goal:** a future clone or session is never misled by stale docs. Stale
instructions are worse than missing ones — the original `CLAUDE.md` described
a build command that silently did the wrong thing.

1. `CLAUDE.md` — keep the folder map, the block list, and the JS-wiring
   warning current as blocks and templates are added.
2. `README.md` — keep the "what `wp-env start` does" list accurate.
3. This `ROADMAP.md` — check off phases and record what was actually verified.
4. Document every new block: attributes, defaults, and an example
   block-markup snippet that can be pasted into a template.

**Done when:** every command in `README.md` and `CLAUDE.md` has been run
verbatim in a clean session and behaves exactly as documented.

---

## Phase 6 — Packaging — **partially done**

**Goal:** `git clone && wp-env start` works on a machine that has never seen
this project.

> **Update (2026-08-01):** the git repo lives one level up, at
> `agentic-wordpress-boilerplate/` (this folder plus a sibling
> `system-design/`, which is `.gitignore`d there on purpose — see "Brand
> inputs" above). Initial commit is pushed to
> `git@github.com:ayoubomari/agentic-wordpress-boilerplate.git`. Items 1–3
> below are done; **4 and 5 (the actual cold-clone test) have not been run
> yet** — don't assume a clean clone works until that's been verified.

1. ~~`git init`, commit everything, confirm `.gitignore` covers `node_modules/`,
   `agentic-blocks/build/`, `lighthouse-report.json`, `.playwright-mcp/`,
   `*.png`.~~ Done.
2. ~~Verify no absolute paths or machine-specific values are committed.~~ Spot-
   checked during commit — re-verify with a real cold clone (step 4).
3. ~~Confirm `.mcp.json` is committed so Playwright MCP works on clone.~~ Done.
4. Full cold test — **not yet run**:
   ```bash
   wp-env destroy                       # in the original checkout
   git clone <repo> /tmp/clone-test && cd /tmp/clone-test
   wp-env start                         # must be the ONLY command needed
   curl -sf http://localhost:8888/shop/ | grep -q "Rejuvenating Night Oil" && echo PASS
   ```
5. Confirm zero fatals after the cold start:
   ```bash
   wp-env run cli sh -c 'grep -c "Fatal error" /var/www/html/wp-content/debug.log || echo 0'
   ```
   Must print `0`.
6. ~~Document the owner-only manual steps (payment keys, live shipping/tax,
   Yoast social profiles, domain/SSL) in `README.md` as an explicit
   pre-launch checklist.~~ Done — see README's "Pre-launch checklist", now
   also self-checked live in wp-admin by the Dashboard "Store setup
   checklist" widget (`inc/setup-checklist.php`).

**Done when:** a clone on a clean machine reaches a working storefront with
`wp-env start` and nothing else, and Phase 4's Lighthouse loop passes there.

---

## Phase 7 — Sleek redesign — **core sections done**

**Goal:** replace the neutral "Dawn" placeholder direction with Shopify's
**Sleek** theme (https://sleek-theme-demo.myshopify.com/) as the actual
visual target — full design-token pivot plus the section library it needs.
See "Design direction" in `CLAUDE.md` for the token details (warm palette,
pill buttons, rounded-sans headings, the 3-value shape scale) — not repeated
here.

Done, verified via WP-CLI render checks + curl (2026-08-01):
- Design tokens: palette, fonts, button/badge/card/panel radius scale.
- `hero-banner` peek carousel (`slides`), `featured-collection` tabs +
  `maxPrice` + hover quick-add, `product-badge` percentage-off/sold-out/
  selling-fast (real low-stock signal, not invented), `image-with-text`
  colored promo panels, `multicolumn` pill trust badges, `rich-text`
  underline-link variant, `testimonials` photo carousel, `faq-accordion`
  intro column, new `cta-cards` block, shared `carousel.js`.
- `front-page.html` recomposed in Sleek's section order; `parts/footer.html`
  restructured (newsletter-first, `core/social-links`).
- Sample data reskinned to skincare/bath-body (`scripts/setup-site.sh`) —
  real categories (not one catch-all) and real sale pricing on 2 of 4
  products, so the sale badge, "Sale" tab, and price-filtered row all have
  genuine data instead of rendering empty.

Two real bugs caught during this pass, worth knowing about if similar work
comes up again:
- `wc_get_products()` **silently drops** a raw `meta_query` argument, and its
  own meta-backed query vars only build exact/IN comparisons — there is no
  price-range operator exposed at all. `maxPrice` filtering is done in PHP
  (over-fetch, filter, slice) in `featured-collection/render.php`, not via
  the query. Same story for `onSale`.
- WooCommerce's `wc wc product_cat`-assigned-by-slug (`{"slug":"..."}` in
  `--categories`) silently no-ops — only `{"id": N}` actually works. Verified
  by testing directly against a real product before trusting it in the seed
  script.

Five more, all caught by the Playwright + Lighthouse verification pass
(exactly the "invisible until you run it" pattern this repo's process exists
to catch) — none of them showed up in code review:
- Core `woocommerce/product-image` auto-nests its own
  `woocommerce/product-sale-badge` sub-block whenever a product is on sale —
  a plain unstyled "Sale" box duplicating our own styled badge in the
  opposite corner. Only visible once real sale-priced products existed to
  test with. Suppressed globally via CSS (`theme/agentic-theme/style.css`).
- `agentic-woocommerce-overrides` and `agentic-theme-style` were both
  cache-busted with the theme's static version string, which never changes
  across edits — a real fix to either file could silently not reach an
  already-loaded browser. Switched both to `filemtime()`-based versioning.
- Tab labels used `--border` (a hairline color, ~1.3:1 against white) as
  text color — fails WCAG contrast outright. Switched to `--muted`.
  `--muted` itself only clears ~3.9:1 against the blush/sand panel colors
  (fine on white, fails on the new pastel backgrounds) — `image-with-text`
  panel body copy now uses `--contrast` instead.
- Hero-banner carousel dots had an 8px hit area — WCAG's minimum touch
  target is 24px. Kept the visual dot small via a centered `::before`, grew
  the actual button to 24×24.
- A hero-banner slide with a dark `backgroundColor` and no image rendered a
  black button on a black background (the light-text override only touched
  the heading/subheading, not the CTA) — invisible until an actual dark
  single-slide banner was screenshotted.
- Yoast's homepage meta description needs a `_yoast_wpseo_metadesc` **post
  meta** field on the front-page post, not the `metadesc-home-wpseo` key in
  the `wpseo_titles` **option** — that option only applies when the blog
  index itself is the homepage. Confirmed by testing both directly; only the
  post-meta version rendered a `<meta name="description">` tag. This gap
  pre-dates the Sleek work (front-page.html never puts real content in
  `post_content` for Yoast to derive a description from) but only started
  failing Lighthouse's SEO gate once this pass ran a full audit.

Lighthouse on `/` after all fixes: performance 91, accessibility 96,
best-practices 100, seo 100 (accessibility isn't 100 because of the
pre-existing, upstream `aria-hidden-focus` issue on WooCommerce's own
mini-cart drawer, documented in Phase 4 above — not something this repo's
code can fix).

**Follow-up round (2026-08-01), matched against real Sleek reference
screenshots section-by-section:**
- `hero-banner`: added `eyebrow` (text above the heading, independent of the
  existing `subheading` below it) and `contentAlign:"left"` on the homepage
  slides; added a default abstract SVG illustration (token-colored rotated
  shapes) for slides with no `imageUrl`, so a color-only slide never looks
  like a bare, unfinished panel.
- `featured-collection` tabbed mode: the "Shop All Products" CTA now shares
  the same row as the tab labels (was a separate line above) — required
  reworking the CSS-only tab-switch selectors, since nesting the labels in a
  new `.tabs-row` wrapper broke the `~` sibling-combinator chain until the
  wrapper was added into the selector path. Also added a category label
  above each product title, colored the sale price with the `sale` token
  (was unstyled default text), and seeded one product with managed low
  stock so the `selling-fast` badge type has real data to render — all of
  it caught a second, unrelated bug: stacking `opacity: 0.8` on top of the
  already-WCAG-passing `--muted` struck-through price dropped it to ~3.5:1
  and failed Lighthouse's color-contrast audit. Fixed by dropping the
  opacity — `--muted` alone was already sufficient.
- New `search-drawer` block: header search icon + slide-out panel, wired
  into `parts/header.html`. Real WP search form (verified end-to-end: a
  single-match query redirects straight to the product, a broader query
  lands on the real search-results template), curated popular-search links,
  and real current products — not invented "most searched" analytics.

**Not done — deferred, do not build without discussing scope first:**
1. Numbered-hotspot shoppable bundle picker (image with clickable pins tied
   to a product list + "Add all to cart").
2. Full shoppable masonry photo gallery with an accordion-driven filter
   ("Customers enjoy their journey everyday" on the live reference).
3. Marquee text/image ticker band (mid-page decorative scrolling strip,
   distinct from the top announcement bar, which is unchanged).

These were explicitly scoped out — they need real client-side interactivity
well beyond the vanilla-JS carousel/CSS-only-tabs patterns used elsewhere in
this block library, and the user chose "core sections first" over full
parity when asked. Pick them up only if asked to go deeper on Sleek parity.

**Done when:** the three deferred items above are either built or explicitly
re-scoped out again, and a full Lighthouse pass (`./scripts/lighthouse-check.sh /`)
confirms the new carousel JS and imagery-heavy sections don't regress the
performance/accessibility budget below 90.

---

## Suggested order

Phases are ordered by dependency: Phase 2 templates want Phase 1 blocks to
compose; Phase 3 needs product templates to inspect; Phase 4 needs everything
rendered to measure; Phases 5–6 close out. Within a phase, items are
independent — finish and verify one completely before starting the next
rather than leaving several half-built.
