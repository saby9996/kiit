<?php
/**
 * WooCommerce Print Invoices/Packing Lists
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Print
 * Invoices/Packing Lists to newer versions in the future. If you wish to
 * customize WooCommerce Print Invoices/Packing Lists for your needs please refer
 * to http://docs.woothemes.com/document/woocommerce-print-invoice-packing-list/
 *
 * @package   WC-Print-Invoices-Packing-Lists/Templates
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

/**
 * PIP Documents Styles Template
 *
 * @version 3.0.5
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Use this template to override styles used in PIP documents.
 * However, you can also add more styles from PIP settings page
 * or hooking `wc_pip_styles` action without copying and editing
 * over this template.
 */
?>
<style type="text/css">


	/* ==========*
	 * HTML TAGS *
	 * ==========*/

	html, body {
		background: #FFFFFF;
	}

	body {
		display: block;
		color: #000000;
		font: normal <?php echo get_option( 'wc_pip_body_font_size', '12' ); ?>px/130% Verdana, Arial, Helvetica, sans-serif;
		margin: 8px;
		-webkit-print-color-adjust: exact;
	}

	a {
		color: <?php echo get_option( 'wc_pip_link_color', '#000000' ); ?>;
	}

	hr {
		margin-top: 1em;
	}

	blockquote {
		border-left: 10px solid #DDD;
		color: #444444;
		font-style: italic;
		margin: 1.5em;
		padding-left: 10px;
	}

	h1, h2, h3, h4, h5, h6 {
		color: <?php echo get_option( 'wc_pip_headings_color', '#000000' ); ?>;
		line-height: 150%;
	}

	<?php $h_size = (int) get_option( 'wc_pip_heading_font_size', '28' ); $i = 0; ?>
	<?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $h ) : ?>
		<?php echo $h . ' { font-size: ' . ( $h_size - ( 4 * $i ) ) . 'px; } '; $i++; ?>
	<?php endforeach; ?>


	/* =============== *
	 * UTILITY CLASSES *
	 * =============== */

	.left {
		float: left;
	}

	.align-left {
		text-align: left;
	}

	.right {
		float: right;
	}

	.align-right {
		text-align: right;
	}

	.center {
		float: none;
		margin: 0 auto;
		text-align: center;
		width: 100%;
	}

	.align-center {
		text-align: center;
	}

	.clear {
		clear: both;
	}

	.container {
		background: #FFF;
		margin: 1em auto;
		max-width: 960px;
		padding: 2em;
	}


	/* ============= *
	 * ORDER DETAILS *
	 * ============= */

	.title a {
		font-size: <?php echo ( (int) get_option( 'wc_pip_heading_font_size', '28' ) + 4 ) . 'px'; ?>;
		font-weight: bold;
		text-decoration: none;
	}

	.title,
	.subtitle {
		margin: 0;
	}

	.left .logo {
		padding-right: 1em;
	}

	.right .logo {
		padding-left: 1em;
	}

	.company-information {
		margin-bottom: 3em;
	}

	.company-address {
		font-style: normal;
		padding-top: 1em;
	}

	.customer-addresses {
		margin-left: -15px;
		margin-right: -15px;
	}

	.customer-addresses .column {
		padding: 0 15px;
		width: 33.33333333%;
		-webkit-box-sizing: border-box;
		-moz-box-sizing: border-box;
		box-sizing: border-box;
	}

	.document-heading {
		margin: 2em 0;
	}

	.order-info {
		margin-bottom: 0;
	}

	.order-date {
		color: #666666;
		margin: 0;
	}

	<?php if ( 1 === (int) get_option( 'wc_pip_return_policy_fine_print' ) ) : ?>
		.terms-and-conditions {
			font-size: 90%;
			line-height: 120%;
		}
	<?php endif; ?>

	span.coupon {
		background: #F4F4F4;
		color: #333;
		font-family: monospace;
		padding: 2px 4px;
	}


	/* ===== *
	 * LISTS *
	 * ===== */

	dl {
		margin: 1em 0;
	}

	dl.variation {
		font-size: 0.85em;
		margin: 0;
	}

	dl.variation dt {
		float: left;
		margin: 0 5px 0 0;
	}

	dl.variation dd {
		display: inline;
		margin: 0;
	}

	dl.variation p {
		margin: 0;
	}


	/* ============ *
	 * ORDER TABLES *
	 * ============ */

	table {
		border-collapse: collapse;
		font: normal <?php echo get_option( 'wc_pip_body_font_size', '12' ); ?>px/130% Verdana, Arial, Helvetica, sans-serif;
		margin: 3em 0 2em;
		text-align: left;
		width: 100%;
	}

	table td,
	table th {
		background: #FFFFFF;
		border: 1px solid #DDDDDD;
		font-weight: normal;
		padding: 0.8em 1.2em;
		text-transform: none;
		vertical-align: top;
	}

	table th {
		font-weight: bold;
		-webkit-print-color-adjust: exact;
	}

	table thead.order-table-head th {
		background-color: <?php echo get_option( 'wc_pip_table_head_bg_color', '#333333' ); ?>;
		border-color: <?php echo get_option( 'wc_pip_table_head_bg_color', '#333333' ); ?>;
		color: <?php echo get_option( 'wc_pip_table_head_color', '#FFFFFF' ); ?>;
	}

	table tbody th a {
		color: #333333;
		font-weight: bold;
	}

	table tfoot td {
		border-color: #CCCCCC;
		border-width: 1px 0 0 0;
		border-style: solid;
		text-align: right;
	}

	table tbody tr.heading th {
		background-color: #666666;
		border-color: #666666;
		color: #FFFFFF;
	}

	table tbody tr.heading th.order-number a {
		color: #FFF;
		font-weight: bold;
		text-decoration: none;
	}

	table tbody tr.heading th.no-items {
		background-color: #A0A0A0;
		font-weight: 400;
	}

	table tbody tr.heading th.breadcrumbs {
		background-color: #D8D8D8;
		border-color: #D8D8D8;
		color: #666666;
		font-weight: normal;
	}

	table tbody tr.even,
	table tbody tr.even td {
		background-color: #F5F5F5;
	}

	tbody tr.odd,
	tbody tr.odd td {
		background-color: #FFFFFF;
	}

	thead th.id,
	tbody td.id,
	thead th.id > span,
	tbody td.id > span {
		border: 0;
		display: none;
		overflow: hidden;
		padding: 0;
		visibility: hidden;
	}

	.quantity,
	.total-quantity {
		text-align: center;
	}

	.price,
	.weight,
	.total-weight {
		text-align: right;
	}


	/* ============ *
	 * PRINT STYLES *
	 * ============ */

	@media print {

		/* Background is always white in print */
		html, body {
			background: #FFFFFF;
		}

		a {
			text-decoration: none;
		}

		/* Break pages when printing multiple documents */
		.container {
			page-break-after: always;
		}
		.container:last-child {
			page-break-after: auto;
		}

		table td,
		table th {
			padding: 0.4em 1.2em;
		}

		/* Print URL after link text */
		.document-heading a:after,
		.document-footer a:after {
			content: " (" attr(href) ")";
		}
	}

	<?php
		/**
		 * Fires inside the document's `<style>` element to allow for custom CSS.
		 *
		 * @since 3.0.0
		 */
		do_action( 'wc_pip_styles' );
	?>
</style>
