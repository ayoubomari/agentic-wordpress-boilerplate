<?php
/**
 * Shop the set — numbered product photo on the left, matching numbered
 * product list (real WooCommerce products via wc_get_product, so name/
 * image/price/sale state stay correct) on the right, plus a single
 * "Add all to cart" button.
 *
 * The button adds every listed product with one click via WooCommerce's
 * own wc-ajax=add_to_cart endpoint (the same one core's ajax add-to-cart
 * buttons use — no nonce, no custom REST route needed), then reloads the
 * page so the header mini-cart reflects the new contents.
 *
 * @var array $attributes
 */
if ( ! function_exists( 'wc_get_product' ) ) {
	return; // WooCommerce inactive — render nothing rather than fataling.
}

$eyebrow   = $attributes['eyebrow'] ?? '';
$heading   = $attributes['heading'] ?? '';
$image_url = $attributes['imageUrl'] ?? '';
$image_alt = $attributes['imageAlt'] ?? '';
$cta_text  = $attributes['ctaText'] ?? __( 'Add All To Cart', 'agentic' );
$items     = ! empty( $attributes['products'] ) && is_array( $attributes['products'] ) ? $attributes['products'] : [];

$rows = [];
foreach ( $items as $item ) {
	$product_id = (int) ( $item['productId'] ?? 0 );
	$product    = $product_id ? wc_get_product( $product_id ) : null;
	if ( ! $product ) {
		continue;
	}
	$rows[] = [
		'product'  => $product,
		'variant'  => $item['variant'] ?? '',
		'hotspotX' => max( 0, min( 100, (float) ( $item['hotspotX'] ?? 50 ) ) ),
		'hotspotY' => max( 0, min( 100, (float) ( $item['hotspotY'] ?? 50 ) ) ),
	];
}

if ( empty( $rows ) ) {
	return;
}

$product_ids = array_map(
	static function ( $row ) {
		return $row['product']->get_id();
	},
	$rows
);
$ajax_url    = class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '';
?>
<section <?php echo agentic_section_classes( 'shop-the-set' ); ?>>
	<div class="agentic-shop-the-set__media">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
		<?php else : ?>
			<span class="agentic-shop-the-set__placeholder" aria-hidden="true"></span>
		<?php endif; ?>

		<?php foreach ( $rows as $i => $row ) : ?>
			<?php
			/**
			 * hotspotX/Y are the pin's position as a percentage of the FULL,
			 * uncropped source photo — deliberately not tied to any one
			 * container size. The inline left/top below is only a pre-JS
			 * fallback (accurate as long as the fallback aspect-ratio in
			 * style.css stays close to the source photo's own ratio);
			 * view.js reads the raw data-hotspot-* values to recompute the
			 * true position against whatever object-fit:cover crop the
			 * current viewport actually produces, so pins stay correct at
			 * every screen size rather than only the one they were tuned
			 * against.
			 */
			?>
			<a
				class="agentic-shop-the-set__pin"
				href="<?php echo esc_url( get_permalink( $row['product']->get_id() ) ); ?>"
				data-shop-set-pin
				data-shop-set-index="<?php echo esc_attr( (string) $i ); ?>"
				data-hotspot-x="<?php echo esc_attr( $row['hotspotX'] ); ?>"
				data-hotspot-y="<?php echo esc_attr( $row['hotspotY'] ); ?>"
				style="left:<?php echo esc_attr( $row['hotspotX'] ); ?>%;top:<?php echo esc_attr( $row['hotspotY'] ); ?>%;"
				aria-label="<?php echo esc_attr( $row['product']->get_name() ); ?>"
			><?php echo esc_html( (string) ( $i + 1 ) ); ?></a>
		<?php endforeach; ?>
	</div>

	<div class="agentic-shop-the-set__content">
		<?php if ( $eyebrow ) : ?>
			<p class="agentic-shop-the-set__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>

		<?php if ( $heading ) : ?>
			<h2 class="agentic-shop-the-set__heading"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<ol class="agentic-shop-the-set__list">
			<?php foreach ( $rows as $i => $row ) : ?>
				<?php $product = $row['product']; ?>
				<li class="agentic-shop-the-set__row agentic-reveal-item" data-shop-set-row data-shop-set-index="<?php echo esc_attr( (string) $i ); ?>">
					<span class="agentic-shop-the-set__number" aria-hidden="true"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>

					<a class="agentic-shop-the-set__row-media" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
						<?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?>
					</a>

					<span class="agentic-shop-the-set__row-info">
						<a class="agentic-shop-the-set__row-title" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
							<?php echo esc_html( $product->get_name() ); ?>
						</a>
						<?php if ( ! empty( $row['variant'] ) ) : ?>
							<span class="agentic-shop-the-set__row-variant"><?php echo esc_html( $row['variant'] ); ?></span>
						<?php endif; ?>
					</span>

					<span class="agentic-shop-the-set__row-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<button
			type="button"
			class="agentic-shop-the-set__cta wp-element-button"
			data-shop-set-add-all
			data-ajax-url="<?php echo esc_url( $ajax_url ); ?>"
			data-product-ids="<?php echo esc_attr( implode( ',', $product_ids ) ); ?>"
			data-loading-text="<?php esc_attr_e( 'Adding…', 'agentic' ); ?>"
		>
			<?php echo esc_html( $cta_text ); ?>
		</button>
	</div>
</section>
