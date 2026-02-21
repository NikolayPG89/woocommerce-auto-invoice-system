<?php
/**
 * PDF generation for invoices.
 */

defined( 'ABSPATH' ) || exit;

// Load Dompdf without Composer (expects Dompdf in plugin/vendor/dompdf).
if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
	$dompdf_autoload = HNH_INVOICES_PATH . 'vendor/dompdf/autoload.inc.php';
	if ( file_exists( $dompdf_autoload ) ) {
		require_once $dompdf_autoload;
	}
}

use Dompdf\Dompdf;

/**
 * Generate invoice PDF and store it in uploads.
 *
 * @param int $invoice_id Invoice ID.
 * @return string Relative PDF path or empty string on failure.
 */
function hnh_generate_invoice_pdf( $invoice_id ) {
	global $wpdb;

	$invoice_id = (int) $invoice_id;
	if ( $invoice_id < 1 ) {
		return '';
	}

	$table_name = $wpdb->prefix . 'hnh_invoices';

	$invoice = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
			$invoice_id
		),
		ARRAY_A
	);

	if ( empty( $invoice ) ) {
		return '';
	}

	if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
		return '';
	}

	$template_path = HNH_INVOICES_PATH . 'templates/invoice-pdf.php';
	if ( ! file_exists( $template_path ) ) {
		return '';
	}

	$data = $invoice;
	$settings = function_exists( 'hnh_invoices_get_settings' ) ? hnh_invoices_get_settings() : array();

	$order_items           = array();
	$payment_method_title  = '';
	$billing_name          = '';
	$billing_company       = '';
	$billing_address       = '';
	$billing_city          = '';
	$billing_country       = '';
	$billing_eik           = '';
	$billing_vat           = '';
	$order_id              = ! empty( $invoice['order_id'] ) ? (int) $invoice['order_id'] : 0;
	if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$payment_method_title = $order->get_payment_method_title();
			$billing_name    = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$billing_company = $order->get_billing_company();
			$billing_address = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
			$billing_city    = $order->get_billing_city();
			$billing_country = $order->get_billing_country();
			$billing_eik     = $order->get_meta( '_billing_eik', true );
			$billing_vat     = $order->get_meta( '_billing_vat', true );
			if ( empty( $billing_vat ) ) {
				$billing_vat = $order->get_meta( 'billing_vat', true );
			}
			if ( empty( $billing_vat ) ) {
				$billing_vat = $order->get_meta( 'vat_number', true );
			}
			if ( empty( $billing_vat ) ) {
				$billing_vat = $order->get_meta( 'billing_vat_number', true );
			}
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$order_items[] = array(
					'name'     => $item->get_name(),
					'qty'      => (int) $item->get_quantity(),
					'unit_net' => (float) $item->get_subtotal() / max( 1, (int) $item->get_quantity() ),
					'net'      => (float) $item->get_subtotal(),
					'vat'      => (float) ( $item->get_total() - $item->get_subtotal() ),
					'total'    => (float) $item->get_total(),
				);
			}
		}
	}

	$logo_path = '';
	if ( ! empty( $settings['company_logo_id'] ) ) {
		$logo_path = get_attached_file( (int) $settings['company_logo_id'] );
	}

	ob_start();
	/** @noinspection PhpIncludeInspection */
	include $template_path;
	$html = ob_get_clean();

	if ( empty( $html ) ) {
		return '';
	}

	$options = new \Dompdf\Options();
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'chroot', ABSPATH );

	$dompdf = new Dompdf( $options );
	$dompdf->loadHtml( $html );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();

	$year = ! empty( $invoice['invoice_date'] ) ? (string) gmdate( 'Y', strtotime( $invoice['invoice_date'] ) ) : gmdate( 'Y' );

	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) {
		return '';
	}

	$relative_dir = 'hnh-invoices/' . $year . '/';
	$target_dir   = trailingslashit( $uploads['basedir'] ) . $relative_dir;

	if ( ! wp_mkdir_p( $target_dir ) ) {
		return '';
	}

	$filename      = 'invoice-' . $invoice_id . '.pdf';
	$full_path     = $target_dir . $filename;
	$relative_path = $relative_dir . $filename;

	$pdf_output = $dompdf->output();
	if ( false === file_put_contents( $full_path, $pdf_output ) ) {
		return '';
	}

	return $relative_path;
}
