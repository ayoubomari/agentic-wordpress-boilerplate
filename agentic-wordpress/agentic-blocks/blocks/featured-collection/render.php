<?php
/**
 * Featured collection — a product grid.
 *
 * Reads through WooCommerce's own API (wc_get_products / wc_get_template_part)
 * rather than querying posts directly, so pricing, stock status, and
 * add-to-cart behaviour stay correct without reimplementing any of it.
 *
 * @var array $attributes
 */
if ( ! function_exists( 'wc_get_products' ) ) {
	return; // WooCommerce inactive — render nothing rather than fataling.
}

$heading  = $attributes['heading'] ?? '';
$category = trim( (string) ( $attributes['category'] ?? '' ) );
$count    = max( 1, min( 24, (int) ( $attributes['count'] ?? 4 ) ) );
$columns  = max( 1, min( 6, (int) ( $attributes['columns'] ?? 4 ) ) );
$orderby  = in_array( $attributes['orderby'] ?? 'date', [ 'date', 'price', 'popularity', 'rating', 'title' ], true )
	? $attributes['orderby']
	: 'date';
$cta_text = $attributes['ctaText'] ?? '';
$cta_url  = $attributes['ctaUrl'] ?? '';

$query_args = [
	'status'  => 'publish',
	'limit'   => $count,
	'orderby' => $orderby,
	'order'   => 'title' === $orderby ? 'ASC' : 'DESC',
];
if ( '' !== $category ) {
	$query_args['category'] = array_map( 'trim', explode( ',', $category ) );
}

$products = wc_get_products( $query_args );
if ( empty( $products ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'featured-collection' ); ?>>
	<?php if ( $heading || ( $cta_text && $cta_url ) ) : ?>
		<div class="agentic-featured-collection__header">
			<?php if ( $heading ) : ?>
				<h2 class="agentic-featured-collection__heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $cta_text && $cta_url ) : ?>
				<a class="agentic-featured-collection__cta" href="<?php echo esc_url( $cta_url ); ?>">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<ul class="agentic-featured-collection__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $products as $product ) : ?>
			<li class="agentic-product-card">
				<a class="agentic-product-card__link" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
					<span class="agentic-product-card__media">
						<?php
						// Shared with agentic/product-badge — see agentic_product_badge_markup().
						echo agentic_product_badge_markup( $product );

						$image = $product->get_image( 'woocommerce_thumbnail' );
						// Escaped by WooCommerce; wp_kses_post keeps srcset/sizes intact.
						echo wp_kses_post( $image );
						?>
					</span>
					<span class="agentic-product-card__title"><?php echo esc_html( $product->get_name() ); ?></span>
				</a>
				<span class="agentic-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
