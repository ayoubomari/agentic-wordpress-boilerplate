<?php
/**
 * Rich text — centred editorial break.
 *
 * @var array $attributes
 */
$heading   = $attributes['heading'] ?? '';
$text      = $attributes['text'] ?? '';
$cta_text   = $attributes['ctaText'] ?? '';
$cta_url    = $attributes['ctaUrl'] ?? '';
$link_style = 'underline' === ( $attributes['linkStyle'] ?? 'button' ) ? 'underline' : 'button';
$max_width  = $attributes['maxWidth'] ?? '40rem';
$watermark  = $attributes['watermarkImageUrl'] ?? '';

if ( ! $heading && ! $text ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'rich-text', $watermark ? [ 'agentic-rich-text--watermarked' ] : [] ); ?>>
	<?php if ( $watermark ) : ?>
		<img class="agentic-rich-text__watermark" src="<?php echo esc_url( $watermark ); ?>" alt="" loading="lazy" />
	<?php endif; ?>
	<div class="agentic-rich-text__inner" style="--agentic-max-width:<?php echo esc_attr( $max_width ); ?>">
		<?php if ( $heading ) : ?>
			<h2 class="agentic-rich-text__heading"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $text ) : ?>
			<p class="agentic-rich-text__text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>

		<?php if ( $cta_text && $cta_url ) : ?>
			<a class="agentic-rich-text__cta <?php echo 'underline' === $link_style ? 'agentic-rich-text__cta--underline' : 'wp-element-button'; ?>" href="<?php echo esc_url( $cta_url ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
