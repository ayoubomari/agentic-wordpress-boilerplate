<?php
/**
 * Photo statement — fixed lines, each a flowing sentence with pill-shaped
 * photos woven inline between the words. Line breaks are explicit (an
 * array of lines, each an array of items) rather than left to the browser
 * to auto-wrap, so the layout always matches the authored grouping instead
 * of collapsing pills onto their own line when a line runs out of room.
 *
 * @var array $attributes
 */
$lines = is_array( $attributes['lines'] ?? null ) ? $attributes['lines'] : [];

if ( ! $lines ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'photo-statement' ); ?>>
	<?php foreach ( $lines as $line ) : ?>
		<?php if ( ! is_array( $line ) || ! $line ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<p class="agentic-photo-statement__line">
			<?php foreach ( $line as $item ) : ?>
				<?php if ( 'image' === ( $item['type'] ?? '' ) ) : ?>
					<?php
					$image_url = $item['imageUrl'] ?? '';
					$image_alt = $item['imageAlt'] ?? '';
					$bg_color  = trim( (string) ( $item['backgroundColor'] ?? '' ) );
					$width     = $item['width'] ?? '200px';
					$height    = $item['height'] ?? '130px';

					$modifiers = [ 'agentic-photo-statement__pill' ];
					$style     = sprintf( '--agentic-ps-width:%s;--agentic-ps-height:%s;', esc_attr( $width ), esc_attr( $height ) );
					if ( '' !== $bg_color ) {
						$modifiers[] = 'agentic-photo-statement__pill--has-background';
						$style      .= sprintf( '--agentic-ps-bg:var(--wp--preset--color--%s);', esc_attr( preg_replace( '/[^a-z0-9-]/', '', $bg_color ) ) );
					}
					?>
					<span class="<?php echo esc_attr( implode( ' ', $modifiers ) ); ?>" style="<?php echo esc_attr( $style ); ?>">
						<?php if ( $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
						<?php else : ?>
							<span class="agentic-photo-statement__placeholder" aria-hidden="true"></span>
						<?php endif; ?>
					</span>
				<?php else : ?>
					<?php $item_text = $item['text'] ?? ''; ?>
					<?php if ( '' !== $item_text ) : ?>
						<span class="agentic-photo-statement__text"><?php echo esc_html( $item_text ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endforeach; ?>
</section>
