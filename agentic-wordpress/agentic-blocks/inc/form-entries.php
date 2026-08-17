<?php
/**
 * Form submission storage + admin notification.
 *
 * Frontend blocks that collect data (currently: newsletter-signup) had no
 * default destination for a submission — the form just posted to whatever
 * `action` attribute was set, and did nothing at all when it was empty. This
 * file is the missing "backend": a private, non-public CPT that stores each
 * submission so the store owner can see it in wp-admin, plus a wp_mail()
 * notification to the site admin (delivered via WP Mail SMTP once the owner
 * configures it — see the "Email deliverability" checklist item).
 *
 * Deliberately not a forms plugin: a full forms plugin (entries UI, spam
 * filtering, conditional logic, its own DB tables) is a lot of surface area
 * to pull in for "store a submission + notify admin" — same call as the
 * GA4/Meta pixel section in functions.php. `agentic_record_form_entry()`
 * below is a small reusable API for newsletter-signup's single-field case;
 * contact-form has more fields (name/phone/message) and its own storage,
 * further down this file, that writes to the same CPT directly.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Private CPT that holds every captured submission. Not public and not in
 * the REST API — this is admin-only storage, never a front-end post type.
 * `create_posts` is disabled so nobody can add a fake entry from wp-admin's
 * "Add New" screen; entries only ever come from agentic_record_form_entry().
 */
add_action(
	'init',
	function () {
		register_post_type(
			'agentic_form_entry',
			[
				'labels'          => [
					'name'          => __( 'Form Entries', 'agentic' ),
					'singular_name' => __( 'Form Entry', 'agentic' ),
					'menu_name'     => __( 'Form Entries', 'agentic' ),
					'all_items'     => __( 'Form Entries', 'agentic' ),
					'search_items'  => __( 'Search entries', 'agentic' ),
					'not_found'     => __( 'No form entries yet.', 'agentic' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 26,
				// 'editor' holds a contact-form message body (post_content)
				// so the store owner can actually read it in wp-admin, not
				// just see that a submission happened.
				'supports'        => [ 'title', 'editor' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => [ 'create_posts' => 'do_not_allow' ],
			]
		);
	}
);

add_filter(
	'manage_agentic_form_entry_posts_columns',
	function ( $columns ) {
		$new                       = [];
		$new['cb']                 = $columns['cb'];
		// Title now holds a contact submitter's name (falls back to their
		// email when no name was given) rather than always being the email
		// address, so the email gets its own explicit column too.
		$new['title']              = __( 'Name', 'agentic' );
		$new['agentic_form_type']  = __( 'Form', 'agentic' );
		$new['agentic_form_email'] = __( 'Email', 'agentic' );
		$new['agentic_form_phone'] = __( 'Phone', 'agentic' );
		$new['agentic_form_page']  = __( 'Page', 'agentic' );
		$new['date']               = $columns['date'];
		return $new;
	}
);

add_action(
	'manage_agentic_form_entry_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'agentic_form_type' === $column ) {
			echo esc_html( ucfirst( (string) get_post_meta( $post_id, '_agentic_form_type', true ) ) );
			return;
		}

		if ( 'agentic_form_email' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_agentic_form_email', true ) );
			return;
		}

		if ( 'agentic_form_phone' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_agentic_form_phone', true ) );
			return;
		}

		if ( 'agentic_form_page' === $column ) {
			$page = get_post_meta( $post_id, '_agentic_form_page', true );
			if ( $page ) {
				$path = wp_parse_url( $page, PHP_URL_PATH );
				printf(
					'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
					esc_url( $page ),
					esc_html( $path ? $path : $page )
				);
			}
		}
	},
	10,
	2
);

/**
 * Store one submission, deduped by (type, email) so re-submitting the same
 * address doesn't pile up duplicate entries.
 *
 * @param string $type  Short form identifier, e.g. "newsletter".
 * @param string $email Already-validated email address.
 * @param string $page  URL the submission came from, for context.
 * @return int Post ID of the (new or existing) entry, or 0 on failure.
 */
if ( ! function_exists( 'agentic_record_form_entry' ) ) {
	function agentic_record_form_entry( $type, $email, $page = '' ) {
		$existing = get_posts(
			[
				'post_type'      => 'agentic_form_entry',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'numberposts'    => 1,
				'no_found_rows'  => true,
				'meta_query'     => [
					[
						'key'   => '_agentic_form_email',
						'value' => $email,
					],
					[
						'key'   => '_agentic_form_type',
						'value' => $type,
					],
				],
			]
		);

		if ( $existing ) {
			return $existing[0];
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => 'agentic_form_entry',
				'post_title'  => $email,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_agentic_form_email', $email );
		update_post_meta( $post_id, '_agentic_form_type', $type );
		update_post_meta( $post_id, '_agentic_form_page', $page );

		return $post_id;
	}
}

/**
 * Handles the newsletter-signup block's form when it has no third-party ESP
 * `action` configured (agentic-blocks/blocks/newsletter-signup/render.php).
 * Registered for both logged-in and logged-out submitters since this is a
 * public-facing form.
 */
add_action( 'admin_post_nopriv_agentic_newsletter_signup', 'agentic_handle_newsletter_signup' );
add_action( 'admin_post_agentic_newsletter_signup', 'agentic_handle_newsletter_signup' );

if ( ! function_exists( 'agentic_handle_newsletter_signup' ) ) {
	function agentic_handle_newsletter_signup() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$redirect = remove_query_arg( [ 'agentic_form', 'agentic_form_instance' ], $redirect );

		// Every rendered form carries its own instance ID (render.php's
		// $field_id) so that if a page has more than one newsletter-signup
		// block, only the one actually submitted shows a notice on redirect
		// — not every instance on the page. Echoed back unchanged, not
		// trusted for anything besides that display match.
		$instance = isset( $_POST['agentic_form_instance'] ) ? sanitize_key( wp_unslash( $_POST['agentic_form_instance'] ) ) : '';
		$redirect_args = [ 'agentic_form_instance' => $instance ];

		// Honeypot: real visitors never see or fill this field (hidden via
		// CSS), so a filled value means a bot. Pretend success and stop —
		// don't store the entry or email the admin over it.
		if ( ! empty( $_POST['agentic_hp_website'] ) ) {
			wp_safe_redirect( add_query_arg( array_merge( $redirect_args, [ 'agentic_form' => 'success' ] ), $redirect ) );
			exit;
		}

		$nonce_ok = isset( $_POST['agentic_newsletter_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agentic_newsletter_nonce'] ) ), 'agentic_newsletter_signup' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! $nonce_ok || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( array_merge( $redirect_args, [ 'agentic_form' => 'error' ] ), $redirect ) );
			exit;
		}

		$page = isset( $_POST['agentic_form_page'] ) ? esc_url_raw( wp_unslash( $_POST['agentic_form_page'] ) ) : '';

		$entry_id = agentic_record_form_entry( 'newsletter', $email, $page );

		if ( $entry_id ) {
			wp_mail(
				get_option( 'admin_email' ),
				sprintf(
					/* translators: %s: site name */
					__( 'New newsletter signup — %s', 'agentic' ),
					get_bloginfo( 'name' )
				),
				sprintf(
					/* translators: 1: email address, 2: page URL, 3: date */
					__( "A visitor subscribed to your newsletter.\n\nEmail: %1\$s\nPage: %2\$s\nDate: %3\$s", 'agentic' ),
					$email,
					$page ? $page : home_url( '/' ),
					current_time( 'mysql' )
				)
			);
		}

		wp_safe_redirect( add_query_arg( array_merge( $redirect_args, [ 'agentic_form' => 'success' ] ), $redirect ) );
		exit;
	}
}

/**
 * Handles the contact-form block's form when it has no third-party `action`
 * configured (agentic-blocks/blocks/contact-form/render.php). Registered
 * for both logged-in and logged-out submitters since this is a public-
 * facing form.
 *
 * Doesn't reuse agentic_record_form_entry(): that helper dedupes by
 * (type, email) on purpose, which is correct for a newsletter signup (one
 * subscription per address) but wrong here — a second contact message from
 * the same visitor is new content, not a duplicate, so it always gets its
 * own entry.
 */
add_action( 'admin_post_nopriv_agentic_contact_form', 'agentic_handle_contact_form' );
add_action( 'admin_post_agentic_contact_form', 'agentic_handle_contact_form' );

if ( ! function_exists( 'agentic_handle_contact_form' ) ) {
	function agentic_handle_contact_form() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$redirect = remove_query_arg( [ 'agentic_form', 'agentic_form_instance' ], $redirect );

		$instance      = isset( $_POST['agentic_form_instance'] ) ? sanitize_key( wp_unslash( $_POST['agentic_form_instance'] ) ) : '';
		$redirect_args = [ 'agentic_form_instance' => $instance ];

		// Honeypot — same convention as agentic_handle_newsletter_signup().
		if ( ! empty( $_POST['agentic_hp_website'] ) ) {
			wp_safe_redirect( add_query_arg( array_merge( $redirect_args, [ 'agentic_form' => 'success' ] ), $redirect ) );
			exit;
		}

		$nonce_ok = isset( $_POST['agentic_contact_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agentic_contact_nonce'] ) ), 'agentic_contact_form' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! $nonce_ok || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( array_merge( $redirect_args, [ 'agentic_form' => 'error' ] ), $redirect ) );
			exit;
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$page    = isset( $_POST['agentic_form_page'] ) ? esc_url_raw( wp_unslash( $_POST['agentic_form_page'] ) ) : '';

		$post_id = wp_insert_post(
			[
				'post_type'    => 'agentic_form_entry',
				'post_title'   => $name ? $name : $email,
				'post_content' => $message,
				'post_status'  => 'publish',
			],
			true
		);

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_agentic_form_email', $email );
			update_post_meta( $post_id, '_agentic_form_type', 'contact' );
			update_post_meta( $post_id, '_agentic_form_page', $page );
			update_post_meta( $post_id, '_agentic_form_phone', $phone );

			wp_mail(
				get_option( 'admin_email' ),
				sprintf(
					/* translators: %s: site name */
					__( 'New contact form message — %s', 'agentic' ),
					get_bloginfo( 'name' )
				),
				sprintf(
					/* translators: 1: name, 2: email, 3: phone, 4: message, 5: page URL, 6: date */
					__( "New contact form submission.\n\nName: %1\$s\nEmail: %2\$s\nPhone: %3\$s\n\nMessage:\n%4\$s\n\nPage: %5\$s\nDate: %6\$s", 'agentic' ),
					$name ? $name : '—',
					$email,
					$phone ? $phone : '—',
					$message,
					$page ? $page : home_url( '/' ),
					current_time( 'mysql' )
				)
			);
		}

		wp_safe_redirect( add_query_arg( array_merge( $redirect_args, [ 'agentic_form' => 'success' ] ), $redirect ) );
		exit;
	}
}
