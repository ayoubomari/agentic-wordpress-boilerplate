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
 * Deliberately not a forms plugin: the only real form in this boilerplate is
 * the newsletter block, and a full forms plugin (entries UI, spam filtering,
 * conditional logic, its own DB tables) is a lot of surface area to pull in
 * for "store an email + notify admin" — same call as the GA4/Meta pixel
 * section in functions.php. `agentic_record_form_entry()` below is written
 * as a small reusable API so a future contact-form (or similar) block can
 * call the same storage without duplicating this layer.
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
				'supports'        => [ 'title' ],
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
		$new           = [];
		$new['cb']     = $columns['cb'];
		$new['title']  = __( 'Email', 'agentic' );
		$new['agentic_form_type'] = __( 'Form', 'agentic' );
		$new['agentic_form_page'] = __( 'Page', 'agentic' );
		$new['date']   = $columns['date'];
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
