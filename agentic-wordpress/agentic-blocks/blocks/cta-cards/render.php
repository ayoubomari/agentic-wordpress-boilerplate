<?php
/**
 * CTA cards — a row of colored content-teaser cards, or set variant:"overlay"
 * for full-bleed panels with a cover background image and the heading/CTA
 * overlaid directly on top of it (a split hero-banner-like promo strip)
 * instead of the image sitting above the text in its own colored card.
 *
 * @var array $attributes
 */
$cards   = is_array( $attributes['cards'] ?? null ) ? $attributes['cards'] : [];
$cards   = array_values( array_filter( $cards, static fn( $card ) => ! empty( $card['heading'] ) ) );
$overlay = 'overlay' === ( $attributes['variant'] ?? 'cards' );

if ( empty( $cards ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'cta-cards', $overlay ? [ 'agentic-cta-cards--overlay' ] : [], '--agentic-columns:' . count( $cards ) ); ?>>
	<?php foreach ( $cards as $card ) : ?>
		<?php
		$bg_color = trim( (string) ( $card['backgroundColor'] ?? 'subtle' ) );
		$bg_safe  = preg_replace( '/[^a-z0-9-]/', '', $bg_color );
		$image_url = $card['imageUrl'] ?? '';
		$card_style = '--agentic-cta-bg:var(--wp--preset--color--' . esc_attr( $bg_safe ) . ')';
		if ( $overlay && $image_url ) {
			$card_style .= ';background-image:url(' . esc_url( $image_url ) . ')';
		}
		?>
		<div class="agentic-cta-cards__card" style="<?php echo esc_attr( $card_style ); ?>">
			<?php if ( ! $overlay && $image_url ) : ?>
				<div class="agentic-cta-cards__media">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>

			<h3 class="agentic-cta-cards__heading"><?php echo esc_html( $card['heading'] ); ?></h3>

			<?php if ( ! empty( $card['text'] ) ) : ?>
				<p class="agentic-cta-cards__text"><?php echo esc_html( $card['text'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $card['ctaText'] ) && ! empty( $card['ctaUrl'] ) ) : ?>
				<a class="agentic-cta-cards__cta wp-element-button" href="<?php echo esc_url( $card['ctaUrl'] ); ?>">
					<?php echo esc_html( $card['ctaText'] ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</section>
