<?php
/**
 * Countdown banner — time-limited promotion.
 *
 * Ticking is handled entirely by view.js (plain JS, no build step, no React
 * dependency) so this stays a cheap, dependency-free enhancement. Without JS
 * the heading/text/CTA still render — only the numeric countdown is inert.
 *
 * @var array $attributes
 */
$heading      = $attributes['heading'] ?? '';
$text         = $attributes['text'] ?? '';
$end_date     = $attributes['endDate'] ?? '';
$expired_text = $attributes['expiredText'] ?? '';
$cta_text     = $attributes['ctaText'] ?? '';
$cta_url      = $attributes['ctaUrl'] ?? '';

if ( ! $heading && ! $text && ! $end_date ) {
	return;
}
?>
<section
	<?php echo agentic_section_classes( 'countdown-banner' ); ?>
	<?php echo $end_date ? 'data-end="' . esc_attr( $end_date ) . '"' : ''; ?>
	data-expired-text="<?php echo esc_attr( $expired_text ); ?>"
>
	<div class="agentic-countdown-banner__inner">
		<?php if ( $heading ) : ?>
			<h2 class="agentic-countdown-banner__heading"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $text ) : ?>
			<p class="agentic-countdown-banner__text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>

		<?php if ( $end_date ) : ?>
			<div class="agentic-countdown-banner__timer" aria-live="polite">
				<div class="agentic-countdown-banner__unit">
					<span class="agentic-countdown-banner__value" data-unit="days">00</span>
					<span class="agentic-countdown-banner__label"><?php esc_html_e( 'Days', 'agentic' ); ?></span>
				</div>
				<div class="agentic-countdown-banner__unit">
					<span class="agentic-countdown-banner__value" data-unit="hours">00</span>
					<span class="agentic-countdown-banner__label"><?php esc_html_e( 'Hrs', 'agentic' ); ?></span>
				</div>
				<div class="agentic-countdown-banner__unit">
					<span class="agentic-countdown-banner__value" data-unit="minutes">00</span>
					<span class="agentic-countdown-banner__label"><?php esc_html_e( 'Min', 'agentic' ); ?></span>
				</div>
				<div class="agentic-countdown-banner__unit">
					<span class="agentic-countdown-banner__value" data-unit="seconds">00</span>
					<span class="agentic-countdown-banner__label"><?php esc_html_e( 'Sec', 'agentic' ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $cta_text && $cta_url ) : ?>
			<a class="agentic-countdown-banner__cta wp-element-button" href="<?php echo esc_url( $cta_url ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
