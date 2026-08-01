<?php
/**
 * Testimonials.
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

if ( empty( $items ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'testimonials' ); ?>>
	<?php if ( $heading ) : ?>
		<h2 class="agentic-testimonials__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<ul class="agentic-testimonials__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<?php if ( empty( $item['quote'] ) ) { continue; } ?>
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
</section>
