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
		<button class="agentic-newsletter-signup__button wp-element-button" type="submit">
			<?php echo esc_html( $button_text ); ?>
		</button>
	</form>
</div>
