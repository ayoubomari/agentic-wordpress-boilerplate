#!/usr/bin/env bash
#
# Runs automatically from .wp-env.json `lifecycleScripts.afterStart`.
#
# Everything a fresh clone needs in order to be a working, browsable store is
# done here in code — never by clicking through wp-admin (see CLAUDE.md).
# The script is idempotent: it is safe to re-run on every `wp-env start`.
set -euo pipefail

cd "$(dirname "$0")/.."

# NOTE: `wp-env run` takes the container as its first positional argument and
# accepts no leading flags — `wp-env run --quiet cli ...` parses the container
# as empty and fails.
wp() { wp-env run cli wp "$@"; }

echo "→ [1/15] Building block editor scripts"
# block.json points editorScript at build/<block>/index.js, so the bundle must
# exist before the plugin is activated or blocks register without editor UI.
if [ -d agentic-blocks/blocks ]; then
  if [ ! -d agentic-blocks/node_modules ]; then
    ( cd agentic-blocks && npm install --silent --no-audit --no-fund )
  fi
  ( cd agentic-blocks && npm run build --silent )
fi

echo "→ [2/15] Installing WooCommerce + The SEO Framework (free, from wordpress.org)"
# Installed by slug via WP-CLI rather than as .wp-env.json zip URLs: zip
# sources land in folders named after the zip (woocommerce.latest-stable),
# which leaks into asset URLs and collides with a slug-named second copy.
#
# SEO plugin is The SEO Framework (`autodescription`), not Yoast. Reasons, in
# the order they mattered for a code-first boilerplate:
#   - It auto-generates titles and meta descriptions from the content that is
#     already there. Yoast free generates nothing, so every product, category
#     and archive shipped with no <meta name="description"> at all until the
#     description templates were configured by hand — a thing a clone's owner
#     would never know to do.
#   - No ads, no upsell notices, no onboarding wizard in wp-admin. A dashboard
#     nagging the owner to buy Premium is noise in a boilerplate.
#   - It emits less schema, which is less to reconcile against the schema
#     WooCommerce already emits (see the BreadcrumbList de-duplication in the
#     theme's functions.php).
#   - No DB-backed redirect manager to tempt this project back into storing
#     site behaviour in the database instead of in files.
wp plugin install woocommerce autodescription --activate

echo "→ [3/15] Installing essential add-ons (free, from wordpress.org)"
# Two things WooCommerce doesn't handle on its own, both free and both things
# an actual store cannot skip:
#   - wp-mail-smtp: WordPress's default wp_mail() gets spam-filtered by most
#     hosts, so order confirmation emails silently never arrive without this.
#   - updraftplus: backups. A store taking real orders needs them.
# Credentials/destinations for both are secrets the store owner sets in
# wp-admin (see the "Owner-editable in wp-admin" table in CLAUDE.md) — same
# category as payment gateway keys, never something to hardcode here.
wp plugin install wp-mail-smtp updraftplus --activate

# Sane, code-first backup schedule (no secrets involved — just cadence and
# retention). Remote storage destination is still an owner step: with none
# configured, UpdraftPlus backs up to wp-content/updraft locally only, which
# does not protect against losing the server. Flagged in README's pre-launch
# checklist.
wp option update updraft_interval 'daily'
wp option update updraft_interval_db 'daily'
wp option update updraft_retain '7'
wp option update updraft_retain_db '7'

echo "→ [4/15] Activating theme + agentic-blocks"
wp theme activate agentic-theme
wp plugin activate agentic-blocks

echo "→ [5/15] Completing WooCommerce installation"
# WooCommerce defers part of its installer to the first request after
# activation, and that deferred run writes its own defaults. Forcing it to
# finish synchronously here means the settings written below actually stick
# instead of being silently overwritten a moment later.
wp eval 'if ( class_exists( "WC_Install" ) ) { WC_Install::install(); }'

echo "→ [6/15] Permalinks"
# Flat /%postname%/ — the sane structure for products; the WP default
# day-and-name buries /shop/ URLs under a date path.
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# Long cache lifetimes for static assets (images, fonts, CSS, JS) — Lighthouse's
# "uses-long-cache-ttl" audit otherwise fails on every asset, since neither
# Apache nor WordPress sets one by default. Enqueued CSS/JS are cache-busted by
# WordPress's own ?ver= query string on every change, so a long lifetime is
# safe even for those. insert_with_markers() is idempotent (marker-scoped, like
# WordPress's own rewrite block above it) so re-running this never duplicates
# the block or clobbers anything else in .htaccess.
wp eval '
	require_once ABSPATH . "wp-admin/includes/misc.php";
	insert_with_markers(
		ABSPATH . ".htaccess",
		"Agentic Cache",
		[
			"<IfModule mod_expires.c>",
			"  ExpiresActive On",
			"  ExpiresByType image/jpg \"access plus 1 year\"",
			"  ExpiresByType image/jpeg \"access plus 1 year\"",
			"  ExpiresByType image/png \"access plus 1 year\"",
			"  ExpiresByType image/webp \"access plus 1 year\"",
			"  ExpiresByType image/svg+xml \"access plus 1 year\"",
			"  ExpiresByType image/x-icon \"access plus 1 year\"",
			"  ExpiresByType font/woff \"access plus 1 year\"",
			"  ExpiresByType font/woff2 \"access plus 1 year\"",
			"  ExpiresByType text/css \"access plus 1 year\"",
			"  ExpiresByType application/javascript \"access plus 1 year\"",
			"  ExpiresByType text/javascript \"access plus 1 year\"",
			"</IfModule>",
		]
	);
'

echo "→ [7/15] Store defaults"
wp option update blogdescription 'A code-first WooCommerce store'
wp option update woocommerce_store_country 'US:CA'
wp option update woocommerce_currency 'USD'
# Skip the wp-admin onboarding wizard — it is the one flow that would
# otherwise force clicking through the UI before the store works.
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json

echo "→ [8/15] Front page + Journal"
# The storefront must be a static page, not the post feed. Blog posts live at
# /journal/ instead.
#
# These two pages are containers, not content. The homepage's actual sections
# stay in templates/front-page.html, and the post list stays in
# templates/home.html — both code, both edited on disk. Putting the section
# markup into a page's post_content would move the homepage into the database,
# where a template edit no longer reaches it. See CLAUDE.md.
ensure_page() {
	local slug="$1" title="$2" id
	id="$( wp post list --post_type=page --name="$slug" --field=ID --format=csv | tr -d '\r\n' )"
	if [ -z "$id" ]; then
		id="$( wp post create \
			--post_type=page \
			--post_title="$title" \
			--post_name="$slug" \
			--post_status=publish \
			--porcelain | tr -d '\r\n' )"
	fi
	printf '%s' "$id"
}

HOME_ID="$( ensure_page 'home' 'Home' )"
JOURNAL_ID="$( ensure_page 'journal' 'Journal' )"

wp option update show_on_front 'page'
wp option update page_on_front "$HOME_ID"
wp option update page_for_posts "$JOURNAL_ID"

# Meta descriptions for these container pages are set in step 14, with every
# other piece of SEO metadata, rather than here — see the note there on why
# empty-content pages are the only ones that need one written by hand.

echo "→ [9/15] About Us + Contact pages"
# Same container-page pattern as Home/Journal above: the page record exists
# only so WordPress has something to route /about-us/ (or /contact/) to. The
# actual content lives in templates/page-about-us.html and
# templates/page-contact.html, resolved automatically by WordPress's
# block-template hierarchy from the page's slug — no manual "Template" picker
# needed, and re-running this never touches an owner's edits to the page.
ensure_page 'about-us' 'About Us' > /dev/null
ensure_page 'contact' 'Contact' > /dev/null

echo "→ [10/15] Legal pages"
# WordPress core auto-creates a "Privacy Policy" page (draft, at
# /privacy-policy/) on every fresh install, pre-filled with its own
# section-by-section suggested text — there is no equivalent core default for
# Terms of Use, so it's created here the same way: idempotent, draft (real
# legal copy is the store owner's to write/review before publishing — see
# "Owner-editable in wp-admin" in CLAUDE.md), and pre-filled with the
# equivalent starting draft below.
#
# That draft is a starting point to edit, NOT reviewed legal copy — same
# status as core's privacy suggested text, and it says so in its own first
# paragraph. It stays a draft until a human has reviewed it, and the
# dashboard checklist's "Legal pages" item is what surfaces that.
TERMS_CONTENT="$( cat <<'HTML'
<!-- wp:paragraph -->
<p><strong>Starting draft — not reviewed legal copy.</strong> These are general ecommerce terms written to be edited, in the same spirit as the suggested text WordPress puts in a new Privacy Policy. Read every section, replace anything in [square brackets], delete what does not apply to this store, and have the result reviewed before publishing.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Last updated: [date]</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">About these terms</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>These Terms of Use govern your access to this website and any order you place through it. By browsing the site or completing a purchase, you agree to them. If you do not agree, please do not use the site.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We may need to update these terms from time to time. The version published on this page at the moment you place an order is the version that applies to that order.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Who may use this store</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>You must be at least [18] years old, or old enough to enter a binding contract where you live, to order from us. By ordering you confirm that the details you give us are accurate and that you are using a payment method you are authorised to use.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Your account</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>You can order as a guest or create an account. If you create one, you are responsible for keeping your password confidential and for activity that happens under your account. Tell us straight away if you think someone else has access to it.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We may suspend or close an account that is being used fraudulently, abusively, or in breach of these terms.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Products, descriptions and availability</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We describe our products as accurately as we can, but photographs are illustrative and colours can vary between screens. Packaging and formulations may change, so please read the label on what you receive rather than relying only on the description here.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>All products are subject to availability. If an item you ordered turns out to be unavailable, we will contact you and refund it in full.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Nothing on this site is medical advice. If you have a skin condition, allergies, or you are pregnant, check with a qualified professional before using a new product, and patch-test first.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Prices and payment</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Prices are shown in [currency] and may change at any time before you order. Whether prices include tax, and any shipping charge, is shown at checkout before you pay.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Payment is taken at the time of the order through our payment provider. We do not store your full card details ourselves.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Obvious pricing errors are not binding on us. If an item is listed at a clearly incorrect price, we will contact you before dispatch and you can confirm the order at the correct price or cancel it for a full refund.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Orders and acceptance</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Your order is an offer to buy. The confirmation email we send when you check out acknowledges that we received it — a contract is formed when we dispatch the goods, or when we tell you we have accepted the order.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We may decline an order, for example where an item is out of stock, where we cannot deliver to your address, or where we suspect fraud. If we decline one you have already paid for, you get a full refund.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Shipping and delivery</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Delivery options, costs and estimated timescales are shown at checkout. Estimates are not guarantees; carrier delays, customs and events outside our control can affect them.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Risk in the goods passes to you on delivery. For international orders, any import duties or taxes charged on arrival are your responsibility.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Returns, refunds and cancellations</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Our returns and refunds process is set out in our Refund and Returns Policy, which forms part of these terms. Where the law gives you a right to cancel a distance purchase, that right applies in addition to it.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>For hygiene reasons, opened or used skincare and body-care products cannot usually be returned unless they are faulty or not what you ordered. Nothing here limits your legal rights in respect of faulty goods.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Reviews and anything else you post</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>If you submit a review, comment, or any other content, you confirm it is your own, it is honest, and it does not break the law or anyone else's rights. You keep ownership of it and give us permission to display and share it in connection with the store.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We may remove content that is unlawful, abusive, misleading, or off-topic.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Acceptable use</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Please do not misuse the site: no attempts to gain unauthorised access, no scraping or bulk automated ordering, no interfering with how the site works for other people, and no using it for anything unlawful.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Intellectual property</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The content on this site — text, photography, graphics, logos and layout — belongs to us or our licensors and is protected by copyright and trade mark law. You may view and print it for your own personal, non-commercial use. Any other use needs our written permission.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Other sites we link to</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Where we link to another website or service, we do so for convenience. We do not control those sites and are not responsible for their content or their privacy practices.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Availability of the site</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We aim to keep the site available and accurate, but we cannot promise it will always be uninterrupted or error-free. We may suspend, withdraw or change any part of it, including individual products, without notice.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Our liability</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Nothing in these terms limits our liability for death or personal injury caused by our negligence, for fraud, or for anything else that cannot lawfully be limited — including your statutory rights as a consumer.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Subject to that, we are not liable for losses that were not reasonably foreseeable, or for business losses such as lost profit or lost opportunity. [Confirm with your adviser how liability should be capped for your jurisdiction and business.]</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Privacy</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>How we handle personal data is described in our Privacy Policy. Please read it alongside these terms.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Governing law</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>These terms are governed by the laws of [jurisdiction], and disputes will be handled by the courts of [jurisdiction]. If you are a consumer, this does not remove the protection of the mandatory rules of the country you live in.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Contact us</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Questions about these terms, or about an order, can go to [email address]. Our registered details are: [legal entity name, address, and company or tax registration number].</p>
<!-- /wp:paragraph -->
HTML
)"

# The one-line placeholder this step wrote before the draft above existed.
# Byte-identical content is treated as "still untouched by the owner", so a
# store seeded by that older copy gets the fuller draft on its next run —
# the same repair rule step 14 applies to its `legacy:` values.
TERMS_LEGACY='<!-- wp:paragraph --><p>Placeholder Terms of Use — replace with real, reviewed legal copy before launch.</p><!-- /wp:paragraph -->'

# `wp post list --name=` silently ignores draft posts no matter what
# --post_status is passed (a WP_Query quirk, not a wp-cli bug) — it always
# came back empty here and recreated a duplicate draft on every single
# re-run. get_page_by_path() doesn't have that restriction.
#
# Content goes through base64 rather than a --post_content= argument: it is
# multi-line block markup with quotes in it, and it has to survive both the
# shell and `wp-env run`'s own argument handling on the way to the container.
# Same technique as the SEO document in step 14.
wp eval '
	$content = base64_decode( "'"$( printf '%s' "$TERMS_CONTENT" | base64 | tr -d '\n' )"'" );
	$legacy  = base64_decode( "'"$( printf '%s' "$TERMS_LEGACY" | base64 | tr -d '\n' )"'" );
	$page    = get_page_by_path( "terms-of-use", OBJECT, "page" );

	if ( ! $page ) {
		wp_insert_post( [
			"post_type"    => "page",
			"post_title"   => "Terms of Use",
			"post_name"    => "terms-of-use",
			"post_status"  => "draft",
			"post_content" => $content,
		] );
		echo "created page (draft) with starting draft copy";
	} elseif ( trim( $page->post_content ) === trim( $content ) ) {
		echo "already holds this draft copy — nothing to do";
	} elseif ( "" === trim( $page->post_content ) || trim( $page->post_content ) === trim( $legacy ) ) {
		wp_update_post( [ "ID" => $page->ID, "post_content" => $content ] );
		echo "filled empty/placeholder page with starting draft copy";
	} else {
		echo "already edited — leaving it alone";
	}
' | sed "s/^/   terms-of-use: /"
echo

# WordPress core also ships a published "Sample Page" containing its own
# placeholder autobiography. Nothing links to it, but it is published, so it is
# indexable and it turns up in the XML sitemap — a fresh clone would hand a
# crawler a page of WordPress boilerplate as real store content. Trashed rather
# than deleted outright, in case an owner has since repurposed the slug.
if [ "$( wp eval 'echo ( ( $p = get_page_by_path( "sample-page", OBJECT, "page" ) ) && "trash" !== $p->post_status ) ? "1" : "0";' | tr -d '\r\n' )" = "1" ]; then
  wp post delete "$( wp eval 'echo (int) get_page_by_path( "sample-page", OBJECT, "page" )->ID;' | tr -d '\r\n' )" >/dev/null
  echo "   trashed core's default 'Sample Page'"
fi

echo "→ [11/15] Seeding sample products (so templates are verifiable)"
# Four, not one: a single product leaves the product grid and the "related
# products" collection with nothing to show, which hides real template bugs.
# Spread across three of the five categories (not one catch-all) so
# collection-list, the featured-collection tabs, and the shop/category archive
# filters all have real taxonomy archives and price/stock spread to work with.
# Two of the four carry real sale pricing so the sale badge, the tabbed "Sale"
# panel, and the maxPrice-filtered row all have real data to render.
# Delete these before launching a real store.
if [ "$(wp post list --post_type=product --format=count | tr -d '\r')" = "0" ]; then
  # wp-cli-wc's --categories flag silently no-ops on {"slug":"..."} entries —
  # only a numeric {"id":N} actually assigns the term (tested directly:
  # updating by slug alone left the product Uncategorized with no error).
  # So categories are created (or looked up if already present) first, and
  # every product below is assigned by the resulting numeric ID.
  ensure_product_cat() {
    local name="$1" slug="$2" parent_id="${3:-0}" description="${4:-}" image="${5:-}" id
    id="$( wp wc product_cat list --slug="$slug" --field=id --user=admin | tr -d '\r\n' )"
    if [ -z "$id" ]; then
      local args=( wp wc product_cat create --name="$name" --slug="$slug" --user=admin --porcelain )
      [ "$parent_id" != "0" ] && args+=( --parent="$parent_id" )
      [ -n "$description" ] && args+=( --description="$description" )
      if [ -n "$image" ]; then
        # Same two-step shape as seed_product's --images below: import the
        # local theme asset into the media library first to get an
        # attachment ID, since product_cat create's --image only accepts an
        # ID or a URL WooCommerce fetches itself.
        local image_id
        image_id="$( wp media import "$image" --porcelain )"
        args+=( --image="{\"id\":$image_id}" )
      fi
      id="$( "${args[@]}" )"
    fi
    printf '%s' "$id"
  }

  # Five flat, top-level categories — no subcategory nesting. Real one-line
  # tagline copy (not a placeholder string) on each, shown centered under the
  # category heading on taxonomy-product_cat.html, the same spot Sleek's own
  # reference collection pages use for a one-line collection blurb.
  # assets/images/collections/<slug>.webp already exists per category — it
  # was never wired up to the actual term before now.
  CLEANSERS_CAT_ID="$( ensure_product_cat 'Cleansers' 'cleansers' 0 'Soap-free formulas that lift away impurities without stripping the skin.' \
    'wp-content/themes/agentic-theme/assets/images/collections/cleansers.webp' )"
  MOISTURIZERS_CAT_ID="$( ensure_product_cat 'Moisturizers' 'moisturizers' 0 'Lightweight hydration that locks in moisture for up to 24 hours.' \
    'wp-content/themes/agentic-theme/assets/images/collections/moisturizers.webp' )"
  TREATMENTS_CAT_ID="$( ensure_product_cat 'Treatments' 'treatments' 0 'Targeted formulas for brighter, smoother, more radiant skin.' \
    'wp-content/themes/agentic-theme/assets/images/collections/treatments.webp' )"

  # No products seeded into these two — they exist purely so
  # agentic/collection-list ("Our Collections" on the homepage) and
  # agentic/product-subcategories (the tile row on /shop/) have real
  # taxonomy-product_cat archives to link to instead of dead hrefs. Empty
  # archives are expected here; the store owner populates them with real
  # products.
  ensure_product_cat 'Eye Care' 'eye-care' 0 "Gentle care for the eye area's especially delicate skin." \
    'wp-content/themes/agentic-theme/assets/images/collections/eye-care.webp' > /dev/null
  ensure_product_cat 'Accessories' 'accessories' 0 'The tools that help your routine actually work.' \
    'wp-content/themes/agentic-theme/assets/images/collections/accessories.webp' > /dev/null

  seed_product() {
    local name="$1" slug="$2" category_id="$3" regular_price="$4" sale_price="$5" description="$6" short_description="$7" stock_quantity="${8:-}" image="${9:-}" image2="${10:-}" sku="${11:-}"
    local args=(
      wp wc product create
      --name="$name"
      --slug="$slug"
      --type=simple
      --regular_price="$regular_price"
      --description="$description"
      --short_description="$short_description"
      --categories="[{\"id\":$category_id}]"
      --status=publish
      --user=admin
    )
    if [ -n "$sku" ]; then
      args+=(--sku="$sku")
    fi
    if [ -n "$sale_price" ]; then
      args+=(--sale_price="$sale_price")
    fi
    if [ -n "$stock_quantity" ]; then
      # Real low-stock data, not an invented badge: agentic/product-badge's
      # "Selling fast!" type only shows when stock is actually managed and at
      # or below the low-stock threshold — see agentic_product_is_low_stock()
      # in agentic-blocks.php.
      args+=(--manage_stock=true --stock_quantity="$stock_quantity")
    fi
    if [ -n "$image" ]; then
      # wc product create's --images only accepts an attachment ID or a
      # remote URL WooCommerce fetches itself — a local theme asset has to be
      # imported into the media library first to get that ID, same two-step
      # shape as seed_post's --featured_image below. First ID in the array
      # becomes the product's primary/featured image; a second (optional)
      # becomes a gallery image alongside it.
      local image_id image2_id images_json
      image_id="$( wp media import "$image" --porcelain )"
      images_json="{\"id\":$image_id}"
      if [ -n "$image2" ]; then
        image2_id="$( wp media import "$image2" --porcelain )"
        images_json="$images_json,{\"id\":$image2_id}"
      fi
      args+=(--images="[$images_json]")
    fi
    "${args[@]}"
  }

  # AI-generated studio product photography, two per product (primary +
  # gallery), square to match the 1:1 aspect-ratio product image CSS
  # (woocommerce.css). assets/images/collections/<slug>.webp were
  # generated the same way, one per category, wired up above.
  #
  # Every short_description below is deliberately EMPTY: WooCommerce's short
  # description is the field The SEO Framework generates a product's meta
  # description from, so the copy for it lives with the rest of this script's
  # SEO copy in step 14 (which also repairs stores seeded before that step
  # existed) rather than being written in two places that can drift apart.
  #
  # Each also gets a placeholder SKU (a made-up internal code, harmless to
  # invent) but deliberately NOT a GTIN/UPC/EAN — that field
  # (WooCommerce's own "GTIN, UPC, EAN, or ISBN" input on the product's
  # Inventory tab, `global_unique_id`) is a real, globally-issued number
  # WooCommerce already feeds straight into Product schema's `gtin` property
  # (see WC_Structured_Data::generate_product_data()) and into any GTIN-keyed
  # feed (Google Merchant, agentic-commerce catalogs). A fabricated one would
  # be actively wrong, unlike a placeholder SKU — it belongs with the rest of
  # the real catalog data in the "Owner-editable in wp-admin" list below.
  seed_product 'Rejuvenating Night Oil' 'rejuvenating-night-oil' "$TREATMENTS_CAT_ID" '79.00' '' \
    'A nourishing night oil formulated with rosehip and squalane to replenish skin while you sleep. Placeholder product — delete before launch.' \
    '' '' \
    'wp-content/themes/agentic-theme/assets/images/products/rejuvenating-night-oil.webp' \
    'wp-content/themes/agentic-theme/assets/images/products/rejuvenating-night-oil-2.webp' \
    'RNO-001'
  seed_product 'Rose Quartz Facial Polish' 'rose-quartz-facial-polish' "$TREATMENTS_CAT_ID" '79.00' '59.00' \
    'A gentle exfoliating polish with fine rose quartz powder to reveal smoother, brighter skin. Placeholder product — delete before launch.' \
    '' '' \
    'wp-content/themes/agentic-theme/assets/images/products/rose-quartz-facial-polish.webp' \
    'wp-content/themes/agentic-theme/assets/images/products/rose-quartz-facial-polish-2.webp' \
    'RQP-002'
  seed_product 'Hydrating Body Serum' 'hydrating-body-serum' "$MOISTURIZERS_CAT_ID" '79.00' '' \
    'A lightweight, fast-absorbing serum that locks in moisture for up to 24 hours. Placeholder product — delete before launch.' \
    '' '2' \
    'wp-content/themes/agentic-theme/assets/images/products/hydrating-body-serum.webp' \
    'wp-content/themes/agentic-theme/assets/images/products/hydrating-body-serum-2.webp' \
    'HBS-003'
  seed_product 'Gentle Gel Cleanser' 'gentle-gel-cleanser' "$CLEANSERS_CAT_ID" '39.00' '29.00' \
    'A soap-free gel cleanser that lifts away impurities without stripping the skin. Placeholder product — delete before launch.' \
    '' '' \
    'wp-content/themes/agentic-theme/assets/images/products/gentle-gel-cleanser.webp' \
    'wp-content/themes/agentic-theme/assets/images/products/gentle-gel-cleanser-2.webp' \
    'GGC-004'
else
  echo "   products already exist — skipping"
fi

echo "→ [12/15] Seeding Journal posts (so agentic/latest-posts has real content)"
# WordPress core seeds a "Hello world!" sample post on every fresh install —
# harmless on its own, but it would sit in the Journal archive and
# occasionally surface in agentic/latest-posts' "3 most recent" query
# alongside the real seeded posts below. Deleted unconditionally: if it's
# already gone (a re-run, or the owner deleted it themselves) this is a
# silent no-op, --force skips the trash since it's placeholder content with
# nothing worth recovering.
HELLO_WORLD_ID="$( wp post list --post_type=post --name=hello-world --field=ID --format=csv | tr -d '\r\n' )"
if [ -n "$HELLO_WORLD_ID" ]; then
  wp post delete "$HELLO_WORLD_ID" --force >/dev/null
  echo "   deleted default 'Hello world!' post"
fi

# Eight short articles so the Journal archive and agentic/latest-posts (the
# "From The Journal" grid on About Us) both have real published posts to
# query instead of rendering empty. Featured images reuse existing theme
# assets — real product/lifestyle photography, not people, so nothing here
# needs replacing before launch except the copy itself and, eventually, real
# photography per the store owner's own content. Delete these before launch.
if [ "$( wp eval 'echo get_page_by_path( "skincare-basics-for-beginners", OBJECT, "post" ) ? "1" : "0";' | tr -d '\r\n' )" = "0" ]; then
  SKINCARE_CAT_ID="$( wp term list category --slug=skincare --field=term_id --format=csv | tr -d '\r\n' )"
  if [ -z "$SKINCARE_CAT_ID" ]; then
    SKINCARE_CAT_ID="$( wp term create category 'Skincare' --slug=skincare --porcelain | tr -d '\r\n' )"
  fi

  seed_post() {
    local title="$1" slug="$2" excerpt="$3" content="$4" image="$5" id
    id="$( wp post create \
      --post_type=post \
      --post_title="$title" \
      --post_name="$slug" \
      --post_status=publish \
      --post_category="$SKINCARE_CAT_ID" \
      --post_excerpt="$excerpt" \
      --post_content="$content" \
      --porcelain )"
    wp media import "$image" --post_id="$id" --featured_image >/dev/null
    echo "   created post '$slug'"
  }

  seed_post 'Skincare Basics for Beginners' 'skincare-basics-for-beginners' \
    "Starting a routine doesn't have to mean nine steps and a shelf of bottles. Here's the short list that actually matters." \
    "<!-- wp:paragraph --><p>Walk into any skincare aisle and it's easy to feel like you're already behind — toners, essences, serums, sheet masks, a dozen steps before you've even left the house. None of that is where a good routine starts.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Three steps cover almost everything: a gentle cleanser to remove the day without stripping your skin, a treatment suited to what your skin actually needs, and a moisturizer to lock it back in. Add sunscreen in the morning and that's a complete routine.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Add one new product at a time, and give it two to three weeks before deciding whether it's working. Skin doesn't change overnight, and neither should your shelf.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/skincare-basics-for-beginners.webp'

  seed_post 'Why Daily SPF Still Belongs in Your Routine' 'why-daily-spf-still-belongs-in-your-routine' \
    "Sun protection is the one step dermatologists agree on across every skin type — here's why it still gets skipped, and how to make it stick." \
    "<!-- wp:paragraph --><p>Sunscreen is the one product nearly every dermatologist agrees on, and it's still the step most people skip — especially on cloudy days, or when they're indoors near a window.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>UV rays reach skin through clouds and glass alike, and they're the single biggest driver of visible aging: fine lines, uneven tone, and loss of elasticity build up gradually from exposure you don't feel happening.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>The easiest fix is habit, not intensity. Keep a broad-spectrum SPF next to your toothbrush, apply it as the last step every morning, and reapply if you're outside for more than a couple of hours.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/why-daily-spf-still-belongs-in-your-routine.webp'

  seed_post 'Building a Calmer Haircare Routine' 'building-a-calmer-haircare-routine' \
    "Your scalp is skin too. A few small changes make a routine feel a lot less like a chore." \
    "<!-- wp:paragraph --><p>It's easy to treat hair and skin as two completely separate categories, but your scalp is skin — and it responds to the same things that irritate skin anywhere else: harsh sulfates, hot water, and over-washing.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Washing less often, using a lukewarm rinse instead of a hot one, and switching to a sulfate-free formula are three small changes that add up. Your scalp's natural oils are there to protect it, not to be stripped out daily.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>If your scalp feels tight or itchy after washing, that's usually a sign your routine is working against you, not for you. Calmer products, used consistently, tend to beat aggressive ones used occasionally.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/building-a-calmer-haircare-routine.webp'

  seed_post 'The Tools Worth Adding to Your Skincare Kit' 'tools-worth-adding-to-your-skincare-kit' \
    "A gua sha stone and a silicone cleansing brush won't replace a good routine — but they make the routine work harder." \
    "<!-- wp:paragraph --><p>Good skincare tools don't replace a solid routine, but they can make the products you already use work a little harder.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>A gua sha stone, used with a facial oil, helps ease tension and encourage circulation along the jaw and under the eyes. A silicone cleansing brush lifts away makeup and daily buildup more gently than fingertips alone, without the microplastics found in traditional exfoliating beads.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Neither is essential, and neither is a shortcut. Think of them as a way to get more out of the formulas you already trust.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/tools-worth-adding-to-your-skincare-kit.webp'

  seed_post 'What Actually Slows Visible Skin Aging' 'what-actually-slows-visible-skin-aging' \
    "Less about a single miracle ingredient, more about three habits dermatologists keep repeating: SPF, retinoids, and consistency." \
    "<!-- wp:paragraph --><p>There's no single ingredient that slows visible aging on its own — despite what most product marketing implies. What actually moves the needle is a short list of proven habits, repeated consistently over years, not weeks.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Daily sunscreen prevents most of the damage before it starts. Retinoids remain the most researched ingredient for supporting cell turnover and collagen. And layering either with a hydrating, barrier-supporting moisturizer keeps skin from getting irritated enough that you stop using them.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>None of that is exciting, but it's the difference between a routine that works and one that just looks good on a shelf.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/what-actually-slows-visible-skin-aging.webp'

  seed_post 'Cold-Weather Skin, Solved' 'cold-weather-skin-solved' \
    "Dry heat and cold wind pull moisture out of skin faster than most routines put it back. Small seasonal swaps fix that." \
    "<!-- wp:paragraph --><p>Cold air holds less moisture, indoor heating dries it out further, and the result is skin that feels tight, flaky, or reactive in ways it didn't a few months earlier.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>The fix isn't a completely new routine — it's a few swaps. Move to a richer moisturizer for the season, skip anything that foams aggressively when you cleanse, and ease off exfoliating acids until your barrier feels steady again.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>A humidifier in the room you sleep in does more for winter skin than most people expect, and it's one change you only have to make once.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/cold-weather-skin-solved.webp'

  seed_post 'Behind Every Formula: How We Choose Ingredients' 'behind-every-formula-how-we-choose-ingredients' \
    "Short ingredient lists aren't a marketing angle for us — they're the actual bar every formula has to clear before it ships." \
    "<!-- wp:paragraph --><p>Every formula starts with a question: does this ingredient earn its place? If it doesn't do something measurable for the skin, it doesn't make the list — no filler, nothing added just to make a label look fuller.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>That means fewer lines on the back of the bottle, but every one of them is there on purpose. Formulas go through dermatologist testing and patch testing before anything ships, and nothing is tested on animals at any stage.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>It's a slower way to build a product line. It's also the only way we're willing to build one.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/behind-every-formula-how-we-choose-ingredients.webp'

  seed_post 'A Simpler Approach to Your Everyday Routine' 'a-simpler-approach-to-your-everyday-routine' \
    "More products doesn't mean more results. Here's how to edit a routine down to what your skin actually uses." \
    "<!-- wp:paragraph --><p>Most routines don't grow because skin needs more — they grow because it's easy to keep adding and hard to know what to take away.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>A simple audit: for two weeks, use only what you'd call essential — cleanser, one treatment, moisturizer, SPF. Anything you don't miss during those two weeks probably wasn't doing much in the first place.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>What's left is usually a shorter, cheaper, more consistent routine — and consistency is the one thing that actually shows up in your skin.</p><!-- /wp:paragraph -->" \
    'wp-content/themes/agentic-theme/assets/images/journal/a-simpler-approach-to-your-everyday-routine.webp'
else
  echo "   posts already exist — skipping"
fi

echo "→ [13/15] Disabling WooCommerce Coming Soon mode"
# WooCommerce 9.1+ ships this ON. Left alone it replaces the entire storefront
# with a "Great things are on the horizon" splash for logged-out visitors, so
# templates cannot be verified and nothing is indexable.
#
# Done LAST and then asserted: WooCommerce re-applies its own defaults during
# install, so setting this earlier in the script silently loses the race on a
# genuinely fresh install.
wp option update woocommerce_coming_soon 'no'
wp option update woocommerce_store_pages_only 'no'

COMING_SOON="$(wp option get woocommerce_coming_soon | tr -d '\r\n[:space:]')"
if [ "$COMING_SOON" != "no" ]; then
  echo "✖ woocommerce_coming_soon is '$COMING_SOON', expected 'no' —" >&2
  echo "  the storefront would be hidden behind the Coming Soon splash." >&2
  exit 1
fi

echo "→ [14/15] Site icon + social share image"
# Wires a real logo into WordPress's Site Icon (favicon everywhere — browser
# tab, bookmarks, apple-touch-icon) and The SEO Framework's sitewide social
# fallback image + Organization schema logo, IF the brand pipeline has
# actually produced one at this path — see "Brand inputs" in CLAUDE.md and
# the apply-brand-input skill, which is what's expected to put a file here.
# Never invented in this script: an unbranded clone (true by default) has no
# file here, so this step is a no-op and the site simply has no favicon/
# fallback social image yet, same "don't invent a brand" rule as everywhere
# else brand assets are involved.
#
# Without a site-wide fallback, og:image/twitter:image are only ever present
# on pages with a natural featured image of their own (products) — the front
# page, shop archive, Journal, About Us, and every category archive share
# their real title/description but no image when linked on social media.
# PNG, not WebP: favicons need broad old-browser/OS support the image-to-webp
# skill's format doesn't guarantee, so that skill doesn't apply to this file.
#
# Only runs once (guarded on site_icon still being unset) — re-importing the
# same file on every `wp-env start` would otherwise grow the media library by
# one attachment per run. Replacing the logo later is `wp option update
# site_icon 0` (or delete the old attachment) before the next run, or just
# setting a new one directly in Settings → General → Site Icon at that point,
# which is legitimate owner-editable content like everything else in that
# settings screen.
# Two spellings of the same file: the `[ -f ]` test below runs on the HOST
# (this whole script does, per the `cd`/`wp()` wrapper at the top), so it
# needs the host-relative path; `wp media import` runs inside the container
# via that wrapper, so it needs the container's wp-content-rooted path.
LOGO_PATH="wp-content/themes/agentic-theme/assets/images/brand/site-icon.png"
if [ -f "theme/agentic-theme/assets/images/brand/site-icon.png" ] && [ "$( wp option get site_icon | tr -d '\r\n' )" = "0" ]; then
  LOGO_ID="$( wp media import "$LOGO_PATH" --title='Site icon' --porcelain )"
  wp option update site_icon "$LOGO_ID"
  wp eval "
    \$id  = $LOGO_ID;
    \$url = wp_get_attachment_image_url( \$id, 'full' );
    \$settings = get_option( 'autodescription-site-settings', [] );
    \$settings['social_image_fb_id']  = \$id;
    \$settings['social_image_fb_url'] = \$url;
    \$settings['knowledge_logo_id']   = \$id;
    \$settings['knowledge_logo_url']  = \$url;
    update_option( 'autodescription-site-settings', \$settings );
  "
  echo "   wired $LOGO_PATH as site icon + social/schema image"
else
  echo "   no $LOGO_PATH yet — skipping (see apply-brand-input skill)"
fi

echo "→ [15/15] SEO titles + meta descriptions"
# The SEO Framework auto-generates a title and a meta description for anything
# with real content underneath it, so the Journal's posts and the populated
# product categories need nothing written here — that is most of why it was
# chosen over Yoast (see step 2).
#
# What it cannot generate a *passing* title or description for is three cases,
# and its own SEO Bar (the coloured TG/DG/I/F/A/R column in wp-admin's post
# lists) flags each of them orange or red until they are fixed here:
#
#  1. A page whose post_content is deliberately EMPTY. Every "container" page
#     in this boilerplate is one: the front page, the Journal, About Us,
#     Contact, and WooCommerce's Shop page all render from templates/*.html
#     rather than from the database (see "Homepage and Journal" in CLAUDE.md),
#     so there is nothing to generate a description *from*.
#  2. A page whose title is one short word. TSF brands every title
#     ("Shop - Store Name"), and "Shop", "Cart", "Checkout", "Privacy Policy"
#     and "Terms of Use" are all still under its 35-character floor even
#     branded. Each gets an explicit SEO title. This sets only
#     <title>/og:title — never the H1, the menu label or the breadcrumb — so
#     /shop/ still reads "Shop" on the page.
#
#     Cart, checkout and my-account get a description as well. They are
#     noindex on purpose (see CLAUDE.md) so no search engine will use it, but
#     they have no content to generate one from either, which leaves the SEO
#     Bar reporting an empty description on three pages forever; the value is
#     also what og:description falls back to when someone shares the link.
#  3. Content whose generated value falls outside the length guidelines: the
#     four sample products' short descriptions (WooCommerce's excerpt field is
#     what TSF generates a product description from, and a 37-character one
#     cannot produce a valid meta description), and the one sample article
#     whose 46-character headline brands out to 66 — one over the upper bound.
#
# Thresholds are TSF's own, from its Helper\Guidelines: a title wants 35-65
# characters (25-75 tolerated), a description 80-160 (45-320 tolerated). The
# copy below sits mid-band on purpose, leaving room for a store name longer
# than this boilerplate's, since TSF appends " - <blogname>" to every title.
#
# Length is not the only rule: TSF also grades a description on REPEATED WORDS
# and marks it orange once more than one word occurs twice, or any word occurs
# four times. A first draft of the checkout description below said "your" three
# times and was flagged for exactly that — keep each word in a description to
# one use where the sentence allows it.
#
# Verify the result with ./scripts/seo-check.py, which gates these same bands.

# One line per value, `locator|field|value`, applied in a SINGLE `wp eval`:
# every `wp` call here is a fresh container round-trip through wp-env, and a
# step that fires a dozen of them is both slow and an extra place for
# `wp-env start` to fall over. The document is handed to PHP base64-encoded
# rather than interpolated into the snippet, because a clone's copy will
# eventually contain an apostrophe or a double quote and either would silently
# break a PHP string built by the shell. The only character the copy may not
# contain is the `|` separator.
#
# Locators: `option:<name>` (a page ID stored in an option), `page:<slug>`,
# `post:<slug>`, `product:<slug>`, `term:<taxonomy>:<slug>`.
# Fields: `seo_title` and `seo_description` write TSF's own post/term meta;
# `term_description` and `short_description` write the real WordPress term
# description / product excerpt, which are visible on the front end too. A
# `legacy:<field>` line writes nothing — it records a value an OLDER version of
# this script once wrote, so the current copy is allowed to replace it. See the
# don't-clobber rule below.
BLOGNAME="$( wp option get blogname | tr -d '\r\n' )"
BLOGDESCRIPTION="$( wp option get blogdescription | tr -d '\r\n' )"
SEO_DOC="$( cat <<DOC
option:page_on_front|seo_description|Shop skincare, body care, and the tools that go with them — plus routine notes and ingredient guides from the Journal.
option:page_for_posts|seo_title|Journal: Notes and Routine Guides
option:page_for_posts|seo_description|Skincare notes, ingredient explainers, and simple routine guides from the team behind the shop — no ten-step routines.
option:woocommerce_shop_page_id|seo_title|Shop All Skincare and Body Care
option:woocommerce_shop_page_id|seo_description|Browse the full range of cleansers, moisturizers, treatments, eye care, and accessories available at ${BLOGNAME}.
option:woocommerce_cart_page_id|seo_title|Your Cart — Review Your Order
option:woocommerce_cart_page_id|seo_description|Review the items in your cart, update quantities, and check your order total before you continue to checkout.
option:woocommerce_checkout_page_id|seo_title|Secure Checkout — Pay Safely
option:woocommerce_checkout_page_id|seo_description|Enter shipping and payment details to complete the order securely. Totals appear in full before any card is charged.
option:woocommerce_myaccount_page_id|seo_title|Your Account and Order History
option:woocommerce_myaccount_page_id|seo_description|Sign in to view your orders, track deliveries, and manage your addresses and account details.
page:about-us|seo_title|Our Story and How We Formulate
page:about-us|seo_description|Learn about our story, how we formulate, and why we make skincare for sensitive skin.
page:contact|seo_title|Get in Touch With Our Team
page:contact|seo_description|Questions about an order, a product, or anything else? Send a message using the form below and our team will respond soon.
page:privacy-policy|seo_title|Privacy Policy and Data Practices
page:terms-of-use|seo_title|Terms of Use and Store Conditions
post:behind-every-formula-how-we-choose-ingredients|seo_title|How We Choose Our Ingredients
term:product_cat:cleansers|seo_description|Soap-free cleansers that lift away makeup, sunscreen, and daily buildup without stripping or tightening the skin.
term:product_cat:moisturizers|seo_description|Lightweight moisturizers and hydrating serums that lock in moisture for up to 24 hours, on every skin type.
term:product_cat:treatments|seo_description|Targeted treatments — night oils, polishes, and actives — for visibly brighter, smoother, more even-looking skin.
term:category:skincare|term_description|Notes, ingredient explainers, and routine guides on skincare — what actually works, and what you can skip.
product:rejuvenating-night-oil|short_description|A nourishing overnight oil with rosehip and squalane that replenishes dry, tired skin while you sleep. Wake up softer.
product:rose-quartz-facial-polish|short_description|A gentle exfoliating polish with finely milled rose quartz that smooths and brightens without scratching the skin.
product:hydrating-body-serum|short_description|A lightweight, fast-absorbing body serum that locks in moisture for up to 24 hours without any greasy finish.
product:gentle-gel-cleanser|short_description|A soap-free gel cleanser that lifts away makeup, sunscreen, and daily buildup without stripping or tightening skin.
option:page_on_front|legacy:seo_description|${BLOGDESCRIPTION}
option:page_for_posts|legacy:seo_description|Skincare notes, ingredient explainers, and routine guides from the team.
option:woocommerce_shop_page_id|legacy:seo_description|Browse the full range of products available at ${BLOGNAME}.
DOC
)"

# Re-runs never clobber an owner's edit. Alongside each value the snippet
# records what it wrote in an `_agentic_seo_seeded` post/term meta map, and it
# only writes a field whose stored value is (a) empty, (b) still byte-identical
# to that record, or (c) byte-identical to one of the `legacy:` values above.
# So: edit a description in wp-admin and this step leaves it alone from then on;
# edit the copy above and every clone that never touched it picks the new copy
# up on the next `wp-env start`.
#
# Case (c) is what lets this step repair a store that was seeded by an older
# version of it, before there was any record to compare against — the front
# page, Journal and Shop descriptions it used to write were all shorter than
# TSF's 45-character floor or its 80-character ideal. Those three `legacy:`
# lines can be dropped once no clone is running the older copy.
#
# The three product categories keep their SHORT term description — that string
# is the visible one-line tagline under the category heading on
# taxonomy-product_cat.html and must stay one line — and get the longer copy as
# TSF's own term meta instead, which only search engines see. Eye Care and
# Accessories deliberately get neither: they hold no products, and TSF
# noindexes an empty archive by itself, so there is nothing to describe.
wp eval '
	$doc     = base64_decode( "'"$( printf '%s' "$SEO_DOC" | base64 | tr -d '\n' )"'" );
	$tsf_tax = \defined( "THE_SEO_FRAMEWORK_TERM_OPTIONS" ) ? THE_SEO_FRAMEWORK_TERM_OPTIONS : "autodescription-term-settings";
	$written = 0;

	// Two passes: `legacy:` lines are declarations, not values to write, and a
	// legacy line may appear after the value it applies to.
	$entries = [];
	$legacy  = [];

	foreach ( explode( "\n", $doc ) as $line ) {
		$line = trim( $line );
		if ( "" === $line ) continue;

		list( $locator, $field, $value ) = array_pad( explode( "|", $line, 3 ), 3, "" );

		if ( 0 === strpos( $field, "legacy:" ) ) {
			$legacy[ $locator ][ substr( $field, 7 ) ][] = $value;
		} else {
			$entries[] = [ $locator, $field, $value ];
		}
	}

	foreach ( $entries as list( $locator, $field, $value ) ) {
		$parts = explode( ":", $locator );

		// Resolve the locator to a post ID or a term.
		$id   = 0;
		$term = null;
		switch ( $parts[0] ) {
			case "option":
				$id = (int) get_option( $parts[1] );
				break;
			case "page":
			case "post":
			case "product":
				$id = (int) ( get_page_by_path( $parts[1], OBJECT, $parts[0] )->ID ?? 0 );
				break;
			case "term":
				$term = get_term_by( "slug", $parts[2], $parts[1] );
				break;
		}
		if ( ! $id && ! $term ) continue;

		// Where this field is stored, and what is in it right now.
		$tsf_key = "seo_title" === $field ? "doctitle" : "description";
		if ( $term ) {
			$seeded  = get_term_meta( $term->term_id, "_agentic_seo_seeded", true ) ?: [];
			$tsf     = get_term_meta( $term->term_id, $tsf_tax, true ) ?: [];
			$current = "term_description" === $field ? $term->description : ( $tsf[ $tsf_key ] ?? "" );
		} else {
			$seeded   = get_post_meta( $id, "_agentic_seo_seeded", true ) ?: [];
			$meta_key = "seo_title" === $field ? "_genesis_title" : "_genesis_description";
			$current  = "short_description" === $field
				? get_post_field( "post_excerpt", $id )
				: (string) get_post_meta( $id, $meta_key, true );
		}

		// A product still carrying the placeholder marker in its long
		// description belongs to this script, not to the store owner, so its
		// short description is always safe to rewrite.
		$is_placeholder = ! $term
			&& "short_description" === $field
			&& false !== strpos( get_post_field( "post_content", $id ), "Placeholder product — delete before launch." );

		$ours = "" === $current
			|| ( $seeded[ $field ] ?? null ) === $current
			|| \in_array( $current, $legacy[ $locator ][ $field ] ?? [], true )
			|| $is_placeholder;

		if ( ! $ours ) continue;

		// Already correct: record it as ours (so a later edit is respected)
		// without touching the database value or counting it as a write.
		if ( $current !== $value ) {
			if ( $term ) {
				if ( "term_description" === $field ) {
					wp_update_term( $term->term_id, $term->taxonomy, [ "description" => $value ] );
				} else {
					$tsf[ $tsf_key ] = $value;
					update_term_meta( $term->term_id, $tsf_tax, $tsf );
				}
			} elseif ( "short_description" === $field ) {
				wp_update_post( [ "ID" => $id, "post_excerpt" => $value ] );
			} else {
				update_post_meta( $id, $meta_key, $value );
			}
			$written++;
		}

		if ( ( $seeded[ $field ] ?? null ) !== $value ) {
			$seeded[ $field ] = $value;
			$term
				? update_term_meta( $term->term_id, "_agentic_seo_seeded", $seeded )
				: update_post_meta( $id, "_agentic_seo_seeded", $seeded );
		}
	}

	printf( "   wrote %d SEO value(s)\n", $written );
'

echo "✔ Site ready → http://localhost:8888"
