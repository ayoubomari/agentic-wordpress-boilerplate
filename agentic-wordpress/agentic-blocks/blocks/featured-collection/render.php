<?php
/**
 * Featured collection — a product grid, optionally tabbed.
 *
 * Reads through WooCommerce's own API (wc_get_products) rather than
 * querying posts directly, so pricing, stock status, and add-to-cart
 * behaviour stay correct without reimplementing any of it.
 *
 * Tabs are CSS-only (radio-input + `~` sibling selectors) — no JS needed,
 * unlike the carousels elsewhere in this block library, because a plain
 * "show the panel whose radio is checked" toggle doesn't need any.
 *
 * @var array $attributes
 */
if ( ! function_exists( 'wc_get_products' ) ) {
	return; // WooCommerce inactive — render nothing rather than fataling.
}

$heading  = $attributes['heading'] ?? '';
$columns  = max( 1, min( 6, (int) ( $attributes['columns'] ?? 4 ) ) );
$count    = max( 1, min( 24, (int) ( $attributes['count'] ?? 4 ) ) );
$orderby  = in_array( $attributes['orderby'] ?? 'date', [ 'date', 'price', 'popularity', 'rating', 'title' ], true )
	? $attributes['orderby']
	: 'date';
$cta_text = $attributes['ctaText'] ?? '';
$cta_url  = $attributes['ctaUrl'] ?? '';
$tabs     = ! empty( $attributes['tabs'] ) && is_array( $attributes['tabs'] ) ? $attributes['tabs'] : null;

/**
 * Query products for one panel (either the single non-tabbed grid, or one
 * tab). $overrides can set 'category', 'maxPrice', and/or 'onSale' per tab.
 *
 * maxPrice/onSale are filtered in PHP, not via the query: wc_get_products()
 * silently *drops* a raw `meta_query` argument
 * (WC_Data_Store_WP::get_wp_query_args() explicitly skips that key), and its
 * own meta-backed args only build exact/IN comparisons — there is no range
 * operator exposed for price, and no "on sale" query var at all. When either
 * filter is active we over-fetch (6x the requested count, capped) so a mixed
 * catalog still likely has enough matches after filtering, then slice down
 * to the requested count.
 */
$query_products = static function ( $overrides ) use ( $attributes, $count, $orderby ) {
	$category  = trim( (string) ( $overrides['category'] ?? $attributes['category'] ?? '' ) );
	$max_price = isset( $overrides['maxPrice'] ) ? (float) $overrides['maxPrice'] : (float) ( $attributes['maxPrice'] ?? 0 );
	$on_sale   = ! empty( $overrides['onSale'] );
	$needs_php_filter = $max_price > 0 || $on_sale;

	$query_args = [
		'status'  => 'publish',
		'limit'   => $needs_php_filter ? min( 100, $count * 6 ) : $count,
		'orderby' => $orderby,
		'order'   => 'title' === $orderby ? 'ASC' : 'DESC',
	];
	if ( '' !== $category ) {
		$query_args['category'] = array_map( 'trim', explode( ',', $category ) );
	}

	$products = wc_get_products( $query_args );

	if ( $on_sale ) {
		$products = array_values(
			array_filter(
				$products,
				static function ( $product ) {
					return $product->is_on_sale();
				}
			)
		);
	}

	if ( $max_price > 0 ) {
		$products = array_values(
			array_filter(
				$products,
				static function ( $product ) use ( $max_price ) {
					return (float) $product->get_price() <= $max_price;
				}
			)
		);
	}

	if ( $needs_php_filter ) {
		$products = array_slice( $products, 0, $count );
	}

	return $products;
};

/**
 * Render one product grid (<ul>) for a given product list.
 */
$render_grid = static function ( $products, $columns ) {
	?>
	<ul class="agentic-featured-collection__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $products as $product ) : ?>
			<li class="agentic-product-card agentic-reveal-item">
				<a class="agentic-product-card__link" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
					<span class="agentic-product-card__media">
						<?php
						// Shared with agentic/product-badge — see agentic_product_badge_markup().
						echo agentic_product_badge_markup( $product );

						$image = $product->get_image( 'woocommerce_thumbnail' );
						// Escaped by WooCommerce; wp_kses_post keeps srcset/sizes intact.
						echo wp_kses_post( $image );

						// Second (gallery) photo, if the product has one — crossfaded
						// in on hover/focus via CSS alone, see style.css. Products
						// without a gallery image just keep the single static photo.
						$gallery_ids = $product->get_gallery_image_ids();
						if ( ! empty( $gallery_ids ) ) {
							echo wp_kses_post(
								wp_get_attachment_image(
									$gallery_ids[0],
									'woocommerce_thumbnail',
									false,
									[
										'class' => 'agentic-product-card__media-hover',
										'alt'   => '',
									]
								)
							);
						}
						?>
						<span class="agentic-product-card__quick-add">
							<?php echo $product->is_type( 'variable' ) ? esc_html__( 'Choose Options', 'agentic' ) : esc_html__( 'Add To Cart', 'agentic' ); ?>
						</span>
					</span>
					<?php
					// Skip the "Uncategorized" default term — showing that label
					// on every card looks like a bug, not a feature, on a fresh
					// clone before the owner has set up real categories.
					$terms          = get_the_terms( $product->get_id(), 'product_cat' );
					$first_term     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
					$category_name  = ( $first_term && 'uncategorized' !== $first_term->slug ) ? $first_term->name : '';
					?>
					<?php if ( $category_name ) : ?>
						<span class="agentic-product-card__category"><?php echo esc_html( $category_name ); ?></span>
					<?php endif; ?>
					<span class="agentic-product-card__title"><?php echo esc_html( $product->get_name() ); ?></span>
				</a>
				<span class="agentic-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
};

if ( $tabs ) {
	// Resolve products for every tab up front so an empty tab can be
	// skipped entirely rather than rendering an empty panel.
	$resolved_tabs = [];
	foreach ( $tabs as $tab ) {
		$products = $query_products( $tab );
		if ( ! empty( $products ) ) {
			$resolved_tabs[] = [
				'label'    => $tab['label'] ?? '',
				'products' => $products,
			];
		}
	}
	if ( empty( $resolved_tabs ) ) {
		return;
	}
	$radio_name = wp_unique_id( 'agentic-fc-tabs-' );
	?>
	<section <?php echo agentic_section_classes( 'featured-collection' ); ?>>
		<div class="agentic-featured-collection__tabs">
			<?php foreach ( $resolved_tabs as $i => $tab ) : ?>
				<input
					type="radio"
					class="agentic-featured-collection__tab-radio"
					name="<?php echo esc_attr( $radio_name ); ?>"
					id="<?php echo esc_attr( $radio_name . '-' . $i ); ?>"
					<?php checked( 0 === $i ); ?>
				/>
			<?php endforeach; ?>

			<div class="agentic-featured-collection__tabs-row">
				<div class="agentic-featured-collection__tab-labels">
					<?php foreach ( $resolved_tabs as $i => $tab ) : ?>
						<label class="agentic-featured-collection__tab-label" for="<?php echo esc_attr( $radio_name . '-' . $i ); ?>">
							<?php echo esc_html( $tab['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<?php if ( $cta_text && $cta_url ) : ?>
					<a class="agentic-featured-collection__cta" href="<?php echo esc_url( $cta_url ); ?>">
						<?php echo esc_html( $cta_text ); ?>
					</a>
				<?php endif; ?>
			</div>

			<div class="agentic-featured-collection__tab-panels">
				<?php foreach ( $resolved_tabs as $tab ) : ?>
					<div class="agentic-featured-collection__tab-panel">
						<?php $render_grid( $tab['products'], $columns ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	return;
}

$products = $query_products( [] );
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

	<?php $render_grid( $products, $columns ); ?>
</section>
