<?php
/**
 * Testimonials — a scroll-snap slider of review cards (columns visible on
 * large screens, fewer as the viewport narrows), or (layout: "carousel")
 * large photo-testimonial cards with a product chip. Both layouts share the
 * agentic-carousel track/nav mechanics.
 *
 * A card with a reviewUrl becomes a whole-card link that opens the real
 * review in a new tab; without one it renders as a plain, non-interactive
 * card.
 *
 * Ships with placeholder quotes and reviewUrls (pointed at the platform's
 * homepage, not a fabricated review permalink) so the section renders
 * immediately. Replace both with real, attributable customer feedback and
 * each review's actual deep link before launch — do not publish invented
 * reviews.
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

wp_enqueue_script( 'agentic-carousel' );
?>
<section <?php echo agentic_section_classes( 'testimonials', [ 'agentic-carousel' ] ); ?> data-agentic-reveal-mode="stagger-css">
	<?php if ( $heading ) : ?>
		<h2 class="agentic-testimonials__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( 'carousel' === $layout ) : ?>
		<div class="agentic-carousel__track agentic-testimonials__track">
			<?php foreach ( $items as $item ) : ?>
				<figure class="agentic-testimonials__card agentic-carousel__slide"
					<?php echo ! empty( $item['photoUrl'] ) ? 'style="background-image:url(' . esc_url( $item['photoUrl'] ) . ')"' : ''; ?>>
					<?php if ( ! empty( $item['rating'] ) ) : ?>
						<div class="agentic-testimonials__stars agentic-testimonials__stars--card"><?php echo agentic_star_rating_svg( $item['rating'] ); ?></div>
					<?php endif; ?>

					<blockquote class="agentic-testimonials__card-quote">
						<p><?php echo esc_html( $item['quote'] ); ?></p>
					</blockquote>

					<?php if ( ! empty( $item['author'] ) ) : ?>
						<figcaption class="agentic-testimonials__card-author">
							<?php echo esc_html( $item['author'] ); ?>
							<?php if ( ! empty( $item['role'] ) ) : ?>
								<span class="agentic-testimonials__role"><?php echo esc_html( $item['role'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['platform'] ) && agentic_review_platform_svg( $item['platform'] ) ) : ?>
								<span class="agentic-testimonials__platform agentic-testimonials__platform--card">
									<?php echo agentic_review_platform_svg( $item['platform'] ); ?>
								</span>
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
		<?php agentic_carousel_loop_precorrect(); ?>

		<button type="button" class="agentic-testimonials__nav agentic-testimonials__nav--prev" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous review', 'agentic' ); ?>">‹</button>
		<button type="button" class="agentic-testimonials__nav agentic-testimonials__nav--next" data-carousel-next aria-label="<?php esc_attr_e( 'Next review', 'agentic' ); ?>">›</button>
	<?php else : ?>
		<div class="agentic-carousel__track agentic-testimonials__track--cards" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
			<?php
			foreach ( $items as $item ) :
				$has_link = ! empty( $item['reviewUrl'] );
				$tag      = $has_link ? 'a' : 'div';
				?>
				<<?php echo $tag; /* phpcs:ignore -- fixed 'a'|'div' only, not attacker input */ ?> class="agentic-testimonials__figure agentic-carousel__slide"
					<?php if ( $has_link ) : ?>
						href="<?php echo esc_url( $item['reviewUrl'] ); ?>" target="_blank" rel="noopener noreferrer"
					<?php endif; ?>>
					<div class="agentic-testimonials__head">
						<?php if ( ! empty( $item['photoUrl'] ) ) : ?>
							<img class="agentic-testimonials__avatar" src="<?php echo esc_url( $item['photoUrl'] ); ?>" alt="" loading="lazy" decoding="async" width="48" height="48" />
						<?php endif; ?>
						<?php if ( ! empty( $item['author'] ) ) : ?>
							<span class="agentic-testimonials__author"><?php echo esc_html( $item['author'] ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $item['rating'] ) ) : ?>
						<div class="agentic-testimonials__stars"><?php echo agentic_star_rating_svg( $item['rating'] ); ?></div>
					<?php endif; ?>

					<blockquote class="agentic-testimonials__quote">
						<p><?php echo esc_html( $item['quote'] ); ?></p>
					</blockquote>

					<?php if ( ! empty( $item['platform'] ) && agentic_review_platform_svg( $item['platform'] ) ) : ?>
						<div class="agentic-testimonials__platform"><?php echo agentic_review_platform_svg( $item['platform'] ); ?></div>
					<?php endif; ?>
				</<?php echo $tag; ?>>
			<?php endforeach; ?>
		</div>
		<?php agentic_carousel_loop_precorrect(); ?>

		<button type="button" class="agentic-testimonials__nav agentic-testimonials__nav--prev" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous review', 'agentic' ); ?>">‹</button>
		<button type="button" class="agentic-testimonials__nav agentic-testimonials__nav--next" data-carousel-next aria-label="<?php esc_attr_e( 'Next review', 'agentic' ); ?>">›</button>
	<?php endif; ?>
</section>
