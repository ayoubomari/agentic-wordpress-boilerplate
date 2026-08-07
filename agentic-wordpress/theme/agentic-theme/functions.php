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
 * Cart, checkout, My Account, and the product archives (shop, category, tag)
 * all render through WooCommerce's own block/classic markup, which ships
 * rounded corners, drop shadows, and blue accents that don't match the
 * Sleek-style token set in theme.json. Re-point their class names at our
 * tokens — see assets/css/woocommerce.css — loaded only on the pages that
 * actually render that markup, so it costs nothing elsewhere.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_cart' ) ) {
			return; // WooCommerce inactive.
		}

		if ( is_cart() || is_checkout() || is_account_page() || is_shop() || is_product_taxonomy() ) {
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
 * Journal archive (the page_for_posts listing) and single post — both
 * render through core query-loop/post blocks rather than a custom
 * agentic/* block, so their default markup needs re-pointing at the same
 * design tokens every agentic/* block uses. See assets/css/journal.css —
 * loaded only on the two views that actually render that markup.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_home() && ! is_singular( 'post' ) ) {
			return;
		}
		$path = get_theme_file_path( 'assets/css/journal.css' );
		wp_enqueue_style(
			'agentic-journal',
			get_theme_file_uri( 'assets/css/journal.css' ),
			[],
			file_exists( $path ) ? (string) filemtime( $path ) : wp_get_theme()->get( 'Version' )
		);
	}
);

/**
 * Scroll-reveal — fades/slides sections and card grids up as they enter the
 * viewport (skipping the hero, which is already on screen at load — see
 * assets/js/scroll-reveal.js for the full contract and why each block is
 * grouped the way it is). Scoped to the specific templates that actually
 * have reveal-eligible content, same pattern as journal.css/woocommerce.css
 * above, rather than loaded everywhere:
 *  - front page: the homepage's stacked agentic/* sections
 *  - shop / product category / product tag archives: product-subcategories'
 *    tile grid and the WooCommerce product-collection grid (see the
 *    render_block_woocommerce/product-collection filter below, which is
 *    what puts `agentic-reveal-item` on WooCommerce's own product cards)
 *  - the About Us page (templates/page-about-us.html): the same stacked-
 *    section layout as the front page, just a different set of blocks
 *  - the Journal listing (home.html) and single posts: the post grid and
 *    agentic/latest-posts' "Related Posts" both need the same card-grid
 *    treatment (see the render_block_core/post-template filter below,
 *    which marks core's own query-loop post cards the same way the
 *    WooCommerce filter above marks product cards)
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$is_shop_page = function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
		$is_journal    = is_home() || is_singular( 'post' );
		if ( ! is_front_page() && ! $is_shop_page && ! is_page( 'about-us' ) && ! $is_journal ) {
			return;
		}

		$css_path = get_theme_file_path( 'assets/css/scroll-reveal.css' );
		wp_enqueue_style(
			'agentic-scroll-reveal',
			get_theme_file_uri( 'assets/css/scroll-reveal.css' ),
			[],
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : wp_get_theme()->get( 'Version' )
		);
		add_filter( 'style_loader_tag', 'agentic_async_load_scroll_reveal_css', 10, 2 );

		$js_path = get_theme_file_path( 'assets/js/scroll-reveal.js' );
		wp_enqueue_script(
			'agentic-scroll-reveal',
			get_theme_file_uri( 'assets/js/scroll-reveal.js' ),
			[],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : wp_get_theme()->get( 'Version' ),
			true
		);
	}
);

/**
 * scroll-reveal.css only matters once scroll-reveal.js adds its
 * `is-agentic-pending`/`is-agentic-load-pending` classes — every rule in it
 * is dead weight until then (see that file's own docblock) — so unlike
 * woocommerce.css/journal.css above it doesn't need to block first paint.
 * The classic "async CSS" trick: load it as `media="print"` (matches
 * nothing on screen, so the browser fetches it without blocking render)
 * and flip it to `media="all"` once it finishes. `wp_enqueue_style()` has
 * no argument for this, so it's rewritten via `style_loader_tag`, scoped to
 * just this one handle so no other stylesheet is affected. No `<noscript>`
 * fallback needed: without JS, nothing ever adds the classes this CSS keys
 * off of either, so content simply renders at its normal, fully-visible
 * default either way — the same reasoning that makes this whole feature
 * safe with JS disabled.
 */
if ( ! function_exists( 'agentic_async_load_scroll_reveal_css' ) ) {
	function agentic_async_load_scroll_reveal_css( $html, $handle ) {
		if ( 'agentic-scroll-reveal' !== $handle ) {
			return $html;
		}
		return str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $html );
	}
}

/**
 * Journal comment form — re-labelled to match the Sleek reference ("Leave a
 * comment" instead of core's default "Leave a Reply", a plain-language
 * moderation note) and dropped down to the three fields the reference
 * shows (Name, Email, Comment). The "Website" field core adds by default is
 * mostly a spam-bot magnet on a storefront blog, not something a shopper
 * filling out a comment expects to be asked for.
 */
add_filter(
	'comment_form_default_fields',
	function ( $fields ) {
		unset( $fields['url'] );
		return $fields;
	}
);

add_filter(
	'comment_form_defaults',
	function ( $defaults ) {
		$defaults['title_reply']         = __( 'Leave a comment', 'agentic' );
		$defaults['comment_notes_after'] = '<p class="agentic-comment-moderation-note">' . esc_html__( 'Please note, comments need to be approved before they are published.', 'agentic' ) . '</p>';
		return $defaults;
	}
);

/**
 * Featured-image LCP fix, two places:
 *
 * single.html's post-featured-image is the largest-contentful-paint element
 * on every Journal post, but core/post-featured-image always renders it
 * `loading="lazy"` — get_the_post_thumbnail() defaults every image to lazy,
 * and unlike images inside the_content(), a template-level featured-image
 * block never runs through the "skip lazy-loading for the first content
 * image" heuristic that would normally exempt it. Lazy-loading your own LCP
 * image makes the browser wait to even discover it, which is exactly what
 * tanked this template's Lighthouse performance score.
 *
 * home.html's Journal listing has the same problem for a different reason:
 * its featured-post banner (a single-item query independent of, and
 * rendered before, the main 3-column grid below it) is the first thing on
 * the page and almost always the LCP element there, but it's still just a
 * post-featured-image block and inherits the same always-lazy default.
 * is_home() alone can't distinguish "the hero" from "one of the nine grid
 * thumbnails further down the same page" (which really do need to stay
 * lazy) — core/post-featured-image doesn't put `queryId` in its block
 * context, so there's no attribute to key off. What's reliably true
 * instead: PHP renders the page top to bottom, and the hero always comes
 * first in template source order, so the first time this filter fires
 * during an is_home() request is always the hero — a plain static counter
 * captures exactly that without needing any block context at all.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $block_content ) {
		static $journal_call_count = 0;

		$is_single_hero = is_singular( 'post' );
		if ( is_home() ) {
			++$journal_call_count;
		}
		$is_journal_hero = is_home() && 1 === $journal_call_count;

		if ( ! $is_single_hero && ! $is_journal_hero ) {
			return $block_content;
		}
		return str_replace( 'loading="lazy"', 'fetchpriority="high"', $block_content );
	}
);

/**
 * Shop/category/tag product cards — second (gallery) photo, crossfaded in on
 * hover, matching agentic/featured-collection's own product cards (see
 * agentic-product-card__media-hover in that block's style.css and
 * render.php). The core Product Collection block's `woocommerce/product-
 * image` has no attribute for a second image, so this reads the product's
 * gallery from the same `render_block_{$name}` filter WordPress already
 * fires for every block — core, not just agentic/* ones — and appends the
 * image markup into that block's own output. Every `woocommerce/product-
 * image` instance in this theme's templates already sits inside a product
 * loop (`isDescendentOfQueryLoop`) — the single-product hero photo uses a
 * different block, `product-image-gallery` — so this never touches
 * anything but grid cards.
 */
add_filter(
	'render_block_woocommerce/product-image',
	function ( $block_content, $parsed_block, $block_instance ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $block_content;
		}

		$product_id = $block_instance->context['postId'] ?? get_the_ID();
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return $block_content;
		}

		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) ) {
			return $block_content;
		}

		$hover_image = wp_get_attachment_image(
			$gallery_ids[0],
			'woocommerce_single',
			false,
			[
				'class' => 'agentic-product-card__media-hover',
				'alt'   => '',
			]
		);

		// Inserted right before WC's own (always-present) inner-container
		// div, so it lands inside the same <a> as the primary photo and can
		// be absolutely positioned over it with no markup restructuring.
		return str_replace(
			'<div class="wc-block-components-product-image__inner-container">',
			wp_kses_post( $hover_image ) . '<div class="wc-block-components-product-image__inner-container">',
			$block_content
		);
	},
	10,
	3
);

/**
 * Shop/category/tag product grid — marks each of WooCommerce's own product
 * cards `agentic-reveal-item`, the same scroll-reveal contract
 * agentic/featured-collection's and agentic/collection-list's render.php
 * files use for their own cards (see assets/js/scroll-reveal.js). The core
 * Product Collection block has no attribute to add a custom class to its
 * cards, so — same technique as the hover-image filter above — this
 * string-inserts the class into the block's own rendered output rather
 * than restructuring the markup. `<li class="wc-block-product ` is always
 * followed by a space before more classes (post ID, product, stock status,
 * …), so this is a targeted match, not a broad string replace.
 */
add_filter(
	'render_block_woocommerce/product-collection',
	function ( $block_content ) {
		return str_replace( '<li class="wc-block-product ', '<li class="wc-block-product agentic-reveal-item ', $block_content );
	}
);

/**
 * Journal listing (home.html) and Related Posts (agentic/latest-posts uses
 * its own markup already, but its host page still needs this asset — see
 * the enqueue above) — marks core's Query Loop post cards
 * `agentic-reveal-item`, same reasoning and technique as the WooCommerce
 * product-collection filter above. `<li class="wp-block-post ` is always
 * followed by a space before more classes (post ID, categories, …), so
 * this is a targeted match. Fires for every core/post-template on the
 * site, including the Journal's single-item "featured post" banner query —
 * harmless there since scroll-reveal.js never hides anything already on
 * screen at load, which that banner always is.
 */
add_filter(
	'render_block_core/post-template',
	function ( $block_content ) {
		return str_replace( '<li class="wp-block-post ', '<li class="wp-block-post agentic-reveal-item ', $block_content );
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
 *    discovering it late. Read straight from the relevant template's own
 *    hero-banner block (not hardcoded) so this can never drift out of sync
 *    with templates/front-page.html or templates/page-about-us.html — the
 *    only two templates that open with agentic/hero-banner.
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
		if ( is_front_page() ) {
			$template_slug = 'front-page';
		} elseif ( is_page( 'about-us' ) ) {
			$template_slug = 'page-about-us';
		} else {
			return;
		}

		$template = get_block_template( get_stylesheet() . '//' . $template_slug, 'wp_template' );
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
 * Restyle the header's mobile nav drawer (parts/header.html's
 * `wp:navigation {"overlayMenu":"mobile"}`) to match Shopify Sleek's mobile
 * menu — a site title next to the close button, and a bottom row (Log In +
 * social icons) instead of core's bare, centered link list floating in an
 * otherwise empty full-screen overlay.
 *
 * The block only exposes a menu-items slot — there's no attribute or inner
 * block for extra drawer chrome — so this splices the extra markup into the
 * already-rendered HTML string, the same technique the product-image hover
 * photo above uses. Two anchors:
 *  - the close button's own opening tag (always present, always unique)
 *    gets the title inserted right before it, so CSS can lay the two out as
 *    a header row via the close button's existing `position:absolute`.
 *  - the LAST `</ul>` in the markup — found with strrpos rather than a
 *    plain str_replace, since a menu item with a submenu renders its own
 *    nested `<ul>` that closes *before* the top-level one — is where the
 *    footer row gets appended, right before the wrapping divs close.
 *
 * Scoped to `overlayMenu === "mobile"` — only the header nav uses a mobile
 * overlay, so this can't touch the footer's own navs (`overlayMenu:"never"`,
 * no drawer to touch).
 */
add_filter(
	'render_block_core/navigation',
	function ( $block_content, $parsed_block ) {
		if ( ( $parsed_block['attrs']['overlayMenu'] ?? '' ) !== 'mobile' ) {
			return $block_content;
		}

		$title_markup = '<span class="agentic-nav-drawer__title">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		$block_content = str_replace(
			'<button aria-label="Close menu" class="wp-block-navigation__responsive-container-close"',
			$title_markup . '<button aria-label="Close menu" class="wp-block-navigation__responsive-container-close"',
			$block_content
		);

		$footer_markup = '<div class="agentic-nav-drawer__footer">';
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$footer_markup .= '<a class="agentic-nav-drawer__login" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'Log In', 'agentic-theme' ) . '</a>';
		}
		$footer_markup .= agentic_nav_drawer_social_links();
		$footer_markup .= '</div>';

		$last_ul_end = strrpos( $block_content, '</ul>' );
		if ( false !== $last_ul_end ) {
			$insert_at     = $last_ul_end + strlen( '</ul>' );
			$block_content = substr( $block_content, 0, $insert_at ) . $footer_markup . substr( $block_content, $insert_at );
		}

		return $block_content;
	},
	10,
	2
);

/**
 * Same six social networks, same styling, as the row in parts/footer.html —
 * rendered here via `render_block()` on a hand-built parsed-block array
 * (rather than duplicating parts/footer.html's markup by hand) so both
 * places stay in sync with whatever core/social-links and core/social-link
 * output for these attributes.
 *
 * @return string Rendered `<ul class="wp-block-social-links …">` markup.
 */
function agentic_nav_drawer_social_links() {
	$services     = [ 'facebook', 'x', 'instagram', 'tiktok', 'pinterest', 'youtube' ];
	$inner_blocks = [];
	foreach ( $services as $service ) {
		$inner_blocks[] = [
			'blockName'    => 'core/social-link',
			'attrs'        => [
				'url'     => '#',
				'service' => $service,
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	// core/social-links is a static block (no render_callback of its own),
	// so render_block() on it renders only the inner core/social-link items
	// — each one correctly picking up the iconColor/iconColorValue context
	// declared here — and drops the block's own saved `<ul>` wrapper, since
	// that wrapper isn't reconstructible from an `innerContent` made only of
	// null placeholders. Add the same wrapper parts/footer.html's copy of
	// this block saves, by hand, around that inner-blocks output.
	$items = render_block(
		[
			'blockName'    => 'core/social-links',
			'attrs'        => [
				'iconColor'      => 'contrast',
				'iconColorValue' => '#1a1a1a',
				'className'      => 'is-style-logos-only',
				'size'           => 'has-small-icon-size',
			],
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
		]
	);

	return '<ul class="wp-block-social-links has-small-icon-size has-icon-color is-style-logos-only agentic-nav-drawer__social">' . $items . '</ul>';
}
