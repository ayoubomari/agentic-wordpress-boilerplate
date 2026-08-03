<?php
/**
 * Agentic Theme — theme behaviour.
 *
 * Keep this file small. Layout belongs in templates/, design tokens belong in
 * theme.json. Only put PHP here when there is genuinely no declarative way to
 * express something.
 */

defined( 'ABSPATH' ) || exit;

// wp-admin Dashboard widget listing every manual, owner-only setup step
// (payment gateway, SMTP, backups, tax, ...) — split out because it's a
// self-contained admin-only feature, not core theme behaviour.
require get_theme_file_path( 'inc/setup-checklist.php' );

/**
 * Analytics / ad pixels — GA4 and Meta Pixel.
 *
 * Deliberately not a plugin: this is two script tags plus one conversion
 * event, not enough surface area to justify a dependency (see CLAUDE.md —
 * "no bloat"). Both IDs are public identifiers, not secrets, but they are
 * still store-specific, so nothing here is invented — both stay empty, and
 * nothing fires, until the store owner sets them. Preferred: as constants in
 * wp-config.php, so real IDs never need a code change or land in git history
 * for a boilerplate meant to be cloned:
 *
 *     define( 'AGENTIC_GA4_ID', 'G-XXXXXXX' );
 *     define( 'AGENTIC_META_PIXEL_ID', '000000000000000' );
 *
 * Admins/store managers are excluded from both so backend/dev traffic never
 * skews the numbers.
 */
defined( 'AGENTIC_GA4_ID' ) || define( 'AGENTIC_GA4_ID', '' );
defined( 'AGENTIC_META_PIXEL_ID' ) || define( 'AGENTIC_META_PIXEL_ID', '' );

add_action(
	'wp_head',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( AGENTIC_GA4_ID ) {
			$id = esc_js( AGENTIC_GA4_ID );
			echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n";
			echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>\n";
		}

		if ( AGENTIC_META_PIXEL_ID ) {
			$id = esc_js( AGENTIC_META_PIXEL_ID );
			echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>\n";
			echo "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1\" /></noscript>\n";
		}
	}
);

/**
 * Purchase conversion — the one ecommerce event worth wiring beyond page
 * views. Guarded by order meta so refreshing/revisiting the thank-you page
 * never double-fires it, a common and easy-to-miss bug in hand-rolled pixel
 * setups that quietly inflates every revenue report downstream.
 */
add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		if ( ! $order_id || ( ! AGENTIC_GA4_ID && ! AGENTIC_META_PIXEL_ID ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_agentic_purchase_tracked' ) ) {
			return;
		}
		$order->update_meta_data( '_agentic_purchase_tracked', 'yes' );
		$order->save();

		$items = [];
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = [
				'item_id'   => $product ? ( $product->get_sku() ?: (string) $product->get_id() ) : '',
				'item_name' => $item->get_name(),
				'quantity'  => (int) $item->get_quantity(),
				'price'     => (float) $order->get_item_total( $item, false, false ),
			];
		}

		$value    = (float) $order->get_total();
		$currency = $order->get_currency();

		if ( AGENTIC_GA4_ID ) {
			$payload = wp_json_encode(
				[
					'transaction_id' => (string) $order_id,
					'value'          => $value,
					'currency'       => $currency,
					'items'          => $items,
				]
			);
			echo "<script>window.gtag && gtag('event','purchase',{$payload});</script>\n";
		}

		if ( AGENTIC_META_PIXEL_ID ) {
			$payload = wp_json_encode(
				[
					'value'       => $value,
					'currency'    => $currency,
					'contents'    => array_map(
						static function ( $item ) {
							return [ 'id' => $item['item_id'], 'quantity' => $item['quantity'] ];
						},
						$items
					),
					'content_type' => 'product',
				]
			);
			echo "<script>window.fbq && fbq('track','Purchase',{$payload});</script>\n";
		}
	}
);

/**
 * Block themes do not auto-enqueue their own style.css — WordPress only
 * pulls in each block's own style.css via block.json. This file holds the
 * handful of rules that aren't owned by a single block (see the comment at
 * the top of style.css), so it needs an explicit, always-on enqueue.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		// filemtime(), not the static theme version header: a version string
		// that never changes across edits means browsers keep serving a
		// stale cached copy of this file after every future CSS change —
		// caught during the Sleek redesign when a fix to this exact file
		// silently didn't apply in an already-loaded browser tab.
		wp_enqueue_style(
			'agentic-theme-style',
			get_stylesheet_uri(),
			[],
			(string) filemtime( get_stylesheet_directory() . '/style.css' )
		);

		// The mini-cart drawer opens from the header on every page, so its
		// override needs to load everywhere — keep it tiny and inline rather
		// than pulling in the whole cart/checkout/account stylesheet below.
		if ( function_exists( 'is_cart' ) ) {
			wp_add_inline_style(
				'agentic-theme-style',
				'.wc-block-mini-cart__drawer .wc-block-mini-cart-items__row{border-color:var(--wp--preset--color--border) !important}
				.wc-block-mini-cart__drawer .wc-block-components-button{border:1px solid var(--wp--preset--color--contrast) !important;background:var(--wp--preset--color--contrast) !important;color:var(--wp--preset--color--base) !important;border-radius:var(--agentic-radius-pill) !important;box-shadow:none !important}'
			);
		}
	}
);

/**
 * Cart, checkout, and My Account render through WooCommerce's own
 * block/classic markup, which ships rounded corners, drop shadows, and blue
 * accents that don't match the Sleek-style token set in theme.json. Re-point
 * their class names at our tokens — see assets/css/woocommerce.css — loaded
 * only on the pages that actually render that markup, so it costs nothing
 * elsewhere.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_cart' ) ) {
			return; // WooCommerce inactive.
		}

		if ( is_cart() || is_checkout() || is_account_page() ) {
			$path = get_theme_file_path( 'assets/css/woocommerce.css' );
			wp_enqueue_style(
				'agentic-woocommerce-overrides',
				get_theme_file_uri( 'assets/css/woocommerce.css' ),
				[],
				file_exists( $path ) ? (string) filemtime( $path ) : wp_get_theme()->get( 'Version' )
			);
		}
	}
);

/**
 * Front-page performance. Three Lighthouse findings on "/", all fixable
 * without touching any visual output:
 *
 * 1. The hero-banner's first slide is the LCP element, but its image is only
 *    knowable once the browser parses an inline `background-image` style
 *    deep inside <main> — long after <head> is sent. A `<link rel=preload>`
 *    lets the browser fetch it in parallel with everything else instead of
 *    discovering it late. Read straight from the front-page template's own
 *    hero-banner block (not hardcoded) so this can never drift out of sync
 *    with templates/front-page.html.
 * 2. jQuery + jquery-migrate are render-blocking by default. WordPress
 *    6.3+'s script "strategy" data defers them — and, being dependency-
 *    aware, automatically keeps anything incompatible blocking instead of
 *    breaking load order, unlike a manual footer move.
 * 3. WooCommerce enqueues its three classic frontend stylesheets
 *    (general/layout/smallscreen) on every page, but no block used on the
 *    front page emits the classic markup those target — e.g.
 *    featured-collection's get_price_html() output is fully re-styled by
 *    that block's own CSS (see its <ins>/<del> rules). Scoped to just the
 *    front page rather than removed sitewide, since archive-product /
 *    single-product still render WooCommerce's own templates.
 */
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() ) {
			return;
		}

		$template = get_block_template( get_stylesheet() . '//front-page', 'wp_template' );
		if ( ! $template ) {
			return;
		}

		$image_url = agentic_find_first_hero_image( parse_blocks( $template->content ) );
		if ( $image_url ) {
			printf( '<link rel="preload" as="image" href="%s" />' . "\n", esc_url( $image_url ) );
		}
	},
	1
);

if ( ! function_exists( 'agentic_find_first_hero_image' ) ) {
	function agentic_find_first_hero_image( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'agentic/hero-banner' === ( $block['blockName'] ?? '' ) ) {
				$attrs = $block['attrs'] ?? [];
				if ( ! empty( $attrs['slides'][0]['imageUrl'] ) ) {
					return $attrs['slides'][0]['imageUrl'];
				}
				return $attrs['imageUrl'] ?? '';
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = agentic_find_first_hero_image( $block['innerBlocks'] );
				if ( $found ) {
					return $found;
				}
			}
		}
		return '';
	}
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_script_add_data( 'jquery-core', 'strategy', 'defer' );
		wp_script_add_data( 'jquery-migrate', 'strategy', 'defer' );
	},
	20
);

add_filter(
	'woocommerce_enqueue_styles',
	function ( $styles ) {
		if ( is_front_page() ) {
			unset( $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'], $styles['woocommerce-general'] );
		}
		return $styles;
	}
);

/**
 * Full-height hero carousels (agentic/hero-banner, "large" + slides) need to
 * fit within one screen *below* the header — including the announcement bar
 * above it — so the slide picture and its pagination dots are never pushed
 * partly under the fold. There is no pure-CSS way to read a sibling's
 * rendered height, so this sets it once as a CSS custom property (and again
 * on resize, since the announcement-bar marquee/nav can wrap differently at
 * different widths). Deferred to wp_footer and a few bytes inline rather
 * than a queued file, since a render-blocking request for this would cost
 * more than it's worth.
 */
add_action(
	'wp_footer',
	function () {
		?>
		<script>
		( function () {
			var header = document.querySelector( 'header.wp-block-template-part' );
			if ( ! header ) {
				return;
			}
			function setAgenticHeaderHeight() {
				document.documentElement.style.setProperty( '--agentic-header-height', header.offsetHeight + 'px' );
			}
			setAgenticHeaderHeight();
			window.addEventListener( 'resize', setAgenticHeaderHeight );
		} )();
		</script>
		<?php
	}
);

/**
 * Let templates reference navigation menus by a stable slug.
 *
 * The core Navigation block points at a menu by numeric post ID (`ref`), which
 * is different in every install — so a template committed to this repo can
 * never hardcode one and still work in a clone.
 *
 * This resolves a custom `agenticMenu` attribute (a `wp_navigation` slug) into
 * the right `ref` at render time:
 *
 *     <!-- wp:navigation {"agenticMenu":"header-menu"} /-->
 *
 * The menus themselves are created by scripts/setup-site.sh and are then fully
 * editable by the store owner in Appearance → Editor → Navigation. Menu items
 * are content, not layout — the same split Shopify makes, where sections live
 * in theme files but menus live in the admin.
 *
 * Uses `render_block_data` rather than the block's own attributes because
 * `prepare_attributes_for_render()` drops attributes that are not in the block
 * schema; the raw parsed attributes are still intact at this point.
 */
add_filter(
	'render_block_data',
	function ( $parsed_block ) {
		if ( 'core/navigation' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$slug = $parsed_block['attrs']['agenticMenu'] ?? '';
		if ( '' === $slug ) {
			return $parsed_block;
		}

		$menu_id = agentic_get_navigation_id( $slug );
		if ( $menu_id ) {
			$parsed_block['attrs']['ref'] = $menu_id;
		}

		return $parsed_block;
	}
);

/**
 * Look up a wp_navigation menu by slug, with a short-lived cache so a page
 * with several menus does not run the same query repeatedly.
 *
 * @param string $slug Menu slug, e.g. "header-menu".
 * @return int Menu post ID, or 0 if there is no menu with that slug.
 */
function agentic_get_navigation_id( $slug ) {
	static $cache = [];

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$menus = get_posts(
		[
			'post_type'        => 'wp_navigation',
			'name'             => $slug,
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'suppress_filters' => false,
			'no_found_rows'    => true,
		]
	);

	$cache[ $slug ] = $menus ? (int) $menus[0]->ID : 0;

	return $cache[ $slug ];
}
