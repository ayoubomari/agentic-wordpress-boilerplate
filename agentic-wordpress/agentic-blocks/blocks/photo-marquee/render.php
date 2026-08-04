<?php
/**
 * Photo marquee — thin hairline-bordered strip alternating a circular photo
 * with a short line of copy, continuously scrolling right to left.
 *
 * Each item is { imageUrl, alt, text }, set directly in the template file.
 * 2+ items render as a seamless marquee (track duplicated N times, same
 * technique as announcement-bar); a single item renders as a static
 * centered row with no animation.
 *
 * @var array $attributes
 */
$items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$items = array_values(
	array_filter(
		$items,
		static function ( $item ) {
			return is_array( $item ) && ( ! empty( $item['text'] ) || ! empty( $item['imageUrl'] ) );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$is_marquee = count( $items ) > 1;

// Estimate one copy's rendered width so the track is duplicated just
// enough times to comfortably exceed even a very wide viewport — see
// announcement-bar/render.php for why a fixed copy count isn't enough.
$copy_width = 0;
foreach ( $items as $item ) {
	$text        = $item['text'] ?? '';
	$length      = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	$copy_width += ( ! empty( $item['imageUrl'] ) ? 56 : 0 ) + ( $length * 11 ) + 116; // photo + text + gaps.
}
$copy_width     = max( $copy_width, 1 );
$marquee_copies = $is_marquee ? max( 2, min( 16, (int) ceil( 6000 / $copy_width ) ) ) : 2;
// 3x the base scroll speed (0.75x of the 4x speed — a third of the duration for the same distance).
$marquee_speed  = $is_marquee ? max( 7, min( 20, (int) round( $copy_width / 120 ) ) ) : 11;

$render_item = static function ( $item ) {
	$image = $item['imageUrl'] ?? '';
	$alt   = $item['alt'] ?? '';
	$text  = $item['text'] ?? '';

	$out = '<span class="agentic-photo-marquee__item">';
	if ( $image ) {
		$out .= sprintf(
			'<img class="agentic-photo-marquee__photo" src="%s" alt="%s" loading="lazy" decoding="async" width="56" height="56" />',
			esc_url( $image ),
			esc_attr( $alt )
		);
	}
	if ( $text ) {
		$out .= sprintf( '<span class="agentic-photo-marquee__text">%s</span>', esc_html( $text ) );
	}
	$out .= '</span>';

	return $out;
};
?>
<div <?php echo agentic_section_classes( 'photo-marquee', $is_marquee ? [ 'is-marquee' ] : [] ); ?>>
	<?php if ( $is_marquee ) : ?>
		<div class="agentic-photo-marquee__track" style="--agentic-marquee-copies:<?php echo (int) $marquee_copies; ?>;--agentic-marquee-duration:<?php echo (int) $marquee_speed; ?>s;">
			<?php
			// Rendered $marquee_copies times back-to-back so the keyframe
			// animation can translate exactly 1/N of the track's width and
			// loop with no seam — see the width estimate above.
			for ( $repeat = 0; $repeat < $marquee_copies; $repeat++ ) :
				foreach ( $items as $item ) :
					echo $render_item( $item ); // phpcs:ignore -- already escaped in $render_item.
				endforeach;
			endfor;
			?>
		</div>
	<?php else : ?>
		<div class="agentic-photo-marquee__track agentic-photo-marquee__track--static">
			<?php echo $render_item( $items[0] ); // phpcs:ignore -- already escaped in $render_item. ?>
		</div>
	<?php endif; ?>
</div>
