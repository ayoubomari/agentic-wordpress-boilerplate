---
description: Run and interpret ./scripts/lighthouse-check.sh to gate performance/accessibility/SEO/best-practices, and fix the recurring causes of a failing score in this codebase (LCP images, render-blocking assets, cache headers). Use after finishing a block/template change, but only once the user has confirmed they want this check run (see "Ask first" below); also use when the user explicitly says "run lighthouse", "check performance", or "optimize this page".
---

# Lighthouse performance gate

## Ask first

Do not run this automatically after every change. Ask the user once the code
change is done — e.g. "Want me to run a Lighthouse check on this before we
call it done?" — unless their own request already implied it. This can be
combined into the same ask as the `playwright-verify` skill's screenshot
question rather than asking twice.

## Running it

```bash
./scripts/lighthouse-check.sh <path> [min-score] [categories]
```
- Default threshold: `90`. Default categories: `performance,accessibility,seo,best-practices`.
- Requires `wp-env start` already running and a local Chrome/Chromium
  (`CHROME_PATH` env var, or `google-chrome`/`chromium` on `PATH`).
- Exits non-zero if any category is below threshold — **treat that as a
  failing check, not a suggestion**, per `CLAUDE.md`.
- **Cart / checkout / my-account are intentionally noindex**, so their SEO
  score is legitimately low — audit those without the `seo` category rather
  than trying to make them indexable:
  ```bash
  ./scripts/lighthouse-check.sh /cart/ 90 performance,accessibility,best-practices
  ```

## Reading a failure

The script prints a ready-to-run inspection snippet on failure. Use it
directly:
```bash
node -e 'const r=require("./lighthouse-report.json");for(const a of Object.values(r.audits))if(a.score!==null&&a.score<0.9)console.log(a.score,a.id,"-",a.title)'
```

## Fixes for the failure modes that actually recur in this codebase

Don't guess generically — these are the specific patterns already used
elsewhere in the theme; match them rather than inventing a new technique.

- **LCP image not prioritized.** Above-the-fold `<img>` tags need
  `fetchpriority="high" decoding="async"` and **no** `loading="lazy"` (see
  `product-subcategories/render.php`'s first tile, and
  `single.html`/`page-about-us.html`'s hero image). Below-the-fold images
  keep `loading="lazy" decoding="async"` (the default across `render.php`
  files — grep `loading="lazy"` in `agentic-blocks/blocks/*/render.php` for
  the pattern).
- **Hero banner is a CSS `background-image`, not an `<img>`** (see
  `hero-banner/render.php` and its `background-size:cover` in
  `hero-banner/style.css`), so it can't carry `fetchpriority` at all and the
  browser preloader won't discover it early. That's why `functions.php`'s
  `wp_head` hook (~line 466) walks the page's own template, finds the first
  `agentic/hero-banner` block's image via `agentic_find_first_hero_image()`,
  and prints a manual `<link rel="preload" as="image" href="...">` for it.
  If you add a hero-banner to a *new* template, that hook only fires for
  `front-page`/`page-about-us` today — extend its `is_front_page() /
  is_page('about-us')` check rather than duplicating the preload logic.
- **Scroll-reveal hiding above-the-fold content.** `assets/js/scroll-reveal.js`
  uses `getBoundingClientRect()` so anything already on screen at first paint
  either skips the reveal class entirely or gets the fast `-load-pending`
  path — never the scroll-triggered fade, which would start at `opacity:0`
  and delay "largest contentful paint" until the observer fires. If you add
  a new above-the-fold section, don't blanket-apply `agentic-reveal-item` to
  it; check how `product-subcategories/render.php` skips it on `index === 0`.
- **Render-blocking scripts/styles.** jQuery is deferred via WordPress 6.3+'s
  script "strategy" data, not a manual footer move (see `functions.php`).
  `scroll-reveal.css` loads asynchronously. WooCommerce's three classic
  frontend stylesheets are dequeued, but **only** on templates that don't
  emit the classic markup they target (front page today) — don't dequeue
  them globally, `archive-product`/`single-product` still need them.
- **Missing long cache headers.** `setup-site.sh` (step "Permalinks") writes
  a marker-scoped `.htaccess` block giving images/fonts/CSS/JS a 1-year
  `ExpiresByType`. If this audit fails on a fresh clone, check that step ran
  (`grep "Agentic Cache" .htaccess` inside the container) rather than adding
  a second caching mechanism.
- **Unoptimized images.** See the `image-to-webp` skill — every image asset
  in this theme is `.webp`; a `.png`/`.jpg` slipping in is very likely what's
  behind an `unminified-images`/`modern-image-formats` failure.

Fix by editing source files (render.php/CSS/functions.php), then re-run the
same command — never by changing a setting through wp-admin.
