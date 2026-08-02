<?php
/**
 * CTA cards — a row of colored content-teaser cards.
 *
 * @var array $attributes
 */
$cards = is_array( $attributes['cards'] ?? null ) ? $attributes['cards'] : [];
$cards = array_values( array_filter( $cards, static fn( $card ) => ! empty( $card['heading'] ) ) );

if ( empty( $cards ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'cta-cards' ); ?> style="--agentic-columns:<?php echo esc_attr( count( $cards ) ); ?>">
	<?php foreach ( $cards as $card ) : ?>
		<?php
		$bg_color = trim( (string) ( $card['backgroundColor'] ?? 'subtle' ) );
		$bg_safe  = preg_replace( '/[^a-z0-9-]/', '', $bg_color );
		?>
		<div class="agentic-cta-cards__card" style="--agentic-cta-bg:var(--wp--preset--color--<?php echo esc_attr( $bg_safe ); ?>)">
			<?php if ( ! empty( $card['imageUrl'] ) ) : ?>
				<div class="agentic-cta-cards__media">
					<img src="<?php echo esc_url( $card['imageUrl'] ); ?>" alt="" loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>

			<h3 class="agentic-cta-cards__heading"><?php echo esc_html( $card['heading'] ); ?></h3>

			<?php if ( ! empty( $card['ctaText'] ) && ! empty( $card['ctaUrl'] ) ) : ?>
				<a class="agentic-cta-cards__cta wp-element-button" href="<?php echo esc_url( $card['ctaUrl'] ); ?>">
					<?php echo esc_html( $card['ctaText'] ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</section>
