<?php
/**
 * Product subcategories — row of photo tiles linking to the current
 * category's child categories, or (on the generic shop archive) the
 * store's top-level categories.
 *
 * Fully dynamic, not attribute-driven: reads the current archive from
 * `get_queried_object()`, the same way core's `wp:term-description` does in
 * these templates. There is nothing to configure per template — it renders
 * whichever categories actually apply, and nothing at all when there are
 * none (a leaf category, an empty store, or a non-category context such as
 * a product tag archive), rather than inventing placeholder tiles.
 *
 * Tile photos come from each category's own "Thumbnail" image, set the same
 * way as any other WooCommerce category field (Products → Categories in
 * wp-admin) — owner-editable content, not layout. When no thumbnail has been
 * set yet, falls back to a real photo from a product already inside that
 * category rather than an invented placeholder image; a category with no
 * thumbnail and no products yet renders a plain colour tile instead of a
 * broken/empty image.
 *
 * This section always renders at the very top of both the shop and every
 * category archive (see the templates), so its first tile is consistently
 * the page's Largest Contentful Paint element — measured on the shop page,
 * where a blanket `loading="lazy"` on every tile made the browser wait to
 * even discover that image, tanking LCP the same way single.html's
 * post-featured-image once did (see the render_block_core/post-featured-
 * image filter in functions.php). Only the first tile skips lazy-loading;
 * the rest are genuinely off past-the-fold on narrower viewports and stay
 * lazy. For the same reason the first tile also skips `agentic-reveal-item`
 * (see assets/js/scroll-reveal.js) — it's on screen immediately, same as
 * the hero banner, so animating it in would just delay the LCP paint it was
 * just exempted from lazy-loading to protect. Only tiles 2+ stagger in.
 *
 * @var array $attributes
 */
if ( ! function_exists( 'is_product_category' ) ) {
	return;
}

if ( is_product_category() && get_queried_object() instanceof WP_Term ) {
	$parent_id = get_queried_object()->term_id;
} elseif ( is_shop() ) {
	$parent_id = 0;
} else {
	return;
}

$args = [
	'taxonomy'   => 'product_cat',
	'parent'     => $parent_id,
	'hide_empty' => false,
];

if ( 0 === $parent_id ) {
	// Top-level shop archive: skip the default "Uncategorized" bucket,
	// same reasoning WooCommerce's own category widgets use.
	$args['exclude'] = [ (int) get_option( 'default_product_cat', 0 ) ];
}

$terms = get_terms( $args );

if ( is_wp_error( $terms ) || empty( $terms ) ) {
	return;
}

$columns = max( 2, min( 6, (int) ( $attributes['columns'] ?? 5 ) ) );
?>
<section <?php echo agentic_section_classes( 'product-subcategories' ); ?>>
	<ul class="agentic-product-subcategories__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $terms as $index => $term ) : ?>
			<?php
			$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
			$image        = $thumbnail_id ? wp_get_attachment_image_url( (int) $thumbnail_id, 'medium_large' ) : '';

			if ( ! $image ) {
				$category_product_ids = get_posts(
					[
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 5,
						'fields'         => 'ids',
						'tax_query'      => [
							[
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $term->term_id,
							],
						],
					]
				);
				foreach ( $category_product_ids as $product_id ) {
					$product_image = get_the_post_thumbnail_url( $product_id, 'medium_large' );
					if ( $product_image ) {
						$image = $product_image;
						break;
					}
				}
			}

			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			?>
			<li class="agentic-product-subcategories__item<?php echo 0 === $index ? '' : ' agentic-reveal-item'; ?>">
				<a class="agentic-product-subcategories__link<?php echo $image ? '' : ' agentic-product-subcategories__link--no-image'; ?>" href="<?php echo esc_url( $link ); ?>">
					<?php if ( $image ) : ?>
						<span class="agentic-product-subcategories__media">
							<?php if ( 0 === $index ) : ?>
								<img src="<?php echo esc_url( $image ); ?>" alt="" fetchpriority="high" decoding="async" />
							<?php else : ?>
								<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
							<?php endif; ?>
						</span>
					<?php endif; ?>
					<span class="agentic-product-subcategories__title"><?php echo esc_html( $term->name ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
