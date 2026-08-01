# Agentic WordPress — Boilerplate for Ecommerce Sites

## What this is
A code-first WordPress boilerplate, structured to feel like developing a
Shopify theme with the Shopify CLI + Liquid — but on WordPress, with
WooCommerce and SEO built in. It is meant to be **cloned** as the starting
point for every new store, not built from scratch each time.

## CORE RULE — read this first
**All building happens by writing files. Never by clicking through wp-admin.**

- Pages/layouts → edit `theme/agentic-theme/templates/*.html` and
  `theme/agentic-theme/parts/*.html` directly (this is the Liquid-file
  equivalent — pure block markup as code).
- Design tokens (colors, spacing, fonts) → edit
  `theme/agentic-theme/theme.json` directly (this is the
  `settings_schema.json` equivalent).
- Custom components ("sections") → run `./scripts/new-block.sh <slug>`, which
  scaffolds `agentic-blocks/blocks/<slug>/` correctly (`block.json` +
  `render.php` + `style.css`, and `index.js` with `--with-js`). Follow the
  `hero-banner` block as the reference pattern.
- New page types → run `./scripts/new-template.sh <template-name>`, which
  creates a template already wired to the header and footer parts.
- The WordPress admin UI (wp-admin), the block editor, and any browser
  automation (Playwright) are **never used to construct pages, blocks, or
  content**. They are only used to verify what the code already produced.

If a task can be done by writing/editing a file, do that — never simulate a
user clicking buttons to achieve the same result.

## Design direction
The visual target is **Shopify's Dawn theme**: generous whitespace, a near-
black-on-white monochrome palette with one accent, restrained type, square-ish
solid buttons, full-width image banners, and clean product grids. When adding
anything new, match that language rather than inventing a new one.

Every section's max-width, both font families, and every color are single
tokens in `theme.json` — change them there and the whole site (including
cart/checkout/account) follows, never per-block or per-template:
- `settings.layout.contentSize` / `wideSize` — both `1400px`. One consistent
  cap everywhere, chosen to make real use of large screens. WooCommerce ships
  its own hardcoded `max-width:1000px` on cart/checkout/account content
  (`woocommerce-blocktheme.css`) that ignores this token entirely — overridden
  back to `var(--wp--style--global--content-size)` in
  `assets/css/woocommerce.css`. If cart/checkout/account content ever looks
  narrower than the rest of the site again, that WooCommerce stylesheet is
  almost certainly why — check it before assuming a regression in ours.
- `settings.typography.fontFamilies` — two slugs, `body` (sans-serif, used for
  all running text) and `heading` (serif, applied globally to every `h1`-`h6`
  via `styles.elements.heading`). Both are system font stacks — no font files,
  no network request — swap the values to rebrand, or replace with real
  webfonts via the `../system-design/fonts/` workflow below.
- `settings.color.palette` — every block and template consumes
  `var(--wp--preset--color--*)`, never a literal hex/rgb/named color. Verify
  with `grep -rnE '#[0-9a-fA-F]{3,8}\b' theme/agentic-theme agentic-blocks/blocks`
  (3-digit hex included — `#fff` slipped past a 6-digit-only check once).

## Brand inputs — `../system-design/`
Brand material lives in a `system-design/` folder **next to** this repo (it is
deliberately outside, so the published boilerplate stays brand-neutral):

```
../system-design/
  identity/    <- brand guideline PDFs, logos, wordmarks
  fonts/       <- font files + licences
  styles/      <- colour specs, type scales, spacing rules
  copy/        <- real text: taglines, about copy, product descriptions
  references/  <- screenshots of layouts to match (e.g. Dawn)
  exports/     <- anything generated from the above
```

**Before any styling or branding work**, check whether that folder exists and
read `identity/` and `styles/`. Translate what you find into code —
`theme.json` for tokens, `parts/*.html` for layout, `blocks/*` for sections.
Fonts you actually use must be copied into
`theme/agentic-theme/assets/fonts/` and registered in `theme.json`; WordPress
can only serve fonts from inside the theme. If the folder is absent or empty,
keep the current neutral defaults and say so — do not invent a brand.

## Stack (what's pre-installed, all free)
- **WooCommerce** (official, free) — ecommerce engine
- **Yoast SEO** (free version) — SEO meta, sitemaps, schema
- **WP Mail SMTP** (free) — WordPress's default `wp_mail()` gets spam-filtered
  by most hosts; order confirmation emails silently never arrive without this
- **UpdraftPlus** (free) — backups. Schedule/retention are set in code (see
  `setup-site.sh`); remote storage destination is an owner step (below)
- Custom theme: `theme/agentic-theme` — block theme (FSE), no page builder
- Custom plugin: `agentic-blocks` — custom block library

WooCommerce, Yoast, WP Mail SMTP, and UpdraftPlus are all installed by slug
from wordpress.org by `scripts/setup-site.sh`, which `.wp-env.json` runs
automatically via `lifecycleScripts.afterStart`. Do **not** also list them as
zip URLs in `.wp-env.json` — zip sources unpack into folders named after the
zip (`woocommerce.latest-stable`), which collides with the slug-named copy and
produces a `Cannot redeclare WC()` fatal.

**Analytics/ad pixels (GA4, Meta Pixel) are deliberately not a plugin** — two
script tags plus one purchase-conversion event isn't enough surface area to
justify a dependency. See the "Analytics / ad pixels" block at the top of
`theme/agentic-theme/functions.php`: both `AGENTIC_GA4_ID` and
`AGENTIC_META_PIXEL_ID` default to empty (nothing fires, nothing is invented)
and are meant to be set as `wp-config.php` constants, not edited into the
theme file, so real IDs never need a code change or land in git history for a
boilerplate meant to be cloned. The purchase event is guarded by order meta
(`_agentic_purchase_tracked`) so revisiting the thank-you page never
double-counts a sale — a common, easy-to-miss bug in hand-rolled pixel setups.

The `theme/agentic-theme` and `agentic-blocks` folders are bind-mounted from
this repo, so editing them on disk edits the live site instantly.

## Environment
- `wp-env start` — boots WordPress + MySQL in Docker, auto-activates the
  theme and all plugins (see `lifecycleScripts.afterStart` in `.wp-env.json`)
- Site: http://localhost:8888 · Admin: http://localhost:8888/wp-admin
  (user: `admin` / pass: `password`, wp-env defaults)
- `wp-env run cli wp <command>` — run any WP-CLI command (never hardcode
  container names; wp-env resolves the container itself)
- `wp-env stop` / `wp-env destroy` — stop / full reset
- Container names include a random hash and can change after `destroy` —
  always use `wp-env run cli`, never `docker exec` with a remembered name

## Folder structure
```
theme/agentic-theme/
  theme.json          <- global design tokens
  functions.php       <- minimal theme PHP (resolves menu slugs → nav refs)
  templates/*.html    <- one file per page type (see the table below)
  parts/*.html        <- reusable regions (header, footer)
agentic-blocks/
  agentic-blocks.php  <- auto-registers every block in blocks/, adds helpers
  webpack.config.js   <- one build entry per block (do NOT replace with a glob)
  blocks/<name>/
    block.json        <- schema: name, attributes, supports
    render.php        <- server-side markup (dynamic block)
    index.js          <- editor UI (only if attributes need editing controls)
    style.css         <- scoped styles, no framework dependency
scripts/
  setup-site.sh       <- code-first site configuration, runs on wp-env start
  new-block.sh        <- scaffold a new section
  new-template.sh     <- scaffold a new template (header/footer pre-wired)
  lighthouse-check.sh <- performance gate
```

### Template coverage
Every template below exists as code and **must keep the header and footer
template parts**. Never let WooCommerce fall back to its PHP templates.

| Template | Used for |
|---|---|
| `front-page` | Storefront homepage at `/` — composed from sections |
| `home` | The Journal at `/journal/` — blog post list |
| `index` | Generic fallback |
| `single`, `page` | Post, page |
| `page-full-width` | Page variant with no content constraint |
| `archive`, `search`, `404` | Archives, search results, not-found |
| `archive-product` | Shop / product archive |
| `single-product` | Product detail |
| `taxonomy-product_cat`, `taxonomy-product_tag` | Product category / tag |
| `page-cart`, `page-checkout`, `page-my-account` | WooCommerce pages |
| `order-confirmation` | Post-purchase |

### Navigation menus — deliberately dashboard-editable
Header and footer menu **items** are NOT hardcoded in the template files, and
must not be turned back into hardcoded links. They are `wp_navigation` menus,
editable by the store owner in **Appearance → Editor → Navigation**:

| Slug | Where |
|---|---|
| `header-menu` | Main header nav |
| `footer-shop` | Footer "Shop" column |
| `footer-help` | Footer "Help" column |

Templates reference them by slug, never by numeric ID:
```html
<!-- wp:navigation {"agenticMenu":"header-menu","overlayMenu":"mobile"} /-->
```
`agenticMenu` is resolved to the core Navigation block's `ref` at render time
by a `render_block_data` filter in `theme/agentic-theme/functions.php`. This
exists because `ref` is a post ID that differs in every install, so a template
committed to this repo could never hardcode one and still work in a clone.

`scripts/setup-site.sh` creates the three menus **only if missing**, so
re-running it never overwrites the owner's edits.

**This is not a violation of the code-first rule.** Menu items are content, not
layout — the same split Shopify makes, where sections live in theme files but
menus live in the admin. Layout (where the nav sits, how it is styled) stays in
`parts/header.html` and `theme.json`. To add a *new* menu: add an
`ensure_menu` call in `setup-site.sh` and reference the new slug from a
template part.

### Homepage and Journal — how the routing works
`scripts/setup-site.sh` creates two container pages and points WordPress at
them: **Home** (`page_on_front`) and **Journal** (`page_for_posts`), with
`show_on_front = page`. So `/` is a static page, not the post feed, and blog
posts live at `/journal/`.

**The homepage's content is in `templates/front-page.html`, not in the Home
page.** `front-page.html` does not render `wp:post-content`, which means
editing the "Home" page in wp-admin has no visible effect — by design. To
change the homepage, edit the section markup in `front-page.html`. Same for
the Journal: edit `templates/home.html`.

That is the whole point of the code-first rule — the homepage is a file, and
the page record exists only so WordPress routes `/` correctly.

### Section library (`agentic-blocks/blocks/`)
`hero-banner`, `featured-collection`, `image-with-text`, `multicolumn`,
`testimonials`, `faq-accordion`, `newsletter-signup`, `collection-list`,
`rich-text`, `logo-list`, `video-section`, `countdown-banner`,
`product-badge`, `announcement-bar`.

Sections are **attribute-driven**, like Shopify section settings — they take
configuration through block attributes rather than requiring nested inner
blocks. Keep that pattern: it is what makes a section composable from a
template file without any editor interaction. Every `render.php` escapes its
output and starts from `agentic_section_classes( '<slug>' )`.

## Build step for block JS
```bash
cd agentic-blocks
npm install
npm run build     # webpack.config.js: one entry per blocks/*/index.js
```
`setup-site.sh` runs this for you on `wp-env start`, so a fresh clone needs
no separate build step. Run it manually after editing any `index.js`.

**How the JS wiring works — do not "simplify" it back:**
- `webpack.config.js` builds one entry per block →
  `build/<block>/index.js` + `build/<block>/index.asset.php`.
- Each `block.json` must point at the *built* file, not the source:
  `"editorScript": "file:../../build/<block>/index.js"`.
- The `.asset.php` sibling is mandatory. Without it WordPress silently
  registers the block with **no editor script** — the block still renders on
  the front end via `render.php`, so this failure is invisible until you open
  the editor. Verify with:
  ```bash
  wp-env run cli wp eval 'print_r(WP_Block_Type_Registry::get_instance()->get_registered("agentic/<block>")->editor_script_handles);'
  ```
  An empty array means the wiring is broken.
- Never use `wp-scripts build blocks/*/index.js --output-path=build`: the
  glob collapses every block into one bundle and silently drops all but the
  first.

## Verification workflow (after writing/editing code — not for building)
1. `wp-env run cli wp plugin activate agentic-blocks` (only if not already active)
2. Via Playwright MCP: open a page that uses the new template/block and take
   a screenshot, purely to visually confirm the code renders as intended
3. Run `./scripts/lighthouse-check.sh <path> [min-score] [categories]` — it
   exits non-zero if any category is below the threshold (default 90), since
   the whole point of this boilerplate is zero bloat. Treat a non-zero exit as
   a failing check, not a suggestion.
   - Cart, checkout, and my-account are intentionally **noindex**, so their
     SEO score is legitimately low. Audit those without the seo category:
     `./scripts/lighthouse-check.sh /cart/ 90 performance,accessibility,best-practices`
     Do not try to make them indexable.
4. Fix issues by editing the source files again — never by adjusting
   settings through the UI

## Site setup lives in code too
`scripts/setup-site.sh` is the code-first equivalent of the WooCommerce
onboarding wizard. Anything a fresh clone needs — permalink structure,
disabling WooCommerce's Coming Soon splash, seeding a sample product,
store defaults — belongs there, not in wp-admin. It is idempotent and re-runs
on every `wp-env start`. If you catch yourself about to change a setting in
wp-admin, add a `wp option update` line to that script instead.

### Dashboard "Store setup checklist" widget
`theme/agentic-theme/inc/setup-checklist.php` adds a widget to the top of
wp-admin's Home/Dashboard screen that self-checks every item in the
"Owner-editable in wp-admin" list below (payment gateway, SMTP, backups,
shipping, tax, Yoast representation, sample content, domain/SSL) and links
straight to the relevant settings screen. This is how a clone's owner finds
out what's left to configure without reading this file first — keep the two
in sync: a new owner-only setup step belongs in **both** places.

### Owner-editable in wp-admin (legitimate, by design)
These are the store owner's job, not the agent's, and editing them in wp-admin
is **not** a violation of the code-first rule — they are content or external
credentials, not layout:
- **Navigation menus** (Appearance → Editor → Navigation) — see the
  Navigation menus section above. Seeded once, then owned by the store.
- Product catalogue, prices, images, and descriptions
- Page copy for About / policy pages
- Payment gateway credentials (Stripe/PayPal API keys)
- Live shipping-carrier accounts and real tax/nexus configuration
- Yoast site-representation + social profile URLs
- Domain, SSL, and production host settings
- WP Mail SMTP mailer + credentials (Settings → WP Mail SMTP) — installed and
  activated by `setup-site.sh`, but *which* SMTP provider and its API
  key/credentials are the owner's to set
- UpdraftPlus remote storage destination (Settings → UpdraftPlus Backups) —
  schedule/retention are set in code; with no remote destination configured,
  backups only ever land in `wp-content/updraft` on the same server, which
  does not protect against losing that server. Set one before launch
- `AGENTIC_GA4_ID` / `AGENTIC_META_PIXEL_ID` — set as `wp-config.php`
  constants (not edited into `functions.php`), see the Stack section above

## Do NOT
- Do not hardcode navigation links back into `parts/header.html` or
  `parts/footer.html` — menus are owner-editable `wp_navigation` menus,
  referenced by slug. Do not hardcode a numeric `ref` either; it breaks on
  every clone.
- Do not install a page builder (Elementor, Divi, etc.)
- Do not build pages/content by driving the browser UI — write the
  template/block files instead
- Do not hardcode Docker container names
- Do not edit WordPress core
- Do not use a paid/premium plugin — this boilerplate only uses free plugins
  or free tiers of otherwise-paid plugins

## Cloning this for a new store
1. `git clone` this repo into a new folder, rename it
2. `wp-env start` — WooCommerce + Yoast SEO + theme + blocks activate automatically
3. Edit `theme.json` for the new brand's colors/fonts
4. Add/adjust blocks under `agentic-blocks/blocks/` for anything store-specific
5. Everything else (product pages, cart, checkout, SEO metadata, sitemaps)
   is already wired up via WooCommerce + Yoast, using their normal WordPress
   data model — no rebuilding those parts per project
