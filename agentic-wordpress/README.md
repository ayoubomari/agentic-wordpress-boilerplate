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
- **The SEO Framework** (free) — meta, sitemaps, schema. Auto-generates
  titles and descriptions from existing content, so pages are not shipped
  without a `<meta name="description">` by default
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

`agentic-blocks/blocks/` currently has 23 sections: `hero-banner`,
`featured-collection`, `image-with-text`, `multicolumn`, `testimonials`,
`faq-accordion`, `newsletter-signup`, `collection-list`, `rich-text`,
`logo-list`, `video-section`, `countdown-banner`, `product-badge`,
`announcement-bar`, `cta-cards`, `search-drawer`, `payment-badges`,
`photo-marquee`, `photo-statement`, `shop-the-set`, `product-subcategories`,
`latest-posts`, `contact-form`.

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

## Content handled outside plain template markup, by design

Header and footer navigation is **not** one of these exceptions — menu
items are plain `wp:navigation-link` blocks hardcoded into
`parts/header.html`/`parts/footer.html`, same as every other section, with
no dashboard-editable menu behind them. See "Navigation menus" in
`CLAUDE.md`.

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
`page`, `page-about-us`, `page-contact`, `page-full-width`, `archive`,
`search`, `404`, `archive-product` (shop), `single-product`,
`taxonomy-product_cat`, `taxonomy-product_tag`, `page-cart`,
`page-checkout`, `page-my-account`, `order-confirmation`.

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
buttons, bold rounded-sans headings) rather than inventing a new direction —
same rule for a logo: dropping one at
`theme/agentic-theme/assets/images/brand/site-icon.png` (square, ≥512×512,
PNG) makes `setup-site.sh` wire it into the favicon and the sitewide social
fallback image on the next `wp-env start`; with no file there, the site
correctly has no favicon yet rather than a placeholder mark.

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
and seeds four sample products across three categories. No wp-admin
clicking required.

Visit:
- Storefront: http://localhost:8888
- Shop: http://localhost:8888/shop/
- Journal: http://localhost:8888/journal/
- Admin: http://localhost:8888/wp-admin (`admin` / `password`)

## Optional: docker-compose for VPS demo hosting

`docker-compose.yml` at the repo root is an alternative to `wp-env` for
hosting a running clone somewhere other than a local dev machine (a plain
VPS demo, for instance) — `wp-env` itself is dev-only tooling. Same
bind-mounts as `wp-env` (`theme/agentic-theme`, `agentic-blocks`), same port
(`8888`), plus a `wpcli` service for running WP-CLI commands:
```bash
DB_PASSWORD=... DB_ROOT_PASSWORD=... docker compose up -d
docker compose run --rm wpcli <command>   # e.g. core install, plugin list
```
It does not run `setup-site.sh` itself — that script currently hardcodes
`wp-env run cli` and can't yet target the `wpcli` service directly; run the
equivalent WP-CLI commands manually via `docker compose run --rm wpcli ...`
until it's made backend-agnostic.

## Optional: product/category sync between local and a VPS

`scripts/sync-products.sh pull [user@host]` pulls the live WooCommerce
catalog (products, categories, images) from a VPS running the
docker-compose setup above down into local wp-env, so you're developing
against real data instead of the four seeded placeholders. Matches by SKU,
safe to re-run. `push` (local → VPS) exists for a one-time initial catalog
migration before a store goes live and requires `--force` — see the
script's own header comment, and "Deployment & environment sync" in
`CLAUDE.md` for the full policy on what does and doesn't sync between
environments (code via git, catalog via this script, orders/customers
never bidirectionally). `user@host` is optional on the command line — see
the next section for setting a default.

## Optional: automated deploy + sync via GitHub Actions

`scripts/deploy.sh` (code → VPS: `git pull` + rebuild JS bundles) and
`scripts/sync-products.sh` both resolve their VPS target the same way, via
`scripts/lib/ssh-target.sh`:
1. An explicit host passed as a CLI argument, if given.
2. Otherwise `VPS_HOST` / `VPS_REMOTE_PATH` / `VPS_SSH_KEY` from `.env`
   (`cp .env.example .env` and fill it in — gitignored, never commit it).
3. In CI, the same variable names come from repository secrets instead —
   `.github/workflows-disabled/deploy.yml` (would auto-run on push to
   `main`) and `sync-products.yml` (manual trigger only, typed confirmation
   required — see its header for why `pull` isn't offered there: it exists
   to load real data into *your own* local wp-env, which a throwaway CI
   runner doesn't have).

Both workflows live in `workflows-disabled/`, not `workflows/` — GitHub
only triggers what's literally inside `.github/workflows/`, so they're
inert until you `mv` them over. See "CI (GitHub Actions) — written,
deliberately not live yet" in the top-level `README.md` for the exact
command and the repository secrets (`VPS_SSH_KEY`, `VPS_HOST`,
`VPS_REMOTE_PATH`) both need first. Once enabled, they still fail fast on
an explicit "secrets are not configured yet" check rather than doing
nothing silently if a secret's missing.

## Optional: read-only WooCommerce MCP server

`.mcp.json` also declares a `woocommerce` MCP server
([`@wppoland/woocommerce-mcp`](https://github.com/wppoland/woocommerce-mcp)) —
lets Claude *inspect* live store state (orders, stock, products) mid-session
without a wp-admin detour. It's read-only ("never creates, edits, or deletes
anything in the store") and opt-in: without credentials it just fails to
connect, same as any other MCP server would, and nothing else in the session
is affected. It does not change the CORE RULE — content and layout are still
only ever written as files; this is purely a way to *read* what a running
store looks like.

To enable it, generate a **Read**-only WooCommerce REST API key (WooCommerce
→ Settings → Advanced → REST API in wp-admin — credential generation is
owner work, same category as the Stripe/PayPal keys in the pre-launch
checklist below, not a CORE RULE exception) and export it before starting
Claude Code:
```bash
export WC_CONSUMER_KEY=ck_xxxxxxxx
export WC_CONSUMER_SECRET=cs_xxxxxxxx
```
Leave them unset to skip it entirely — the rest of `.mcp.json` (Playwright)
is unaffected either way.

## AI shopping visibility

Two endpoints in `functions.php`, both generated live from real WooCommerce
data on every request (no static file to regenerate as the catalog changes):

- `/robots.txt` — explicit allow/disallow rules for named AI user-agents
  (GPTBot, ClaudeBot, PerplexityBot, …): catalog open, cart/checkout/account
  closed.
- `/llms.txt` — a short, curated summary for AI agents, per the
  [llms.txt](https://llmstxt.org/) convention. Worth knowing: real crawl data
  suggests most AI bots don't actually fetch this file — it's a cheap
  courtesy, not the thing that gets a product into a chat answer.
- `/product-feed.xml` — a Google Merchant Center product feed (RSS 2.0 +
  `g:` namespace). This is the one that matters: Google's own docs say AI
  Mode, AI Overviews, and Gemini all ground their shopping answers in the
  Merchant Center feed. No checkout integration needed, and unlike OpenAI's
  ChatGPT product-discovery feed or Perplexity's Merchant Program, it isn't
  bundled under any beta agentic-commerce protocol. Register the feed URL in
  a free Merchant Center account to turn it on (see the pre-launch checklist
  below).

## Verification workflow

Screenshot and Lighthouse checks are opt-in — Claude asks before running
either, rather than doing it after every edit, so quick iteration isn't
blocked on a check each time. When you do want one:
1. `wp-env run cli wp plugin activate agentic-blocks` if not already active
2. Screenshot the affected page via Playwright — visual confirmation only,
   never how the change was made (`.claude/skills/playwright-verify/`)
3. `./scripts/lighthouse-check.sh <path> [min-score] [categories]` — fails
   non-zero under the threshold (default 90). Cart/checkout/account are
   intentionally noindex, so audit those without the `seo` category
   (`.claude/skills/lighthouse-optimize/`)

Fix issues by editing source files again — never by adjusting settings
through the UI.

## Skills

Recurring workflows live under `.claude/skills/`, each grounded in this
repo's actual code:

| Skill | For |
|---|---|
| `playwright-verify` | Screenshotting a page/template/block to confirm it renders as intended |
| `lighthouse-optimize` | Running the Lighthouse gate and fixing the LCP/render-blocking/caching failures that actually recur here |
| `image-to-webp` | Converting a sourced/generated image to WebP, sized to the target block's aspect ratio |
| `responsive-design` | Breakpoints, fluid type, and aspect-ratio conventions each block follows |
| `apply-brand-input` | Turning a prompt, reference screenshot, and/or `../system-design/` into `theme.json`/attribute edits |
| `ai-image-prompts` | AI image-generation prompts matched to a block's aspect ratio and design language, plus safe handling of a user-supplied image-gen API key |

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
- Payment gateway credentials, live shipping/tax accounts, site
  representation + social profile URLs, domain/SSL — full list in
  `CLAUDE.md`'s "Owner-editable in wp-admin" section.
- Register `/product-feed.xml` in a Google Merchant Center account (see "AI
  shopping visibility" above) and give each real product a GTIN.
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

## License

MIT — see [`LICENSE`](../LICENSE). Fork it, rebrand it, ship it.
