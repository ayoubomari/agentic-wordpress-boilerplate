<?php
/**
 * Multicolumn — reassurance / feature row, or a compact pill-badge row.
 *
 * @var array $attributes
 */
$heading = $attributes['heading'] ?? '';
$columns = max( 1, min( 6, (int) ( $attributes['columns'] ?? 3 ) ) );
$items   = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$variant = 'pills' === ( $attributes['variant'] ?? 'columns' ) ? 'pills' : 'columns';

if ( empty( $items ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'multicolumn', [ 'agentic-multicolumn--' . $variant ] ); ?>>
	<?php if ( $heading ) : ?>
		<h2 class="agentic-multicolumn__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( 'pills' === $variant ) : ?>
		<ul class="agentic-multicolumn__pills">
			<?php foreach ( $items as $item ) : ?>
				<?php if ( empty( $item['heading'] ) ) : continue; endif; ?>
				<li class="agentic-multicolumn__pill agentic-reveal-item">
					<span class="agentic-multicolumn__pill-check" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 12.5 9.5 18 20 6" /></svg>
					</span>
					<?php echo esc_html( $item['heading'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<ul class="agentic-multicolumn__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<li class="agentic-multicolumn__item agentic-reveal-item">
					<?php if ( ! empty( $item['iconUrl'] ) ) : ?>
						<img class="agentic-multicolumn__icon agentic-multicolumn__icon--img" src="<?php echo esc_url( $item['iconUrl'] ); ?>" alt="" loading="lazy" decoding="async" />
					<?php elseif ( ! empty( $item['icon'] ) ) : ?>
						<span class="agentic-multicolumn__icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $item['heading'] ) ) : ?>
						<h3 class="agentic-multicolumn__item-heading"><?php echo esc_html( $item['heading'] ); ?></h3>
					<?php endif; ?>
					<?php if ( ! empty( $item['text'] ) ) : ?>
						<p class="agentic-multicolumn__text"><?php echo esc_html( $item['text'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
