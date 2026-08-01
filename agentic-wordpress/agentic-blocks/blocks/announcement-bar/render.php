<?php
/**
 * Announcement bar — thin top-of-page bar, e.g. shipping/promo messaging.
 *
 * @var array $attributes
 */
$text = $attributes['text'] ?? '';
$url  = $attributes['url'] ?? '';

if ( ! $text ) {
	return;
}
?>
<div <?php echo agentic_section_classes( 'announcement-bar' ); ?>>
	<?php if ( $url ) : ?>
		<a class="agentic-announcement-bar__text" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $text ); ?></a>
	<?php else : ?>
		<p class="agentic-announcement-bar__text"><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
</div>
