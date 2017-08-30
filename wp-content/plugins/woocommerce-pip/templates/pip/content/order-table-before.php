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
 * PIP Template Body before content
 *
 * @version 3.0.3
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

		?>
		<div id="order-<?php echo esc_attr( $order->id ); ?>" class="container">

			<header>

				<?php
					/**
					 * Fires before the document's header.
					 *
					 * @since 3.0.0
					 * @param string $type Document type
					 * @param string $action Current action running on document, one of 'print' or 'send_email'
					 * @param WC_PIP_Document $document Document object
					 * @param WC_Order $order Order object
					 */
					do_action( 'wc_pip_before_header', $type, $action, $document, $order );
				?>

				<div class="document-header <?php echo $type; ?>-header">

					<div class="head company-information">

						<?php $align_title = get_option( 'wc_pip_company_title_align', 'left' ); ?>

						<div class="company-title <?php echo empty( $align_title ) ? 'left' : $align_title; ?>">

							<?php
								$logo  = $document->get_company_logo();
								$title = ! empty( $logo ) ? $document->get_company_logo() : $document->get_company_name();
							?>

							<h1 class="title"><?php echo $document->get_company_link( $title ); ?></h1>

							<?php $subtitle = $document->get_company_extra_info(); ?>

							<?php if ( $subtitle ) : ?>
								<h5 class="company-subtitle align-<?php echo $align_title; ?>"><?php echo $subtitle; ?></h5>
							<?php endif; ?>
						</div>

						<?php $align_address = get_option( 'wc_pip_company_address_align' ); ?>

						<address class="company-address <?php echo empty( $align_address ) ? 'right' : $align_address; ?>">
							<p><?php echo $document->get_company_address(); ?></p>
						</address>

						<div class="clear"></div>
					</div>

					<?php

						/**
						 * Fires inside the document's header after company information.
						 *
						 * @since 3.0.0
						 * @param string $type Document type
						 * @param string $action Current action running on document, one of 'print' or 'email'
						 * @param WC_PIP_Document $document Document object
						 * @param WC_Order $order Order object
						 */
						do_action( 'wc_pip_header', $type, $action, $document, $order );

						if ( 'pick-list' === $type ) :

							$orders_count = max( 1, count( (array) $document->order_ids ) );

							/* translators: Placeholder: %d - orders count (one or many) */
							printf( '<h3 class="order-info">' . _n( 'List of items needed to process %d order.', 'List of items needed to process %d orders.', $orders_count, 'woocommerce-pip' ) . '</h3>', $orders_count );

							/* translators: Placeholders: %1$s - date, %2$s time */
							printf( '<em>' . __( 'Printed on: %1$s at %2$s', 'woocommerce-pip' ) . '</em>', date_i18n( wc_date_format(), time() ), date_i18n( wc_time_format(), current_time( 'timestamp' ) ) );

						endif;

					?>

					<div class="customer-addresses">

						<?php if ( $document->show_billing_address() ) : ?>

							<div class="column customer-address billing-address left">

								<h3><?php esc_html_e( 'Billing Address', 'woocommerce-pip' ); ?></h3>

								<address class="customer-addresss">
									<?php
										/**
										 * Filters the customer's billing address.
										 *
										 * @since 3.0.0
										 * @param string $billing_address The formatted billing address
										 * @param string $type WC_PIP_Document type
										 * @param WC_Order $order The WC Order object
										 */
										echo apply_filters( 'wc_pip_billing_address', $order->get_formatted_billing_address(), $type, $order );
									?>
								</address>
							</div>

						<?php endif; ?>

						<?php if ( $document->show_shipping_address() ) : ?>

							<div class="column customer-address shipping-address left">

								<h3><?php esc_html_e( 'Shipping Address', 'woocommerce-pip' ); ?></h3>

								<address class="customer-address">
									<?php
										/**
										 * Filters the customer's shipping address.
										 *
										 * @since 3.0.0
										 * @param string $shipping_address The formatted shipping address
										 * @param string $type WC_PIP_Document type
										 * @param WC_Order $order The WC Order object
										 */
										echo apply_filters( 'wc_pip_shipping_address', $order->get_formatted_shipping_address(), $type, $order );
									?>
								</address>
							</div>

						<?php endif; ?>

						<?php if ( $document->show_shipping_method() ) : ?>

							<div class="column shipping-method left">

								<h3><?php esc_html_e( 'Shipping Method', 'woocommerce-pip' ); ?></h3>

								<em class="shipping-method">
									<?php echo $document->get_shipping_method(); ?>
								</em>
							</div>

						<?php endif; ?>

						<div class="clear"></div>
					</div>

					<?php
						/**
						 * Fires after the customer's address is printed on the document.
						 *
						 * @since 3.0.0
						 * @param string $type Document type
						 * @param string $action Current action running on Document
						 * @param WC_PIP_Document $document Document object
						 * @param WC_Order $order Order object
						 */
						do_action( 'wc_pip_after_customer_addresses', $type, $action, $document, $order );
					?>

					<?php if ( $document->show_header() ) : ?>

						<div class="document-heading <?php echo $type; ?>-heading">
							<?php echo $document->get_header(); ?>
						</div>

					<?php endif; ?>

				</div>

				<?php
					/**
					 * Fires after the document's header.
					 *
					 * @since 3.0.0
					 * @param string $type Document type
					 * @param string $action Current action running on Document
					 * @param WC_PIP_Document $document Document object
					 * @param WC_Order $order Order object
					 */
					do_action( 'wc_pip_after_header', $type, $action, $document, $order );
				?>
			</header>

			<main class="document-body <?php echo $type; ?>-body">

				<?php
					/**
					 * Fires before the document's body (order table).
					 *
					 * @since 3.0.0
					 * @param string $type Document type
					 * @param string $action Current action running on Document
					 * @param WC_PIP_Document $document Document object
					 * @param WC_Order $order Order object
					 */
					do_action( 'wc_pip_before_body', $type, $action, $document, $order );
				?>

				<table class="order-table <?php echo $type; ?>-order-table">
					<?php
