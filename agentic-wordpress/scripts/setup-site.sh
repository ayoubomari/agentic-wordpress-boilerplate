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

echo "→ [1/13] Building block editor scripts"
# block.json points editorScript at build/<block>/index.js, so the bundle must
# exist before the plugin is activated or blocks register without editor UI.
if [ -d agentic-blocks/blocks ]; then
  if [ ! -d agentic-blocks/node_modules ]; then
    ( cd agentic-blocks && npm install --silent --no-audit --no-fund )
  fi
  ( cd agentic-blocks && npm run build --silent )
fi

echo "→ [2/13] Installing WooCommerce + Yoast SEO (free, from wordpress.org)"
# Installed by slug via WP-CLI rather than as .wp-env.json zip URLs: zip
# sources land in folders named after the zip (woocommerce.latest-stable),
# which leaks into asset URLs and collides with a slug-named second copy.
wp plugin install woocommerce wordpress-seo --activate

echo "→ [3/13] Installing essential add-ons (free, from wordpress.org)"
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

echo "→ [4/13] Activating theme + agentic-blocks"
wp theme activate agentic-theme
wp plugin activate agentic-blocks

echo "→ [5/13] Completing WooCommerce installation"
# WooCommerce defers part of its installer to the first request after
# activation, and that deferred run writes its own defaults. Forcing it to
# finish synchronously here means the settings written below actually stick
# instead of being silently overwritten a moment later.
wp eval 'if ( class_exists( "WC_Install" ) ) { WC_Install::install(); }'

echo "→ [6/13] Permalinks"
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

echo "→ [7/13] Store defaults"
wp option update blogdescription 'A code-first WooCommerce store'
wp option update woocommerce_store_country 'US:CA'
wp option update woocommerce_currency 'USD'
# Skip the wp-admin onboarding wizard — it is the one flow that would
# otherwise force clicking through the UI before the store works.
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json

echo "→ [8/13] Front page + Journal"
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

# Yoast has nothing to auto-generate a homepage meta description from — the
# front page's post_content is deliberately empty (its sections live in
# templates/front-page.html, not the database, per "Homepage and Journal"
# above) — so without this it fails Lighthouse's SEO meta-description check
# on every fresh install. This must be a POST META field on the front-page
# post (`_yoast_wpseo_metadesc`), not the `metadesc-home-wpseo` key in the
# wpseo_titles option: that option only applies when the blog index itself
# is the homepage. With show_on_front=page (our setup), Yoast treats the
# front page like any other page and reads its per-page SEO fields instead
# — confirmed by testing both directly; only the post-meta version actually
# rendered a <meta name="description"> tag.
wp eval '
	if ( ! get_post_meta( (int) get_option( "page_on_front" ), "_yoast_wpseo_metadesc", true ) ) {
		update_post_meta(
			(int) get_option( "page_on_front" ),
			"_yoast_wpseo_metadesc",
			get_bloginfo( "description" ) ?: "A code-first WooCommerce store."
		);
	}
'

echo "→ [9/13] About Us page"
# Same container-page pattern as Home/Journal above: the page record exists
# only so WordPress has something to route /about-us/ to. The actual content
# lives in templates/page-about-us.html, resolved automatically by WordPress's
# block-template hierarchy from the page's slug — no manual "Template" picker
# needed, and re-running this never touches an owner's edits to the page.
ABOUT_US_ID="$( ensure_page 'about-us' 'About Us' )"

# Same reasoning as the front page's meta description above: post_content is
# empty (content lives in the template file), so Yoast has nothing to
# auto-generate a description from without this.
wp eval '
	if ( ! get_post_meta( '"$ABOUT_US_ID"', "_yoast_wpseo_metadesc", true ) ) {
		update_post_meta(
			'"$ABOUT_US_ID"',
			"_yoast_wpseo_metadesc",
			"Learn about our story, how we formulate, and why we make skincare for sensitive skin."
		);
	}
'

echo "→ [10/13] Legal pages"
# WordPress core auto-creates a "Privacy Policy" page (draft, at
# /privacy-policy/) on every fresh install — there is no equivalent core
# default for Terms of Use, so it's created here the same way: idempotent,
# draft (real legal copy is the store owner's to write/review before
# publishing — see "Owner-editable in wp-admin" in CLAUDE.md), placeholder
# body text flagged for replacement, same as the seeded sample products.
#
# `wp post list --name=` silently ignores draft posts no matter what
# --post_status is passed (a WP_Query quirk, not a wp-cli bug) — it always
# came back empty here and recreated a duplicate draft on every single
# re-run. get_page_by_path() doesn't have that restriction.
if [ "$( wp eval 'echo get_page_by_path( "terms-of-use", OBJECT, "page" ) ? "1" : "0";' | tr -d '\r\n' )" = "0" ]; then
  wp post create \
    --post_type=page \
    --post_title='Terms of Use' \
    --post_name='terms-of-use' \
    --post_status=draft \
    --post_content='<!-- wp:paragraph --><p>Placeholder Terms of Use — replace with real, reviewed legal copy before launch.</p><!-- /wp:paragraph -->' \
    --porcelain >/dev/null
  echo "   created page 'terms-of-use'"
else
  echo "   page 'terms-of-use' already exists — leaving it alone"
fi

echo "→ [11/13] Seeding sample products (so templates are verifiable)"
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
    local name="$1" slug="$2" category_id="$3" regular_price="$4" sale_price="$5" description="$6" short_description="$7" stock_quantity="${8:-}" image="${9:-}"
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
      # shape as seed_post's --featured_image below.
      local image_id
      image_id="$( wp media import "$image" --porcelain )"
      args+=(--images="[{\"id\":$image_id}]")
    fi
    "${args[@]}"
  }

  # assets/images/products/<slug>.webp — individual crops taken from
  # bundle/skincare-set.webp (a single studio shot of the three matching
  # products together, labels reading "ROSE QUARTZ GENTLE EXFOLIATING
  # SCRUB", "REJUVENATING NIGHT OIL", "GENTLE GEL CLEANSER"), one per
  # product rather than reusing the same group shot on every product page.
  # Hydrating Body Serum has no matching group-shot crop, so it uses
  # hero/product-right.webp (a standalone bottle+jar shot) directly.
  seed_product 'Rejuvenating Night Oil' 'rejuvenating-night-oil' "$TREATMENTS_CAT_ID" '79.00' '' \
    'A nourishing night oil formulated with rosehip and squalane to replenish skin while you sleep. Placeholder product — delete before launch.' \
    'Nourishing night oil with rosehip and squalane.' '' \
    'wp-content/themes/agentic-theme/assets/images/products/rejuvenating-night-oil.webp'
  seed_product 'Rose Quartz Facial Polish' 'rose-quartz-facial-polish' "$TREATMENTS_CAT_ID" '79.00' '59.00' \
    'A gentle exfoliating polish with fine rose quartz powder to reveal smoother, brighter skin. Placeholder product — delete before launch.' \
    'Gentle exfoliating polish with rose quartz powder.' '' \
    'wp-content/themes/agentic-theme/assets/images/products/rose-quartz-facial-polish.webp'
  seed_product 'Hydrating Body Serum' 'hydrating-body-serum' "$MOISTURIZERS_CAT_ID" '79.00' '' \
    'A lightweight, fast-absorbing serum that locks in moisture for up to 24 hours. Placeholder product — delete before launch.' \
    'Lightweight, fast-absorbing hydrating serum.' '2' \
    'wp-content/themes/agentic-theme/assets/images/products/hydrating-body-serum.webp'
  seed_product 'Gentle Gel Cleanser' 'gentle-gel-cleanser' "$CLEANSERS_CAT_ID" '39.00' '29.00' \
    'A soap-free gel cleanser that lifts away impurities without stripping the skin. Placeholder product — delete before launch.' \
    'Soap-free gel cleanser for daily use.' '' \
    'wp-content/themes/agentic-theme/assets/images/products/gentle-gel-cleanser.webp'
else
  echo "   products already exist — skipping"
fi

echo "→ [12/13] Seeding Journal posts (so agentic/latest-posts has real content)"
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

echo "→ [13/13] Disabling WooCommerce Coming Soon mode"
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

echo "✔ Site ready → http://localhost:8888"
