<?php
/**
 * Video section — self-hosted or embedded video with a poster.
 *
 * Self-hosted video uses preload="none" so the browser fetches nothing until
 * playback starts; embeds use a lazy-loaded iframe. Either way this block
 * never competes with the page's real LCP candidate.
 *
 * @var array $attributes
 */
$heading    = $attributes['heading'] ?? '';
$text       = $attributes['text'] ?? '';
$video_url  = $attributes['videoUrl'] ?? '';
$embed_url  = $attributes['embedUrl'] ?? '';
$poster_url = $attributes['posterUrl'] ?? '';
$height     = in_array( $attributes['height'] ?? 'medium', [ 'small', 'medium', 'large' ], true )
	? $attributes['height']
	: 'medium';

if ( ! $video_url && ! $embed_url ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'video-section', [ 'agentic-video-section--height-' . $height ] ); ?>>
	<?php if ( $heading || $text ) : ?>
		<div class="agentic-video-section__header">
			<?php if ( $heading ) : ?>
				<h2 class="agentic-video-section__heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $text ) : ?>
				<p class="agentic-video-section__text"><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="agentic-video-section__media">
		<?php if ( $video_url ) : ?>
			<video
				class="agentic-video-section__video"
				controls
				preload="none"
				playsinline
				<?php echo $poster_url ? 'poster="' . esc_url( $poster_url ) . '"' : ''; ?>
			>
				<source src="<?php echo esc_url( $video_url ); ?>" />
			</video>
		<?php else : ?>
			<iframe
				class="agentic-video-section__embed"
				src="<?php echo esc_url( $embed_url ); ?>"
				title="<?php echo esc_attr( $heading ?: __( 'Video', 'agentic' ) ); ?>"
				loading="lazy"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
				allowfullscreen
			></iframe>
		<?php endif; ?>
	</div>
</section>
