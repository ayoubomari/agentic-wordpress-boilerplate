<?php
/**
 * Upserts a WooCommerce product-export CSV (as produced by
 * export-products.php) into this site, matched by SKU only — never by the
 * source site's raw post ID, which is only meaningful within one database
 * and is unsafe to trust across two independent WordPress installs (it can
 * silently collide with unrelated content on the target, or fail outright
 * if that ID no longer maps to anything).
 *
 * WooCommerce's own admin CSV importer (WC_Product_CSV_Importer) was tried
 * first and dropped: it force-deletes anything still in its internal
 * "importing" placeholder status at the end of a batch, which its wp-admin
 * screen only avoids because the browser drives it through several AJAX
 * steps. Driven as a single headless call it silently lost a product in
 * testing — exactly the "recreate a product that's missing on the target"
 * case this script exists for. This is a small, transparent replacement
 * built on WooCommerce's public WC_Product API instead, so failures are
 * loud (WP_CLI::warning) rather than silent. Simple products only — this
 * boilerplate doesn't seed variable/grouped/external products, and neither
 * does this script (see /product-feed.xml in functions.php for the same
 * scope decision).
 *
 * Expects a AGENTIC_SYNC_CSV_B64 constant (base64 of the CSV bytes) defined
 * before this file runs — see scripts/sync-products.sh, which prepends that
 * define() before passing this file's contents to `wp eval`.
 */
if ( ! defined( 'AGENTIC_SYNC_CSV_B64' ) ) {
	WP_CLI::error( 'AGENTIC_SYNC_CSV_B64 not defined — run this via scripts/sync-products.sh, not directly.' );
}

$csv = base64_decode( AGENTIC_SYNC_CSV_B64 );
$fh  = fopen( 'php://memory', 'r+' );
fwrite( $fh, $csv );
rewind( $fh );

$header = fgetcsv( $fh );
// The exporter writes a UTF-8 BOM on the first header cell — strip it.
$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );

$created = 0;
$updated = 0;
$failed  = array();

/**
 * Resolves (creating if missing) a product_cat term chain like "Skin >
 * Cleansers" — matches WooCommerce's own CSV category format — and returns
 * the deepest term's ID.
 */
function agentic_sync_resolve_category_ids( $categories_field ) {
	$ids = array();
	foreach ( explode( ',', $categories_field ) as $path ) {
		$path = trim( $path );
		if ( '' === $path ) {
			continue;
		}
		$parent = 0;
		foreach ( array_map( 'trim', explode( '>', $path ) ) as $name ) {
			$term = get_term_by( 'name', $name, 'product_cat' );
			if ( $term ) {
				$term_id = $term->term_id;
			} else {
				$result  = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
				$term_id = is_wp_error( $result ) ? 0 : $result['term_id'];
			}
			if ( $term_id ) {
				$parent = $term_id;
			}
		}
		if ( $parent ) {
			$ids[] = $parent;
		}
	}
	return $ids;
}

/**
 * Resolves each image URL to a local attachment ID, reusing an existing
 * attachment for that exact URL if this site already has one (so re-running
 * a sync doesn't re-download and duplicate media every time) and downloading
 * it otherwise. A failed download is warned about and skipped, not fatal —
 * one bad image shouldn't abort the whole sync.
 */
function agentic_sync_resolve_image_ids( $images_field ) {
	$ids = array();
	foreach ( explode( ',', $images_field ) as $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			continue;
		}
		$existing = attachment_url_to_postid( $url );
		if ( $existing ) {
			$ids[] = $existing;
			continue;
		}
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$id = media_sideload_image( $url, 0, null, 'id' );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "Image download failed for $url: " . $id->get_error_message() );
			continue;
		}
		$ids[] = $id;
	}
	return $ids;
}

while ( ( $row = fgetcsv( $fh ) ) !== false ) {
	$data = array_combine( $header, $row );
	$sku  = trim( $data['SKU'] ?? '' );

	if ( '' === $sku ) {
		$failed[] = 'Row with no SKU skipped: ' . ( $data['Name'] ?? '(unnamed)' );
		continue;
	}
	if ( 'simple' !== ( $data['Type'] ?? 'simple' ) ) {
		$failed[] = "SKU $sku: only 'simple' products are supported, got '{$data['Type']}'";
		continue;
	}

	$existing_id = wc_get_product_id_by_sku( $sku );
	$is_update   = (bool) $existing_id;
	$product     = $is_update ? wc_get_product( $existing_id ) : new WC_Product_Simple();

	$product->set_sku( $sku );
	$product->set_name( $data['Name'] ?? '' );
	$product->set_status( '1' === ( $data['Published'] ?? '1' ) ? 'publish' : 'draft' );
	$product->set_short_description( $data['Short description'] ?? '' );
	$product->set_description( $data['Description'] ?? '' );
	$product->set_regular_price( $data['Regular price'] ?? '' );
	$product->set_sale_price( $data['Sale price'] ?? '' );

	$product->set_stock_status( '1' === ( $data['In stock?'] ?? '1' ) ? 'instock' : 'outofstock' );
	if ( '' !== ( $data['Stock'] ?? '' ) ) {
		$product->set_manage_stock( true );
		$product->set_stock_quantity( (int) $data['Stock'] );
	}

	if ( ! empty( $data['Categories'] ) ) {
		$product->set_category_ids( agentic_sync_resolve_category_ids( $data['Categories'] ) );
	}

	if ( ! empty( $data['Images'] ) ) {
		$image_ids = agentic_sync_resolve_image_ids( $data['Images'] );
		if ( $image_ids ) {
			$product->set_image_id( $image_ids[0] );
			$product->set_gallery_image_ids( array_slice( $image_ids, 1 ) );
		}
	}

	if ( ! $product->save() ) {
		$failed[] = "SKU $sku: save() failed";
		continue;
	}
	$is_update ? ++$updated : ++$created;
}
fclose( $fh );

WP_CLI::log( "created: $created" );
WP_CLI::log( "updated: $updated" );
foreach ( $failed as $f ) {
	WP_CLI::warning( $f );
}
