<?php
/**
 * Plugin Name: HNH Invoices System
 * Description: Custom invoices system for WordPress & WooCommerce.
 * Version: 1.0.0
 * Author: HNH
 */

defined( 'ABSPATH' ) || exit;

define( 'HNH_INVOICES_VERSION', '1.0.0' );
define( 'HNH_INVOICES_PATH', plugin_dir_path( __FILE__ ) );
define( 'HNH_INVOICES_URL', plugin_dir_url( __FILE__ ) );
define( 'HNH_INVOICES_OPTION', 'hnh_invoices_settings' );

function hnh_t( $s ) {
	return html_entity_decode( $s, ENT_QUOTES, 'UTF-8' );
}

$hnh_pdf_file = HNH_INVOICES_PATH . 'includes/invoice-pdf.php';
if ( file_exists( $hnh_pdf_file ) ) {
	require_once $hnh_pdf_file;
} else {
	function hnh_generate_invoice_pdf( $invoice_id ) {
		return '';
	}
}

register_activation_hook( __FILE__, 'hnh_invoices_activate' );
register_uninstall_hook( __FILE__, 'hnh_invoices_uninstall' );

add_action( 'woocommerce_order_status_completed', 'hnh_create_invoice_for_completed_order', 10, 1 );
add_action( 'init', 'hnh_invoices_register_endpoints' );
add_filter( 'query_vars', 'hnh_invoices_query_vars' );
add_filter( 'woocommerce_account_menu_items', 'hnh_invoices_account_menu_items' );
add_action( 'woocommerce_account_invoices_endpoint', 'hnh_invoices_account_endpoint' );

add_action( 'admin_menu', 'hnh_invoices_admin_menu' );
add_action( 'admin_init', 'hnh_invoices_register_settings' );
add_action( 'admin_enqueue_scripts', 'hnh_invoices_admin_assets' );

add_filter( 'cron_schedules', 'hnh_invoices_cron_schedules' );
add_action( 'hnh_invoices_monthly_csv', 'hnh_invoices_generate_monthly_csv' );
add_action( 'init', 'hnh_invoices_ensure_schedule' );

function hnh_invoices_get_settings() {
	$defaults = array(
		'company_name'    => hnh_t('&#1040;&#1084;&#1085;&#1077;&#1079;&#1080;&#1103; &#1041;&#1102;&#1090;&#1080; &#1045;&#1054;&#1054;&#1044;'),
		'company_eik'     => 'BG204089450',
		'company_vat'     => '',
		'company_address' => hnh_t('&#1078;&#1082;. &#1051;&#1102;&#1083;&#1080;&#1085;, &#1073;&#1083;. 367'),
		'company_city'    => hnh_t('&#1057;&#1086;&#1092;&#1080;&#1103;'),
		'company_country' => hnh_t('&#1041;&#1098;&#1083;&#1075;&#1072;&#1088;&#1080;&#1103;'),
		'company_logo_id' => 0,
		'email_enabled'   => 1,
	);

	$settings = get_option( HNH_INVOICES_OPTION, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return wp_parse_args( $settings, $defaults );
}

function hnh_generate_invoice_number() {
	$year        = gmdate( 'Y' );
	$counter_key = 'hnh_invoice_counter_' . $year;
	$lock_key    = $counter_key . '_lock';
	$lock_ttl    = 10;
	$max_wait    = 20;
	$attempts    = 0;

	while ( ! add_option( $lock_key, time(), '', 'no' ) ) {
		$lock_time = (int) get_option( $lock_key );
		if ( $lock_time > 0 && ( time() - $lock_time ) > $lock_ttl ) {
			delete_option( $lock_key );
			continue;
		}
		usleep( 200000 );
		$attempts++;
		if ( $attempts >= $max_wait ) {
			return '';
		}
	}

	if ( false === get_option( $counter_key, false ) ) {
		add_option( $counter_key, 0, '', 'no' );
	}

	$counter = (int) get_option( $counter_key, 0 );
	$counter++;

	$updated = update_option( $counter_key, $counter );
	delete_option( $lock_key );

	if ( ! $updated ) {
		return '';
	}

	$sequence = str_pad( (string) $counter, 6, '0', STR_PAD_LEFT );
	return 'INV-' . $year . '-' . $sequence;
}

function hnh_invoices_register_endpoints() {
	add_rewrite_endpoint( 'invoices', EP_ROOT | EP_PAGES );
}

function hnh_invoices_query_vars( $vars ) {
	$vars[] = 'invoices';
	return $vars;
}

function hnh_invoices_account_menu_items( $items ) {
	$items['invoices'] = hnh_t('&#1060;&#1072;&#1082;&#1090;&#1091;&#1088;&#1080;');
	return $items;
}

function hnh_invoices_account_endpoint() {
	if ( ! is_user_logged_in() ) {
		echo '<p>' . esc_html( hnh_t('&#1052;&#1086;&#1083;&#1103;, &#1074;&#1083;&#1077;&#1079;&#1090;&#1077; &#1074; &#1087;&#1088;&#1086;&#1092;&#1080;&#1083;&#1072; &#1089;&#1080;.') ) . '</p>';
		return;
	}

	global $wpdb;
	$user_id    = get_current_user_id();
	$table_name = $wpdb->prefix . 'hnh_invoices';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, invoice_number, invoice_date, total_amount, currency, pdf_path FROM {$table_name} WHERE created_by = %d ORDER BY id DESC",
			$user_id
		),
		ARRAY_A
	);

	if ( empty( $rows ) ) {
		echo '<p>' . esc_html( hnh_t('&#1053;&#1103;&#1084;&#1072; &#1085;&#1072;&#1084;&#1077;&#1088;&#1077;&#1085;&#1080; &#1092;&#1072;&#1082;&#1090;&#1091;&#1088;&#1080;.') ) . '</p>';
		return;
	}

	$uploads = wp_upload_dir();
	$baseurl = ! empty( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';

	echo '<table class="shop_table shop_table_responsive my_account_orders">';
	echo '<thead><tr><th>' . esc_html( hnh_t('&#1053;&#1086;&#1084;&#1077;&#1088;') ) . '</th><th>' . esc_html( hnh_t('&#1044;&#1072;&#1090;&#1072;') ) . '</th><th>' . esc_html( hnh_t('&#1057;&#1091;&#1084;&#1072;') ) . '</th><th>PDF</th></tr></thead><tbody>';

	foreach ( $rows as $row ) {
		if ( empty( $row['pdf_path'] ) ) {
			$row['pdf_path'] = hnh_invoices_ensure_pdf( (int) $row['id'] );
		}

		$pdf_link = '';
		if ( ! empty( $row['pdf_path'] ) && $baseurl ) {
			$pdf_link = esc_url( trailingslashit( $baseurl ) . ltrim( $row['pdf_path'], '/' ) );
		}

		echo '<tr>';
		echo '<td>' . esc_html( $row['invoice_number'] ) . '</td>';
		echo '<td>' . esc_html( $row['invoice_date'] ) . '</td>';
		echo '<td>' . esc_html( $row['total_amount'] ) . ' ' . esc_html( $row['currency'] ) . '</td>';
		if ( $pdf_link ) {
			echo '<td><a href="' . $pdf_link . '" target="_blank" rel="noopener">' . esc_html( hnh_t('&#1048;&#1079;&#1090;&#1077;&#1075;&#1083;&#1080;') ) . '</a></td>';
		} else {
			echo '<td>' . esc_html( hnh_t('&#8212;') ) . '</td>';
		}
		echo '</tr>';
	}

	echo '</tbody></table>';
}

function hnh_invoices_admin_menu() {
	add_submenu_page(
		'woocommerce',
		hnh_t('&#1060;&#1072;&#1082;&#1090;&#1091;&#1088;&#1080;'),
		hnh_t('&#1060;&#1072;&#1082;&#1090;&#1091;&#1088;&#1080;'),
		'manage_options',
		'hnh-invoices-list',
		'hnh_invoices_list_page'
	);

	add_submenu_page(
		'woocommerce',
		'HNH Invoices',
		'HNH Invoices',
		'manage_options',
		'hnh-invoices-settings',
		'hnh_invoices_settings_page'
	);
}

function hnh_invoices_ensure_pdf( $invoice_id ) {
	$invoice_id = (int) $invoice_id;
	if ( $invoice_id < 1 ) {
		return '';
	}

	if ( ! function_exists( 'hnh_generate_invoice_pdf' ) ) {
		return '';
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hnh_invoices';

	$pdf_path = hnh_generate_invoice_pdf( $invoice_id );
	if ( ! empty( $pdf_path ) ) {
		$wpdb->update(
			$table_name,
			array( 'pdf_path' => $pdf_path ),
			array( 'id' => $invoice_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	return $pdf_path;
}

function hnh_invoices_list_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hnh_invoices';

	$rows = $wpdb->get_results(
		"SELECT id, invoice_number, invoice_date, order_id, client_name, client_email, total_amount, currency, pdf_path FROM {$table_name} ORDER BY id DESC LIMIT 200",
		ARRAY_A
	);

	$uploads = wp_upload_dir();
	$baseurl = ! empty( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';

	echo '<div class="wrap">';
	echo '<h1>' . esc_html( hnh_t('&#1060;&#1072;&#1082;&#1090;&#1091;&#1088;&#1080;') ) . '</h1>';

	if ( empty( $rows ) ) {
		echo '<p>' . esc_html( hnh_t('&#1053;&#1103;&#1084;&#1072; &#1088;&#1077;&#1079;&#1091;&#1083;&#1090;&#1072;&#1090;&#1080;.') ) . '</p>';
		echo '</div>';
		return;
	}

	echo '<table class="widefat fixed striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html( hnh_t('&#8470;') ) . '</th>';
	echo '<th>' . esc_html( hnh_t('&#1044;&#1072;&#1090;&#1072;') ) . '</th>';
	echo '<th>' . esc_html( hnh_t('&#1055;&#1086;&#1088;&#1098;&#1095;&#1082;&#1072;') ) . '</th>';
	echo '<th>' . esc_html( hnh_t('&#1050;&#1083;&#1080;&#1077;&#1085;&#1090;') ) . '</th>';
	echo '<th>' . esc_html( hnh_t('&#1048;&#1084;&#1077;&#1081;&#1083;') ) . '</th>';
	echo '<th>' . esc_html( hnh_t('&#1057;&#1091;&#1084;&#1072;') ) . '</th>';
	echo '<th>PDF</th>';
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		if ( empty( $row['pdf_path'] ) ) {
			$row['pdf_path'] = hnh_invoices_ensure_pdf( (int) $row['id'] );
		}

		$pdf_link = '';
		if ( ! empty( $row['pdf_path'] ) && $baseurl ) {
			$pdf_link = esc_url( trailingslashit( $baseurl ) . ltrim( $row['pdf_path'], '/' ) );
		}

		echo '<tr>';
		echo '<td>' . esc_html( $row['invoice_number'] ) . '</td>';
		echo '<td>' . esc_html( $row['invoice_date'] ) . '</td>';
		echo '<td>' . esc_html( $row['order_id'] ) . '</td>';
		echo '<td>' . esc_html( $row['client_name'] ) . '</td>';
		echo '<td>' . esc_html( $row['client_email'] ) . '</td>';
		echo '<td>' . esc_html( $row['total_amount'] ) . ' ' . esc_html( $row['currency'] ) . '</td>';
		if ( $pdf_link ) {
			echo '<td><a href="' . $pdf_link . '" target="_blank" rel="noopener">' . esc_html( hnh_t('&#1048;&#1079;&#1090;&#1077;&#1075;&#1083;&#1080;') ) . '</a></td>';
		} else {
			echo '<td>' . esc_html( hnh_t('&#8212;') ) . '</td>';
		}
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '</div>';
}

function hnh_invoices_register_settings() {
	register_setting( 'hnh_invoices_settings_group', HNH_INVOICES_OPTION, 'hnh_invoices_sanitize_settings' );

	add_settings_section( 'hnh_invoices_company', hnh_t('&#1060;&#1080;&#1088;&#1084;&#1077;&#1085;&#1080; &#1076;&#1072;&#1085;&#1085;&#1080;'), '__return_false', 'hnh-invoices-settings' );

	add_settings_field( 'company_name', hnh_t('&#1048;&#1084;&#1077;'), 'hnh_invoices_field_text', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_name' ) );
	add_settings_field( 'company_eik', hnh_t('&#1045;&#1048;&#1050;'), 'hnh_invoices_field_text', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_eik' ) );
	add_settings_field( 'company_vat', hnh_t('&#1044;&#1044;&#1057; &#8470;'), 'hnh_invoices_field_text', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_vat' ) );
	add_settings_field( 'company_address', hnh_t('&#1040;&#1076;&#1088;&#1077;&#1089;'), 'hnh_invoices_field_text', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_address' ) );
	add_settings_field( 'company_city', hnh_t('&#1043;&#1088;&#1072;&#1076;'), 'hnh_invoices_field_text', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_city' ) );
	add_settings_field( 'company_country', hnh_t('&#1044;&#1098;&#1088;&#1078;&#1072;&#1074;&#1072;'), 'hnh_invoices_field_text', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_country' ) );
	add_settings_field( 'company_logo_id', hnh_t('&#1051;&#1086;&#1075;&#1086;'), 'hnh_invoices_field_logo', 'hnh-invoices-settings', 'hnh_invoices_company', array( 'key' => 'company_logo_id' ) );

	add_settings_section( 'hnh_invoices_email', hnh_t('&#1048;&#1084;&#1077;&#1081;&#1083;'), '__return_false', 'hnh-invoices-settings' );
	add_settings_field( 'email_enabled', hnh_t('&#1048;&#1079;&#1087;&#1088;&#1072;&#1097;&#1072;&#1085;&#1077; &#1085;&#1072; PDF &#1087;&#1086; &#1080;&#1084;&#1077;&#1081;&#1083;'), 'hnh_invoices_field_checkbox', 'hnh-invoices-settings', 'hnh_invoices_email', array( 'key' => 'email_enabled' ) );
}

function hnh_invoices_sanitize_settings( $input ) {
	$output = array();
	$output['company_name']    = isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : '';
	$output['company_eik']     = isset( $input['company_eik'] ) ? sanitize_text_field( $input['company_eik'] ) : '';
	$output['company_vat']     = isset( $input['company_vat'] ) ? sanitize_text_field( $input['company_vat'] ) : '';
	$output['company_address'] = isset( $input['company_address'] ) ? sanitize_text_field( $input['company_address'] ) : '';
	$output['company_city']    = isset( $input['company_city'] ) ? sanitize_text_field( $input['company_city'] ) : '';
	$output['company_country'] = isset( $input['company_country'] ) ? sanitize_text_field( $input['company_country'] ) : '';
	$output['company_logo_id'] = isset( $input['company_logo_id'] ) ? (int) $input['company_logo_id'] : 0;
	$output['email_enabled']   = ! empty( $input['email_enabled'] ) ? 1 : 0;

	return $output;
}

function hnh_invoices_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap">';
	echo '<h1>HNH Invoices</h1>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'hnh_invoices_settings_group' );
	do_settings_sections( 'hnh-invoices-settings' );
	submit_button();
	echo '</form>';
	echo '</div>';
}

function hnh_invoices_field_text( $args ) {
	$settings = hnh_invoices_get_settings();
	$key      = $args['key'];
	$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	printf(
		'<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
		esc_attr( HNH_INVOICES_OPTION ),
		esc_attr( $key ),
		esc_attr( $value )
	);
}

function hnh_invoices_field_checkbox( $args ) {
	$settings = hnh_invoices_get_settings();
	$key      = $args['key'];
	$checked  = ! empty( $settings[ $key ] ) ? 'checked' : '';
	printf(
		'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
		esc_attr( HNH_INVOICES_OPTION ),
		esc_attr( $key ),
		$checked,
		esc_html( hnh_t('&#1044;&#1072;') )
	);
}

function hnh_invoices_field_logo( $args ) {
	$settings = hnh_invoices_get_settings();
	$key      = $args['key'];
	$value    = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
	printf(
		'<input type="number" id="hnh_invoices_company_logo_id" name="%s[%s]" value="%d" class="small-text" />',
		esc_attr( HNH_INVOICES_OPTION ),
		esc_attr( $key ),
		$value
	);
	echo ' <button type="button" class="button hnh-logo-upload">' . esc_html( hnh_t('&#1050;&#1072;&#1095;&#1080; &#1083;&#1086;&#1075;&#1086;') ) . '</button>';
	echo ' <span id="hnh_invoices_company_logo_preview">ID: ' . esc_html( $value ) . '</span>';
}

function hnh_invoices_admin_assets( $hook ) {
	if ( 'woocommerce_page_hnh-invoices-settings' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	wp_add_inline_script(
		'jquery',
		"(function($){\n" .
		"  $(document).on('click','.hnh-logo-upload',function(e){\n" .
		"    e.preventDefault();\n" .
		"    var frame = wp.media({title:'" . hnh_t('&#1048;&#1079;&#1073;&#1077;&#1088;&#1080; &#1083;&#1086;&#1075;&#1086;') . "',button:{text:'" . hnh_t('&#1048;&#1079;&#1087;&#1086;&#1083;&#1079;&#1074;&#1072;&#1081;') . "'},multiple:false});\n" .
		"    frame.on('select',function(){\n" .
		"      var attachment = frame.state().get('selection').first().toJSON();\n" .
		"      $('#hnh_invoices_company_logo_id').val(attachment.id);\n" .
		"      $('#hnh_invoices_company_logo_preview').text('ID: ' + attachment.id);\n" .
		"    });\n" .
		"    frame.open();\n" .
		"  });\n" .
		"})(jQuery);\n"
	);
}

function hnh_create_invoice_for_completed_order( $order_id ) {
	static $running = false;
	if ( $running ) {
		return;
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return;
	}
	if ( empty( $order_id ) ) {
		return;
	}
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$running = true;

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		$running = false;
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'hnh_invoices';

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE order_id = %d LIMIT 1",
			$order_id
		)
	);
	if ( $existing ) {
		$running = false;
		return;
	}

	$billing_first   = $order->get_billing_first_name();
	$billing_last    = $order->get_billing_last_name();
	$billing_company = $order->get_billing_company();
	$billing_email   = $order->get_billing_email();
	$billing_phone   = $order->get_billing_phone();

	$vat_number = $order->get_meta( '_billing_vat', true );
	if ( empty( $vat_number ) ) {
		$vat_number = $order->get_meta( 'billing_vat', true );
	}
	if ( empty( $vat_number ) ) {
		$vat_number = $order->get_meta( 'vat_number', true );
	}
	if ( empty( $vat_number ) ) {
		$vat_number = $order->get_meta( 'billing_vat_number', true );
	}

	if ( ! empty( $billing_company ) ) {
		$client_name = $billing_company;
	} else {
		$client_name = trim( $billing_first . ' ' . $billing_last );
	}
	if ( '' === $client_name && ! empty( $billing_phone ) ) {
		$client_name = $billing_phone;
	}
	if ( '' === $client_name && ! empty( $billing_email ) ) {
		$client_name = $billing_email;
	}
	if ( '' === $client_name ) {
		$client_name = 'Guest';
	}

	$net_amount   = 0.0;
	$total_amount = 0.0;
	foreach ( $order->get_items( 'line_item' ) as $item ) {
		$net_amount   += (float) $item->get_subtotal();
		$total_amount += (float) $item->get_total();
	}

	$payment_method           = $order->get_payment_method();
	$invoice_shipping_methods = array( 'stripe', 'paypal' );
	$shipping_subtotal        = (float) $order->get_shipping_total();
	$shipping_tax             = (float) $order->get_shipping_tax();
	$shipping_total           = $shipping_subtotal + $shipping_tax;

	$vat_rate     = 20.00;
	$order_tax_total = (float) $order->get_total_tax();

	// Start from order total (gross). Remove shipping if payment method is not in the list.
	$gross_total = (float) $order->get_total();
	if ( ! in_array( $payment_method, $invoice_shipping_methods, true ) ) {
		$gross_total     = max( 0, $gross_total - $shipping_total );
		$order_tax_total = max( 0, $order_tax_total - $shipping_tax );
	}

	// If Woo taxes are available, trust them; otherwise compute VAT from gross total.
	if ( $order_tax_total > 0 ) {
		$vat_amount = $order_tax_total;
		$net_amount = $gross_total - $vat_amount;
	} else {
		$net_amount = ( $gross_total > 0 ) ? ( $gross_total / 1.20 ) : 0.0;
		$vat_amount = $gross_total - $net_amount;
	}

	$net_amount   = round( $net_amount, 2 );
	$vat_amount   = round( $vat_amount, 2 );
	$total_amount = round( $gross_total, 2 );
	$currency     = 'EUR';

	$invoice_number = hnh_generate_invoice_number();
	if ( '' === $invoice_number ) {
		$running = false;
		return;
	}

	$order_date   = $order->get_date_created();
	$invoice_date = $order_date ? $order_date->date( 'Y-m-d' ) : gmdate( 'Y-m-d' );

	$created_by = (int) $order->get_user_id();
	if ( $created_by < 1 ) {
		$created_by = 0;
	}

	$wpdb->insert(
		$table_name,
		array(
			'invoice_number' => $invoice_number,
			'invoice_date'   => $invoice_date,
			'type'           => 'order',
			'order_id'       => $order_id,
			'client_name'    => $client_name,
			'client_email'   => $billing_email,
			'eik'            => null,
			'vat_number'     => $vat_number,
			'net_amount'     => $net_amount,
			'vat_rate'       => $vat_rate,
			'vat_amount'     => $vat_amount,
			'total_amount'   => $total_amount,
			'vat_type'       => 'standard',
			'currency'       => $currency,
			'pdf_path'       => null,
			'created_by'     => $created_by,
			'created_at'     => current_time( 'mysql' ),
		),
		array(
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%f',
			'%f',
			'%f',
			'%f',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
		)
	);

	$invoice_id = (int) $wpdb->insert_id;
	if ( $invoice_id > 0 ) {
		$pdf_path = hnh_generate_invoice_pdf( $invoice_id );
		if ( ! empty( $pdf_path ) ) {
			$wpdb->update(
				$table_name,
				array( 'pdf_path' => $pdf_path ),
				array( 'id' => $invoice_id ),
				array( '%s' ),
				array( '%d' )
			);

			$settings = hnh_invoices_get_settings();
			if ( ! empty( $settings['email_enabled'] ) ) {
				hnh_invoices_send_email( $order, $pdf_path, $invoice_number );
			}
		}
	}

	$running = false;
}

function hnh_invoices_send_email( $order, $pdf_path, $invoice_number ) {
	if ( ! $order ) {
		return;
	}
	$to = $order->get_billing_email();
	if ( empty( $to ) ) {
		return;
	}
	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) {
		return;
	}
	$full_path = trailingslashit( $uploads['basedir'] ) . ltrim( $pdf_path, '/' );
	if ( ! file_exists( $full_path ) ) {
		return;
	}
	$subject = hnh_t('&#1042;&#1072;&#1096;&#1072;&#1090;&#1072; &#1092;&#1072;&#1082;&#1090;&#1091;&#1088;&#1072; ') . $invoice_number;
	$message = "" . hnh_t('&#1047;&#1076;&#1088;&#1072;&#1074;&#1077;&#1081;&#1090;&#1077;,') . "\n\n" . hnh_t('&#1055;&#1088;&#1080;&#1083;&#1072;&#1075;&#1072;&#1084;&#1077; &#1042;&#1072;&#1096;&#1072;&#1090;&#1072; &#1092;&#1072;&#1082;&#1090;&#1091;&#1088;&#1072;.') . "\n\n" . hnh_t('&#1055;&#1086;&#1079;&#1076;&#1088;&#1072;&#1074;&#1080;,') . "\n";
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	wp_mail( $to, $subject, $message, $headers, array( $full_path ) );
}

function hnh_invoices_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	hnh_invoices_register_endpoints();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'hnh_invoices_monthly_csv' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hnh_monthly', 'hnh_invoices_monthly_csv' );
	}
	if ( false === get_option( HNH_INVOICES_OPTION, false ) ) {
		add_option( HNH_INVOICES_OPTION, hnh_invoices_get_settings(), '', 'no' );
	}

	$table_name      = $wpdb->prefix . 'hnh_invoices';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		invoice_number VARCHAR(50) NOT NULL,
		invoice_date DATE NOT NULL,
		type VARCHAR(20) NOT NULL COMMENT 'order | manual',
		order_id BIGINT UNSIGNED NULL,
		client_name VARCHAR(255) NOT NULL,
		client_email VARCHAR(255) NOT NULL,
		eik VARCHAR(50) NULL,
		vat_number VARCHAR(50) NULL,
		net_amount DECIMAL(10,2) NOT NULL,
		vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
		vat_amount DECIMAL(10,2) NOT NULL,
		total_amount DECIMAL(10,2) NOT NULL,
		vat_type VARCHAR(50) NOT NULL DEFAULT 'standard',
		currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
		pdf_path TEXT NULL,
		created_by BIGINT UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY invoice_number (invoice_number)
	) {$charset_collate};";

	dbDelta( $sql );
}

function hnh_invoices_uninstall() {
	if ( ! defined( 'HNH_INVOICES_REMOVE_DATA' ) || true !== HNH_INVOICES_REMOVE_DATA ) {
		return;
	}
	$timestamp = wp_next_scheduled( 'hnh_invoices_monthly_csv' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'hnh_invoices_monthly_csv' );
	}
	global $wpdb;
	$table_name = $wpdb->prefix . 'hnh_invoices';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}

function hnh_invoices_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['hnh_monthly'] ) ) {
		$schedules['hnh_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'HNH Monthly',
		);
	}
	return $schedules;
}

function hnh_invoices_generate_monthly_csv() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'hnh_invoices';
	$start = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
	$end   = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE invoice_date BETWEEN %s AND %s ORDER BY id ASC",
			$start,
			$end
		),
		ARRAY_A
	);

	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) {
		return;
	}

	$dir = trailingslashit( $uploads['basedir'] ) . 'hnh-invoices/exports/';
	if ( ! wp_mkdir_p( $dir ) ) {
		return;
	}

	$filename = 'invoices-' . gmdate( 'Y-m', strtotime( 'last month' ) ) . '.csv';
	$path     = $dir . $filename;

	$handle = fopen( $path, 'w' );
	if ( ! $handle ) {
		return;
	}

	$headers = array(
		'InvoiceNumber',
		'InvoiceDate',
		'Type',
		'OrderID',
		'ClientName',
		'ClientEmail',
		'EIK',
		'VATNumber',
		'NetAmount',
		'VatRate',
		'VatAmount',
		'TotalAmount',
		'Currency',
	);

	fputcsv( $handle, $headers );

	foreach ( $rows as $row ) {
		fputcsv(
			$handle,
			array(
				$row['invoice_number'],
				$row['invoice_date'],
				$row['type'],
				$row['order_id'],
				$row['client_name'],
				$row['client_email'],
				$row['eik'],
				$row['vat_number'],
				$row['net_amount'],
				$row['vat_rate'],
				$row['vat_amount'],
				$row['total_amount'],
				$row['currency'],
			)
		);
	}

	fclose( $handle );
}

function hnh_invoices_ensure_schedule() {
	if ( ! wp_next_scheduled( 'hnh_invoices_monthly_csv' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hnh_monthly', 'hnh_invoices_monthly_csv' );
	}
}
