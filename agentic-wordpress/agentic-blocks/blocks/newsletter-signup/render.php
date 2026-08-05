<?php
/**
 * Newsletter signup.
 *
 * No third-party ESP dependency ships in the boilerplate. Set the `action`
 * attribute to your provider's form endpoint (Mailchimp, Klaviyo,
 * Buttondown …) and the browser posts straight to it. Leave `action` empty
 * (the default) and the form is instead handled in-house: it posts to
 * admin-post.php, which stores the email as an `agentic_form_entry` post and
 * emails the site admin — see agentic-blocks/inc/form-entries.php. Either
 * way a submission always goes somewhere; it's never silently dropped.
 *
 * @var array $attributes
 */
$heading     = $attributes['heading'] ?? '';
$description = $attributes['description'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Subscribe';
$action      = $attributes['action'] ?? '';
$placeholder = $attributes['placeholder'] ?? 'your@email.com';
$field_id    = wp_unique_id( 'agentic-newsletter-' );

// $field_id doubles as this instance's ID: wp_unique_id() increments per
// prefix in render order, so it comes out identical on the POST that
// submitted this exact form and the following GET that redisplays it —
// which is what lets the status notice below attach to the one form that
// was actually submitted rather than every newsletter-signup block on the
// page. See agentic_handle_newsletter_signup() in
// agentic-blocks/inc/form-entries.php, which echoes it back unchanged.
$use_local_handler = ! $action;
$is_this_instance   = $use_local_handler
	&& isset( $_GET['agentic_form_instance'] )
	&& sanitize_key( wp_unslash( $_GET['agentic_form_instance'] ) ) === $field_id;
$status             = $is_this_instance && isset( $_GET['agentic_form'] )
	? sanitize_key( wp_unslash( $_GET['agentic_form'] ) )
	: '';
?>
<div <?php echo agentic_section_classes( 'newsletter-signup' ); ?>>
	<?php if ( $heading ) : ?>
		<h3 class="agentic-newsletter-signup__heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<?php if ( $description ) : ?>
		<p class="agentic-newsletter-signup__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>

	<?php if ( $status ) : ?>
		<p class="agentic-newsletter-signup__notice agentic-newsletter-signup__notice--<?php echo esc_attr( $status ); ?>" role="status">
			<?php
			echo esc_html(
				'success' === $status
					? __( 'Thanks — you’re on the list.', 'agentic' )
					: __( 'That email didn’t look right — please try again.', 'agentic' )
			);
			?>
		</p>
	<?php endif; ?>

	<form
		class="agentic-newsletter-signup__form"
		method="post"
		<?php echo $action ? 'action="' . esc_url( $action ) . '"' : 'action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"'; ?>
		<?php echo $action ? 'target="_blank" rel="noopener"' : ''; ?>
	>
		<?php if ( $use_local_handler ) : ?>
			<input type="hidden" name="action" value="agentic_newsletter_signup" />
			<input type="hidden" name="agentic_form_page" value="<?php echo esc_attr( home_url( add_query_arg( null, null ) ) ); ?>" />
			<input type="hidden" name="agentic_form_instance" value="<?php echo esc_attr( $field_id ); ?>" />
			<?php wp_nonce_field( 'agentic_newsletter_signup', 'agentic_newsletter_nonce' ); ?>
			<span class="agentic-newsletter-signup__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $field_id ); ?>-website">Website</label>
				<input type="text" id="<?php echo esc_attr( $field_id ); ?>-website" name="agentic_hp_website" tabindex="-1" autocomplete="off" />
			</span>
		<?php endif; ?>
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
</div>
