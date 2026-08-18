<?php
/**
 * Search drawer — header icon that opens a slide-out search panel.
 *
 * The search form is a real WordPress/WooCommerce search (GET to the home
 * URL with `s=...`), landing on templates/search.html — not a decorative
 * mock. "Popular products" pulls real, current products via wc_get_products
 * rather than inventing "most searched" analytics this store doesn't
 * actually track; "popular searches" is a curated, configurable list of
 * suggested terms, which is honest since it's presented as suggestions, not
 * a claimed metric.
 *
 * @var array $attributes
 */
$heading            = $attributes['heading'] ?? __( 'Search', 'agentic' );
$placeholder        = $attributes['placeholder'] ?? __( 'Search', 'agentic' );
$popular_searches    = is_array( $attributes['popularSearches'] ?? null ) ? $attributes['popularSearches'] : [];
$products_heading   = $attributes['popularProductsHeading'] ?? __( 'Popular products', 'agentic' );
$products_count     = max( 1, min( 12, (int) ( $attributes['popularProductsCount'] ?? 4 ) ) );

$products = [];
if ( function_exists( 'wc_get_products' ) ) {
	$products = wc_get_products(
		[
			'status'  => 'publish',
			'limit'   => $products_count,
			'orderby' => 'date',
			'order'   => 'DESC',
		]
	);
}

$panel_id = wp_unique_id( 'agentic-search-drawer-' );
?>
<div <?php echo agentic_section_classes( 'search-drawer' ); ?>>
	<button
		type="button"
		class="agentic-search-drawer__trigger"
		data-search-drawer-open
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $panel_id ); ?>"
		aria-label="<?php esc_attr_e( 'Open search', 'agentic' ); ?>"
	>
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
			<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6" />
			<line x1="16.3" y1="16.3" x2="21" y2="21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
		</svg>
	</button>

	<div class="agentic-search-drawer__overlay" data-search-drawer-close></div>

	<div
		id="<?php echo esc_attr( $panel_id ); ?>"
		class="agentic-search-drawer__panel"
		role="dialog"
		aria-modal="true"
		aria-label="<?php echo esc_attr( $heading ); ?>"
	>
		<div class="agentic-search-drawer__panel-header">
			<h2 class="agentic-search-drawer__heading"><?php echo esc_html( $heading ); ?></h2>
			<button type="button" class="agentic-search-drawer__close" data-search-drawer-close aria-label="<?php esc_attr_e( 'Close search', 'agentic' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<line x1="5" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
					<line x1="19" y1="5" x2="5" y2="19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
				</svg>
			</button>
		</div>

		<form class="agentic-search-drawer__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg class="agentic-search-drawer__form-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6" />
				<line x1="16.3" y1="16.3" x2="21" y2="21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
			</svg>
			<label class="screen-reader-text" for="<?php echo esc_attr( $panel_id . '-input' ); ?>"><?php esc_html_e( 'Search', 'agentic' ); ?></label>
			<input
				id="<?php echo esc_attr( $panel_id . '-input' ); ?>"
				type="search"
				name="s"
				class="agentic-search-drawer__input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				autocomplete="off"
				data-search-drawer-input
			/>
			<?php if ( function_exists( 'is_shop' ) ) : ?>
				<input type="hidden" name="post_type" value="product" />
			<?php endif; ?>
		</form>

		<?php if ( ! empty( $popular_searches ) ) : ?>
			<div class="agentic-search-drawer__section">
				<h3 class="agentic-search-drawer__section-heading"><?php esc_html_e( 'Popular searches', 'agentic' ); ?></h3>
				<p class="agentic-search-drawer__keywords">
					<?php foreach ( $popular_searches as $i => $keyword ) : ?>
						<?php if ( ! $keyword ) { continue; } ?>
						<?php if ( $i > 0 ) : ?>, <?php endif; ?>
						<a href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $keyword ), home_url( '/' ) ) ); ?>"><?php echo esc_html( $keyword ); ?></a>
					<?php endforeach; ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $products ) ) : ?>
			<div class="agentic-search-drawer__section">
				<h3 class="agentic-search-drawer__section-heading"><?php echo esc_html( $products_heading ); ?></h3>
				<ul class="agentic-search-drawer__products">
					<?php foreach ( $products as $product ) : ?>
						<li class="agentic-search-drawer__product">
							<a class="agentic-search-drawer__product-link" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
								<span class="agentic-search-drawer__product-media">
									<?php
									// 'woocommerce_gallery_thumbnail' (100px, cropped square) —
									// this list item renders at 56px, so the default
									// 'woocommerce_thumbnail' (300px) was ~89% wasted bytes on
									// every drawer open.
									echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) );
									?>
								</span>
								<span class="agentic-search-drawer__product-info">
									<span class="agentic-search-drawer__product-name"><?php echo esc_html( $product->get_name() ); ?></span>
									<span class="agentic-search-drawer__product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
