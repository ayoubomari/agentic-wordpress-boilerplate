<?php
/**
 * Hero banner — full-width image/color banner, optionally a peek carousel.
 *
 * Backward compatible: with no `slides` attribute, a single slide is
 * synthesised from the flat heading/subheading/ctaText/ctaUrl/imageUrl
 * attributes — existing single-banner usages keep rendering exactly as
 * before, with no carousel markup or script.
 *
 * Falls back to a solid background color (never a bare grey box) when a
 * slide has no image, so the block always renders something intentional.
 *
 * @var array $attributes
 */
$slides = ! empty( $attributes['slides'] ) && is_array( $attributes['slides'] ) ? $attributes['slides'] : null;

if ( ! $slides ) {
	$slides = [
		[
			'eyebrow'         => $attributes['eyebrow'] ?? '',
			'heading'         => $attributes['heading'] ?? '',
			'subheading'      => $attributes['subheading'] ?? '',
			'ctaText'         => $attributes['ctaText'] ?? '',
			'ctaUrl'          => $attributes['ctaUrl'] ?? '',
			'imageUrl'        => $attributes['imageUrl'] ?? '',
			'videoUrl'        => $attributes['videoUrl'] ?? '',
			'videoWebmUrl'    => $attributes['videoWebmUrl'] ?? '',
			'backgroundColor' => $attributes['backgroundColor'] ?? 'blush',
			'accentColor'     => $attributes['accentColor'] ?? '',
			'overlayOpacity'  => $attributes['overlayOpacity'] ?? 35,
		],
	];
}

$default_align = in_array( $attributes['contentAlign'] ?? 'left', [ 'left', 'center', 'right' ], true )
	? $attributes['contentAlign']
	: 'left';
$height        = in_array( $attributes['height'] ?? 'medium', [ 'small', 'medium', 'large' ], true )
	? $attributes['height']
	: 'medium';

/**
 * Each slide can set its own `contentAlign` (e.g. one slide's text on the
 * left, the next slide's on the right) and falls back to the block-level
 * `contentAlign` attribute when it doesn't — so existing content with no
 * per-slide value keeps rendering exactly as before.
 */
$slide_align = static function ( $slide ) use ( $default_align ) {
	$value = $slide['contentAlign'] ?? $default_align;
	return in_array( $value, [ 'left', 'center', 'right' ], true ) ? $value : $default_align;
};

$is_carousel = count( $slides ) > 1;
// Fixed, not a block attribute — the dot progress-fill animation in
// style.css is hardcoded to the same 5000ms and must be kept in sync.
$autoplay_delay = 5000;
if ( $is_carousel ) {
	wp_enqueue_script( 'agentic-carousel' );
}

/**
 * Render one slide's inner markup (heading/subheading/CTA over its own
 * image or color background). Shared between the single and carousel paths
 * so they never drift out of sync.
 */
$render_slide = static function ( $slide, $align, $height, $extra_classes = [] ) {
	$eyebrow    = $slide['eyebrow'] ?? '';
	$heading    = $slide['heading'] ?? '';
	$subheading = $slide['subheading'] ?? '';
	$cta_text   = $slide['ctaText'] ?? '';
	$cta_url    = $slide['ctaUrl'] ?? '';
	$image_url  = $slide['imageUrl'] ?? '';
	$video_url  = $slide['videoUrl'] ?? '';
	$video_webm = $slide['videoWebmUrl'] ?? '';
	$bg_color   = $slide['backgroundColor'] ?? 'blush';
	$accent     = $slide['accentColor'] ?? '';
	$overlay    = max( 0, min( 100, (int) ( $slide['overlayOpacity'] ?? 35 ) ) );

	$classes = array_merge(
		[
			'agentic-hero-banner__slide',
			'agentic-hero-banner--align-' . $align,
			'agentic-hero-banner--height-' . $height,
		],
		$extra_classes
	);
	if ( $image_url ) {
		$classes[] = 'agentic-hero-banner--has-image';
	}
	if ( $accent ) {
		$classes[] = 'agentic-hero-banner--has-accent';
	}
	if ( $video_url ) {
		$classes[] = 'agentic-hero-banner--has-video';
	}
	// Only the palette's "contrast" slug is dark enough to need forced light
	// text — everything else in the palette (blush, sand, subtle, base) is
	// light. A short explicit check here is simpler and less fragile than
	// computing luminance from an arbitrary CSS var. Separate from
	// --has-image (which also toggles the darkening overlay pseudo-element,
	// not wanted here — there's no image to darken).
	if ( ! $image_url && 'contrast' === $bg_color ) {
		$classes[] = 'agentic-hero-banner--force-light-text';
	}

	// backgroundColor is any theme.json palette slug — referencing the CSS
	// custom property directly (rather than a hardcoded class per slug)
	// means every palette color works here, not just a fixed enum.
	$bg_color_safe = preg_replace( '/[^a-z0-9-]/', '', $bg_color );
	$style         = sprintf( '--agentic-hero-bg:var(--wp--preset--color--%s);', esc_attr( $bg_color_safe ) );
	if ( $image_url ) {
		$style .= sprintf(
			'background-image:url(%s);--agentic-hero-overlay:%s;',
			esc_url( $image_url ),
			esc_attr( $overlay / 100 )
		);
	}
	if ( $accent ) {
		$accent_safe = preg_replace( '/[^a-z0-9-]/', '', $accent );
		$style      .= sprintf( '--agentic-hero-accent:var(--wp--preset--color--%s);', esc_attr( $accent_safe ) );
	}
	?>
	<div class="<?php echo esc_attr( implode( ' ', array_filter( $classes ) ) ); ?>"
		<?php echo $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>>
		<?php if ( $video_url ) : ?>
			<video
				class="agentic-hero-banner__video"
				autoplay muted loop playsinline
				<?php echo $image_url ? 'poster="' . esc_url( $image_url ) . '"' : ''; ?>
			>
				<?php if ( $video_webm ) : ?>
					<source src="<?php echo esc_url( $video_webm ); ?>" type="video/webm">
				<?php endif; ?>
				<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4">
			</video>
		<?php endif; ?>
		<div class="agentic-hero-banner__inner">
			<?php if ( $eyebrow ) : ?>
				<p class="agentic-hero-banner__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>

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

		<?php if ( ! $image_url ) : ?>
			<?php agentic_hero_banner_illustration(); ?>
		<?php endif; ?>
	</div>
	<?php
};
?>
<section <?php echo agentic_section_classes( 'hero-banner', $is_carousel ? [ 'agentic-carousel' ] : [] ); ?><?php echo $is_carousel ? ' data-carousel-autoplay="' . (int) $autoplay_delay . '"' : ''; ?>>
	<?php if ( $is_carousel ) : ?>
		<div class="agentic-carousel__track">
			<?php foreach ( $slides as $slide ) : ?>
				<?php $render_slide( $slide, $slide_align( $slide ), $height, [ 'agentic-carousel__slide' ] ); ?>
			<?php endforeach; ?>
		</div>
		<?php agentic_carousel_loop_precorrect(); ?>

		<button type="button" class="agentic-hero-banner__nav agentic-hero-banner__nav--prev" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous slide', 'agentic' ); ?>">‹</button>
		<button type="button" class="agentic-hero-banner__nav agentic-hero-banner__nav--next" data-carousel-next aria-label="<?php esc_attr_e( 'Next slide', 'agentic' ); ?>">›</button>

		<div class="agentic-hero-banner__dots">
			<?php foreach ( $slides as $i => $slide ) : ?>
				<button type="button" class="agentic-hero-banner__dot" data-carousel-dot aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Go to slide %d', 'agentic' ), $i + 1 ) ); ?>"></button>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<?php $render_slide( $slides[0], $slide_align( $slides[0] ), $height ); ?>
	<?php endif; ?>
</section>
