<?php
/**
 * Image with text — split editorial section.
 *
 * @var array $attributes
 */
$heading   = $attributes['heading'] ?? '';
$text      = $attributes['text'] ?? '';
$image_url = $attributes['imageUrl'] ?? '';
$image_alt = $attributes['imageAlt'] ?? '';
$position  = 'right' === ( $attributes['imagePosition'] ?? 'left' ) ? 'right' : 'left';
$cta_text  = $attributes['ctaText'] ?? '';
$cta_url   = $attributes['ctaUrl'] ?? '';
?>
<section <?php echo agentic_section_classes( 'image-with-text', [ 'agentic-image-with-text--image-' . $position ] ); ?>>
	<div class="agentic-image-with-text__media">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
		<?php else : ?>
			<span class="agentic-image-with-text__placeholder" aria-hidden="true"></span>
		<?php endif; ?>
	</div>

	<div class="agentic-image-with-text__content">
		<?php if ( $heading ) : ?>
			<h2 class="agentic-image-with-text__heading"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $text ) : ?>
			<p class="agentic-image-with-text__text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>

		<?php if ( $cta_text && $cta_url ) : ?>
			<a class="agentic-image-with-text__cta wp-element-button" href="<?php echo esc_url( $cta_url ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
