<?php
/**
 * Testimonials — a grid, or (layout: "carousel") large photo-testimonial
 * cards in the shared scroll-snap carousel, each with a small product chip.
 *
 * Ships with placeholder quotes so the section renders immediately. Replace
 * them with real, attributable customer feedback before launch — do not
 * publish invented reviews.
 *
 * @var array $attributes
 */
$heading = $attributes['heading'] ?? '';
$columns = max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$items   = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$layout  = 'carousel' === ( $attributes['layout'] ?? 'grid' ) ? 'carousel' : 'grid';

$items = array_values( array_filter( $items, static fn( $item ) => ! empty( $item['quote'] ) ) );
if ( empty( $items ) ) {
	return;
}

if ( 'carousel' === $layout ) {
	wp_enqueue_script( 'agentic-carousel' );
}
?>
<section <?php echo agentic_section_classes( 'testimonials', 'carousel' === $layout ? [ 'agentic-carousel' ] : [] ); ?>>
	<?php if ( $heading ) : ?>
		<h2 class="agentic-testimonials__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( 'carousel' === $layout ) : ?>
		<div class="agentic-carousel__track agentic-testimonials__track">
			<?php foreach ( $items as $item ) : ?>
				<figure class="agentic-testimonials__card agentic-carousel__slide"
					<?php echo ! empty( $item['photoUrl'] ) ? 'style="background-image:url(' . esc_url( $item['photoUrl'] ) . ')"' : ''; ?>>
					<blockquote class="agentic-testimonials__card-quote">
						<p><?php echo esc_html( $item['quote'] ); ?></p>
					</blockquote>

					<?php if ( ! empty( $item['author'] ) ) : ?>
						<figcaption class="agentic-testimonials__card-author">
							<?php echo esc_html( $item['author'] ); ?>
							<?php if ( ! empty( $item['role'] ) ) : ?>
								<span class="agentic-testimonials__role"><?php echo esc_html( $item['role'] ); ?></span>
							<?php endif; ?>
						</figcaption>
					<?php endif; ?>

					<?php if ( ! empty( $item['productName'] ) ) : ?>
						<div class="agentic-testimonials__product-chip">
							<?php if ( ! empty( $item['productImageUrl'] ) ) : ?>
								<img src="<?php echo esc_url( $item['productImageUrl'] ); ?>" alt="" loading="lazy" decoding="async" />
							<?php endif; ?>
							<span class="agentic-testimonials__product-info">
								<span class="agentic-testimonials__product-name"><?php echo esc_html( $item['productName'] ); ?></span>
								<?php if ( ! empty( $item['productPrice'] ) ) : ?>
									<span class="agentic-testimonials__product-price"><?php echo esc_html( $item['productPrice'] ); ?></span>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>

		<button type="button" class="agentic-testimonials__nav agentic-testimonials__nav--prev" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous review', 'agentic' ); ?>">‹</button>
		<button type="button" class="agentic-testimonials__nav agentic-testimonials__nav--next" data-carousel-next aria-label="<?php esc_attr_e( 'Next review', 'agentic' ); ?>">›</button>
	<?php else : ?>
		<ul class="agentic-testimonials__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<li class="agentic-testimonials__item">
					<figure class="agentic-testimonials__figure">
						<blockquote class="agentic-testimonials__quote">
							<p><?php echo esc_html( $item['quote'] ); ?></p>
						</blockquote>
						<?php if ( ! empty( $item['author'] ) ) : ?>
							<figcaption class="agentic-testimonials__author">
								<?php echo esc_html( $item['author'] ); ?>
								<?php if ( ! empty( $item['role'] ) ) : ?>
									<span class="agentic-testimonials__role"><?php echo esc_html( $item['role'] ); ?></span>
								<?php endif; ?>
							</figcaption>
						<?php endif; ?>
					</figure>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
