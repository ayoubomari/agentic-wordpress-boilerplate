<?php
/**
 * Contact form — location photo (optional name/address caption overlaid
 * bottom-left) on one side, a "Get in touch" form on the other.
 *
 * Leave `action` empty (the default) and the form posts to admin-post.php,
 * which stores the submission as an `agentic_form_entry` post and emails
 * the site admin — see agentic_handle_contact_form() in
 * agentic-blocks/inc/form-entries.php. Unlike newsletter-signup's
 * dedup-by-email, every contact message is stored as its own entry: a
 * second message from the same visitor is new content, not a duplicate
 * subscription. Set `action` to a real inbox/service endpoint to bypass
 * this and post straight there instead.
 *
 * @var array $attributes
 */
$heading          = $attributes['heading'] ?? '';
$description      = $attributes['description'] ?? '';
$image_url        = $attributes['imageUrl'] ?? '';
$image_url_mobile = $attributes['imageUrlMobile'] ?? '';
$image_alt        = $attributes['imageAlt'] ?? '';
$location_name    = $attributes['locationName'] ?? '';
$location_address = $attributes['locationAddress'] ?? '';
$button_text      = $attributes['buttonText'] ?? 'Send Message';
$action           = $attributes['action'] ?? '';
$field_id         = wp_unique_id( 'agentic-contact-' );

// Same instance-matching trick as newsletter-signup: wp_unique_id()
// increments per prefix in render order, so it comes out identical on the
// POST that submitted this exact form and the following GET that
// redisplays it — letting the status notice attach to the one form that
// was actually submitted rather than every contact-form block on the page.
$use_local_handler = ! $action;
$is_this_instance  = $use_local_handler
	&& isset( $_GET['agentic_form_instance'] )
	&& sanitize_key( wp_unslash( $_GET['agentic_form_instance'] ) ) === $field_id;
$status            = $is_this_instance && isset( $_GET['agentic_form'] )
	? sanitize_key( wp_unslash( $_GET['agentic_form'] ) )
	: '';
?>
<section <?php echo agentic_section_classes( 'contact-form' ); ?>>
	<?php // Same mode-1 item-stagger reveal as image-with-text's media/content halves — see assets/js/scroll-reveal.js. Above the fold here, so scroll-reveal.js gives it the fast load-fade rather than an indefinite scroll-gated wait. ?>
	<div class="agentic-contact-form__media agentic-reveal-item">
		<?php if ( $image_url ) : ?>
			<?php /* Always the page's primary above-the-fold visual (this block sits right after the breadcrumb) — fetchpriority hint instead of loading="lazy", same treatment as product-subcategories' first tile. `sizes` mirrors style.css's own 780px stack breakpoint and the block's 5fr/11 image-column share above it. */ ?>
			<img
				src="<?php echo esc_url( $image_url ); ?>"
				<?php if ( $image_url_mobile ) : ?>
					srcset="<?php echo esc_url( $image_url_mobile ); ?> 800w, <?php echo esc_url( $image_url ); ?> 1200w"
					sizes="(max-width: 780px) 100vw, 45vw"
				<?php endif; ?>
				alt="<?php echo esc_attr( $image_alt ); ?>"
				fetchpriority="high"
				decoding="async"
			/>
		<?php else : ?>
			<span class="agentic-contact-form__placeholder" aria-hidden="true"></span>
		<?php endif; ?>

		<?php if ( $location_name || $location_address ) : ?>
			<div class="agentic-contact-form__location">
				<?php if ( $location_name ) : ?>
					<p class="agentic-contact-form__location-name"><?php echo esc_html( $location_name ); ?></p>
				<?php endif; ?>
				<?php if ( $location_address ) : ?>
					<p class="agentic-contact-form__location-address"><?php echo esc_html( $location_address ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="agentic-contact-form__content agentic-reveal-item">
		<?php if ( $heading ) : ?>
			<h2 class="agentic-contact-form__heading"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $description ) : ?>
			<p class="agentic-contact-form__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>

		<?php if ( $status ) : ?>
			<p class="agentic-contact-form__notice agentic-contact-form__notice--<?php echo esc_attr( $status ); ?>" role="status">
				<?php
				echo esc_html(
					'success' === $status
						? __( 'Thanks — your message is on its way. We’ll get back to you soon.', 'agentic' )
						: __( 'Something didn’t look right — please check your details and try again.', 'agentic' )
				);
				?>
			</p>
		<?php endif; ?>

		<form
			class="agentic-contact-form__form"
			method="post"
			<?php echo $action ? 'action="' . esc_url( $action ) . '"' : 'action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"'; ?>
			<?php echo $action ? 'target="_blank" rel="noopener"' : ''; ?>
		>
			<?php if ( $use_local_handler ) : ?>
				<input type="hidden" name="action" value="agentic_contact_form" />
				<input type="hidden" name="agentic_form_page" value="<?php echo esc_attr( home_url( add_query_arg( null, null ) ) ); ?>" />
				<input type="hidden" name="agentic_form_instance" value="<?php echo esc_attr( $field_id ); ?>" />
				<?php wp_nonce_field( 'agentic_contact_form', 'agentic_contact_nonce' ); ?>
				<span class="agentic-contact-form__hp" aria-hidden="true">
					<label for="<?php echo esc_attr( $field_id ); ?>-website">Website</label>
					<input type="text" id="<?php echo esc_attr( $field_id ); ?>-website" name="agentic_hp_website" tabindex="-1" autocomplete="off" />
				</span>
			<?php endif; ?>

			<div class="agentic-contact-form__row">
				<div class="agentic-contact-form__field">
					<label for="<?php echo esc_attr( $field_id ); ?>-name"><?php esc_html_e( 'Name', 'agentic' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $field_id ); ?>-name" name="name" autocomplete="name" />
				</div>
				<div class="agentic-contact-form__field">
					<label for="<?php echo esc_attr( $field_id ); ?>-email"><?php esc_html_e( 'Email', 'agentic' ); ?> *</label>
					<input type="email" id="<?php echo esc_attr( $field_id ); ?>-email" name="email" required autocomplete="email" />
				</div>
			</div>

			<div class="agentic-contact-form__field">
				<label for="<?php echo esc_attr( $field_id ); ?>-phone"><?php esc_html_e( 'Phone', 'agentic' ); ?></label>
				<input type="tel" id="<?php echo esc_attr( $field_id ); ?>-phone" name="phone" autocomplete="tel" />
			</div>

			<div class="agentic-contact-form__field">
				<label for="<?php echo esc_attr( $field_id ); ?>-message"><?php esc_html_e( 'Your message', 'agentic' ); ?></label>
				<textarea id="<?php echo esc_attr( $field_id ); ?>-message" name="message" rows="4"></textarea>
			</div>

			<button class="agentic-contact-form__button wp-element-button" type="submit">
				<span class="agentic-contact-form__button-label"><?php echo esc_html( $button_text ); ?></span>
			</button>
		</form>
	</div>
</section>
