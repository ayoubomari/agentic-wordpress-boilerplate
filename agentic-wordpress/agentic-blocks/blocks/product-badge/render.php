<?php
/**
 * Product badge — "New" / "Sale" ribbon for a product card or single product.
 *
 * Reads the product from the surrounding block context (`postId`, provided
 * by the Query/Product Template loop) so it works unmodified whether it sits
 * inside a product grid or on single-product. Badge logic itself lives in
 * agentic_product_badge_markup() so featured-collection (which builds its
 * own card markup) can reuse the exact same rules.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */
if ( ! function_exists( 'wc_get_product' ) ) {
	return;
}

$product_id = $block->context['postId'] ?? get_the_ID();
$product    = $product_id ? wc_get_product( $product_id ) : null;

echo agentic_product_badge_markup(
	$product,
	[
		'showSale' => ! empty( $attributes['showSale'] ),
		'showNew'  => ! empty( $attributes['showNew'] ),
		'newDays'  => $attributes['newDays'] ?? 14,
		'position' => $attributes['position'] ?? 'top-left',
	]
);
