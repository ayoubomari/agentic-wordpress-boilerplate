<?php
/**
 * Newsletter signup.
 *
 * Markup only, on purpose: no third-party ESP dependency ships in the
 * boilerplate. Set the `action` attribute to your provider's form endpoint
 * (Mailchimp, Klaviyo, Buttondown …) and the browser posts straight to it.
 *
 * @var array $attributes
 */
$heading     = $attributes['heading'] ?? '';
$description = $attributes['description'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Subscribe';
$action      = $attributes['action'] ?? '';
$placeholder = $attributes['placeholder'] ?? 'your@email.com';
$field_id    = wp_unique_id( 'agentic-newsletter-' );
?>
<div <?php echo agentic_section_classes( 'newsletter-signup' ); ?>>
	<?php if ( $heading ) : ?>
		<h3 class="agentic-newsletter-signup__heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<?php if ( $description ) : ?>
		<p class="agentic-newsletter-signup__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>

	<form
		class="agentic-newsletter-signup__form"
		method="post"
		<?php echo $action ? 'action="' . esc_url( $action ) . '"' : ''; ?>
		<?php echo $action ? 'target="_blank" rel="noopener"' : ''; ?>
	>
		<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>">
			<?php esc_html_e( 'Email address', 'agentic' ); ?>
		</label>
		<input
			class="agentic-newsletter-signup__input"
			id="<?php echo esc_attr( $field_id ); ?>"
			type="email"
			name="email"
			required
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="email"
		/>
		<button class="agentic-newsletter-signup__button" type="submit">
			<span class="screen-reader-text"><?php echo esc_html( $button_text ); ?></span>
			<svg class="agentic-newsletter-signup__button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
				<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
			</svg>
		</button>
	</form>

	<p class="agentic-newsletter-signup__consent">
		<?php esc_html_e( 'By subscribing you agree to the', 'agentic' ); ?>
		<a href="/sample-page/"><?php esc_html_e( 'Terms of Use', 'agentic' ); ?></a>
		&amp;
		<a href="/privacy-policy/"><?php esc_html_e( 'Privacy Policy', 'agentic' ); ?></a>.
	</p>
</div>
