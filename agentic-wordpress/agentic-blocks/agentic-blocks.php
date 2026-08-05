<?php
/**
 * Plugin Name: Agentic Blocks
 * Description: Custom minimal Gutenberg blocks — the "sections" layer of this boilerplate.
 * Version: 0.2.0
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/inc/form-entries.php';

/**
 * Group every custom block under one inserter category, so a store builder
 * (human or agent) sees the section library as a single set.
 */
add_filter(
	'block_categories_all',
	function ( $categories ) {
		array_unshift(
			$categories,
			[
				'slug'  => 'agentic',
				'title' => __( 'Agentic Sections', 'agentic' ),
				'icon'  => null,
			]
		);
		return $categories;
	}
);

/**
 * Shared carousel script, registered once as a plain handle (not a "file:"
 * path) so blocks that need it reference `"viewScript": "agentic-carousel"`
 * in block.json. If each block instead pointed viewScript at its own
 * "file:../../assets/carousel.js", WordPress would register a *different*
 * handle per block and the browser would load the identical file twice on
 * any page using both — registering the handle once here means it's loaded
 * at most once no matter how many of those blocks are present.
 */
add_action(
	'init',
	function () {
		wp_register_script(
			'agentic-carousel',
			plugins_url( 'assets/carousel.js', __FILE__ ),
			[],
			(string) filemtime( __DIR__ . '/assets/carousel.js' ),
			true
		);
	}
);

/**
 * Auto-register every block that has a blocks/<name>/block.json.
 *
 * Adding a section means adding a folder — no PHP changes, no registry to
 * update. See scripts/new-block.sh, which scaffolds one correctly.
 */
add_action(
	'init',
	function () {
		$blocks_dir = __DIR__ . '/blocks';
		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}
		foreach ( glob( $blocks_dir . '/*', GLOB_ONLYDIR ) as $block_path ) {
			if ( file_exists( $block_path . '/block.json' ) ) {
				register_block_type( $block_path );
			}
		}
	}
);

/**
 * Shared helpers for render.php files.
 *
 * Every block's render.php should escape through these rather than inventing
 * its own approach, so output handling stays consistent across the library.
 */
/**
 * Shared badge logic, used by both the agentic/product-badge block (for
 * query-loop product templates) and agentic/featured-collection (which
 * builds its own card markup rather than nesting blocks).
 *
 * Renders up to TWO badges side by side — a "status" badge (sold-out beats
 * sale% beats selling-fast, mutually exclusive) plus an independent "New"
 * badge that can co-occur with sale%/selling-fast, but never with sold-out.
 * "Selling fast" is a real low-stock signal (managed stock at/below the
 * product's low-stock threshold), not an invented one.
 *
 * @param WC_Product $product
 * @param array      $args { showSale, showNew, showSoldOut, showSellingFast, newDays, position }
 * @return string HTML, or '' if no badge applies.
 */
if ( ! function_exists( 'agentic_product_badge_markup' ) ) {
	function agentic_product_badge_markup( $product, $args = [] ) {
		if ( ! $product ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			[
				'showSale'        => true,
				'showNew'         => true,
				'showSoldOut'     => true,
				'showSellingFast' => true,
				'newDays'         => 14,
				'position'        => 'top-left',
			]
		);

		$badges = [];

		if ( $args['showSoldOut'] && ! $product->is_in_stock() ) {
			$badges[] = [ 'sold-out', __( 'Sold out', 'agentic' ) ];
		} else {
			if ( $args['showSale'] && $product->is_on_sale() ) {
				$regular = (float) $product->get_regular_price();
				$sale    = (float) $product->get_sale_price();
				$percent = $regular > 0 ? (int) round( ( $regular - $sale ) / $regular * 100 ) : 0;
				$badges[] = [
					'sale',
					$percent > 0
						/* translators: %d: percentage discount */
						? sprintf( __( '-%d%%', 'agentic' ), $percent )
						: __( 'Sale', 'agentic' ),
				];
			} elseif ( $args['showSellingFast'] && agentic_product_is_low_stock( $product ) ) {
				$badges[] = [ 'selling-fast', __( 'Selling fast!', 'agentic' ) ];
			}

			if ( $args['showNew'] ) {
				$created = $product->get_date_created();
				if ( $created && $created->getTimestamp() >= strtotime( '-' . max( 1, (int) $args['newDays'] ) . ' days' ) ) {
					$badges[] = [ 'new', __( 'New', 'agentic' ) ];
				}
			}
		}

		if ( ! $badges ) {
			return '';
		}

		$position = in_array( $args['position'], [ 'top-left', 'top-right' ], true ) ? $args['position'] : 'top-left';

		$html = '<span class="agentic-product-badge-group agentic-product-badge-group--' . esc_attr( $position ) . '">';
		foreach ( $badges as [ $type, $label ] ) {
			$html .= sprintf(
				'<span class="agentic-product-badge agentic-product-badge--%1$s">%2$s</span>',
				esc_attr( $type ),
				esc_html( $label )
			);
		}
		$html .= '</span>';

		return $html;
	}
}

/**
 * A real low-stock signal ("Selling fast!") rather than an invented one:
 * true only when the product manages stock, has a numeric quantity, and
 * that quantity is at or below the low-stock threshold (product-level or
 * the site-wide WooCommerce default).
 *
 * @param WC_Product $product
 * @return bool
 */
if ( ! function_exists( 'agentic_product_is_low_stock' ) ) {
	function agentic_product_is_low_stock( $product ) {
		if ( ! $product->managing_stock() || ! $product->is_in_stock() ) {
			return false;
		}

		$quantity = $product->get_stock_quantity();
		if ( null === $quantity ) {
			return false;
		}

		$threshold = $product->get_low_stock_amount();
		if ( '' === $threshold ) {
			$threshold = get_option( 'woocommerce_notify_low_stock_amount', 2 );
		}

		return $quantity <= (int) $threshold;
	}
}

if ( ! function_exists( 'agentic_section_classes' ) ) {
	/**
	 * Build the wrapper attributes for a section block.
	 *
	 * @param string $name       Block slug, e.g. "image-with-text".
	 * @param array  $extra      Extra classes.
	 * @return string
	 */
	function agentic_section_classes( $name, $extra = [] ) {
		$classes = array_merge( [ 'agentic-section', 'agentic-' . $name ], $extra );
		return get_block_wrapper_attributes( [ 'class' => implode( ' ', array_filter( $classes ) ) ] );
	}
}

/**
 * Loop-correction for looping carousels (hero-banner 2+ slides,
 * testimonials layout:"carousel" 2+ items), done synchronously and inline
 * instead of by the external assets/carousel.js.
 *
 * carousel.js clones the last slide before the first (and the first after
 * the last) so the track can scroll-snap "past" either end onto a
 * pixel-identical clone and loop infinitely, then instantly scrolls onto
 * the real first slide. That's cheap to do from the external script when
 * the page renders slowly enough for it to finish before first paint — but
 * on a fast-rendering page (render-blocking resources deferred, as this
 * theme now does) the browser paints the *pre*-correction layout first,
 * and the correction becomes a visible jump — a real, measured layout
 * shift, not a theoretical one.
 *
 * Called immediately after a `.agentic-carousel__track`'s closing tag, this
 * duplicates just that clone-and-scroll step as a plain synchronous inline
 * `<script>`, which blocks parsing/painting until it completes — so the
 * *first* frame the browser ever paints for that track is already the
 * corrected one, and there is nothing to hide or reveal. carousel.js
 * detects the `data-carousel-precorrected` flag this leaves behind and
 * skips redoing the same work (see its `precorrected` check).
 *
 * `inert` (plus the `aria-hidden` fallback for older assistive tech) keeps
 * the cloned slide's own CTA link out of tab order — matching what
 * carousel.js's own clone creation now also does.
 */
if ( ! function_exists( 'agentic_carousel_loop_precorrect' ) ) {
	function agentic_carousel_loop_precorrect() {
		echo '<script>(function(){'
			. 'var t=document.currentScript.previousElementSibling;'
			. 'if(!t||t.children.length<2){return;}'
			. 'var kids=t.children;'
			. 'var lead=kids[kids.length-1].cloneNode(true);'
			. 'var trail=kids[0].cloneNode(true);'
			. 'lead.setAttribute("aria-hidden","true");lead.setAttribute("inert","");'
			. 'trail.setAttribute("aria-hidden","true");trail.setAttribute("inert","");'
			. 't.insertBefore(lead,kids[0]);'
			. 't.appendChild(trail);'
			. 'var real=t.children[1];'
			. 't.scrollLeft+=real.getBoundingClientRect().left-t.getBoundingClientRect().left;'
			. 't.setAttribute("data-carousel-precorrected","1");'
			. '})();</script>';
	}
}

/**
 * Default hero-banner graphic — used only when a slide has no imageUrl, so
 * a colour-only slide never looks like a bare, unfinished panel. Deliberately
 * abstract (a few rounded smear shapes, not a depiction of any real
 * product) since there is no real photography to fall back to and CLAUDE.md
 * rules out inventing brand imagery — every fill references a theme.json
 * palette token, so it always matches whatever the current palette is.
 */
if ( ! function_exists( 'agentic_hero_banner_illustration' ) ) {
	function agentic_hero_banner_illustration() {
		?>
		<div class="agentic-hero-banner__illustration" aria-hidden="true">
			<svg viewBox="0 0 220 320" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="xMidYMid meet">
				<rect class="agentic-hero-banner__illustration-shape agentic-hero-banner__illustration-shape--1" x="6" y="70" width="34" height="170" rx="17" transform="rotate(-9 23 155)" />
				<rect class="agentic-hero-banner__illustration-shape agentic-hero-banner__illustration-shape--2" x="60" y="20" width="36" height="230" rx="18" transform="rotate(4 78 135)" />
				<rect class="agentic-hero-banner__illustration-shape agentic-hero-banner__illustration-shape--3" x="118" y="50" width="34" height="190" rx="17" transform="rotate(-5 135 145)" />
				<rect class="agentic-hero-banner__illustration-shape agentic-hero-banner__illustration-shape--4" x="172" y="90" width="34" height="150" rx="17" transform="rotate(7 189 165)" />
			</svg>
		</div>
		<?php
	}
}

/**
 * Inline payment-network badges for the footer. Simplified logo-style marks
 * (brand-colour chip + wordmark/glyph), not traced official artwork — close
 * enough to read at a glance as "we take these cards" without redistributing
 * exact trademarked logo files in the boilerplate. Swap for real brand SVGs
 * per-store if the owner has a licence to use the official artwork.
 */
if ( ! function_exists( 'agentic_payment_icon_svg' ) ) {
	function agentic_payment_icon_svg( $method ) {
		switch ( $method ) {
			case 'visa':
				return '<svg viewBox="0 0 40 24" width="38" height="24" role="img" aria-label="Visa"><rect width="40" height="24" rx="4" fill="#1A1F71" /><text x="20" y="16" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="700" font-style="italic" fill="#fff">VISA</text></svg>';
			case 'mastercard':
				return '<svg viewBox="0 0 40 24" width="38" height="24" role="img" aria-label="Mastercard"><rect width="40" height="24" rx="4" fill="#F4F4F4" /><circle cx="17" cy="12" r="7" fill="#EB001B" /><circle cx="23" cy="12" r="7" fill="#F79E1B" fill-opacity="0.9" /></svg>';
			case 'amex':
				return '<svg viewBox="0 0 40 24" width="38" height="24" role="img" aria-label="American Express"><rect width="40" height="24" rx="4" fill="#006FCF" /><text x="20" y="15.5" text-anchor="middle" font-family="Arial, sans-serif" font-size="8.5" font-weight="700" fill="#fff">AMEX</text></svg>';
			case 'paypal':
				return '<svg viewBox="0 0 40 24" width="38" height="24" role="img" aria-label="PayPal"><rect width="40" height="24" rx="4" fill="#F4F4F4" /><text x="20" y="16" text-anchor="middle" font-family="Arial, sans-serif" font-size="9.5" font-weight="700" font-style="italic" fill="#003087">Pay<tspan fill="#0070E0">Pal</tspan></text></svg>';
			default:
				return '';
		}
	}
}

/**
 * Inline review-platform marks for testimonial cards ("Verified on Google" /
 * "Verified on Trustpilot" chips). Same approach as agentic_payment_icon_svg()
 * above — simplified logo-style marks (brand-colour glyph + wordmark), not
 * traced official artwork. Swap for real brand SVGs per-store if the owner
 * has a licence to use the official artwork.
 */
if ( ! function_exists( 'agentic_review_platform_svg' ) ) {
	function agentic_review_platform_svg( $platform ) {
		switch ( $platform ) {
			case 'google':
				return '<svg viewBox="0 0 74 24" width="64" height="20" role="img" aria-label="Google"><text x="0" y="17" font-family="Arial, sans-serif" font-size="15" font-weight="700"><tspan fill="#4285F4">G</tspan><tspan fill="#EA4335">o</tspan><tspan fill="#FBBC05">o</tspan><tspan fill="#4285F4">g</tspan><tspan fill="#34A853">l</tspan><tspan fill="#EA4335">e</tspan></text></svg>';
			case 'trustpilot':
				return '<svg viewBox="0 0 96 24" width="80" height="20" role="img" aria-label="Trustpilot"><rect x="0" y="4" width="16" height="16" rx="3" fill="#00B67A" /><path d="M8 7.2l1.55 3.55L13.4 11l-2.7 2.45.75 3.85L8 15.3l-3.45 2 .75-3.85L2.6 11l3.85-.25L8 7.2z" fill="#fff" /><text x="20" y="17" font-family="Arial, sans-serif" font-size="12" font-weight="700" fill="#00B67A">Trustpilot</text></svg>';
			default:
				return '';
		}
	}
}

/**
 * Star-rating row (filled/empty SVG stars) for testimonial cards. Renders
 * only the real, declared rating on the item — never invents a number.
 */
if ( ! function_exists( 'agentic_star_rating_svg' ) ) {
	function agentic_star_rating_svg( $rating ) {
		$rating = max( 0, min( 5, (int) $rating ) );
		$star   = '<svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path d="M10 1.5l2.6 5.3 5.8.8-4.2 4.1 1 5.8L10 14.7l-5.2 2.8 1-5.8-4.2-4.1 5.8-.8L10 1.5z" fill="%s" /></svg>';
		$out    = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= sprintf( $star, $i <= $rating ? '#F5A623' : '#E2DDD6' );
		}
		return $out;
	}
}
