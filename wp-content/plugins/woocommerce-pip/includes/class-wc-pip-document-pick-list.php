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
 * @package   WC-Print-Invoices-Packing-Lists/Document/Pick-List
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * PIP Pick List for Shop Manager class
 *
 * Packing List document object for Shop Managers
 *
 * @since 3.0.0
 */
class WC_PIP_Document_Pick_List extends WC_PIP_Document_Packing_List {


	/**
	 * PIP Shop Manager Pick List document constructor
	 *
	 * @since 3.0.0
	 * @param array $args
	 */
	public function __construct( array $args ) {

		parent::__construct( $args );

		$this->type        = 'pick-list';
		$this->name        = __( 'Pick List', 'woocommerce-pip' );
		$this->name_plural = __( 'Pick Lists', 'woocommerce-pip' );

		$this->table_headers = array(
			'sku'      => __( 'SKU' , 'woocommerce-pip' ),
			'product'  => __( 'Product' , 'woocommerce-pip' ),
			'details'  => __( 'Details', 'woocommerce-pip' ),
			'quantity' => __( 'Quantity' , 'woocommerce-pip' ),
			/* translators: Placeholder: %s - weight measurement unit */
			'weight'   => sprintf( __( 'Total Weight (%s)' , 'woocommerce-pip' ), get_option( 'woocommerce_weight_unit' ) ),
			'id'       => '', // leave this blank
		);

		$this->column_widths = array(
			'sku'      => 18,
			'product'  => 30,
			'details'  => 25,
			'quantity' => 10,
			'weight'   => 17,
		);

		$this->show_shipping_address     = false;
		$this->show_billing_address      = false;
		$this->show_terms_and_conditions = false;
		$this->show_shipping_method      = false;
		$this->show_customer_note        = false;
		$this->show_customer_details     = false;
		$this->show_header               = false;
		$this->show_footer               = false;

		// Remove invoice number from document title
		add_filter( 'wc_pip_document_title', array( $this, 'get_document_title' ), 100 );

		// Filter the Packing List table rows
		add_filter( 'wc_pip_document_table_row_cells', array( $this, 'filter_table_row_cells' ), 1, 5 );
	}


	/**
	 * Change the document title in template
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_document_title() {

		// Site name - Pick List (Today's date)
		return sprintf( esc_html( '%1$s - %2$s (%3$s)' ), get_bloginfo( 'name' ), esc_html( $this->name ), date_i18n( wc_date_format(), time() ) );
	}


	/**
	 * Set the packing list table body cells contents
	 *
	 * @since 3.0.0
	 * @param array $row Table row cells as an associative array
	 * @param string $type WC_PIP_Document type
	 * @param int $item_id Order item id
	 * @param array $item Order item
	 * @param WC_Product $product Product object
	 * @return array Table row cells
	 */
	public function filter_table_row_cells( $row, $type, $item_id, $item, $product ) {

		if ( 'pick-list' === $type ) {

			return array(
				'sku'      => $this->get_order_item_sku_html( $product ),
				'product'  => $this->get_order_item_name_html( $product, $item ),
				'details'  => $this->get_order_item_meta_html( $item_id, $item, $product ),
				'quantity' => $this->get_order_item_quantity_html( $item_id, $item ),
				'weight'   => $this->get_order_item_weight_html( $item_id, $item, $product ),
				'id'       => $this->get_order_item_id_html( $item_id ),
			);
		}

		return $row;
	}


	/**
	 * Get the document template HTML
	 *
	 * @since 3.0.0
	 * @param array $args
	 */
	public function output_template( $args = array() ) {

		if ( ! $this->order instanceof WC_Order ) {
			return;
		}

		$template_args = wp_parse_args( $args, array(
			'document'  => $this,
			'order'     => $this->order,
			'order_id'  => $this->order_id,
			'order_ids' => $this->order_ids,
			'type'      => $this->type,
		) );

		$original_order = $this->order;

		wc_pip()->get_template( 'head', $template_args );

		if ( ! empty( $this->order_ids ) ) {

			wc_pip()->get_template( 'content/order-table-before', $template_args );
			wc_pip()->get_template( 'content/order-table', $template_args );

			// Documents for multiple orders
			foreach ( $this->order_ids as $order_id ) {

				$wc_order = wc_get_order( (int) $order_id );

				$template_args['order']    = $this->order = $wc_order;
				$template_args['order_id'] = $this->order_id = $wc_order->id;

				if ( $wc_order ) {
					wc_pip()->get_template( 'content/order-table-items', $template_args );
				}
			}

			// Restore the original order
			$template_args['order']    = $this->order = $original_order;
			$template_args['order_id'] = $this->order_id = $original_order->id;

			wc_pip()->get_template( 'content/order-table-after', $template_args );
		}

		wc_pip()->get_template( 'foot', $template_args );
	}


}
