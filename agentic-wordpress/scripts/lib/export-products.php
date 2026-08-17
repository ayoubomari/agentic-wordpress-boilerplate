<?php
/**
 * Exports the WooCommerce product catalog to CSV, using WooCommerce's own
 * exporter class (the same one behind Products -> Export in wp-admin), and
 * prints it base64-encoded between two markers so a shell script driving
 * `wp eval` can capture exact bytes over stdout with no temp-file or
 * container-filesystem assumptions on either end of a sync.
 *
 * Invoked via: wp eval "$(cat export-products.php)"
 * (see scripts/sync-products.sh)
 */
if ( ! class_exists( 'WC_Product_CSV_Exporter' ) ) {
	require_once WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php';
}

$exporter = new WC_Product_CSV_Exporter();
$exporter->set_page( 1 );
do {
	$exporter->generate_file();
} while ( 100 > $exporter->get_percent_complete() );

$csv = $exporter->get_headers_row_file() . $exporter->get_file();

echo "===AGENTIC-CSV-START===\n";
echo base64_encode( $csv ) . "\n";
echo "===AGENTIC-CSV-END===\n";
fwrite( STDERR, 'Exported ' . $exporter->get_total_exported() . " products.\n" );
