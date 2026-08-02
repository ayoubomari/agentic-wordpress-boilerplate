<?php
/**
 * FAQ accordion.
 *
 * Built on native <details>/<summary>: keyboard accessible and expandable
 * with zero JavaScript, which keeps the performance budget intact.
 *
 * With introHeading/introText/imageUrl set, renders a 3-column layout
 * (intro blurb, image, accordion) instead of a centered heading over a
 * plain list — the top heading is skipped in that mode since the intro
 * column's own heading takes its place. Defaults reproduce the original
 * plain layout exactly, so existing usages are unaffected.
 *
 * @var array $attributes
 */
$heading      = $attributes['heading'] ?? '';
$items        = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$intro_heading = $attributes['introHeading'] ?? '';
$intro_text    = $attributes['introText'] ?? '';
$image_url     = $attributes['imageUrl'] ?? '';
$cta_text      = $attributes['ctaText'] ?? '';
$cta_url       = $attributes['ctaUrl'] ?? '';
$has_intro     = (bool) ( $intro_heading || $intro_text || $image_url );

if ( empty( $items ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'faq-accordion', $has_intro ? [ 'agentic-faq-accordion--with-intro' ] : [] ); ?>>
	<?php if ( $has_intro ) : ?>
		<div class="agentic-faq-accordion__intro">
			<?php if ( $intro_heading ) : ?>
				<h2 class="agentic-faq-accordion__intro-heading"><?php echo esc_html( $intro_heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $intro_text ) : ?>
				<p class="agentic-faq-accordion__intro-text"><?php echo esc_html( $intro_text ); ?></p>
			<?php endif; ?>
			<?php if ( $cta_text && $cta_url ) : ?>
				<a class="agentic-faq-accordion__intro-cta" href="<?php echo esc_url( $cta_url ); ?>">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( $image_url ) : ?>
			<div class="agentic-faq-accordion__intro-media">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" decoding="async" />
			</div>
		<?php endif; ?>
	<?php elseif ( $heading ) : ?>
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
