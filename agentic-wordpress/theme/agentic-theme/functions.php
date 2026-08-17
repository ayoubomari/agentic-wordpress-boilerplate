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
 *
 * `is_product()` is in that list for the "You may also like" grid at the
 * bottom of single-product.html: it is a woocommerce/product-collection,
 * i.e. the exact same `.wc-block-product-template` card markup the archives
 * use, so it needs the same card CSS. Without it the cards render unstyled
 * *and* the second gallery photo — which the
 * render_block_woocommerce/product-image filter below injects on every
 * product loop, archive or not — has nothing to absolutely-position and
 * hide it, so it stacks under the primary photo instead of crossfading in
 * on hover. Leaving it out is what caused exactly that bug once.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_cart' ) ) {
			return; // WooCommerce inactive.
		}

		if ( is_cart() || is_checkout() || is_account_page() || is_shop() || is_product_taxonomy() || is_product() ) {
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
 *  - the About Us and Contact pages (templates/page-about-us.html,
 *    templates/page-contact.html): the same stacked-section layout as the
 *    front page, just a different set of blocks
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
		if ( ! is_front_page() && ! $is_shop_page && ! is_page( 'about-us' ) && ! is_page( 'contact' ) && ! $is_journal ) {
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

/**
 * Structured data: one BreadcrumbList per URL, not two.
 *
 * WooCommerce emits its own JSON-LD `BreadcrumbList` on the shop archive,
 * every product category, and every product page — independently of whatever
 * SEO plugin is installed, which emits one too. Both land on the same URL, and
 * they do not necessarily agree: on this store, under Yoast, one said
 * "Home > Shop > Gentle Gel Cleanser" while WooCommerce said
 * "Home > Cleansers > Gentle Gel Cleanser". Two contradictory trails for one
 * page is worse than a plain duplicate — it hands a crawler a conflict to
 * resolve rather than a fact.
 *
 * The SEO plugin's copy is the one kept, for a structural reason rather than a
 * preference: it lives inside that plugin's own graph and is referenced by its
 * `WebPage` node's `breadcrumb` property. Dropping it would leave a dangling
 * reference, whereas WooCommerce's is a standalone node nothing else points at,
 * so removing it costs nothing.
 *
 * Guarded on an SEO plugin actually being active, so removing the plugin
 * doesn't silently leave the store with no breadcrumb schema at all. The list
 * covers the plugins this boilerplate is realistically cloned with; a store
 * running something else keeps both, which is the safe direction to fail.
 */
add_filter(
	'woocommerce_structured_data_breadcrumblist',
	function ( $markup ) {
		$seo_plugin_active = defined( 'WPSEO_VERSION' )                 // Yoast SEO
			|| defined( 'THE_SEO_FRAMEWORK_VERSION' )                   // The SEO Framework
			|| defined( 'SEOPRESS_VERSION' )                            // SEOPress
			|| defined( 'RANK_MATH_VERSION' );                          // Rank Math

		return $seo_plugin_active ? [] : $markup;
	}
);

/**
 * Put the product's category back into the breadcrumb trail.
 *
 * Consequence of the de-duplication above: WooCommerce's BreadcrumbList was
 * the category-aware one ("Home > Cleansers > Gentle Gel Cleanser"), and
 * dropping it left The SEO Framework's flatter trail
 * ("Home > Shop > Gentle Gel Cleanser") as the only one. Removing the
 * duplicate should not have cost the store a level of taxonomy detail, so it
 * is added back here — one trail, and the more useful one.
 *
 * Term selection deliberately mirrors WooCommerce's own
 * `WC_Breadcrumb::add_crumbs_single()`: order by `parent DESC` and take the
 * first, which yields the deepest assigned category rather than an arbitrary
 * one, then walk its ancestors so a nested category renders its full path.
 * Matching Woo's rule means the schema trail agrees with the breadcrumb
 * WooCommerce would render on the page, instead of inventing a second opinion.
 *
 * @param array[] $list Breadcrumb items, each `[ 'url' => string, 'name' => string ]`.
 * @return array[]
 */
add_filter(
	'the_seo_framework_breadcrumb_list',
	function ( $list ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || count( $list ) < 2 ) {
			return $list;
		}

		$terms = wc_get_product_terms(
			get_queried_object_id(),
			'product_cat',
			[
				'orderby' => 'parent',
				'order'   => 'DESC',
			]
		);
		if ( empty( $terms ) || ! $terms[0] instanceof WP_Term ) {
			return $list;
		}

		// Ancestors come back deepest-first; the trail needs them outermost-first.
		$crumb_terms = array_reverse( get_ancestors( $terms[0]->term_id, 'product_cat' ) );
		$crumb_terms[] = $terms[0]->term_id;

		$category_crumbs = [];
		foreach ( $crumb_terms as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$category_crumbs[] = [
				'url'  => $link,
				'name' => $term->name,
			];
		}

		if ( ! $category_crumbs ) {
			return $list;
		}

		// Splice in ahead of the product itself, which is always the last crumb.
		$product_crumb = array_pop( $list );
		return array_merge( $list, $category_crumbs, [ $product_crumb ] );
	}
);

/**
 * Product and post categories in the XML sitemap.
 *
 * The SEO Framework's free sitemap covers post types only — taxonomy archives
 * are simply absent from it. For a blog that is a defensible default; for a
 * store it is not, because `/product-category/*` archives are real landing
 * pages (they are indexable, they carry their own meta description from the
 * term description, and they are what a category link in the nav points at).
 * Yoast free did include taxonomy sitemaps, so this is the one capability that
 * had to be re-added in code when the boilerplate moved to TSF.
 *
 * `category` is listed for the same reason: the Journal's own category archives
 * are indexable pages TSF leaves out of the sitemap, which left the store with
 * indexable URLs a crawler could only reach by following links.
 *
 * Only terms that actually have posts are listed: TSF noindexes an empty
 * archive by itself ("No posts are attached to this term"), so submitting one
 * would be asking a crawler to spend budget fetching a page that tells it not
 * to index the page. Terms hidden from the catalogue (WooCommerce's per-term
 * display setting) are left out for the same reason.
 *
 * @param array $urls Custom URLs keyed by absolute URL, each `[ 'lastmod' => string ]`.
 * @return array
 */
add_filter(
	'the_seo_framework_sitemap_additional_urls',
	function ( $urls ) {
		foreach ( [ 'product_cat', 'category' ] as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				]
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$urls[ $link ] = [ 'lastmod' => '' ];
			}
		}

		return $urls;
	}
);

/**
 * One Organization entity across the page, not two.
 *
 * The SEO Framework describes the store once, as the `WebSite`'s publisher.
 * WooCommerce describes it again, independently, as `offers.seller` on the
 * Product node — same name, same URL, in a separate `<script>` with no `@id`
 * on it. Nothing errors, but a crawler is handed two unlinked Organization
 * nodes for one real-world entity and has to guess they are the same store.
 *
 * Giving WooCommerce's seller the `@id` the SEO plugin already minted merges
 * them into one entity. The value comes from TSF's own public entity API
 * rather than from a reconstructed URL fragment (that fragment is an internal
 * format, and guessing it would break silently the day it changes) and rather
 * than from capturing TSF's `the_seo_framework_schema_graph_data` filter:
 * despite TSF's script appearing first in the HTML, WooCommerce generates its
 * product data *before* TSF builds its graph, so a capture-then-reuse approach
 * reads an empty value every time. Verified — that ordering is the opposite of
 * what the markup order suggests.
 *
 * Guarded on the class existing, so swapping the SEO plugin leaves today's
 * two-node output rather than a fatal.
 */
add_filter(
	'woocommerce_structured_data_product',
	function ( $markup ) {
		if ( ! class_exists( '\The_SEO_Framework\Meta\Schema\Entities\Organization' ) ) {
			return $markup;
		}
		if ( empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) ) {
			return $markup;
		}

		$org_id = \The_SEO_Framework\Meta\Schema\Entities\Organization::get_id();
		if ( ! $org_id ) {
			return $markup;
		}

		foreach ( $markup['offers'] as &$offer ) {
			if ( isset( $offer['seller'] ) && is_array( $offer['seller'] ) ) {
				$offer['seller']['@id'] = $org_id;
			}
		}
		unset( $offer );

		return $markup;
	}
);

/**
 * AI-crawler access: robots.txt rules + a dynamic /llms.txt.
 *
 * "Agentic commerce" — ChatGPT/Claude/Perplexity-style agents browsing and
 * citing a store on a shopper's behalf — reads two files neither WooCommerce
 * nor The SEO Framework produces: explicit allow/disallow rules for AI
 * user-agents (distinct from search-engine crawlers, and not all of them
 * respect a page's noindex meta as reliably), and llms.txt, an emerging
 * convention (https://llmstxt.org/) for a short, structured "here's what
 * this site is and how to read it" file separate from a human-facing
 * sitemap. Neither is core WordPress or WooCommerce territory, so — same
 * call as the breadcrumb/sitemap gaps above — it's closed here in code
 * rather than by adding a plugin for two small files.
 *
 * Priority 20: TSF's own `robots_txt` callback (priority 10) *replaces*
 * $output outright rather than appending to it ("This method completely
 * hijacks default output" — inc/classes/robotstxt/main.class.php), so this
 * has to run after it to add to TSF's version instead of being overwritten
 * by it.
 *
 * Guarded on $public (WordPress's own "discourage search engines" setting,
 * Settings → Reading): a site that has asked not to be indexed shouldn't be
 * inviting AI crawlers either.
 */
add_filter(
	'robots_txt',
	function ( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$ai_user_agents = [
			'GPTBot',        // OpenAI — training/browsing crawler
			'OAI-SearchBot', // OpenAI — ChatGPT search
			'ChatGPT-User',  // OpenAI — live "browse" actions inside a chat
			'ClaudeBot',     // Anthropic — training/browsing crawler
			'anthropic-ai',  // Anthropic — Claude's own fetches
			'PerplexityBot', // Perplexity — search/answer crawler
			'Google-Extended', // Google — Gemini/AI Overviews training signal
		];

		$output .= "\n# AI assistants / shopping agents — catalog is open, checkout is not.\n";
		foreach ( $ai_user_agents as $agent ) {
			$output .= "User-agent: {$agent}\n";
			$output .= "Allow: /\n";
			$output .= "Disallow: /cart/\n";
			$output .= "Disallow: /checkout/\n";
			$output .= "Disallow: /my-account/\n";
		}
		$output .= "\n# Structured summary of this store for AI agents:\n";
		$output .= '# ' . home_url( '/llms.txt' ) . "\n";

		return $output;
	},
	20,
	2
);

/**
 * /llms.txt — generated fresh on every request from live WooCommerce data
 * (categories, page URLs), not written once by setup-site.sh and left to go
 * stale. Add a product category next month and it appears here next month
 * too, with no regeneration step to remember. Deliberately does NOT
 * enumerate every product the way the XML sitemap does — llmstxt.org's own
 * guidance is a concise, curated index, not an exhaustive dump — so
 * per-product data is left to the two machine-readable links at the bottom
 * (sitemap + WooCommerce's public Store API) instead.
 */
if ( ! function_exists( 'agentic_generate_llms_txt' ) ) {
	function agentic_generate_llms_txt() {
		$site    = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$tagline = wp_strip_all_tags( get_bloginfo( 'description' ) );

		$lines   = [];
		$lines[] = '# ' . $site;
		$lines[] = '';
		if ( $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}
		$lines[] = 'A WooCommerce store. This file is a structured summary for AI assistants and shopping agents, per the llms.txt convention (https://llmstxt.org/) — browse and cite what is listed below; cart, checkout, and account pages are intentionally excluded (see robots.txt) and offer no purchasable action to an automated agent.';
		$lines[] = '';

		$lines[] = '## Store';
		$shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		if ( $shop_page_id > 0 ) {
			$lines[] = '- [Shop](' . get_permalink( $shop_page_id ) . '): Full product catalog.';
		}
		$journal_page_id = (int) get_option( 'page_for_posts' );
		if ( $journal_page_id > 0 ) {
			$lines[] = '- [Journal](' . get_permalink( $journal_page_id ) . '): Articles and guides.';
		}
		$lines[] = '';

		$categories = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			]
		);
		if ( ! is_wp_error( $categories ) && $categories ) {
			$lines[] = '## Product categories';
			foreach ( $categories as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$description = wp_strip_all_tags( $term->description );
				$lines[]      = '- [' . $term->name . '](' . $link . ')' . ( $description ? ': ' . $description : '' );
			}
			$lines[] = '';
		}

		$lines[] = '## Machine-readable data';
		$lines[] = '- [XML sitemap](' . home_url( '/sitemap.xml' ) . '): every product, category, and post.';
		$lines[] = '- [Live product catalog, JSON](' . home_url( '/wp-json/wc/store/v1/products' ) . '): WooCommerce\'s public Store API — current prices, stock, and images, no auth required.';

		return implode( "\n", $lines ) . "\n";
	}
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^llms\.txt$', 'index.php?agentic_llms_txt=1', 'top' );
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'agentic_llms_txt';
		return $vars;
	}
);

// Without this, WordPress's canonical redirect 301s /llms.txt to /llms.txt/
// before template_redirect ever runs — core exempts its own /robots.txt and
// /favicon.ico from that redirect the same way, this just extends the same
// exemption to this endpoint.
add_filter(
	'redirect_canonical',
	function ( $redirect_url ) {
		return get_query_var( 'agentic_llms_txt' ) ? false : $redirect_url;
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'agentic_llms_txt' ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo agentic_generate_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput -- plain text, not HTML.
		exit;
	}
);

/**
 * /product-feed.xml — a Google Merchant Center product feed (RSS 2.0 +
 * the `g:` namespace), the format Google's own documentation says AI Mode,
 * AI Overviews, and the Gemini app all ground their shopping answers in —
 * the actual mechanism for "products showing up in an AI chat," as opposed
 * to /llms.txt above, which real-world crawl data shows AI bots mostly
 * don't even fetch. Unlike OpenAI's ChatGPT product-discovery feed or
 * Perplexity's Merchant Program, Merchant Center needs no checkout
 * integration and isn't part of any beta agentic-commerce protocol — it
 * predates all of that by over a decade — so it's the one piece of that
 * research this boilerplate builds now. Registering the feed URL in a (free)
 * Merchant Center account is the owner's step, same as the REST keys and
 * Stripe/PayPal credentials elsewhere in this file's Owner-editable list.
 *
 * Generated live from real WooCommerce data on every request, same
 * reasoning as /llms.txt: no regeneration step to remember as products
 * change. Simple and variable products only — grouped/external products
 * aren't real purchasable line items in the same sense and are left out
 * rather than emitting a feed entry Merchant Center would reject anyway.
 *
 * Google requires EITHER a valid gtin OR an mpn+brand pair per item, and an
 * explicit `identifier_exists: no` when a product genuinely has none of
 * those — omitting the flag on an identifier-less item gets it disapproved
 * outright rather than merely down-ranked. `get_sku()` covers the mpn case
 * for every seeded sample product (see the SKU note in "SEO and structured
 * data" above); a real store's GTINs, once entered, are picked up
 * automatically since both read the same WooCommerce product data.
 */
if ( ! function_exists( 'agentic_product_feed_category_path' ) ) {
	function agentic_product_feed_category_path( $product_id ) {
		$terms = wc_get_product_terms(
			$product_id,
			'product_cat',
			[
				'orderby' => 'parent',
				'order'   => 'DESC',
			]
		);
		if ( empty( $terms ) || ! $terms[0] instanceof WP_Term ) {
			return '';
		}

		$ancestor_ids = array_reverse( get_ancestors( $terms[0]->term_id, 'product_cat' ) );
		$names        = [];
		foreach ( $ancestor_ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term instanceof WP_Term ) {
				$names[] = $term->name;
			}
		}
		$names[] = $terms[0]->name;

		return implode( ' > ', $names );
	}
}

if ( ! function_exists( 'agentic_product_feed_item_xml' ) ) {
	function agentic_product_feed_item_xml( $product, $parent_id = 0 ) {
		$currency = get_woocommerce_currency();
		$price    = $product->get_regular_price();
		if ( '' === $price ) {
			return '';
		}

		$gtin = $product->get_global_unique_id();
		$mpn  = $product->get_sku();

		$xml  = "\t<item>\n";
		$xml .= "\t\t<g:id>" . esc_xml( (string) $product->get_id() ) . "</g:id>\n";
		if ( $parent_id ) {
			$xml .= "\t\t<g:item_group_id>" . esc_xml( (string) $parent_id ) . "</g:item_group_id>\n";
		}
		$xml .= "\t\t<title>" . esc_xml( $product->get_name() ) . "</title>\n";
		$xml .= "\t\t<description>" . esc_xml( wp_strip_all_tags( $product->get_description() ? $product->get_description() : $product->get_short_description() ) ) . "</description>\n";
		$xml .= "\t\t<link>" . esc_url( get_permalink( $parent_id ? $parent_id : $product->get_id() ) ) . "</link>\n";

		$image_id = $product->get_image_id() ? $product->get_image_id() : ( $parent_id ? wc_get_product( $parent_id )->get_image_id() : 0 );
		if ( $image_id ) {
			$xml .= "\t\t<g:image_link>" . esc_url( wp_get_attachment_image_url( $image_id, 'full' ) ) . "</g:image_link>\n";
		}

		$xml .= "\t\t<g:availability>" . ( $product->is_in_stock() ? 'in stock' : 'out of stock' ) . "</g:availability>\n";
		$xml .= "\t\t<g:price>" . esc_xml( number_format( (float) $price, 2, '.', '' ) . ' ' . $currency ) . "</g:price>\n";
		if ( $product->is_on_sale() && '' !== $product->get_sale_price() ) {
			$xml .= "\t\t<g:sale_price>" . esc_xml( number_format( (float) $product->get_sale_price(), 2, '.', '' ) . ' ' . $currency ) . "</g:sale_price>\n";
		}
		$xml .= "\t\t<g:condition>new</g:condition>\n";
		$xml .= "\t\t<g:brand>" . esc_xml( wp_strip_all_tags( get_bloginfo( 'name' ) ) ) . "</g:brand>\n";

		if ( $gtin ) {
			$xml .= "\t\t<g:gtin>" . esc_xml( $gtin ) . "</g:gtin>\n";
		} elseif ( $mpn ) {
			$xml .= "\t\t<g:mpn>" . esc_xml( $mpn ) . "</g:mpn>\n";
		} else {
			$xml .= "\t\t<g:identifier_exists>no</g:identifier_exists>\n";
		}

		$category_path = agentic_product_feed_category_path( $parent_id ? $parent_id : $product->get_id() );
		if ( $category_path ) {
			$xml .= "\t\t<g:product_type>" . esc_xml( $category_path ) . "</g:product_type>\n";
		}

		$xml .= "\t</item>\n";
		return $xml;
	}
}

if ( ! function_exists( 'agentic_generate_product_feed_xml' ) ) {
	function agentic_generate_product_feed_xml() {
		$products = wc_get_products(
			[
				'status' => 'publish',
				'type'   => [ 'simple', 'variable' ],
				'limit'  => -1,
			]
		);

		$items = '';
		foreach ( $products as $product ) {
			if ( ! $product->is_visible() ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation && $variation->is_purchasable() ) {
						$items .= agentic_product_feed_item_xml( $variation, $product->get_id() );
					}
				}
				continue;
			}

			$items .= agentic_product_feed_item_xml( $product );
		}

		$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\n";
		$xml .= "<channel>\n";
		$xml .= '<title>' . esc_xml( wp_strip_all_tags( get_bloginfo( 'name' ) ) ) . "</title>\n";
		$xml .= '<link>' . esc_url( home_url( '/' ) ) . "</link>\n";
		$xml .= '<description>' . esc_xml( wp_strip_all_tags( get_bloginfo( 'description' ) ) ) . "</description>\n";
		$xml .= $items;
		$xml .= "</channel>\n</rss>\n";

		return $xml;
	}
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^product-feed\.xml$', 'index.php?agentic_product_feed=1', 'top' );
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'agentic_product_feed';
		return $vars;
	}
);

add_filter(
	'redirect_canonical',
	function ( $redirect_url ) {
		return get_query_var( 'agentic_product_feed' ) ? false : $redirect_url;
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'agentic_product_feed' ) ) {
			return;
		}
		header( 'Content-Type: application/xml; charset=utf-8' );
		echo agentic_generate_product_feed_xml(); // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped via esc_xml()/esc_url() per field above.
		exit;
	}
);
