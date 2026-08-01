<?php
/**
 * Hero banner — the "image banner" section.
 *
 * Falls back to a flat subtle background when no image is set, so the block
 * always renders something sensible with default attributes.
 *
 * @var array $attributes
 */
$heading    = $attributes['heading'] ?? '';
$subheading = $attributes['subheading'] ?? '';
$cta_text   = $attributes['ctaText'] ?? '';
$cta_url    = $attributes['ctaUrl'] ?? '';
$image_url  = $attributes['imageUrl'] ?? '';
$overlay    = max( 0, min( 100, (int) ( $attributes['overlayOpacity'] ?? 35 ) ) );
$align      = in_array( $attributes['contentAlign'] ?? 'center', [ 'left', 'center', 'right' ], true )
	? $attributes['contentAlign']
	: 'center';
$height     = in_array( $attributes['height'] ?? 'medium', [ 'small', 'medium', 'large' ], true )
	? $attributes['height']
	: 'medium';

$modifiers = [
	'agentic-hero-banner--align-' . $align,
	'agentic-hero-banner--height-' . $height,
];
if ( $image_url ) {
	$modifiers[] = 'agentic-hero-banner--has-image';
}

$style = $image_url
	? sprintf(
		'background-image:url(%s);--agentic-hero-overlay:%s;',
		esc_url( $image_url ),
		esc_attr( $overlay / 100 )
	)
	: '';
?>
<section <?php echo agentic_section_classes( 'hero-banner', $modifiers ); ?>
	<?php echo $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>>
	<div class="agentic-hero-banner__inner">
		<?php if ( $heading ) : ?>
			<h1 class="agentic-hero-banner__heading"><?php echo esc_html( $heading ); ?></h1>
		<?php endif; ?>

		<?php if ( $subheading ) : ?>
			<p class="agentic-hero-banner__subheading"><?php echo esc_html( $subheading ); ?></p>
		<?php endif; ?>

		<?php if ( $cta_text && $cta_url ) : ?>
			<a class="agentic-hero-banner__cta wp-element-button" href="<?php echo esc_url( $cta_url ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
