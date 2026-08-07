# Agentic WordPress Ecommerce Boilerplate

A code-first WordPress + WooCommerce boilerplate, structured to be developed
the way a Shopify theme is developed with the Shopify CLI: everything is a
file, an AI coding agent (Claude Code) is the primary developer, and
wp-admin/the block editor are used only to verify output — never to build it.

This document is the **technical** reference: architecture, stack, and how
the pieces fit together. For the agent's own operating rules (what it must
never do, how verification works, brand-input pipeline) see `CLAUDE.md` —
that file is the actual contract the agent follows and is kept intentionally
prescriptive; this README explains *why* the system is shaped that way.

## Architecture, mapped from Shopify's mental model

| Shopify concept | This boilerplate |
|---|---|
| Liquid theme files (`sections/*.liquid`) | `theme/agentic-theme/templates/*.html` + `parts/*.html` — pure block markup, no PHP templating |
| `settings_schema.json` | `theme/agentic-theme/theme.json` — colors, type, spacing, shape all as design tokens |
| Custom Liquid sections | `agentic-blocks/blocks/<slug>/` — one folder per section: `block.json` (schema/attributes) + `render.php` (server-rendered markup) + `style.css`, optionally `index.js` for editor controls |
| Section settings (attribute-driven, no free content editing) | Block **attributes**, not nested inner blocks — a whole page is composed by writing attribute JSON into a template file |
| Shopify CLI dev server | `wp-env` — Docker-based WordPress + MySQL, started with `wp-env start` |
| `shopify theme push` / theme duplication | `git clone` this repo under a new name |

The block theme is FSE (Full Site Editing) but used in a restricted way: no
page is ever assembled by dragging blocks in wp-admin. Every template file
is committed source, read and written directly.

## Stack

- **WordPress block theme** — `theme/agentic-theme/` (no `functions.php`
  templating beyond a menu-slug-to-`ref` resolver; see below)
- **WooCommerce** (free, official) — product catalogue, cart, checkout,
  orders
- **Yoast SEO** (free tier) — meta, sitemaps, schema
- **WP Mail SMTP** (free) — WordPress's default `wp_mail()` is spam-filtered
  by most hosts; without this, order confirmation emails silently vanish
- **UpdraftPlus** (free) — scheduled backups; remote storage destination is
  the one piece left for the store owner to configure
- **`agentic-blocks`** — custom plugin holding the section/block library
- All four plugins install by slug from wordpress.org via
  `scripts/setup-site.sh`, triggered automatically by `wp-env`'s
  `lifecycleScripts.afterStart`

No page builder, no premium plugins, no paid tiers.

## Design token system

Every visual property in the codebase — color, font, spacing, corner
radius, shadow — resolves to a token defined once in `theme.json`. Nothing
in a template or block ever hardcodes a hex value, a pixel radius, or a
raw font stack; `grep -rnE '#[0-9a-fA-F]{3,8}\b' theme/agentic-theme
agentic-blocks/blocks` should return nothing.

- **Color** — a 12-entry palette (`base`, `contrast`, `subtle`, `muted`,
  `border`, `accent`, `sale`, `blush`, `sand`, `new-badge`,
  `selling-fast`, `sky`), consumed everywhere as
  `var(--wp--preset--color--*)`.
- **Typography** — one self-hosted variable font, **DM Sans**
  (`assets/fonts/`, weights 100–1000, upright + italic, SIL OFL), used for
  both body and heading roles; headings are the same family set to 800
  weight / -0.02em tracking via `styles.elements.heading`. Type sizes are
  fluid (`clamp()`-based) via `theme.json`'s `fluid: true`.
- **Spacing** — a 7-step named scale (`Tight` → `Hero`, 0.5rem–7rem), so
  spacing intent (e.g. "gap between homepage sections") stays consistent
  without picking arbitrary pixel values per block.
- **Shape** — exactly three corner-radius custom properties defined once in
  `theme.json`'s top-level `styles.css`: `--agentic-radius-pill` (999px:
  buttons/badges/pill nav), `--agentic-radius-card` (10px: product
  cards/thumbnails), `--agentic-radius-panel` (20px: hero/promo panels).
- **Layout** — a single content cap (`contentSize`/`wideSize`, both
  1400px) applies everywhere, including WooCommerce's cart/checkout/
  account pages (which ship their own hardcoded 1000px cap that
  `assets/css/woocommerce.css` overrides back to the shared token).

Changing brand look and feel is a `theme.json` edit, full stop — never a
per-block or per-template override.

## Block/section library

`agentic-blocks/blocks/` currently has 22 sections: `hero-banner`,
`featured-collection`, `image-with-text`, `multicolumn`, `testimonials`,
`faq-accordion`, `newsletter-signup`, `collection-list`, `rich-text`,
`logo-list`, `video-section`, `countdown-banner`, `product-badge`,
`announcement-bar`, `cta-cards`, `search-drawer`, `payment-badges`,
`photo-marquee`, `photo-statement`, `shop-the-set`, `product-subcategories`,
`latest-posts`.

Each is scaffolded with `./scripts/new-block.sh <slug>` (`--with-js` if it
needs editor controls) and follows `hero-banner` as the reference pattern:
a dynamic block, server-rendered from `render.php`, configured entirely
through `block.json` attributes (e.g. `hero-banner`'s `slides` array,
`featured-collection`'s `tabs` array) so a page can be fully composed by
editing a template file's attribute JSON — no inner-block content editing.

**Shared JS is centralized, not per-block.** The one exception to "no JS
build step needed for content changes" is `agentic-blocks/assets/
carousel.js`, registered once as the `agentic-carousel` script handle and
enqueued conditionally only by blocks that actually render carousel markup
(`hero-banner` with 2+ slides, `testimonials` in carousel layout). A
same-frame layout-shift bug in naive infinite carousels (the loop-clone
correction happening after first paint) is fixed by printing that
correction synchronously inline via
`agentic_carousel_loop_precorrect()` — see `agentic-blocks.php` and the
"Carousels" section of `CLAUDE.md` for the full mechanism; the two halves
are load-bearing together and shouldn't be split.

**Editor JS build**: `agentic-blocks/webpack.config.js` compiles one entry
per `blocks/*/index.js` into `build/<block>/index.js` +
`index.asset.php` (the `.asset.php` sibling is mandatory — without it
WordPress silently registers the block with no editor script, and the
failure is invisible until you open the editor, since `render.php` still
renders on the front end regardless).
```bash
cd agentic-blocks && npm install && npm run build
```
`setup-site.sh` runs this on `wp-env start`, so a fresh clone needs no
separate build step; run it manually after editing any `index.js`.

## Content that stays out of git-tracked templates, by design

- **Navigation menus** — header/footer menu *items* are real
  `wp_navigation` posts, editable in Appearance → Editor → Navigation, not
  hardcoded links in `parts/*.html`. Templates reference them by slug
  (`agenticMenu: "header-menu"`), resolved to a post-ID `ref` at render
  time by a `render_block_data` filter in `functions.php` — because `ref`
  is a per-install post ID, a committed template can never hardcode one.
  `setup-site.sh` creates the three menus (`header-menu`, `footer-shop`,
  `footer-help`) only if missing, so re-runs never clobber owner edits.
- **Form submissions** — `newsletter-signup` posts to `admin-post.php`
  (nonce + honeypot) into a private `agentic_form_entry` CPT
  (`agentic-blocks/inc/form-entries.php`) and emails the admin, rather than
  pulling in a forms plugin for one field.
- **Analytics/ad pixels** — GA4 and Meta Pixel IDs are `wp-config.php`
  constants (`AGENTIC_GA4_ID`, `AGENTIC_META_PIXEL_ID`), not theme code, so
  real tracking IDs never need a code change or enter git history. Purchase
  events are deduped via order meta (`_agentic_purchase_tracked`).

## Template coverage

Every page type is a committed template under
`theme/agentic-theme/templates/`, and every one keeps the shared header/
footer template parts — WooCommerce is never allowed to fall back to its
own PHP templates:

`front-page` (homepage), `home` (Journal blog list), `index`, `single`,
`page`, `page-full-width`, `archive`, `search`, `404`, `archive-product`
(shop), `single-product`, `taxonomy-product_cat`, `taxonomy-product_tag`,
`page-cart`, `page-checkout`, `page-my-account`, `order-confirmation`.

The homepage and Journal are static pages (`page_on_front`/
`page_for_posts`, set by `setup-site.sh`) whose *content* is entirely in
`templates/front-page.html` / `templates/home.html` — editing the "Home"
page record in wp-admin has no visible effect, by design, since
`front-page.html` never renders `wp:post-content`.

## Site configuration as code

`scripts/setup-site.sh` is the code-first equivalent of the WooCommerce
onboarding wizard: permalink structure, disabling the Coming-Soon splash,
seeding a sample product, creating the three nav menus, homepage/Journal
routing. It is idempotent and re-runs on every `wp-env start`. Anything a
fresh clone needs that would otherwise be a wp-admin setting belongs here.

## Brand-input pipeline

Brand material (logos, licensed fonts, real copy, style references) lives
in `system-design/` alongside this repo (`identity/`, `fonts/`, `styles/`,
`copy/`, `references/`, `exports/` — see `system-design/README.md`). Before
any styling work, the agent reads that folder and translates it into code:
tokens into `theme.json`, layout into `parts/*.html`, fonts copied into
`theme/agentic-theme/assets/fonts/` (WordPress can only serve fonts from
inside the theme). With the folder empty, the theme keeps its current
Shopify-"Sleek"-inspired defaults (warm blush/terracotta palette, pill
buttons, bold rounded-sans headings) rather than inventing a new direction.

## Requirements

- Docker Desktop (running)
- Node.js 18+
- Google Chrome or Chromium (for `scripts/lighthouse-check.sh`)
- Claude Code (`npm install -g @anthropic-ai/claude-code`)

## First-time setup

```bash
npm -g install @wordpress/env   # once per machine
git clone <this-repo-url> my-new-store
cd my-new-store
wp-env start
```
`wp-env start` runs `scripts/setup-site.sh`, which builds the block bundles,
installs/activates all four plugins, activates the theme and
`agentic-blocks`, sets permalinks, routes `/` to the static homepage and
`/journal/` to the post feed, disables the WooCommerce Coming-Soon splash,
and seeds one sample product. No wp-admin clicking required.

Visit:
- Storefront: http://localhost:8888
- Shop: http://localhost:8888/shop/
- Journal: http://localhost:8888/journal/
- Admin: http://localhost:8888/wp-admin (`admin` / `password`)

## Verification workflow

After writing or editing code (not for building it):
1. `wp-env run cli wp plugin activate agentic-blocks` if not already active
2. Screenshot the affected page via Playwright — visual confirmation only,
   never how the change was made
3. `./scripts/lighthouse-check.sh <path> [min-score] [categories]` — fails
   non-zero under the threshold (default 90). Cart/checkout/account are
   intentionally noindex, so audit those without the `seo` category.

Fix issues by editing source files again — never by adjusting settings
through the UI.

## Adding a section or page

```bash
./scripts/new-block.sh logo-list          # add --with-js for editor controls
./scripts/new-template.sh page-lookbook   # header/footer pre-wired
```
```html
<!-- wp:agentic/logo-list /-->
```

## Pre-launch checklist (owner steps — not code)

- Real SMTP mailer + credentials under Settings → WP Mail SMTP.
- A remote backup destination under Settings → UpdraftPlus Backups.
- `AGENTIC_GA4_ID` / `AGENTIC_META_PIXEL_ID` as `wp-config.php` constants,
  if you want analytics/ad pixels — neither fires until set.
- Payment gateway credentials, live shipping/tax accounts, Yoast social
  profile URLs, domain/SSL — full list in `CLAUDE.md`'s "Owner-editable in
  wp-admin" section.
- Delete the seeded sample products before launch.

## Start developing with Claude

```bash
cd my-new-store
claude
```
Claude Code reads `CLAUDE.md` automatically. Example first prompt:
```
Read CLAUDE.md. Build a new template for a WooCommerce single product page
(templates/single-product.html), and a custom "product-badge" block that
shows a "New" ribbon on products tagged as new-arrival. Verify with a
screenshot and a lighthouse check afterward.
```

## Cloning for a new project

`git clone` this repo under a new name, `wp-env start`, then point Claude at
`system-design/` (once populated with the new brand's material) and ask it
to apply the brand — it edits `theme.json` and the relevant blocks, never
wp-admin.
