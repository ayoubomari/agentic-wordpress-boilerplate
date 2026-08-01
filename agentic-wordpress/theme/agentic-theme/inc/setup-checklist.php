<?php
/**
 * Store setup checklist — wp-admin Dashboard widget.
 *
 * This boilerplate is meant to be cloned and handed off, so whoever clones it
 * needs a way to find out what still needs a human (credentials, live
 * accounts, a real domain) without reading CLAUDE.md first. This surfaces
 * exactly the "Owner-editable in wp-admin" list from CLAUDE.md, pinned to the
 * top of wp-admin's Home/Dashboard screen, and self-checks state wherever
 * that's reliably possible from code rather than just listing static text.
 *
 * Items that are pure business/environment config (a real domain, a live
 * shipping-carrier account, real tax nexus) can't be reliably detected, so
 * those stay static reminders rather than false "done" checks.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_dashboard_setup',
	function () {
		// $priority = 'high' alone is not enough: plugins load before the
		// theme, so WooCommerce's/WP Mail SMTP's own `wp_dashboard_setup`
		// callbacks already ran and claimed the front of the 'high' bucket by
		// the time a default-priority (10) callback here would run. Hooking
		// this at priority 1 makes this callback run first, so this widget is
		// the first thing appended to 'high' rather than appended after theirs.
		wp_add_dashboard_widget(
			'agentic_setup_checklist',
			__( 'Store setup checklist', 'agentic' ),
			'agentic_render_setup_checklist',
			null,
			null,
			'normal',
			'high'
		);
	},
	1
);

/**
 * @return array<int, array{label:string, status:string, description:string, link:string, link_label:string}>
 *         status is one of 'done', 'pending', 'optional'.
 */
function agentic_get_setup_checklist_items() {
	$items = [];

	// --- Payment gateway ------------------------------------------------
	$gateways = function_exists( 'WC' ) ? WC()->payment_gateways()->get_available_payment_gateways() : [];
	$items[]  = [
		'label'       => __( 'Payment gateway', 'agentic' ),
		'status'      => ! empty( $gateways ) ? 'done' : 'pending',
		'description' => ! empty( $gateways )
			? sprintf(
				/* translators: %s: comma-separated list of enabled gateway names */
				__( 'Enabled: %s', 'agentic' ),
				implode( ', ', wp_list_pluck( $gateways, 'title' ) )
			)
			: __( 'No payment method is enabled — customers cannot check out yet.', 'agentic' ),
		'link'        => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
		'link_label'  => __( 'Configure payments', 'agentic' ),
	];

	// --- Email deliverability (WP Mail SMTP) -----------------------------
	$mailer  = get_option( 'wp_mail_smtp' )['mail']['mailer'] ?? 'mail';
	$items[] = [
		'label'       => __( 'Email deliverability', 'agentic' ),
		'status'      => 'mail' !== $mailer ? 'done' : 'pending',
		'description' => 'mail' !== $mailer
			? sprintf(
				/* translators: %s: configured mailer name, e.g. "smtp" */
				__( 'WP Mail SMTP is sending via "%s".', 'agentic' ),
				$mailer
			)
			: __( 'WP Mail SMTP is installed but not configured — order emails may be marked as spam or never arrive.', 'agentic' ),
		'link'        => admin_url( 'admin.php?page=wp-mail-smtp' ),
		'link_label'  => __( 'Configure WP Mail SMTP', 'agentic' ),
	];

	// --- Backup destination (UpdraftPlus) --------------------------------
	$services = array_filter( (array) get_option( 'updraft_service', [] ) );
	$items[]  = [
		'label'       => __( 'Backup destination', 'agentic' ),
		'status'      => ! empty( $services ) ? 'done' : 'pending',
		'description' => ! empty( $services )
			? sprintf(
				/* translators: %s: comma-separated list of configured remote storage services */
				__( 'Remote storage configured: %s.', 'agentic' ),
				implode( ', ', (array) $services )
			)
			: __( 'UpdraftPlus is installed and scheduled, but has no remote storage destination — backups only exist on this server.', 'agentic' ),
		'link'        => admin_url( 'options-general.php?page=updraftplus' ),
		'link_label'  => __( 'Configure UpdraftPlus', 'agentic' ),
	];

	// --- Analytics / ad pixels (optional — see functions.php) -----------
	$has_ga4   = defined( 'AGENTIC_GA4_ID' ) && AGENTIC_GA4_ID;
	$has_pixel = defined( 'AGENTIC_META_PIXEL_ID' ) && AGENTIC_META_PIXEL_ID;
	$configured_bits = array_filter(
		[
			$has_ga4 ? __( 'GA4', 'agentic' ) : '',
			$has_pixel ? __( 'Meta Pixel', 'agentic' ) : '',
		]
	);
	$items[] = [
		'label'       => __( 'Analytics / ad pixels', 'agentic' ),
		'status'      => ( $has_ga4 || $has_pixel ) ? 'done' : 'optional',
		'description' => ( $has_ga4 || $has_pixel )
			? sprintf(
				/* translators: %s: comma-separated list of configured tracking tools */
				__( 'Configured: %s.', 'agentic' ),
				implode( ', ', $configured_bits )
			)
			: __( 'Optional — add AGENTIC_GA4_ID / AGENTIC_META_PIXEL_ID constants in wp-config.php if you want tracking. Nothing fires without them.', 'agentic' ),
		'link'        => '',
		'link_label'  => '',
	];

	// --- Yoast site representation + social profiles ---------------------
	// `company_or_person` defaults to 'company' out of the box even when
	// nothing has actually been filled in — the real signal that a human set
	// this is a non-empty name for whichever type is selected.
	$wpseo_titles = get_option( 'wpseo_titles', [] );
	$is_person    = 'person' === ( $wpseo_titles['company_or_person'] ?? '' );
	$has_rep      = ! empty( $wpseo_titles[ $is_person ? 'person_name' : 'company_name' ] ?? '' );
	$items[]      = [
		'label'       => __( 'Yoast site representation', 'agentic' ),
		'status'      => $has_rep ? 'done' : 'pending',
		'description' => $has_rep
			? __( 'Site representation and social profile URLs are set.', 'agentic' )
			: __( 'Not set — this feeds the Organization/Person schema Google reads. Set it under Yoast SEO → Settings → Site representation.', 'agentic' ),
		'link'        => admin_url( 'admin.php?page=wpseo_dashboard' ),
		'link_label'  => __( 'Configure Yoast SEO', 'agentic' ),
	];

	// --- Shipping ----------------------------------------------------------
	$has_shipping = false;
	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( ! empty( $zone['shipping_methods'] ) ) {
				$has_shipping = true;
				break;
			}
		}
	}
	$items[] = [
		'label'       => __( 'Shipping methods', 'agentic' ),
		'status'      => $has_shipping ? 'done' : 'pending',
		'description' => $has_shipping
			? __( 'At least one shipping zone has a method configured.', 'agentic' )
			: __( 'No shipping zone has a method configured — checkout cannot calculate shipping.', 'agentic' ),
		'link'        => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
		'link_label'  => __( 'Configure shipping', 'agentic' ),
	];

	// --- Tax -----------------------------------------------------------
	$tax_enabled = 'yes' === get_option( 'woocommerce_calc_taxes' );
	$has_rates   = $tax_enabled && class_exists( 'WC_Tax' ) && ! empty( WC_Tax::get_rates() );
	$items[]     = [
		'label'       => __( 'Tax rates', 'agentic' ),
		'status'      => ! $tax_enabled ? 'optional' : ( $has_rates ? 'done' : 'pending' ),
		'description' => ! $tax_enabled
			? __( 'Tax calculation is off. Turn it on and add real rates/nexus before launch if you need to charge tax.', 'agentic' )
			: ( $has_rates
				? __( 'Tax calculation is on and at least one rate is configured.', 'agentic' )
				: __( 'Tax calculation is on but no rates are configured yet.', 'agentic' ) ),
		'link'        => admin_url( 'admin.php?page=wc-settings&tab=tax' ),
		'link_label'  => __( 'Configure tax', 'agentic' ),
	];

	// --- Sample content cleanup -----------------------------------------
	$sample_slugs      = [ 'sample-product', 'everyday-tote', 'ceramic-mug', 'linen-apron' ];
	$remaining_samples = 0;
	foreach ( $sample_slugs as $slug ) {
		if ( get_posts(
			[
				'post_type'      => 'product',
				'name'           => $slug,
				'post_status'    => 'publish',
				'numberposts'    => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		) ) {
			++$remaining_samples;
		}
	}
	$items[] = [
		'label'       => __( 'Sample products', 'agentic' ),
		'status'      => $remaining_samples > 0 ? 'pending' : 'done',
		'description' => $remaining_samples > 0
			? sprintf(
				/* translators: %d: number of placeholder products still published */
				_n(
					'%d placeholder product is still published — delete it before launch.',
					'%d placeholder products are still published — delete them before launch.',
					$remaining_samples,
					'agentic'
				),
				$remaining_samples
			)
			: __( 'No placeholder products remain.', 'agentic' ),
		'link'        => admin_url( 'edit.php?post_type=product' ),
		'link_label'  => __( 'View products', 'agentic' ),
	];

	// --- Domain & SSL ----------------------------------------------------
	$home  = home_url();
	$local = (bool) preg_match( '/localhost|127\.0\.0\.1|\.local\b/i', $home );
	$items[] = [
		'label'       => __( 'Domain & SSL', 'agentic' ),
		'status'      => $local ? 'pending' : 'done',
		'description' => $local
			? sprintf(
				/* translators: %s: current site URL */
				__( 'Still on a local/dev URL (%s). Point a real domain and enable SSL before launch.', 'agentic' ),
				$home
			)
			: sprintf(
				/* translators: %s: current site URL */
				__( 'Site URL looks live: %s. Double-check SSL is enforced.', 'agentic' ),
				$home
			),
		'link'        => admin_url( 'options-general.php' ),
		'link_label'  => __( 'General settings', 'agentic' ),
	];

	return $items;
}

function agentic_render_setup_checklist() {
	$items   = agentic_get_setup_checklist_items();
	$pending = array_filter( $items, static fn( $item ) => 'pending' === $item['status'] );

	$icons = [
		'done'     => [ 'dashicons-yes-alt', '#00a32a', __( 'Done', 'agentic' ) ],
		'pending'  => [ 'dashicons-warning', '#d63638', __( 'Needs attention', 'agentic' ) ],
		'optional' => [ 'dashicons-info-outline', '#787c82', __( 'Optional', 'agentic' ) ],
	];
	?>
	<p>
		<?php
		if ( empty( $pending ) ) {
			esc_html_e( 'Everything on this list is either configured or optional. Nice.', 'agentic' );
		} else {
			printf(
				/* translators: %d: number of items still needing attention */
				esc_html(
					_n(
						'%d item still needs attention before this store is ready to launch.',
						'%d items still need attention before this store is ready to launch.',
						count( $pending ),
						'agentic'
					)
				),
				count( $pending )
			);
		}
		?>
	</p>
	<ul style="margin:0;">
		<?php foreach ( $items as $item ) : ?>
			<?php list( $dashicon, $color, $status_label ) = $icons[ $item['status'] ]; ?>
			<li style="padding:8px 0;border-top:1px solid #dcdcde;">
				<span class="dashicons <?php echo esc_attr( $dashicon ); ?>" style="color:<?php echo esc_attr( $color ); ?>;margin-right:4px;" title="<?php echo esc_attr( $status_label ); ?>"></span>
				<strong><?php echo esc_html( $item['label'] ); ?></strong>
				<br />
				<span style="color:#50575e;"><?php echo esc_html( $item['description'] ); ?></span>
				<?php if ( $item['link'] ) : ?>
					— <a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p style="margin-bottom:0;">
		<?php
		printf(
			/* translators: %s: link to CLAUDE.md context */
			wp_kses(
				__( 'Full context for every item: <code>CLAUDE.md</code> → "Owner-editable in wp-admin".', 'agentic' ),
				[ 'code' => [] ]
			)
		);
		?>
	</p>
	<?php
}
