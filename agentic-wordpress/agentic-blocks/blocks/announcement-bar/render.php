<?php
/**
 * Announcement bar — thin top-of-page bar, e.g. shipping/promo messaging.
 *
 * `messages` (2+ entries) renders as a continuously scrolling marquee, the
 * track duplicated once so translateX(-50%) loops seamlessly. A single
 * message (via `messages` or the legacy `text`/`url` pair) renders as a
 * plain static centered bar — no animation, no duplication.
 *
 * @var array $attributes
 */
$messages = $attributes['messages'] ?? [];
if ( ! is_array( $messages ) ) {
	$messages = [];
}
$messages = array_values(
	array_filter(
		$messages,
		static function ( $message ) {
			return is_array( $message ) && ! empty( $message['text'] );
		}
	)
);

if ( empty( $messages ) && ! empty( $attributes['text'] ) ) {
	$messages = [ [ 'text' => $attributes['text'], 'url' => $attributes['url'] ?? '' ] ];
}

if ( empty( $messages ) ) {
	return;
}

$is_marquee = count( $messages ) > 1;

// The track is duplicated N times and translated by exactly 1/N of its own
// width each loop, so any N tiles seamlessly — but a fixed N=2 only stays
// wider than the viewport when the messages are long enough. With a short
// message list on a wide/ultrawide screen, 2 copies can be narrower than
// the screen, so the track visibly runs out (blank gap) right before it
// loops. Estimate one copy's rendered width and pick N so the doubled
// track comfortably exceeds even a very wide viewport, capped so a
// pathologically short message list doesn't blow up the DOM.
$copy_width = 0;
foreach ( $messages as $message ) {
	$text        = $message['text'] ?? '';
	$length      = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	$copy_width += ( $length * 9 ) + 64; // ~9px/char (small uppercase text) + 2rem padding each side.
}
$copy_width     = max( $copy_width, 1 );
$marquee_copies = $is_marquee ? max( 2, min( 16, (int) ceil( 6000 / $copy_width ) ) ) : 2;
$marquee_speed  = $is_marquee ? max( 18, min( 50, (int) round( $copy_width / 50 ) ) ) : 24;

$render_item = static function ( $message ) {
	$text = $message['text'] ?? '';
	$url  = $message['url'] ?? '';

	if ( ! $text ) {
		return '';
	}

	if ( $url ) {
		return sprintf(
			'<a class="agentic-announcement-bar__item" href="%s">%s</a>',
			esc_url( $url ),
			esc_html( $text )
		);
	}

	return sprintf(
		'<span class="agentic-announcement-bar__item">%s</span>',
		esc_html( $text )
	);
};
?>
<div <?php echo agentic_section_classes( 'announcement-bar', $is_marquee ? [ 'is-marquee' ] : [] ); ?>>
	<?php if ( $is_marquee ) : ?>
		<div class="agentic-announcement-bar__track" style="--agentic-marquee-copies:<?php echo (int) $marquee_copies; ?>;--agentic-marquee-duration:<?php echo (int) $marquee_speed; ?>s;">
			<?php
			// Rendered $marquee_copies times back-to-back so the keyframe
			// animation can translate exactly 1/N of the track's width and
			// loop with no seam — see the width estimate above for why N
			// isn't just a fixed 2.
			for ( $repeat = 0; $repeat < $marquee_copies; $repeat++ ) :
				foreach ( $messages as $message ) :
					echo $render_item( $message ); // phpcs:ignore -- already escaped in $render_item.
				endforeach;
			endfor;
			?>
		</div>
	<?php else : ?>
		<?php echo $render_item( $messages[0] ); // phpcs:ignore -- already escaped in $render_item. ?>
	<?php endif; ?>
</div>
