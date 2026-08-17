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
- Images/files → **decide which of two things they are before saving them
  anywhere**, because only one of the two travels with `git push`/`git pull`
  (see "Deployment & environment sync" below for why this matters):
  - Section/theme imagery a block's `render.php` reads by a static
    `imageUrl` attribute (hero slides, promo panels, decorative shapes) →
    `theme/agentic-theme/assets/images/` or `agentic-blocks` assets,
    WebP (`image-to-webp` skill). Git-tracked, syncs for free.
  - Real store content (a WooCommerce product photo, anything the store
    owner manages as inventory) → the media library via wp-admin/WooCommerce
    APIs, same as the rest of "Owner-editable in wp-admin" below. **Never**
    git-tracked — it syncs via `scripts/sync-products.sh`, not `git push`.

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
can only serve fonts from inside the theme. A logo from `identity/` goes to
`theme/agentic-theme/assets/images/brand/site-icon.png` (square, ≥512×512,
PNG) — `setup-site.sh` wires it into the favicon and the sitewide social/
schema image automatically from there, see that script's step 14. If the
folder is absent or empty, keep the current defaults and say so — do not
invent a brand (this includes the logo: leaving the site icon-less is the
correct default until a real one exists, not a placeholder mark).

See the `apply-brand-input` skill (`.claude/skills/apply-brand-input/`) for
the concrete step-by-step version of this — how to reconcile this folder, a
user's written prompt, and a pasted reference screenshot into one set of
`theme.json`/attribute edits.

## Stack (what's pre-installed, all free)
- **WooCommerce** (official, free) — ecommerce engine
- **The SEO Framework** (free) — SEO meta, sitemaps, schema. Chosen over
  Yoast because it auto-generates titles/descriptions from existing content
  (Yoast free generates nothing, so products and archives shipped with no
  meta description at all), carries no admin ads or upsell nags, and emits
  less schema to reconcile against WooCommerce's own
- **WP Mail SMTP** (free) — WordPress's default `wp_mail()` gets spam-filtered
  by most hosts; order confirmation emails silently never arrive without this
- **UpdraftPlus** (free) — backups. Schedule/retention are set in code (see
  `setup-site.sh`); remote storage destination is an owner step (below)
- Custom theme: `theme/agentic-theme` — block theme (FSE), no page builder
- Custom plugin: `agentic-blocks` — custom block library

WooCommerce, The SEO Framework, WP Mail SMTP, and UpdraftPlus are all
installed by slug from wordpress.org by `scripts/setup-site.sh`, which `.wp-env.json` runs
automatically via `lifecycleScripts.afterStart`. Do **not** also list them as
zip URLs in `.wp-env.json` — zip sources unpack into folders named after the
zip (`woocommerce.latest-stable`), which collides with the slug-named copy and
produces a `Cannot redeclare WC()` fatal.

### SEO and structured data — three deliberate pieces of code
The SEO plugin does most of it, but three gaps are closed in the theme's
`functions.php` because no free plugin closes them for a WooCommerce store:

1. **One `BreadcrumbList` per URL.** WooCommerce emits its own on the shop
   archive, every product category and every product, on top of whatever the
   SEO plugin emits — and the two do not necessarily agree (under Yoast they
   said "Home > Shop > Product" and "Home > Cleansers > Product" on the same
   page). WooCommerce's copy is dropped via
   `woocommerce_structured_data_breadcrumblist`, guarded on an SEO plugin
   actually being active so uninstalling one doesn't leave the store with no
   breadcrumb schema at all.
2. **The product's category, put back into the trail.** Dropping WooCommerce's
   breadcrumb cost a level of detail, so `the_seo_framework_breadcrumb_list`
   splices the deepest assigned `product_cat` (plus its ancestors) in ahead of
   the product. Term selection mirrors `WC_Breadcrumb::add_crumbs_single()` on
   purpose — matching Woo's rule keeps the schema agreeing with the breadcrumb
   Woo itself would render.
3. **Category archives in the XML sitemap.** The SEO Framework's free sitemap
   covers post types only; taxonomy archives are absent from it entirely.
   `the_seo_framework_sitemap_additional_urls` adds every non-empty
   `product_cat` *and* `category`. Empty terms stay out, because TSF noindexes
   an empty archive by itself — submitting one would spend crawl budget on a
   page that answers "don't index me". TSF caches the sitemap, so after editing
   this filter run `wp-env run cli wp transient delete --all` before trusting
   what `/sitemap.xml` shows.

Titles and descriptions are otherwise **not** configured per page: TSF
generates both from existing content, which is most of why it was chosen over
Yoast. Step 15 of `setup-site.sh` writes the exceptions — the cases where
generation has nothing good to work with — and is the single place all of that
copy lives:

- **No content to generate from.** The container pages (front page, Journal,
  About Us, Shop) have deliberately empty `post_content` because they render
  from `templates/*.html`, so each gets an explicit `_genesis_description`.
- **A one-word page title.** "Shop", "Cart" and "Checkout" stay under TSF's
  25-character floor even after it appends " - <blogname>", so each gets a
  `_genesis_title`. That sets only `<title>`/`og:title` — never the H1, the
  menu label or the breadcrumb.
- **A generated value outside TSF's length guidelines** (title 35–65, ideally;
  description 80–160): the sample products' short descriptions — WooCommerce's
  excerpt field is what TSF builds a product description from, so that copy
  lives in step 15 and *not* in step 11's `seed_product` calls, which pass an
  empty string on purpose — and the one sample article whose headline brands
  out one character over the limit.
- **Product category SEO descriptions.** The three populated categories keep
  their short term description (it is the visible one-line tagline under the
  category heading) and get longer copy in TSF's own term meta, which only
  search engines see.

Step 11's seeded products also get a placeholder SKU each (`RNO-001`,
`RQP-002`, …) but deliberately no GTIN/UPC/EAN — WooCommerce already wires
that field (`global_unique_id`) straight into Product schema's `gtin`
property (`WC_Structured_Data::generate_product_data()`) and into any
GTIN-keyed feed, so unlike a made-up internal SKU, a fabricated one would be
actively wrong. It's real inventory data, so it belongs in "Owner-editable in
wp-admin" below, same as price.

Step 15 never clobbers an owner's edit: it records what it wrote in an
`_agentic_seo_seeded` post/term meta map and only writes a value that is empty,
still identical to that record, or identical to one of the `legacy:` lines in
the same document (which exist so a store seeded by an older version of the
script gets repaired once).

Length is not the only thing TSF grades. It also flags a description for
**repeated words** — more than one word used twice, or any word used four
times, turns the badge orange. A draft of the checkout description said "your"
three times and was flagged for exactly that.

**Every title (T) and description (D) badge across posts, pages, products and
the populated categories is green.** What remains non-green is only the
indexing/following/archiving trio (I/F/A), on two sets of pages, and in both
cases the grey is the bar reporting the truth — don't "fix" it:

- Cart, Checkout, My account: `noindex` on purpose. F and A go orange purely
  *because* of that ("the page may not be indexed, this may also discourage
  link following"), so the only way to green is making cart/checkout/account
  indexable — bad SEO, and something this boilerplate deliberately does not do.
  Their titles and descriptions are set anyway, since the SEO Bar grades them
  regardless and og:description falls back to the same value.
- Privacy Policy, Terms of Use, Refund and Returns Policy: drafts, because the
  real legal copy is the owner's to write (step 10). "Page is invisible" is
  accurate, and publishing placeholder legal text to clear a badge would be
  worse than the badge. The dashboard checklist's **Legal pages** item is how
  the owner finds out these are still unpublished; the badges go green by
  themselves once real copy is written and published.
- Eye Care, Accessories, Uncategorized product categories: empty, so TSF
  noindexes them ("No posts are attached to this term"). They exist only so
  `collection-list` and `product-subcategories` have real archives to link to.

Generating the bar outside wp-admin gives **wrong answers for terms** —
`SEOBar\Builder::generate_bar( [ 'id' => …, 'tax' => … ] )` in `wp eval`
reports every term as noindex with an empty description, including populated
ones the live page proves are fine. Read the real column instead (log in and
fetch `wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`), or
just use `seo-check.py`.

Re-audit with `./scripts/seo-check.py`, which fetches every page type and exits
non-zero if any invariant breaks: every non-`noindex` page has a description;
no schema entity appears twice; exactly one `BreadcrumbList` per URL; every
indexable page's title and description are inside TSF's tolerated length range
(values merely outside its *ideal* range print as warnings, since that is what
turns the SEO Bar orange); and the pages that are supposed to be `noindex`
still are. Treat a non-zero exit as a failing check, same as the Lighthouse
gate.

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

**AI-crawler access (robots.txt) and `/llms.txt` are the same call again** —
two small, store-agnostic text endpoints, not a "AI SEO" plugin. Both live in
`functions.php`, next to the breadcrumb/sitemap fixes above:
- A `robots_txt` filter (priority 20, after The SEO Framework's own
  priority-10 callback, which replaces `$output` outright rather than
  appending) adds explicit allow/disallow rules for named AI user-agents
  (GPTBot, ClaudeBot, PerplexityBot, …) — catalog open, `/cart/`,
  `/checkout/`, `/my-account/` closed, same three paths already `noindex`
  for search engines.
- `/llms.txt` is a real rewrite endpoint (`init` + `query_vars` +
  `template_redirect`, mirroring how WordPress core itself serves
  `/robots.txt`), not a static file — its category list is built from
  `get_terms( 'product_cat' )` on every request. Add or remove a product
  category and this file reflects it immediately; there is no regeneration
  step to remember, unlike a file `setup-site.sh` would write once. It
  deliberately does not enumerate individual products — the sitemap and
  WooCommerce's own public Store API (`/wp-json/wc/store/v1/products`,
  linked from the file itself) already cover that, and llmstxt.org's own
  convention is a short curated index, not a catalog dump. **Worth being
  honest about:** real crawl data (an Ahrefs study of 137k sites, May 2026)
  found AI bots mostly don't fetch `/llms.txt` at all — it's cheap enough to
  keep as a discovery courtesy, but it is not what puts products in front of
  a shopper inside a chat answer. `/product-feed.xml`, next, is.

**`/product-feed.xml` is the piece that actually does that** — a Google
Merchant Center product feed (RSS 2.0 + the `g:` namespace), which Google's
own documentation says AI Mode, AI Overviews, and the Gemini app all ground
their shopping answers in. Chosen deliberately over OpenAI's ChatGPT
product-discovery feed or Perplexity's Merchant Program: those need no
checkout either, but both are newer and still branded/bundled under the same
"agentic commerce" umbrella as ACP's beta checkout flow; Merchant Center
predates all of it by over a decade and needs no protocol commitment, just a
feed URL. Same generation pattern as `/llms.txt` — built live from
`wc_get_products()` on every request, so no regeneration step exists to fall
behind the real catalog. Handles simple and variable products (variations
each get their own `<item>`, linked to the parent via `g:item_group_id`);
grouped/external products are skipped, since they aren't a single
purchasable line item in the sense Merchant Center expects. Every item needs
either a `gtin` or an `mpn`, or Google disapproves it outright rather than
merely down-ranking it — `get_sku()` (the placeholder SKUs from step 11)
covers `mpn` for every seeded sample product; a real product's GTIN, once
entered in wp-admin, is picked up automatically since both read the same
WooCommerce product data (see the SKU/GTIN note above). `g:brand` defaults to
the site title (`bloginfo( 'name' )`) — the common case for a single-brand
D2C store; a real multi-brand catalog would need a real per-product brand
field instead. Registering the feed URL in a Merchant Center account is an
owner step, same as the REST keys and payment credentials elsewhere in this
file's Owner-editable list.

**Block markup is hand-authored for the same reason AI-generated Gutenberg
content is a known failure mode elsewhere in the WordPress ecosystem.** A
block's saved form is a serialized tree wrapped in HTML-comment delimiters
(`<!-- wp:core/paragraph {...} -->`) that a model generating post content at
runtime has to reproduce exactly — get one delimiter or attribute wrong and
WordPress either falls back to the classic editor or drops the block
entirely, a problem real enough that third-party tools exist purely to
validate AI-generated block markup before it's saved. This boilerplate never
has that failure mode because it was never going to hit it: `render.php`
files are written directly, by hand, from real PHP + attribute arrays — the
agent is authoring the block's implementation once, not generating its saved
markup on every request. See "CORE RULE" above; this is *why* that rule
avoids a documented, ongoing pain point rather than a stylistic preference.

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
  seo-check.py        <- metadata/schema gate (descriptions, duplicate schema)
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
| `page-about-us` | About Us page |
| `page-contact` | Contact page |
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
`payment-badges`, `photo-marquee`, `photo-statement`, `shop-the-set`,
`product-subcategories`, `latest-posts`, `contact-form`.

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
**All checks — Playwright screenshots, Lighthouse, `seo-check.py` — are
fully manual and opt-in.** After writing/editing a file, just report what
changed and stop there. Do **not** run a check, and do **not** ask whether
the user wants one — asking after every edit is itself the slowdown this
rule exists to prevent. Only run a check when the user's message explicitly
calls for it (by name, or a closing clause like "...and then verify it" /
"...and run all checks after"). This applies even to `seo-check.py`, which
is fast enough to seem harmless to run proactively — don't; the user asks
when they want it.

Trigger phrases → what runs:

| Say this (or similar) | What runs |
|---|---|
| "screenshot this" / "show me how it looks" / "verify visually" | `playwright-verify` skill |
| "check lighthouse" / "run lighthouse" / "check performance" | `lighthouse-optimize` skill → `./scripts/lighthouse-check.sh` |
| "check SEO" / "run seo check" | `./scripts/seo-check.py` |
| "run all checks" / "do all verification" / "verify everything" | all three below, in order |

1. `wp-env run cli wp plugin activate agentic-blocks` (only if not already
   active — this is a dependency step required for the block to render at
   all, not a verification check, so it's fine to run without being asked)
2. Visual check → `playwright-verify` skill
   (`.claude/skills/playwright-verify/`) — screenshot via Playwright MCP,
   purely to confirm the code renders as intended, never to build content.
3. Performance check → `lighthouse-optimize` skill
   (`.claude/skills/lighthouse-optimize/`) —
   `./scripts/lighthouse-check.sh <path> [min-score] [categories]`, which
   exits non-zero if any category is below threshold (default 90). Treat a
   non-zero exit as a failing check, not a suggestion.
   - Cart, checkout, and my-account are intentionally **noindex**, so their
     SEO score is legitimately low. Audit those without the seo category:
     `./scripts/lighthouse-check.sh /cart/ 90 performance,accessibility,best-practices`
     Do not try to make them indexable.
4. SEO/schema check → `./scripts/seo-check.py`. Most relevant after
   anything that touches metadata, schema, templates that render
   product/category content, or `setup-site.sh` — but still only when asked.
5. Fix issues by editing the source files again — never by adjusting
   settings through the UI

## Skills
Recurring workflows for this boilerplate are written up as Claude Code
skills under `.claude/skills/`, each self-contained and grounded in this
repo's actual code (not generic advice):

| Skill | For | Manual-only check? |
|---|---|---|
| `playwright-verify` | Screenshotting a page/template/block to confirm it renders as intended — see "Verification workflow" above for when to invoke it | Yes — run only when asked |
| `lighthouse-optimize` | Running the Lighthouse gate and fixing the specific LCP/render-blocking/caching failure modes that actually recur in this codebase | Yes — run only when asked |
| `image-to-webp` | Converting a sourced or generated PNG/JPG into WebP, sized/cropped to the target block's aspect ratio, before it goes into `assets/images/` | No — a build step, not a check |
| `responsive-design` | The breakpoints, fluid-type, and aspect-ratio conventions each block already follows | No — reference doc, not a check |
| `apply-brand-input` | Turning a written brand prompt, a reference screenshot, and/or `../system-design/` into actual `theme.json`/block-attribute edits | No — a build step, not a check |
| `ai-image-prompts` | Writing an AI image-generation prompt matched to a block's aspect ratio and the current design language, including how to handle a user-supplied image-gen API key without it touching git or chat output | No — a build step, not a check |

`./scripts/seo-check.py` (a script, not a skill — see "Verification
workflow" above) is also manual-only: run it only when asked.

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
shipping, tax, site representation, sample content, legal pages, domain/SSL) and links
straight to the relevant settings screen. This is how a clone's owner finds
out what's left to configure without reading this file first — keep the two
in sync: a new owner-only setup step belongs in **both** places.

### Owner-editable in wp-admin (legitimate, by design)
These are the store owner's job, not the agent's, and editing them in wp-admin
is **not** a violation of the code-first rule — they are content or external
credentials, not layout:
- Product catalogue, prices, images, and descriptions — including each
  product's GTIN/UPC/EAN/ISBN (Product data → Inventory tab,
  `global_unique_id`) once real inventory exists. It's a real, globally-
  issued number WooCommerce already feeds into Product schema's `gtin`
  property and into any GTIN-keyed feed, so it belongs here with the rest of
  the real catalog data rather than being invented by a seed script — see
  the SKU/GTIN note in "SEO and structured data" above
- Page copy for About / policy pages — and **publishing** the three legal pages
  (privacy, terms, refunds), which `setup-site.sh` deliberately leaves as
  drafts holding placeholder text until real copy is reviewed
- Payment gateway credentials (Stripe/PayPal API keys)
- Live shipping-carrier accounts and real tax/nexus configuration
- Site representation (organization vs person) + social profile URLs, under
  SEO → Settings
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
- WooCommerce REST API keys (WooCommerce → Settings → Advanced → REST API,
  **Read** permission only) for the optional `woocommerce` MCP server in
  `.mcp.json` — exported as `WC_CONSUMER_KEY`/`WC_CONSUMER_SECRET` shell env
  vars, never committed. See "Optional: read-only WooCommerce MCP server" in
  README.md
- Registering `/product-feed.xml` in a Google Merchant Center account
  (free) — the feed itself is generated by `functions.php`; submitting its
  URL there is a credential/account step like the others on this list

## Deployment & environment sync
`wp-env` is dev-only tooling — it's not a deploy target. `docker-compose.yml`
at the repo root is the VPS-hosting equivalent (see "Optional: docker-compose
for VPS demo hosting" in README.md): same bind-mounts, same port, plus a
`wpcli` service. Getting a clone from "runs on my machine" to "runs on a VPS
and stays in sync" is really three separate sync problems with different
correctness rules — do not treat them as one:

1. **Code** (`theme/`, `agentic-blocks/`, `scripts/`, `theme.json`,
   `CLAUDE.md`) — one-way `local → VPS`, and it's already solved: `git push`
   locally, `scripts/deploy.sh` on the VPS side (`git pull` + rebuild block
   bundles — PHP/template changes need no restart, since docker-compose.yml
   bind-mounts them directly). `.github/workflows-disabled/deploy.yml`
   would run it automatically on every push to `main` once `VPS_HOST`/
   `VPS_SSH_KEY` repository secrets are set — parked in `workflows-
   disabled/`, not `workflows/`, until then, since GitHub only triggers
   what's literally inside `.github/workflows/`. See that workflow's own
   header, and "Deploying and syncing to a VPS" in the top-level README.md
   for the exact command to enable it.
   Deliberately does **not** run `setup-site.sh` — see point 2 below and
   the "Site setup lives in code too" section above for why that script
   still isn't safe to point at a live store as-is.
2. **Product catalog** (products, categories, images) — `scripts/
   sync-products.sh pull [user@vps]` (see its own header comment for full
   usage; the host defaults from `.env`/`VPS_HOST` if omitted — see
   `scripts/lib/ssh-target.sh`). Matches products by **SKU only, never by post ID** — a raw post
   ID is only meaningful within one database; trusting the source site's ID
   on a different site can silently collide with unrelated content on the
   target, or fail outright. Uses a small hand-rolled importer
   (`scripts/lib/import-products.php`) built on WooCommerce's public
   `WC_Product` API rather than `WC_Product_CSV_Importer` — that class force-
   deletes anything still in its internal "importing" placeholder status at
   the end of a batch (its wp-admin screen only avoids this because the
   browser drives it through several AJAX steps), and lost a product outright
   when driven headlessly in testing. Re-running the sync is safe: existing
   SKUs are updated, missing ones are created, nothing is ever duplicated.
   `pull` (remote catalog → local) is the safe, routine direction. `push`
   (local → remote) requires `--force` and is for a one-time initial
   migration before a store goes live — never routine sync against a live
   store, since it can overwrite real stock/price edits made directly on the
   live site since your last pull.
3. **Everything else that's live-site state** (orders, customers, real
   pages/posts edited in wp-admin) — **no bidirectional sync exists or
   should exist for this.** Once a store takes real orders, its database is
   the single source of truth; pushing a local DB export over it would
   silently delete any order placed since the last pull. UpdraftPlus (see
   "Owner-editable in wp-admin" above) covers disaster-recovery backup of
   this, which is a different concern from dev-data sync.

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
2. `wp-env start` — WooCommerce + The SEO Framework + theme + blocks
   activate automatically
3. Edit `theme.json` for the new brand's colors/fonts
4. Add/adjust blocks under `agentic-blocks/blocks/` for anything store-specific
5. Everything else (product pages, cart, checkout, SEO metadata, sitemaps)
   is already wired up via WooCommerce + The SEO Framework, using their
   normal WordPress data model — no rebuilding those parts per project
