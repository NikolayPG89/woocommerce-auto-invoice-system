<?php
/**
 * Invoice PDF template.
 *
 * Available:
 * - $data (invoice row)
 * - $settings (company settings)
 * - $order_items (line items)
 * - $payment_method_title
 * - $logo_path
 * - $billing_name
 * - $billing_company
 * - $billing_address
 * - $billing_city
 * - $billing_country
 * - $billing_eik
 * - $billing_vat
 */

defined( 'ABSPATH' ) || exit;

$company_name    = isset( $settings['company_name'] ) ? $settings['company_name'] : '';
$company_eik     = isset( $settings['company_eik'] ) ? $settings['company_eik'] : '';
$company_vat     = isset( $settings['company_vat'] ) ? $settings['company_vat'] : '';
$company_address = isset( $settings['company_address'] ) ? $settings['company_address'] : '';
$company_city    = isset( $settings['company_city'] ) ? $settings['company_city'] : '';
$company_country = isset( $settings['company_country'] ) ? $settings['company_country'] : '';

$recipient_name    = ! empty( $billing_company ) ? $billing_company : $billing_name;
$recipient_address = $billing_address;
$recipient_city    = $billing_city;
$recipient_country = $billing_country;
$recipient_eik     = $billing_eik;
$recipient_vat     = $billing_vat;

$invoice_number = $data['invoice_number'] ?? '';
$invoice_date   = $data['invoice_date'] ?? '';
$vat_rate       = isset( $data['vat_rate'] ) ? $data['vat_rate'] : '20.00';

$logo_src = '';
if ( ! empty( $logo_path ) && file_exists( $logo_path ) ) {
	$logo_src = 'file://' . $logo_path;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
		.header { margin-bottom: 10px; }
		.logo { text-align: right; margin-bottom: 6px; }
		.title { font-size: 22px; font-weight: bold; text-align: right; }
		.grid { width: 100%; border-collapse: collapse; }
		.grid th, .grid td { border: 1px solid #333; padding: 6px; vertical-align: top; }
		.box-title { font-weight: bold; background: #f2f2f2; text-transform: uppercase; }
		.label { font-weight: bold; }
		.meta { margin-top: 8px; }
		.meta td { border: none; padding: 2px 0; }
		.items { margin-top: 12px; width: 100%; border-collapse: collapse; }
		.items th, .items td { border: 1px solid #333; padding: 6px; }
		.items th { background: #f2f2f2; }
		.totals { width: 100%; margin-top: 12px; border-collapse: collapse; }
		.totals td { padding: 4px 0; }
		.totals .label { width: 70%; text-align: right; padding-right: 8px; }
		.payment { margin-top: 10px; border-top: 1px solid #333; padding-top: 6px; }
	</style>
</head>
<body>
	<div class="header">
		<div class="logo">
			<?php if ( ! empty( $logo_src ) ) : ?>
				<img src="<?php echo esc_attr( $logo_src ); ?>" style="max-width:150px;" alt="Logo">
			<?php endif; ?>
		</div>
		<div class="title">&#1060;&#1040;&#1050;&#1058;&#1059;&#1056;&#1040;</div>
	</div>

	<table class="grid">
		<tr>
			<td>
				<div class="box-title">&#1055;&#1054;&#1051;&#1059;&#1063;&#1040;&#1058;&#1045;&#1051;</div>
				<div><?php echo esc_html( $recipient_name ?: ( $data['client_name'] ?? '' ) ); ?></div>
				<?php if ( ! empty( $recipient_address ) ) : ?>
					<div><?php echo esc_html( $recipient_address ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $recipient_city ) ) : ?>
					<div><?php echo esc_html( $recipient_city ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $recipient_country ) ) : ?>
					<div><?php echo esc_html( $recipient_country ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $recipient_eik ) ) : ?>
					<div><span class="label">&#1045;&#1048;&#1050;:</span> <?php echo esc_html( $recipient_eik ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $recipient_vat ) ) : ?>
					<div><span class="label">&#1044;&#1044;&#1057; &#8470;:</span> <?php echo esc_html( $recipient_vat ); ?></div>
				<?php endif; ?>
			</td>
			<td>
				<div class="box-title">&#1044;&#1054;&#1057;&#1058;&#1040;&#1042;&#1063;&#1048;&#1050;</div>
				<div><?php echo esc_html( $company_name ); ?></div>
				<?php if ( ! empty( $company_address ) ) : ?>
					<div><?php echo esc_html( $company_address ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $company_city ) ) : ?>
					<div><?php echo esc_html( $company_city ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $company_country ) ) : ?>
					<div><?php echo esc_html( $company_country ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $company_eik ) ) : ?>
					<div><span class="label">&#1045;&#1048;&#1050;:</span> <?php echo esc_html( $company_eik ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $company_vat ) ) : ?>
					<div><span class="label">&#1044;&#1044;&#1057; &#8470;:</span> <?php echo esc_html( $company_vat ); ?></div>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<table class="meta">
		<tr>
			<td class="label">&#1053;&#1086;&#1084;&#1077;&#1088;:</td>
			<td><?php echo esc_html( $invoice_number ); ?></td>
		</tr>
		<tr>
			<td class="label">&#1044;&#1072;&#1090;&#1072; &#1085;&#1072; &#1080;&#1079;&#1076;&#1072;&#1074;&#1072;&#1085;&#1077;:</td>
			<td><?php echo esc_html( $invoice_date ); ?></td>
		</tr>
		<tr>
			<td class="label">&#1044;&#1072;&#1090;&#1072; &#1085;&#1072; &#1076;&#1072;&#1085;&#1098;&#1095;&#1085;&#1086; &#1089;&#1098;&#1073;&#1080;&#1090;&#1080;&#1077;:</td>
			<td><?php echo esc_html( $invoice_date ); ?></td>
		</tr>
	</table>

	<table class="items">
		<thead>
			<tr>
				<th style="width:40px;">&#8470;</th>
				<th>&#1040;&#1088;&#1090;&#1080;&#1082;&#1091;&#1083;</th>
				<th style="width:80px;">&#1050;&#1086;&#1083;&#1080;&#1095;&#1077;&#1089;&#1090;&#1074;&#1086;</th>
				<th style="width:80px;">&#1045;&#1076;. &#1094;&#1077;&#1085;&#1072;</th>
				<th style="width:60px;">&#1044;&#1044;&#1057; %</th>
				<th style="width:90px;">&#1057;&#1090;&#1086;&#1081;&#1085;&#1086;&#1089;&#1090;</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $order_items ) ) : ?>
				<?php $i = 1; ?>
				<?php foreach ( $order_items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $i ); ?></td>
						<td><?php echo esc_html( $item['name'] ); ?></td>
						<td><?php echo esc_html( $item['qty'] ); ?></td>
						<td><?php echo esc_html( number_format( (float) $item['unit_net'], 2, '.', '' ) ); ?></td>
						<td><?php echo esc_html( number_format( (float) $vat_rate, 2, '.', '' ) ); ?></td>
						<td><?php echo esc_html( number_format( (float) $item['total'], 2, '.', '' ) ); ?></td>
					</tr>
					<?php $i++; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6">&#1053;&#1103;&#1084;&#1072; &#1087;&#1088;&#1086;&#1076;&#1091;&#1082;&#1090;&#1080;.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<table class="totals">
		<tr>
			<td class="label">&#1044;&#1072;&#1085;&#1098;&#1095;&#1085;&#1072; &#1086;&#1089;&#1085;&#1086;&#1074;&#1072;:</td>
			<td><?php echo esc_html( $data['net_amount'] ?? '' ); ?> EUR</td>
		</tr>
		<tr>
			<td class="label">&#1053;&#1072;&#1095;&#1080;&#1089;&#1083;&#1077;&#1085; &#1044;&#1044;&#1057;:</td>
			<td><?php echo esc_html( $data['vat_amount'] ?? '' ); ?> EUR</td>
		</tr>
		<tr>
			<td class="label">&#1057;&#1091;&#1084;&#1072; &#1079;&#1072; &#1087;&#1083;&#1072;&#1097;&#1072;&#1085;&#1077;:</td>
			<td><strong><?php echo esc_html( $data['total_amount'] ?? '' ); ?> EUR</strong></td>
		</tr>
	</table>

	<?php if ( ! empty( $payment_method_title ) ) : ?>
		<div class="payment">
			<span class="label">&#1053;&#1072;&#1095;&#1080;&#1085; &#1085;&#1072; &#1087;&#1083;&#1072;&#1097;&#1072;&#1085;&#1077;:</span>
			<?php echo esc_html( $payment_method_title ); ?>
		</div>
	<?php endif; ?>

	<div class="payment" style="border-top:none;margin-top:6px;">
		<span class="label">&#1055;&#1086;&#1083;&#1091;&#1095;&#1072;&#1090;&#1077;&#1083;:</span>
		<?php echo esc_html( $recipient_name ?: ( $data['client_name'] ?? '' ) ); ?>
	</div>
	<div class="payment" style="border-top:none;margin-top:2px;">
		<span class="label">&#1057;&#1098;&#1089;&#1090;&#1072;&#1074;&#1080;&#1083;:</span>
		HITNAILS
	</div>
</body>
</html>
