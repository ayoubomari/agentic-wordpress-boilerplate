<?php
/**
 * Multicolumn — reassurance / feature row.
 *
 * @var array $attributes
 */
$heading = $attributes['heading'] ?? '';
$columns = max( 1, min( 6, (int) ( $attributes['columns'] ?? 3 ) ) );
$items   = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];

if ( empty( $items ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'multicolumn' ); ?>>
	<?php if ( $heading ) : ?>
		<h2 class="agentic-multicolumn__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<ul class="agentic-multicolumn__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<li class="agentic-multicolumn__item">
				<?php if ( ! empty( $item['icon'] ) ) : ?>
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
</section>
