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

echo "→ [1/11] Building block editor scripts"
# block.json points editorScript at build/<block>/index.js, so the bundle must
# exist before the plugin is activated or blocks register without editor UI.
if [ -d agentic-blocks/blocks ]; then
  if [ ! -d agentic-blocks/node_modules ]; then
    ( cd agentic-blocks && npm install --silent --no-audit --no-fund )
  fi
  ( cd agentic-blocks && npm run build --silent )
fi

echo "→ [2/11] Installing WooCommerce + Yoast SEO (free, from wordpress.org)"
# Installed by slug via WP-CLI rather than as .wp-env.json zip URLs: zip
# sources land in folders named after the zip (woocommerce.latest-stable),
# which leaks into asset URLs and collides with a slug-named second copy.
wp plugin install woocommerce wordpress-seo --activate

echo "→ [3/11] Installing essential add-ons (free, from wordpress.org)"
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

echo "→ [4/11] Activating theme + agentic-blocks"
wp theme activate agentic-theme
wp plugin activate agentic-blocks

echo "→ [5/11] Navigation menus"
# Menu *items* are content the store owner edits in
# Appearance → Editor → Navigation — not layout baked into a template file.
# Templates reference these by slug via the `agenticMenu` attribute, resolved
# to a post ID in theme/agentic-theme/functions.php, because the core
# Navigation block's own `ref` is a numeric ID that differs in every install.
#
# Done early, before anything renders a page: if a Navigation block renders
# while these are missing, WordPress silently auto-creates a stray fallback
# menu that then clutters the owner's Navigation list.
#
# Created only if missing, so re-running never clobbers the owner's edits.
ensure_menu() {
	local slug="$1" title="$2" content="$3" existing
	existing="$( wp post list --post_type=wp_navigation --name="$slug" --field=ID --format=csv | tr -d '\r\n' )"
	if [ -n "$existing" ]; then
		echo "   menu '$slug' already exists (#$existing) — leaving it alone"
		return
	fi
	wp post create \
		--post_type=wp_navigation \
		--post_title="$title" \
		--post_name="$slug" \
		--post_status=publish \
		--post_content="$content" \
		--porcelain >/dev/null
	echo "   created menu '$slug'"
}

ensure_menu 'header-menu' 'Header' \
'<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom","isTopLevelLink":true} /--><!-- wp:navigation-link {"label":"About","url":"/sample-page/","kind":"custom","isTopLevelLink":true} /--><!-- wp:navigation-link {"label":"Journal","url":"/journal/","kind":"custom","isTopLevelLink":true} /-->'

ensure_menu 'footer-shop' 'Footer — Shop' \
'<!-- wp:navigation-link {"label":"All products","url":"/shop/","kind":"custom","isTopLevelLink":true} /--><!-- wp:navigation-link {"label":"Cart","url":"/cart/","kind":"custom","isTopLevelLink":true} /--><!-- wp:navigation-link {"label":"My account","url":"/my-account/","kind":"custom","isTopLevelLink":true} /-->'

ensure_menu 'footer-help' 'Footer — Help' \
'<!-- wp:navigation-link {"label":"Shipping &amp; returns","url":"/sample-page/","kind":"custom","isTopLevelLink":true} /--><!-- wp:navigation-link {"label":"Contact","url":"/sample-page/","kind":"custom","isTopLevelLink":true} /--><!-- wp:navigation-link {"label":"Privacy policy","url":"/privacy-policy/","kind":"custom","isTopLevelLink":true} /-->'

echo "→ [6/11] Completing WooCommerce installation"
# WooCommerce defers part of its installer to the first request after
# activation, and that deferred run writes its own defaults. Forcing it to
# finish synchronously here means the settings written below actually stick
# instead of being silently overwritten a moment later.
wp eval 'if ( class_exists( "WC_Install" ) ) { WC_Install::install(); }'

echo "→ [7/11] Permalinks"
# Flat /%postname%/ — the sane structure for products; the WP default
# day-and-name buries /shop/ URLs under a date path.
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

echo "→ [8/11] Store defaults"
wp option update blogdescription 'A code-first WooCommerce store'
wp option update woocommerce_store_country 'US:CA'
wp option update woocommerce_currency 'USD'
# Skip the wp-admin onboarding wizard — it is the one flow that would
# otherwise force clicking through the UI before the store works.
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json

echo "→ [9/11] Front page + Journal"
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

echo "→ [10/11] Seeding sample products (so templates are verifiable)"
# Four, not one: a single product leaves the product grid and the "related
# products" collection with nothing to show, which hides real template bugs.
# Delete these before launching a real store.
if [ "$(wp post list --post_type=product --format=count | tr -d '\r')" = "0" ]; then
  wp wc product_cat create --name='Sample Category' --slug='sample-category' --user=admin || true

  seed_product() {
    wp wc product create \
      --name="$1" \
      --slug="$2" \
      --type=simple \
      --regular_price="$3" \
      --description="$4" \
      --short_description="$5" \
      --categories='[{"slug":"sample-category"}]' \
      --status=publish \
      --user=admin
  }

  seed_product 'Sample Product' 'sample-product' '29.00' \
    'A placeholder product so product templates render with real data. Delete it before launch.' \
    'Placeholder product for template verification.'
  seed_product 'Everyday Tote' 'everyday-tote' '48.00' \
    'A placeholder product used to fill the product grid during development.' \
    'Roomy canvas tote for daily use.'
  seed_product 'Ceramic Mug' 'ceramic-mug' '18.00' \
    'A placeholder product used to fill the product grid during development.' \
    'Stoneware mug, dishwasher safe.'
  seed_product 'Linen Apron' 'linen-apron' '36.00' \
    'A placeholder product used to fill the product grid during development.' \
    'Washed linen apron with adjustable strap.'
else
  echo "   products already exist — skipping"
fi

echo "→ [11/11] Disabling WooCommerce Coming Soon mode"
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
