<?php
/**
 * FAQ accordion.
 *
 * Built on native <details>/<summary>: keyboard accessible and expandable
 * with zero JavaScript, which keeps the performance budget intact.
 *
 * @var array $attributes
 */
$heading = $attributes['heading'] ?? '';
$items   = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];

if ( empty( $items ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'faq-accordion' ); ?>>
	<?php if ( $heading ) : ?>
		<h2 class="agentic-faq-accordion__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<div class="agentic-faq-accordion__list">
		<?php foreach ( $items as $item ) : ?>
			<?php if ( empty( $item['question'] ) ) { continue; } ?>
			<details class="agentic-faq-accordion__item">
				<summary class="agentic-faq-accordion__question">
					<?php echo esc_html( $item['question'] ); ?>
				</summary>
				<?php if ( ! empty( $item['answer'] ) ) : ?>
					<div class="agentic-faq-accordion__answer">
						<p><?php echo esc_html( $item['answer'] ); ?></p>
					</div>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
	</div>
</section>
