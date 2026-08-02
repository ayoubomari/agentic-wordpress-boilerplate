# Agentic WordPress Ecommerce Boilerplate

A cloneable starting point for building WooCommerce stores with an AI coding
agent (Claude Code), structured like a Shopify theme + Shopify CLI workflow —
code-first, no page builders, no manual clicking.

## Requirements
- Docker Desktop (running)
- Node.js 18+
- Google Chrome or Chromium (for `scripts/lighthouse-check.sh`)
- Claude Code (`npm install -g @anthropic-ai/claude-code` or see claude.com/code)

## First-time setup
```bash
npm -g install @wordpress/env   # once per machine
git clone <this-repo-url> my-new-store
cd my-new-store
wp-env start
```
That's it. `wp-env start` runs `scripts/setup-site.sh`, which:
- builds the block editor bundles (`npm install && npm run build`)
- installs + activates WooCommerce and Yoast SEO (free) from wordpress.org
- installs + activates WP Mail SMTP and UpdraftPlus (free) — email
  deliverability and backups, the two things a store can't skip
- activates the custom theme and the `agentic-blocks` plugin
- sets permalinks to `/%postname%/`
- makes `/` a **static storefront page** (not the blog feed) and puts blog
  posts at `/journal/`
- **turns off WooCommerce's "Coming Soon" splash** (on by default in
  WooCommerce 9.1+; it otherwise hides your entire storefront)
- seeds one sample product so the product templates render with real data

No wp-admin clicking required. The script is idempotent — it re-runs safely
on every start.

Visit:
- Storefront: http://localhost:8888
- Shop: http://localhost:8888/shop/
- Journal: http://localhost:8888/journal/
- Admin: http://localhost:8888/wp-admin (`admin` / `password`)

The homepage lives in `theme/agentic-theme/templates/front-page.html`, not in
the "Home" page record — editing that page in wp-admin does nothing. Edit the
template file instead.

## What the store owner edits, vs. what you edit in code
| Edited in wp-admin | Edited in code |
|---|---|
| Navigation menus (Appearance → Editor → Navigation) | Page layout and sections |
| Products, prices, images | Design tokens (`theme.json`) |
| Page copy | Templates and template parts |

Header and footer menus are real, editable WordPress menus (`header-menu`,
`footer-shop`, `footer-help`) — add or reorder items from the dashboard and
the change appears everywhere, no rebuild. `wp-env start` seeds them once and
never overwrites your edits afterwards.

Delete the 4 seeded skincare/bath-body products before launching a real store.

## Pre-launch checklist (owner steps — not code)
- Set a real SMTP mailer + credentials under Settings → WP Mail SMTP, so order
  emails actually deliver.
- Set a remote backup destination under Settings → UpdraftPlus Backups —
  without one, backups only ever land on the same server they're backing up.
- If you want analytics/ad pixels, add `AGENTIC_GA4_ID` and/or
  `AGENTIC_META_PIXEL_ID` as constants in `wp-config.php`. Neither fires until
  set.
- Payment gateway credentials, live shipping/tax accounts, Yoast social
  profile URLs, domain/SSL — see CLAUDE.md's "Owner-editable in wp-admin"
  section for the full list.

## Start developing with Claude
```bash
cd my-new-store
claude
```
Claude Code reads `CLAUDE.md` automatically and knows: the folder structure,
the "write code, don't click" rule, how to run WP-CLI, and how to verify its
own work with Playwright + Lighthouse.

Example first prompt:
```
Read CLAUDE.md. Build a new template for a WooCommerce single product page
(templates/single-product.html), and a custom "product-badge" block that
shows a "New" ribbon on products tagged as new-arrival. Verify with a
screenshot and a lighthouse check afterward.
```

## Why this structure
See `CLAUDE.md` for the full mapping, but in short: WordPress **block
themes** (HTML template files) are the direct equivalent of Shopify Liquid
theme files, `theme.json` is the equivalent of `settings_schema.json`, and
custom **blocks** (`block.json` + `render.php`) are the equivalent of custom
Liquid sections. `wp-env` is the Docker-based equivalent of the Shopify CLI
dev server.

## What's included, free
- WooCommerce (official, free)
- Yoast SEO (free tier)
- A block theme styled after Shopify's **Dawn** — monochrome, generous
  whitespace, no page-builder dependency
- Full template coverage: homepage, shop, product, cart, checkout, account,
  order confirmation, category, tag, search, archive, 404 — every one of them
  carries the site header and footer
- A section library you compose pages from, like Shopify sections:
  `hero-banner`, `featured-collection`, `image-with-text`, `multicolumn`,
  `testimonials`, `faq-accordion`, `newsletter-signup`, `collection-list`,
  `rich-text`, `logo-list`, `video-section`, `countdown-banner`,
  `product-badge`, `announcement-bar`

## Adding a section or page
```bash
./scripts/new-block.sh logo-list          # add --with-js for editor controls
./scripts/new-template.sh page-lookbook   # header/footer pre-wired
```
Then reference the section in a template:
```html
<!-- wp:agentic/logo-list /-->
```
Sections take their configuration from block attributes, so a whole page can
be composed from a template file with no clicking.

## Branding a clone
Brand material goes in a `system-design/` folder **next to** this repo:
```
system-design/
  identity/  fonts/  styles/  copy/  references/  exports/
```
It sits outside the repo so the boilerplate stays brand-neutral when
published. See `system-design/README.md`. Point Claude at it and ask it to
apply the brand — it writes the result into `theme.json`, not into wp-admin.

## Cloning for a new project
Just `git clone` this repo again under a new name and repeat "First-time
setup." Swap colors/fonts in `theme.json`, add store-specific blocks, done.
