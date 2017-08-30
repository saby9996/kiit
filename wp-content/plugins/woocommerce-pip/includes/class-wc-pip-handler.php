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
 * @package   WC-Print-Invoices-Packing-Lists/Handler
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handler class
 *
 * Handles general PIP tasks
 *
 * @since 3.0.0
 */
class WC_PIP_Handler {


	/** @var array Shop manager capabilities besides Admin allowed to handle documents */
	private $can_manage_documents = array();


	/**
	 * Add hooks/filters
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		/**
		 * Filter lower capabilities allowed to manage documents
		 * i.e. print, email, from admin or front end
		 *
		 * @since 3.0.5
		 * @param array $capabilities
		 */
		$this->can_manage_documents = (array) apply_filters( 'wc_pip_can_manage_documents', array(
			'manage_woocommerce',
			'manage_woocommerce_orders',
			'edit_shop_orders',
		) );

		/**
		 * Toggle invoice number generation upon paid order
		 *
		 * @since 3.0.3
		 * @param bool $generate_invoice_number Default true
		 */
		if ( false !== apply_filters( 'wc_pip_generate_invoice_number_on_order_paid', true ) ) {

			// generate invoice number upon order status change to paid
			add_action( 'woocommerce_order_status_processing', array( $this, 'generate_invoice_number' ), 20 );
			add_action( 'woocommerce_order_status_completed',  array( $this, 'generate_invoice_number' ), 20 );
		}
	}


	/**
	 * Get user capabilities allowed to print or email documents
	 *
	 * @since 3.0.5
	 * @return array
	 */
	public function get_admin_capabilities() {
		return $this->can_manage_documents;
	}


	/**
	 * Check if current user can print a document
	 *
	 * @since 3.0.5
	 * @return bool
	 */
	public function current_admin_user_can_manage_documents() {

		// admin can always manage
		$can_manage = is_user_admin();

		if ( ! $can_manage ) {

			foreach ( $this->can_manage_documents as $capability ) {

				// stop as soon as there's at least one capability that grants management rights
				if ( $can_manage = current_user_can( $capability ) ) {
					break;
				}
			}
		}

		return $can_manage;
	}


	/**
	 * Generate a document invoice number
	 *
	 * Normally runs as a callback upon order status change to paid
	 * It will not generate a new one if already set
	 *
	 * @since 3.0.0
	 * @param $order_id
	 */
	public function generate_invoice_number( $order_id ) {

		$document = wc_pip()->get_document( 'invoice', array( 'order_id' => $order_id ) );

		if ( $document ) {

			$document->get_invoice_number();
		}
	}


}
