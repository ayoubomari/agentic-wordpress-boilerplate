<?php
/**
 * Plugin Name: Agentic Blocks
 * Description: Custom minimal Gutenberg blocks — the "sections" layer of this boilerplate.
 * Version: 0.2.0
 */

defined( 'ABSPATH' ) || exit;

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
