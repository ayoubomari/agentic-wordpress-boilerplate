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
 * Shared "New" / "Sale" badge logic, used by both the agentic/product-badge
 * block (for query-loop product templates) and agentic/featured-collection
 * (which builds its own card markup rather than nesting blocks).
 *
 * @param WC_Product $product
 * @param array      $args { showSale, showNew, newDays, position }
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
				'showSale' => true,
				'showNew'  => true,
				'newDays'  => 14,
				'position' => 'top-left',
			]
		);

		$label = '';
		$type  = '';

		if ( $args['showSale'] && $product->is_on_sale() ) {
			$label = __( 'Sale', 'agentic' );
			$type  = 'sale';
		} elseif ( $args['showNew'] ) {
			$created = $product->get_date_created();
			if ( $created && $created->getTimestamp() >= strtotime( '-' . max( 1, (int) $args['newDays'] ) . ' days' ) ) {
				$label = __( 'New', 'agentic' );
				$type  = 'new';
			}
		}

		if ( ! $label ) {
			return '';
		}

		$position = in_array( $args['position'], [ 'top-left', 'top-right' ], true ) ? $args['position'] : 'top-left';

		return sprintf(
			'<span class="agentic-product-badge agentic-product-badge--%1$s agentic-product-badge--%2$s">%3$s</span>',
			esc_attr( $type ),
			esc_attr( $position ),
			esc_html( $label )
		);
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
