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
 * @package   WC-Print-Invoices-Packing-Lists/Integrations/Subscriptions
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Integration class for WooCommerce Subscriptions
 *
 * @since 3.0.0
 */
class WC_PIP_Integration_Subscriptions {


	/**
	 * Add actions and filters
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		// Subscriptions 2.0+
		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

			// don't copy over PIP invoice meta from the original order to the subscription (subscription objects should not have an invoice)
			add_filter( 'wcs_subscription_meta', array( $this, 'remove_subscription_order_meta' ), 10, 3 );

			// don't copy over PIP invoice meta to subscription object during upgrade from 1.5.x to 2.0
			add_filter( 'wcs_upgrade_subscription_meta_to_copy', array( $this, 'remove_subscription_order_meta_during_upgrade' ) );

			// don't copy over PIP invoice meta from the subscription to the renewal order
			add_filter( 'wcs_renewal_order_meta', array( $this, 'remove_renewal_order_meta' ) );

			// remove actions meant for orders and not Subscriptions
			add_filter( 'woocommerce_order_actions', array( $this, 'remove_order_meta_box_actions' ), 20 );

		// Subscriptions 1.5.x
		} else {

			// don't copy over PIP invoice meta from the subscription to the renewal order
			add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'remove_renewal_order_meta_1_5' ), 10, 4 );
		}

	}


	/**
	 * Don't copy invoice meta to renewal orders from the WC_Subscription
	 * object. Generally the subscription object should not have any order-specific
	 * meta. This allows an invoice to be created for each renewal order.
	 *
	 * @since 3.0.0
	 * @param array $order_meta order meta to copy
	 * @return array
	 */
	public function remove_renewal_order_meta( $order_meta ) {

		foreach ( $order_meta as $index => $meta ) {

			if ( '_pip_invoice_number' === $meta['meta_key'] ) {
				unset( $order_meta[ $index ] );
			}
		}

		return $order_meta;
	}


	/**
	 * Don't copy over PIP invoice meta during the upgrade from Subscription 1.5.x to 2.0
	 *
	 * @since 3.0.0
	 * @param array $order_meta meta to copy
	 * @return array
	 */
	public function remove_subscription_order_meta_during_upgrade( $order_meta ) {

		if ( isset( $order_meta[ '_pip_invoice_number' ] ) ) {
			unset( $order_meta[ '_pip_invoice_number' ] );
		}

		return $order_meta;
	}


	/**
	 * Remove PIP invoice meta when creating a subscription object from an order at checkout.
	 * Subscriptions aren't true orders so they shouldn't have a FreshBooks invoice
	 *
	 * @since 3.0.0
	 * @param array $order_meta meta on order
	 * @param \WC_Subscription $to_order order meta is being copied to
	 * @param \WC_Order $from_order order meta is being copied from
	 * @return array
	 */
	public function remove_subscription_order_meta( $order_meta, $to_order, $from_order ) {

		// only when copying from an order to a subscription
		if ( $to_order instanceof WC_Subscription && $from_order instanceof WC_Order ) {

			foreach ( $order_meta as $index => $meta ) {

				if ( '_pip_invoice_number' === $meta['meta_key'] ) {
					unset( $order_meta[ $index ] );
				}
			}
		}

		return $order_meta;
	}


	/**
	 * Don't copy over PIP invoice order meta when creating a parent renewal order.
	 *
	 * @since 3.0.0
	 * @param string $order_meta_query MySQL query for pulling the metadata
	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
	 * @return string MySQL meta query for pulling the metadata, excluding data added by this gateway
	 */
	public function remove_renewal_order_meta_1_5( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {

		// guessing we don't want to carry the pip invoice number meta to either parent or child orders
		return $order_meta_query . " AND `meta_key` NOT IN ( '_pip_invoice_number' )";
	}


	/**
	 * Remove order actions
	 *
	 * Removes actions meant for orders and not subscriptions
	 *
	 * @since 3.0.5
	 * @param array $actions Associative array of actions
	 * @return array
	 */
	public function remove_order_meta_box_actions( $actions ) {
		global $post;

		$maybe_subscription = is_object( $post ) ? wcs_get_subscription( $post ) : false;

		if ( $maybe_subscription || ( is_object( $maybe_subscription ) && 'shop_subscription' === $maybe_subscription->order_type ) ) {

			if ( $orders_instance = wc_pip()->get_orders_instance() ) {

				if ( $order_actions = $orders_instance->get_actions() ) {

					foreach ( array_keys( $order_actions ) as $action ) {

						if ( isset( $actions[ $action ] ) ) {
							unset( $actions[ $action ] );
						}
					}
				}
			}
		}

		return $actions;
	}


}
