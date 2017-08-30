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
 * @package   WC-Print-Invoices-Packing-Lists/Document/Packing-List
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * PIP Packing List class
 *
 * Packing List document object
 *
 * @since 3.0.0
 */
class WC_PIP_Document_Packing_List extends WC_PIP_Document {


	/** @var bool Whether to hide virtual items from list */
	protected $hide_virtual_items = false;


	/**
	 * PIP Packing List document constructor
	 *
	 * @since 3.0.0
	 * @param array $args
	 */
	public function __construct( array $args ) {

		parent::__construct( $args );

		$this->type        = 'packing-list';
		$this->name        = __( 'Packing List', 'woocommerce-pip' );
		$this->name_plural = __( 'Packing Lists', 'woocommerce-pip' );

		$this->table_headers = array(
			'sku'      => __( 'SKU' , 'woocommerce-pip' ),
			'product'  => __( 'Product' , 'woocommerce-pip' ),
			'quantity' => __( 'Quantity' , 'woocommerce-pip' ),
			'weight'   => __( 'Total Weight' , 'woocommerce-pip' ),
			'id'       => '', // leave this blank
		);

		$this->column_widths = array(
			'sku'      => 25,
			'product'  => 50,
			'quantity' => 10,
			'weight'   => 15,
		);

		$this->show_billing_address      = false;
		$this->show_shipping_address     = true;
		$this->show_shipping_method      = true;
		$this->show_header               = false;
		$this->show_footer               = 'yes' === get_option( 'wc_pip_packing_list_show_footer', 'no' );
		$this->show_terms_and_conditions = 'yes' === get_option( 'wc_pip_packing_list_show_terms_and_conditions', 'no' );
		$this->show_customer_details     = 'yes' === get_option( 'wc_pip_packing_list_show_customer_details', 'no' );
		$this->show_customer_note        = 'yes' === get_option( 'wc_pip_packing_list_show_customer_note', 'yes' );
		$this->hide_virtual_items        = 'yes' === get_option( 'wc_pip_packing_list_exclude_virtual_items', 'no' );

		// Maybe subtract hidden items from order items count
		add_filter( 'wc_pip_order_items_count', array( $this, 'filter_order_items_count' ), 100, 2 );

		// Customize document header output
		add_action( 'wc_pip_header', array( $this, 'document_header' ), 1, 4 );

		// Do not output virtual items in template tables
		add_filter( 'wc_pip_document_table_row_item_data', array( $this, 'exclude_order_items' ), 1, 3 );

		// Filter the output of items in table rows
		add_filter( 'wc_pip_document_table_rows', array( $this, 'add_table_rows_headings' ), 40, 2 );
	}


	/**
	 * Whether an intangible item should be hidden
	 *
	 * @since 3.0.0
	 * @param array $item WC_Order item
	 * @return bool Default false (do not hide)
	 */
	private function maybe_hide_virtual_item( $item ) {

		if ( ! is_object( $this->order ) ) {
			return $item;
		}

		$product = $this->order->get_product_from_item( $item );

		return $product ? ( true === $this->hide_virtual_items && true === $product->is_virtual() ) : false;
	}


	/**
	 * Filter the order items count
	 * if excluding virtual or downloadable items
	 *
	 * @since 3.0.0
	 * @param int $count
	 * @param array $items
	 * @return int
	 */
	public function filter_order_items_count( $count, $items ) {

		// Filter only if we are hiding virtual products in list
		if ( $items && true === $this->hide_virtual_items ) {

			$count = 0;

			foreach ( $items as $item_id => $item ) {

				$refund_qty = (float) $this->order->get_qty_refunded_for_item( $item_id );
				$item_qty   = isset( $item['qty'] ) ? max( 0, (float) $item['qty'] ) : 1;
				$qty        = max( 0, $item_qty - $refund_qty );

				// Add to count only if not a virtual item
				if ( ! $this->maybe_hide_virtual_item( $item ) ) {
					$count += ( 1 * $qty );
				}
			}

			return $count;
		}

		return $count;
	}


	/**
	 * Document header
	 *
	 * @since 3.0.0
	 * @param string $type Document type
	 * @param string $action Document action
	 * @param WC_PIP_Document $document Document object
	 * @param WC_Order $order Order object
	 */
	public function document_header( $type, $action, $document, $order ) {

		// prevent duplicating this content in bulk actions
		if ( 'packing-list' !== $type || ( ( (int) $order->id !== (int) $this->order_id ) && has_action( 'wc_pip_header', array( $this, 'document_header' ) ) ) ) {
			return;
		}

		$view_order_url = is_admin() && 'send_email' !== $action ? admin_url( 'post.php?post=' . $order->id . '&action=edit' ) : wc_get_endpoint_url( 'view-order', $order->id, get_permalink( wc_get_page_id( 'myaccount' ) ) );
		$invoice_number = $document->get_invoice_number();
		$order_number   = $order->get_order_number();

		// note: this is deliberately loose, do not use !== to compare invoice number and order number
		if ( 'yes' !== get_option( 'wc_pip_use_order_number', 'no' ) || $invoice_number != $order_number ) {
			/* translators: Placeholders: %1$s - invoice number, %2$s - order number */
			$heading = sprintf( '<h3 class="order-info">' . esc_html__( 'Packing List for invoice %1$s (order %2$s)', 'woocommerce-pip') . '</h3>', $invoice_number, '<a href="' . $view_order_url . '" target="_blank">' . $order_number . '</a>' );
		} else {
			/* translators: Placeholder: %s urder number */
			$heading = sprintf( '<h3 class="order-info">' . esc_html__( 'Packing List for order %s', 'woocommerce-pip' ) . '</h3>', '<a href="' . $view_order_url . '" target="_blank">' . $order_number . '</a>' );
		}

		/** This filter is documented in includes/class-wc-pip-document-invoice.php */
		echo wc_pip_parse_merge_tags( apply_filters( 'wc_pip_document_heading', $heading, $type, $action, $order ), $type, $order );
	}


	/**
	 * Exclude virtual/downloadable items from packing lists
	 * if set in settings options
	 *
	 * @since 3.0.0
	 * @param array $item_data
	 * @param array $item WC_Order item meta
	 * @param WC_Product $product Product object
	 * @return array
	 */
	public function exclude_order_items( $item_data, $item, $product ) {

		/**
		 * Filters if an order item should be excluded from the packing list.
		 *
		 * @since 3.0.0
		 * @param bool $exclude Whether to exclude this product to be listed in packing list, default false (show)
		 * @param WC_Product $product
		 * @param array $item WC_Order item meta
		 * @param array $item_data
		 */
		$exclude = apply_filters( 'wc_pip_packing_list_exclude_item', false, $product, $item, $item_data );

		if ( in_array( true, array( $exclude, $this->maybe_hide_virtual_item( $item ) ), true ) ) {
			$item_data = array();
		}

		return $item_data;
	}


	/**
	 * Get table group breadcrumb
	 *
	 * Format the product category to get a link to product category page
	 * and the parent category product category page
	 *
	 * @since 3.0.0
	 * @param int $term_id WP_Term id
	 * @return string HTML
	 */
	public function get_table_order_items_group_breadcrumb( $term_id ) {

		$term = get_term( $term_id, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$urls = array( get_term_link( $term, 'product_cat' ) => $term->name );

		if ( isset( $term->parent ) && $term->parent > 0 ) {

			$parent_term = get_term( $term->parent, 'product_cat' );
			$parent_link = get_term_link( $parent_term, 'product_cat' );

			if ( $parent_link && ! is_wp_error( $parent_link ) ) {
				/** @type string $parent_link */
				$urls[ $parent_link ] = $parent_term->name;
			}
		}

		$crumbs = array();

		foreach ( $urls as $url => $term_name ) {
			$crumbs[] = '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $term_name ) . '</a>';
		}

		return implode( '&nbsp; &gt; &nbsp;', array_reverse( $crumbs ) );
	}


	/**
	 * Add headings to table rows items
	 *
	 * @since 3.0.0
	 * @param array $table_rows Original table rows
	 * @param array $items Order items
	 * @return array New table rows
	 */
	public function add_table_rows_headings( $table_rows, $items ) {

		if ( ! is_object( $this->order ) ) {
			return $table_rows;
		}

		$new_table_rows = array();

		// For Shop Manager pick list, add information on the current order at the top header
		if ( 'pick-list' === $this->type ) {

			$column_widths   = $this->get_column_widths();
			$shipping_method = $this->order->get_shipping_method();
			$edit_post_url   = get_edit_post_link( $this->order->id );

			$new_table_rows[0] = array(

				'headings' => array(

					'order-number'    => array(
						/* translators: Placeholders: %1$s - order number, %2$s - invoice number */
						'content' => sprintf( '<strong><a href="' . esc_url( $edit_post_url ). '" target="_blank">' . __( 'Order %1$s - Invoice %2$s', 'woocommerce-pip' ) . '</a></strong>', '#' . $this->order->get_order_number(), $this->get_invoice_number() ),
						'colspan' => ceil( count( $column_widths ) / 2 ),
					),

					'shipping-method' => array(
						'content' => '<em>' . ( $shipping_method ? $shipping_method : __( 'No shipping', 'woocommerce-pip' ) ) . '</em>',
						'colspan' => floor( count( $column_widths ) / 2 ),
					),
				),

				'items' => array(),
			);
		}

		if ( $this->get_items_count() > 0 ) {

			$items_grouped_by_category = array();

			// Group first order items per product category id
			foreach ( $items as $item_id => $item ) {

				$product_id = isset( $item['variation_id'] ) && (int) $item['variation_id'] > 0 ? (int) $item['variation_id'] : ( isset( $item['product_id'] ) ? (int) $item['product_id'] : 0 );
				$product    = wc_get_product( $product_id );

				if ( ! $product || $this->maybe_hide_virtual_item( $item ) ) {
					continue;
				}

				$product_categories = wp_get_post_terms( $product->id, 'product_cat', array( 'orderby' => 'term_order' ) );

				if ( ! $product_categories || empty( $product_categories[0] ) ) {

					$items_grouped_by_category[0][] = (array) $this->get_table_row_order_item_data( $item_id, $item );

				} else {

					/* @type WP_Term $product_category */
					$product_category = $product_categories[0];
					// Group the items together by term_id
					$items_grouped_by_category[ (int) $product_category->term_id ][] = (array) $this->get_table_row_order_item_data( $item_id, $item );
				}
			}

			/** This filter is documented in includes/abstract-wc-pip-document.php */
			$sort_alphabetically = apply_filters( 'wc_pip_document_sort_order_items_alphabetically', true, $this->order->id, $this->type );

			// Loop groups and insert table headings
			$i = 1;
			foreach ( $items_grouped_by_category as $term_id => $grouped_items ) {

				if ( true === $sort_alphabetically ) {
					usort( $grouped_items, array( $this, 'sort_order_items_by_column_key' ) );
				}

				$new_table_rows[ $i ] = array(
					'headings' => array(
						'breadcrumbs' => array(
							'content' => $this->get_table_order_items_group_breadcrumb( $term_id ),
							'colspan' => count( $this->get_column_widths() ),
						),
					),
					'items'    => $grouped_items,
				);

				$i ++;
			}

		} else {

			$new_table_rows[1] = array(
				'headings' => array(
					'no-items'    => array(
						'content' => '<em>' . esc_html__( 'This order does not contain shippable items.', 'woocommerce-pip' ) . '</em>',
						'colspan' => count( $this->get_column_widths() ),
					)
				),
				'items' => array(),
			);
		}

		return $new_table_rows;
	}


	/**
	 * Get item data
	 *
	 * @since 3.0.0
	 * @param string $item_id Item id
	 * @param array $item Item data
	 * @param WC_Product $product Product object
	 * @return array
	 */
	protected function get_order_item_data( $item_id, $item, $product ) {

		$item_meta = $this->get_order_item_meta_html( $item_id, $item, $product );

		/** This filter is documented in includes/class-wc-pip-document-invoice.php */
		return apply_filters( 'wc_pip_document_table_row_cells', array(
			'sku'      => $this->get_order_item_sku_html( $product ),
			'product'  => $this->get_order_item_name_html( $product, $item ) . ( $item_meta ? '<br>' . $item_meta : '' ),
			'quantity' => $this->get_order_item_quantity_html( $item_id, $item ),
			'weight'   => $this->get_order_item_weight_html( $item_id, $item, $product ),
			'id'       => $this->get_order_item_id_html( $item_id ),
		), $this->type, $item_id, $item, $product, $this->order );
	}


	/**
	 * Get the total weight of items in the document order
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_items_total_weight() {

		if ( ! is_object( $this->order ) ) {
			return '';
		}

		$total_weight = 0;
		$items        = $this->order->get_items();

		// Loop through items in order to add to total weight
		foreach ( $items as $item_id => $item ) {

			if ( isset( $item['qty'], $item['product_id'] ) ) {

				$item_qty     = max( (float) $item['qty'], 0 );
				$refunded_qty = (float) $this->order->get_qty_refunded_for_item( $item_id );
				$total_qty    = max( 0, $item_qty - $refunded_qty );
				$product      = $this->order->get_product_from_item( $item );

				if ( ! $product || $total_qty < 1 ) {
					continue;
				}

				$item_weight  = (float) $product->weight;
				$items_weight = (float) ( max( $item_weight, 0 ) * $total_qty );

				/** This filter is documented in includes/abstract-wc-pip-document.php */
				$total_weight += apply_filters( 'wc_pip_order_item_weight', (float) $items_weight, $item, wc_get_product( (int) $item['product_id'] ), $this->order );
			}
		}

		$weight_unit = get_option( 'woocommerce_weight_unit' );

		/**
		 * Filters the total weight of items in the order.
		 *
		 * @since 3.0.0
		 * @param string $formatted_weight Total weight with weight unit text
		 * @param float $total_weight Total weight of items in order
		 * @param string $weight_unit Weight unit as per store option
		 * @param WC_Order $order The order object
		 */
		return apply_filters( 'wc_pip_order_items_total_weight', $total_weight . ' ' . $weight_unit, $total_weight, $weight_unit, $this->order );
	}


	/**
	 * Get table footer
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_table_footer() {

		$rows = array();

		if ( ! is_object( $this->order ) || $this->get_items_count() === 0 ) {
			return $rows;
		}

		$rows['totals'] = array(
			'colspan'        => '<strong>' . __( 'Totals:', 'woocommerce-pip' ) . '</strong>',
			/* translators: Placeholder: %d - total amount of items in packing list */
			'total-quantity' => '<strong>' . sprintf( _n( '%d pc.', '%d pcs.', $this->get_items_count(), 'woocommerce-pip' ), $this->get_items_count() ) . '</strong>',
			'total-weight'   => '<strong>' . $this->get_items_total_weight() . '</strong>',
		);

		/** This filter is documented in includes/class-wc-pip-document-invoice.php */
		return apply_filters( 'wc_pip_document_table_footer', $rows, $this->type, $this->order_id );
	}


}
