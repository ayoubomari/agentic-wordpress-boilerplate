<?php
/**
 * Image with text — split editorial section.
 *
 * With `backgroundColor` set, the whole section becomes a colored promo
 * panel (Sleek's "dual promo split" look) — combine with
 * `imageStyle: "inset"` to contain the image within padding rather than
 * bleeding it to the panel edge. Defaults ('' / "full") reproduce the
 * original plain edge-bleed layout exactly, so existing usages are
 * unaffected.
 *
 * @var array $attributes
 */
$eyebrow    = $attributes['eyebrow'] ?? '';
$heading    = $attributes['heading'] ?? '';
$text       = $attributes['text'] ?? '';
$image_url        = $attributes['imageUrl'] ?? '';
$image_url_mobile = $attributes['imageUrlMobile'] ?? '';
$image_alt        = $attributes['imageAlt'] ?? '';
$position   = 'right' === ( $attributes['imagePosition'] ?? 'left' ) ? 'right' : 'left';
$image_style = 'inset' === ( $attributes['imageStyle'] ?? 'full' ) ? 'inset' : 'full';
$bg_color   = trim( (string) ( $attributes['backgroundColor'] ?? '' ) );
$cta_text   = $attributes['ctaText'] ?? '';
$cta_url    = $attributes['ctaUrl'] ?? '';

$modifiers = [
	'agentic-image-with-text--image-' . $position,
	'agentic-image-with-text--image-style-' . $image_style,
];

$style = '';
if ( '' !== $bg_color ) {
	$modifiers[] = 'agentic-image-with-text--has-background';
	$style       = sprintf( '--agentic-iwt-bg:var(--wp--preset--color--%s);', esc_attr( preg_replace( '/[^a-z0-9-]/', '', $bg_color ) ) );
}
?>
<section <?php echo agentic_section_classes( 'image-with-text', $modifiers ); ?>
	<?php echo $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>>
	<div class="agentic-image-with-text__media agentic-reveal-item">
		<?php if ( $image_url ) : ?>
			<?php /* sizes mirrors this block's own 780px stack breakpoint and 50/50 desktop column split. */ ?>
			<img
				src="<?php echo esc_url( $image_url ); ?>"
				<?php if ( $image_url_mobile ) : ?>
					srcset="<?php echo esc_url( $image_url_mobile ); ?> 800w, <?php echo esc_url( $image_url ); ?> 1448w"
					sizes="(max-width: 780px) 100vw, 45vw"
				<?php endif; ?>
				alt="<?php echo esc_attr( $image_alt ); ?>"
				loading="lazy"
				decoding="async"
			/>
		<?php else : ?>
			<span class="agentic-image-with-text__placeholder" aria-hidden="true"></span>
		<?php endif; ?>
	</div>

	<div class="agentic-image-with-text__content agentic-reveal-item">
		<div class="agentic-image-with-text__content-inner">
			<?php if ( $eyebrow ) : ?>
				<p class="agentic-image-with-text__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>

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
	</div>
</section>
