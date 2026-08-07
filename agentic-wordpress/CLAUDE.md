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
The visual target is **Shopify's Sleek theme** (a beauty/DTC storefront —
reference: https://sleek-theme-demo.myshopify.com/, linked from
themes.shopify.com/themes/sleek): a warm blush/terracotta palette, fully-
rounded pill buttons and badges, bold rounded-sans display headings,
full-bleed hero carousels, tabbed/badged product grids, and colored promo
panels. This replaced an earlier neutral "Dawn" (monochrome, square-button)
placeholder direction — if you find old references to Dawn/square buttons/
serif headings elsewhere, they're stale; Sleek's language below is current.
When adding anything new, match that language rather than inventing a new one.

Every section's max-width, both font families, every color, and the shape
scale (button/badge/card/panel corner radius) are single tokens in
`theme.json` — change them there and the whole site (including cart/
checkout/account) follows, never per-block or per-template:
- `settings.layout.contentSize` / `wideSize` — both `1400px`. One consistent
  cap everywhere, chosen to make real use of large screens. WooCommerce ships
  its own hardcoded `max-width:1000px` on cart/checkout/account content
  (`woocommerce-blocktheme.css`) that ignores this token entirely — overridden
  back to `var(--wp--style--global--content-size)` in
  `assets/css/woocommerce.css`. If cart/checkout/account content ever looks
  narrower than the rest of the site again, that WooCommerce stylesheet is
  almost certainly why — check it before assuming a regression in ours.
- `settings.typography.fontFamilies` — `body` and `heading` both use **DM
  Sans**, a self-hosted variable webfont (weights 100–1000, upright +
  italic) checked into `theme/agentic-theme/assets/fonts/` (`DMSans-
  Variable.woff2` / `DMSans-Variable-Italic.woff2`, SIL Open Font License —
  see `OFL.txt` in the same folder) and declared via `fontFace` entries on
  the `body` font-family so WordPress emits the `@font-face` rules and no
  request ever leaves the theme. `heading` reuses the same family and is
  styled bold (800 weight, tight letter-spacing) via
  `styles.elements.heading`. To rebrand: swap the two `.woff2` files and the
  `fontFamily` name, or follow the `../system-design/fonts/` workflow below
  for a different typeface.
- `settings.color.palette` — `blush`/`sand` are the warm promo-panel surfaces,
  `new-badge`/`selling-fast`/`sale` are the three product badge colors.
  Every block and template consumes `var(--wp--preset--color--*)`, never a
  literal hex/rgb/named color. Verify with
  `grep -rnE '#[0-9a-fA-F]{3,8}\b' theme/agentic-theme agentic-blocks/blocks`
  (3-digit hex included — `#fff` slipped past a 6-digit-only check once).
- **Shape scale** — three custom properties defined once in `theme.json`'s
  top-level `styles.css`: `--agentic-radius-pill` (999px — buttons, badges,
  pill nav), `--agentic-radius-card` (10px — product cards, thumbnails),
  `--agentic-radius-panel` (20px — hero/promo full-size panels). Every block
  references one of these three, never a literal `border-radius: Npx`.

### Carousels — shared script, no per-block JS
`hero-banner` (2+ `slides`) and `testimonials` (`layout: "carousel"`) both
use one shared vanilla-JS file, `agentic-blocks/assets/carousel.js`,
registered once as the `agentic-carousel` script **handle** in
`agentic-blocks.php` (not a per-block `"file:"` path — that would register a
*different* handle per block and load the identical file twice on any page
using both). Each block conditionally `wp_enqueue_script('agentic-carousel')`s
it from `render.php` only when it actually renders carousel markup, rather
than declaring it in `block.json`'s `viewScript` — block.json is static, so
that would load the script on every hero-banner instance including
single-slide ones that need no JS at all. `featured-collection`'s tabs use a
different, JS-free technique (radio-input + CSS `~` sibling selectors) since
a plain "show the panel whose radio is checked" toggle doesn't need a script.

Both blocks also call `agentic_carousel_loop_precorrect()` (agentic-blocks.php)
immediately after their `.agentic-carousel__track` markup. Looping needs a
clone of the last slide before the first (and vice versa) so the track can
scroll-snap "past" either end onto a pixel-identical clone; `carousel.js`
alone did that clone-insert-and-scroll correction, and it stayed invisible
only as long as the page rendered slowly enough to finish it before first
paint. On a fast-rendering page the browser paints the *pre*-correction
layout first and the correction becomes a real, measured layout shift.
`agentic_carousel_loop_precorrect()` prints that same correction as a
synchronous inline `<script>` right in the HTML stream — which blocks
parsing/painting of just that point until it finishes — so the first frame
ever painted is already correct. It leaves a `data-carousel-precorrected`
flag on the track; `carousel.js` checks for it and skips redoing the same
work rather than double-inserting clones. Don't remove either half without
the other — carousel.js alone would reintroduce the shift, and the inline
script alone would leave prev/next/dots/autoplay unwired.

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
keep the current defaults and say so — do not invent a brand.

See the `apply-brand-input` skill (`.claude/skills/apply-brand-input/`) for
the concrete step-by-step version of this — how to reconcile this folder, a
user's written prompt, and a pasted reference screenshot into one set of
`theme.json`/attribute edits.

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

**Form submissions (newsletter-signup) are likewise not a forms plugin** —
same call as analytics above: storing an email and notifying the admin isn't
enough surface area to justify a dependency with its own entries UI and DB
tables. `agentic-blocks/inc/form-entries.php` is the storage layer: a
private, non-public `agentic_form_entry` CPT (wp-admin → Form Entries, admin
capability only, `create_posts` disabled so entries only ever come from real
submissions) plus `agentic_record_form_entry( $type, $email, $page )`, a
small reusable helper any future form block can call. `newsletter-signup`'s
`render.php` uses it automatically whenever its `action` attribute is empty:
the form posts to `admin-post.php` (nonce + a CSS-hidden honeypot field
against bots) instead of your ESP, which stores the entry and emails
`admin_email` via `wp_mail()` — actual delivery still depends on WP Mail SMTP
being configured (see the "Email deliverability" setup-checklist item). Set
`action` to a real ESP endpoint (Mailchimp, Klaviyo, Buttondown …) to bypass
this entirely and post straight there instead, same as before.

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

### Navigation menus — hardcoded, like everything else
Header and footer menu **items** are plain `wp:navigation-link` blocks
written directly into `parts/header.html` and `parts/footer.html`, using
core's Navigation block with real inner links instead of a `ref` to a
`wp_navigation` post:

```html
<!-- wp:navigation {"overlayMenu":"mobile"} -->
<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom","isTopLevelLink":true} /-->
<!-- /wp:navigation -->
```

This boilerplate is fully agentic-oriented: there is no dashboard-editable
content left as an exception, including navigation. Changing a menu means
editing the template part file and, if needed, verifying with a screenshot —
the same workflow as any other section change. There is no `wp_navigation`
post, no `agenticMenu` attribute, and no `ensure_menu` step in
`setup-site.sh` to keep in sync — this *is* the sync.

To add a new menu location: write the `wp:navigation` + `wp:navigation-link`
blocks directly into the relevant template part.

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
`product-badge`, `announcement-bar`, `cta-cards`, `search-drawer`,
`payment-badges`.

`payment-badges` is footer chrome, not a page section — a row of accepted
card-network icons (`methods` attribute, defaults to
`visa, mastercard, amex, paypal`), wired into `parts/footer.html` next to
the copyright line. Icons are simplified logo-style marks from
`agentic_payment_icon_svg()` in `agentic-blocks.php` (brand-colour chip +
wordmark/glyph, not traced official artwork — see that function's docblock
for why). The consent line ("By subscribing you agree to…") and social
icons are a full-width `wp:paragraph` + core `wp:social-links` row in
`parts/footer.html` directly, sitting *below* the narrow 30%-width
newsletter-signup column rather than inside it — that column isn't wide
enough for the sentence to stay on one line next to the icons.

`search-drawer` is header chrome, not a page section — a search icon plus a
slide-out panel (real WP search form, curated `popularSearches` keyword
links, and real `wc_get_products()`-sourced popular products — never
invented "most searched" analytics this store doesn't track). Wired into
`parts/header.html` next to the account/cart icons. Own `view.js` (open/
close/Escape/focus management), registered per-block via `block.json`'s
`viewScript` (not the shared `agentic-carousel` handle pattern below — this
script is only ever used by this one block, so there's no double-load risk
to avoid).

Several of these carry Sleek-specific attributes on top of their original
ones, always backward compatible (omit the attribute, get the original
behavior) — see each block's own `block.json` description, or the "Design
direction" section above for the shared carousel/tabs mechanics:
- `hero-banner` — `slides` (array) for a peek carousel; a single slide (or
  the attribute omitted) renders with no carousel chrome at all. Each slide
  also takes an `eyebrow` (small text above the heading) independent of the
  existing `subheading` (larger text below it) — both optional, use
  whichever the specific slide calls for. When a slide has no `imageUrl`,
  a brand-neutral abstract illustration (a few rotated rounded shapes, every
  fill a theme.json palette token) renders on the right instead of a bare
  color panel — see `agentic_hero_banner_illustration()` in
  `agentic-blocks.php`.
- `featured-collection` — `tabs` (array of `{label, category, onSale,
  maxPrice}`) for a tabbed collection switcher; `maxPrice` also works
  standalone (no tabs) for a price-filtered row.
- `product-badge` (and the shared `agentic_product_badge_markup()` helper in
  `agentic-blocks.php`) — sale badge shows a computed `-X%` instead of the
  word "Sale"; adds `sold-out` (real stock status) and `selling-fast` (real
  low-stock threshold, not invented) badge types. Up to two badges can show
  at once — a status badge (sold-out beats sale beats selling-fast) plus an
  independent "New" badge.
- `image-with-text` — `backgroundColor` + `imageStyle` (`"full"` | `"inset"`)
  for the colored promo-panel look; two instances side by side in a
  `wp:columns` wrapper make the "dual promo split" pattern.
- `multicolumn` — `variant` (`"columns"` | `"pills"`) for a compact
  checkmark trust-badge row. Named `variant`, not `style` — `style` is a
  reserved key WordPress auto-generates for any block with spacing/border/
  color supports enabled, so a custom attribute literally named `style`
  silently collides with it in the block's JSON attributes.
- `rich-text` — `linkStyle` (`"button"` | `"underline"`).
- `testimonials` — `layout` (`"grid"` | `"carousel"`) plus per-item
  `photoUrl`/`productName`/`productPrice`/`productImageUrl` for large
  photo-testimonial cards with a product chip.
- `faq-accordion` — `eyebrow`/`introHeading`/`introText`/`imageUrl`/`ctaText`/
  `ctaUrl` for a 2-column layout (portrait image on one side, eyebrow +
  heading + accordion stacked on the other, first item open by default)
  instead of a plain centered list.

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
Screenshot and Lighthouse checks are **opt-in, not automatic**. Once a
block/template change is done, ask the user whether they want a visual check
and/or a Lighthouse run before moving on — don't run either by default
unless their own request already implied it (e.g. "build X and show me a
screenshot"). This keeps the agent from burning a screenshot + a ~15s
Lighthouse run after every single small edit when the user is mid-iteration
and would rather batch verification at the end.

1. `wp-env run cli wp plugin activate agentic-blocks` (only if not already active)
2. If the user wants a visual check: see the `playwright-verify` skill
   (`.claude/skills/playwright-verify/`) — screenshot via Playwright MCP,
   purely to confirm the code renders as intended, never to build content.
3. If the user wants a performance check: see the `lighthouse-optimize`
   skill (`.claude/skills/lighthouse-optimize/`) —
   `./scripts/lighthouse-check.sh <path> [min-score] [categories]`, which
   exits non-zero if any category is below threshold (default 90). Treat a
   non-zero exit as a failing check, not a suggestion.
   - Cart, checkout, and my-account are intentionally **noindex**, so their
     SEO score is legitimately low. Audit those without the seo category:
     `./scripts/lighthouse-check.sh /cart/ 90 performance,accessibility,best-practices`
     Do not try to make them indexable.
4. Fix issues by editing the source files again — never by adjusting
   settings through the UI

## Skills
Recurring workflows for this boilerplate are written up as Claude Code
skills under `.claude/skills/`, each self-contained and grounded in this
repo's actual code (not generic advice):

| Skill | For |
|---|---|
| `playwright-verify` | Screenshotting a page/template/block to confirm it renders as intended — see "Verification workflow" above for when to invoke it |
| `lighthouse-optimize` | Running the Lighthouse gate and fixing the specific LCP/render-blocking/caching failure modes that actually recur in this codebase |
| `image-to-webp` | Converting a sourced or generated PNG/JPG into WebP, sized/cropped to the target block's aspect ratio, before it goes into `assets/images/` |
| `responsive-design` | The breakpoints, fluid-type, and aspect-ratio conventions each block already follows |
| `apply-brand-input` | Turning a written brand prompt, a reference screenshot, and/or `../system-design/` into actual `theme.json`/block-attribute edits |
| `ai-image-prompts` | Writing an AI image-generation prompt matched to a block's aspect ratio and the current design language, including how to handle a user-supplied image-gen API key without it touching git or chat output |

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
- Do not turn navigation menus back into owner-editable `wp_navigation`
  posts — menu items are hardcoded `wp:navigation-link` blocks in
  `parts/header.html`/`parts/footer.html`, same as every other section. See
  "Navigation menus" above.
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
