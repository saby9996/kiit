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
 * @package   WC-Print-Invoices-Packing-Lists/Admin/Orders
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * PIP Admin Order class
 *
 * Handles customizations to the Orders/Edit Order screens
 *
 * @since 3.0.0
 */
class WC_PIP_Orders_Admin {


	/**
	 * Add various admin hooks/filters
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		// add 'Print Count' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_status_column_header' ), 20 );

		// add 'Print Count' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_status_column_content' ) );

		// add bulk order filter for printed / non-printed orders
		add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_print_status') , 20 );
		add_filter( 'request',               array( $this, 'filter_orders_by_print_status_query' ) );

		// add invoice numbers to shop orders search fields
		add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'make_invoice_numbers_searchable' ) );

		// generate invoice number upon order save
		add_action( 'save_post', array( $this, 'generate_invoice_number_order_save' ), 20, 2 );

		// display invoice number on order screen
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_order_invoice_number' ), 42, 1 );

		// add buttons for PIP actions for individual orders in Orders screen table
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_actions' ), 10, 2 );

		// add bulk actions to the Orders screen table bulk action drop-downs
		add_action( 'admin_footer-edit.php', array( $this, 'add_order_bulk_actions' ) );

		// add actions to individual Order edit screen
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );

		// process orders bulk actions
		add_action( 'load-edit.php', array( $this, 'process_orders_bulk_actions' ) );

		// process individual order actions
		if ( $actions = $this->get_actions() ) {
			foreach ( array_keys( $actions ) as $action ) {
				add_action( 'woocommerce_order_action_' . $action, array( $this, 'process_order_actions' ) );
			}
		}

		// add a nonce for individual order actions
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'send_email_order_action_nonce' ) );

		// handling of individual orders send email actions
		add_action( 'admin_init', array( $this, 'send_email_order_action' ) );

		// display admin notices for bulk actions
		add_action( 'current_screen', array( $this, 'render_messages' ) );

		// display admin notices for emails sent
		add_action( 'admin_init', array( $this, 'render_email_sent_message' ) );
	}


	/**
	 * Render any messages set by bulk actions
	 *
	 * @since 3.0.0
	 * @param WP_Screen $current_screen
	 */
	public function render_messages( $current_screen ) {

		if ( isset( $current_screen->id ) && in_array( $current_screen->id, array( 'shop_order', 'edit-shop_order' ), true ) ) {

			if ( ( $bulk_action_message_transient = get_transient( '_woocommerce_pip_bulk_action_confirmation' ) ) && is_array( $bulk_action_message_transient ) ) {

				$user_id = key( $bulk_action_message_transient );

				if ( get_current_user_id() !== (int) $user_id ) {
					return;
				}

				$handler = wc_pip()->get_message_handler();
				$message = wp_kses_post( current( $bulk_action_message_transient ) );

				$handler->add_message( $message );

				delete_transient( '_woocommerce_pip_bulk_action_confirmation' );
			}

			wc_pip()->get_message_handler()->show_messages( array(
				'capabilities' => wc_pip()->get_handler_instance()->get_admin_capabilities(),
			) );
		}
	}


	/**
	 * Process bulk actions
	 *
	 * @since 3.0.0
	 */
	public function process_orders_bulk_actions() {
		global $typenow;

		if ( 'shop_order' === $typenow ) {

			// Get the bulk action
			$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
			$action        = $wp_list_table->current_action();
			$order_ids     = array();

			// Return if not processing PIP actions
			if ( ! in_array( $action, array_keys( $this->get_bulk_actions() ) ) ) {
				return;
			}

			// Make sure order IDs are submitted
			if ( isset( $_REQUEST['post'] ) ) {
				$order_ids = array_map( 'absint', $_REQUEST['post'] );
			}

 			// Return if there are no orders to print
 			if ( ! $order_ids ) {
				return;
 			}

			// Get document and document action
			$document_type = str_replace( '_', '-', str_replace( array( 'wc_pip_print_', 'wc_pip_send_email_' ), '', $action ) );
			$action_type   = strpos( $action, 'print' ) ? 'print' : 'send_email';
			$redirect_url  = admin_url( 'edit.php?post_type=shop_order' );

			if ( 'send_email' === $action_type ) {

				$emails_sent = 0;

				if ( in_array( $document_type, array( 'invoice', 'packing-list' ), true ) ) {

					foreach ( $order_ids as $order_id ) {

						$document = wc_pip()->get_document( $document_type, array( 'order_id' => $order_id ) );

						if ( $document ) {

							$document->send_email();
							$emails_sent++;
						}
					}

				} elseif ( 'pick-list' === $document_type ) {

					$document = wc_pip()->get_document( $document_type, array( 'order_id' => $order_ids[0], 'order_ids' => $order_ids ) );

					if ( $document ) {

						$document->send_email();
						$emails_sent = 1;
					}
				}

				/**
				 * Fires after emails are sent via a bulk action.
				 *
				 * @since 3.0.0
				 * @param string $document_type WC_PIP_Document type
				 * @param int[] $order_ids Array of WC_Order ids
				 */
				do_action( 'wc_pip_process_orders_bulk_action_send_email', $document_type, $order_ids );

				$pip_message = array(
					'wc_pip_document'  => $document_type,
					'wc_pip_action'    => 'admin_message',
					'wc_pip_message'   => 'emails_sent',
					'emails_count'     => $emails_sent,
					'orders_processed' => count( $order_ids ),
				);

				/* @see WC_PIP_Orders_Admin::render_email_sent_message() */
				wp_redirect( add_query_arg( $pip_message, $redirect_url ) );
				exit;
			}

			$document = wc_pip()->get_document( $document_type, array( 'order_id' => $order_ids[0], 'order_ids' => $order_ids ) );

			if ( 'print' === $action_type ) {

				// Trigger an admin notice to have the user manually open a print window
				$message = $this->get_print_confirmation_message( $document, $order_ids, $redirect_url );

				/* @see WC_PIP_Orders_Admin::render_messages() */
				set_transient( '_woocommerce_pip_bulk_action_confirmation', array( get_current_user_id() => $message ), 120 );

			} else {

				/**
				 * Fires after order bulk action is processed.
				 *
				 * @since 3.0.0
				 * @param string $action_type Action to be performed
				 * @param WC_PIP_Document $document Document object
				 */
				do_action( 'wc_pip_process_orders_bulk_action', $action_type, $document );
			}
 		}
	}


	/**
	 * Process individual order actions
	 *
	 * @since 3.0.0
	 * @param WC_Order $order
	 */
	public function process_order_actions( $order ) {

		if ( $actions = $this->get_actions() ) {

			$document = null;
			$message  = '';

			foreach ( array_keys( $actions ) as $action ) {

				if ( doing_action( 'woocommerce_order_action_'. $action ) ) {

					if ( in_array( $action, array( 'wc_pip_print_invoice', 'wc_pip_send_email_invoice' ), true ) ) {

						$document = wc_pip()->get_document( 'invoice', array( 'order' => $order ) );

					} elseif ( in_array( $action, array( 'wc_pip_print_packing_list', 'wc_pip_send_email_packing_list' ), true ) ) {

						$document = wc_pip()->get_document( 'packing-list', array( 'order' => $order ) );
					}

					if ( in_array( $action, array( 'wc_pip_print_invoice', 'wc_pip_print_packing_list' ), true ) ) {
						$message = $this->get_print_confirmation_message( $document, array( $order->id ), admin_url() );
					}

					switch ( $action ) {

						case 'wc_pip_print_invoice' :
						case 'wc_pip_print_packing_list' :
							/* @see WC_PIP_Orders_Admin::render_messages() */
							set_transient( '_woocommerce_pip_bulk_action_confirmation', array( get_current_user_id() => $message, 120 ) );
						break;

						case 'wc_pip_send_email_invoice' :
						case 'wc_pip_send_email_packing_list' :
							/* TODO it seems that actions that contain 'email' in the key are hijacked by WooCommerce - the following won't work, workaround in JS */
							$document->send_email();
						break;

					}
				}
			}
		}
	}


	/**
	 * Add a nonce for individual order actions
	 *
	 * @since 3.0.4
	 */
	public function send_email_order_action_nonce() {

		wp_nonce_field( 'wc_pip_document', 'wc_pip_document_nonce' );
	}


	/**
	 * Send email by admin action
	 *
	 * @since 3.0.0
	 */
	public function send_email_order_action() {

		$get_request  = isset( $_GET['wc_pip_document'] ) && isset( $_GET['wc_pip_action'] ) && 'send_email' === $_GET['wc_pip_action'];
		$post_request = isset( $_POST['wc_order_action'] ) && isset( $_POST['wc_pip_document_nonce'] ) && in_array( $_POST['wc_order_action'], array( 'wc_pip_send_email_invoice', 'wc_pip_send_email_packing_list' ), true );

		// listen for 'send_email' query string or order action post
		if ( $get_request || $post_request ) {

			// bail out early if user hasn't the necessary privileges
			if ( ! is_user_logged_in() || ! wc_pip()->get_handler_instance()->current_admin_user_can_manage_documents() ) {
				return;
			}

			// get nonce according to request type
			if ( $get_request ) {
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '';
			} else {
				$nonce = $_POST['wc_pip_document_nonce'];
			}

			// verify nonce
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wc_pip_document' ) ) {
				return;
			}

			// get the document type to send an email for
			if ( $get_request ) {
				$type = str_replace( '_', '-', $_GET['wc_pip_document'] );
			} else {
				$type = str_replace( '_', '-', str_replace( 'wc_pip_send_email_', '', $_POST['wc_order_action'] ) );
			}

			$document  = null;
			$order_id  = 0;
			$order_ids = array();

			// get order id(s)
			if ( $get_request ) {

				if ( isset( $_GET['order_ids'] ) ) {
					$order_ids = is_array( $_GET['order_ids'] ) ? array_map( 'intval', $_GET['order_ids'] ) : array_map( 'intval', implode( ',', $_GET['order_ids'] ) );
				}

				if ( isset( $_GET['order_id'] ) ) {
					$order_id = $order_ids ? $order_ids[0] : max( 0, (int) $_GET['order_id'] );
				}

			} else {

				// in single order action, we only have one id
				$order_id = (int) $_POST['post_ID'];
			}

			$order = wc_get_order( $order_id );

			// if we have an order, get the document...
			if ( $order ) {

				$args = $order_ids ? array( 'order' => $order, 'order_id' => $order_ids[0], 'order_ids' => $order_ids ) : array( 'order' => $order, 'order_id' => $order_id );

				$document = wc_pip()->get_document( $type, $args );

				// if we have a document, send an email...
				if ( $document ) {

					$document->send_email();

					// we can stop here if this was a single order action
					if ( $post_request ) {
						return;
					}

					// little hack to clean the address bar from send email query strings
					// which might cause to send emails again if the user reloads the page...
					$previous_screen = remove_query_arg( array(
						'wc_pip_action',
						'wc_pip_document',
						'emails_count',
						'order_id',
						'order_ids',
						'_wpnonce',
					) );

					// ... however we add a new query string to generate a notice message:
					$pip_message = array(
						'wc_pip_document' => $type,
						'wc_pip_action'   => 'admin_message',
						'wc_pip_message'  => 'emails_sent',
						'emails_count'    => 1,
					);

					/* @see WC_PIP_Email::render_sent_email_message() */
					wp_redirect( add_query_arg( $pip_message, $previous_screen ) );
					exit;
				}
			}
		}
	}


	/**
	 * Render sent email messages
	 *
	 * @see WC_PIP_Email::send_email_action()
	 *
	 * @since 3.0.0
	 */
	public function render_email_sent_message() {

		// Listen for 'admin_message' query string
		if ( ( isset( $_GET['wc_pip_document'] ) && isset( $_GET['wc_pip_action'] ) ) && 'admin_message' === $_GET['wc_pip_action'] ) {

			$document_type    = $_GET['wc_pip_document'];
			$emails_sent      = isset( $_GET['emails_count'] ) ? (int) $_GET['emails_count'] : 0;
			$orders_processed = isset( $_GET['orders_processed'] ) ? max( 1, (int) $_GET['orders_processed'] ) : 1;
			$message_type     = isset( $_GET['wc_pip_message'] ) ? $_GET['wc_pip_message'] : '';
			$document         = wc_pip()->get_document( $document_type, array( 'order_id' => 0 ) );

			if ( $document && 'emails_sent' === $message_type ) {

				if ( $emails_sent > 0 ) {
					if ( 'pick-list' === $document->type ) {
						/* translators: Placeholders: %d - number of emails sent */
						$message = sprintf( _n( 'Pick List email for %d order sent.', 'Pick List email for %d orders sent.', $orders_processed, 'woocommerce-pip' ), $orders_processed );
					} else {
						/* translators: Placeholders: %d - number of emails sent, %s - document name */
						$message = sprintf( _n( '%d %s email sent.', '%d %s emails sent.', (int) $emails_sent, 'woocommerce-pip' ), (int) $emails_sent, $document->name );
					}
				} else {
					/* translators: Placeholder: %s - document name */
					$message = sprintf( __( 'No %s emails sent.', 'woocommerce-pip' ), $document->name );
				}

				wc_pip()->get_message_handler()->add_message( $message );
			}
		}
	}


	/**
	 * Get print confirmation message
	 *
	 * @since 3.0.0
	 * @param WC_PIP_Document $document Document object
	 * @param int[] $order_ids Array of WC_Order ids
	 * @param string $redirect_url Optional, defaults to admin url
	 * @return string
	 */
	public function get_print_confirmation_message( $document, $order_ids, $redirect_url = '' ) {

		$orders_count = count( $order_ids );

		if ( $orders_count < 1 ) {
			/* translators: Placeholder: %s - Document name */
			return sprintf( __( 'No %s created for printing. Please select valid orders or reload this page first.', 'woocommerce-pip' ), $document->name );
		}

		$action_url = wp_nonce_url(
			add_query_arg(
				array(
					'wc_pip_action'   => 'print',
					'wc_pip_document' => $document->type,
					'order_id'        => $order_ids[0],
					'order_ids'       => $order_ids,
				),
				'' !== $redirect_url ? $redirect_url : admin_url()
			),
			'wc_pip_document'
		);

		$print_link = '<a href="' . $action_url .'" target="_blank">' . __( 'Print now.', 'woocommerce-pip' ) . '</a>';

		if ( $orders_count > 1 ) {

			if ( $document->is_type( 'pick-list' ) ) {
				/* translators: Placeholders: %1$s - document name (pick list), %2$s - number of documents created, %3$s - link to print */
				$message = sprintf( __( '%1$s for %2$s orders created. %3$s', 'woocommerce-pip' ), $document->name, $orders_count, $print_link );
			} else {
				/* translators: Placeholders: %1$s - number of documents created, %2$s - document name, %3$s - link to print */
				$message = sprintf( __( '%1$s %2$s created. %3$s', 'woocommerce-pip' ), $orders_count, $document->name_plural, $print_link );
			}

		} else {
			/* translators: Placeholders: %1$s - document name, %2$s - link to print */
			$message = sprintf( __( '%1$s created. %2$s', 'woocommerce-pip' ), $document->name, $print_link );
		}

		return $message;
	}


	/**
	 * Generate the invoice number upon order save
	 *
	 * @since 3.0.0
	 * @param int $post_id Post id
	 * @param WP_Post $post Post object
	 */
	public function generate_invoice_number_order_save( $post_id, $post ) {

		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		/* This filter is documented in /includes/class-wc-pip-handler.php */
		if ( false === apply_filters( 'wc_pip_generate_invoice_number_on_order_paid', true ) ) {
			return;
		}

		$wc_order = wc_get_order( $post_id );

		if ( ! $wc_order ) {
			return;
		}

		// Generate the invoice number, will trigger post meta update
		if ( $wc_order->has_status( array( 'completed', 'processing' ) ) ) {

			$document = wc_pip()->get_document( 'invoice', array( 'order' => $wc_order ) );

			if ( $document ) {
				$document->get_invoice_number();
			}
		}
	}


	/**
	 * Display the invoice number in the order screen meta box
	 *
	 * @since 3.0.0
	 * @param WC_Order|int $wc_order Order object or id
	 */
	public function display_order_invoice_number( $wc_order ) {

		if ( is_int( $wc_order ) ) {
			$wc_order = wc_get_order( $wc_order );
		}

		// only display if the invoice number was generated before
		if ( isset( $wc_order->id ) && $document = wc_pip()->get_document( 'invoice', array( 'order' => $wc_order ) ) ) :

			if ( $document->has_invoice_number() ) :

				?>
				<p class="form-field form-field-wide wc-pip-invoice-number">
					<label for="pip-invoice-number"><?php esc_html_e( 'Invoice Number', 'woocommerce-pip' ); ?></label>
					<strong><?php echo esc_html( $document->get_invoice_number() ); ?></strong>
				</p>
				<?php

			endif;

		endif;
	}


	/**
	 * Get individual order actions
	 *
	 * @since 3.0.0
	 * @return array Associative array of actions with their labels
	 */
	public function get_actions() {

		$actions = array();

		if ( wc_pip()->get_handler_instance()->current_admin_user_can_manage_documents() ) {

			/**
			 * Filters the admin order actions.
			 *
			 * @since 3.0.0
			 * @param array $actions
			 */
			$actions = apply_filters( 'wc_pip_admin_order_actions', array(
				'wc_pip_print_invoice'           => __( 'Print Invoice', 'woocommerce-pip' ),
				'wc_pip_send_email_invoice'      => __( 'Send Email Invoice', 'woocommerce-pip' ),
				'wc_pip_print_packing_list'      => __( 'Print Packing List', 'woocommerce-pip' ),
				'wc_pip_send_email_packing_list' => __( 'Send Email Packing List', 'woocommerce-pip' ),
			) );

		}

		return $actions;
	}


	/**
	 * Get orders bulk actions
	 *
	 * @since 3.0.0
	 * @return array Associative array of actions with their labels
	 */
	public function get_bulk_actions() {

		$shop_manager_actions = array();

		if ( wc_pip()->get_handler_instance()->current_admin_user_can_manage_documents() ) {

			/**
			 * Filters the bulk order actions.
			 *
			 * @since 3.0.0
			 *
			 * @param array $actions
			 */
			$shop_manager_actions = apply_filters( 'wc_pip_admin_order_bulk_actions', array_merge( $this->get_actions(), array(
				'wc_pip_print_pick_list'      => __( 'Print Pick List', 'woocommerce-pip' ),
				'wc_pip_send_email_pick_list' => __( 'Send Email Pick List', 'woocommerce-pip' ),
			) ) );
		}

		return $shop_manager_actions;
	}


	/**
	 * Adds 'Invoice' and 'Packing List' column headers
	 * to 'Orders' page immediately before the 'Actions' column
	 *
	 * @since 3.0.0
	 * @param array $columns
	 * @return array $new_columns
	 */
	public function add_order_status_column_header( $columns ) {

		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {

			$new_columns[ $column_name ] = $column_info;

			if ( 'order_total' === $column_name ) {

				$new_columns['pip_print_invoice']      = __( 'Invoice', 'woocommerce-pip' );
				$new_columns['pip_print_packing-list'] = __( 'Packing List', 'woocommerce-pip' );
			}
		}

		return $new_columns;
	}


	/**
	 * Adds 'Print Count' column content to 'Orders' page immediately before 'Actions' column
	 *
	 * @since 3.0.0
	 * @param array $column name of column being displayed
	 */
	public function add_order_status_column_content( $column ) {
		global $post;

		// Get the order
		$wc_order = in_array( $column, array(
			'pip_print_invoice',
			'pip_print_packing-list',
			'order_title',
			'order_actions'
		), true ) ? wc_get_order( $post->ID ) : false;

		// Invoice print status
		if ( $wc_order && in_array( $column, array( 'pip_print_invoice', 'order_title' ), true ) ) {

			$invoice = wc_pip()->get_document( 'invoice', array( 'order' => $wc_order ) );

			if ( 'pip_print_invoice' === $column ) {

				echo $this->get_print_status( $invoice );

			} elseif ( 'order_title' === $column && $invoice && get_post_meta( $wc_order->id, '_pip_invoice_number', true ) ) {

				/* translators: Placeholder: %s - invoice number */
				echo '<span class="wc-pip-invoice-number">' . sprintf( __( 'Invoice: %s', 'woocommerce-pip' ), $invoice->get_invoice_number() ) . '</span>';
			}
		}

		// Packing List print status
		if ( $wc_order && 'pip_print_packing-list' === $column ) {

			$packing_list = wc_pip()->get_document( 'packing-list', array( 'order' => $wc_order ) );

			echo $this->get_print_status( $packing_list );
		}

		// Hidden content that will be injected as order button actions tooltip content in js
		if ( $wc_order && 'order_actions' === $column ) {

			?>
			<div id="wc-pip-tooltip-order-actions-<?php echo $wc_order->id; ?>"
			     class="wc-pip-tooltip-order-actions"
			     style="display:none;">
				<div class="wc-pip-tooltip-content">
					<h3><?php esc_html_e( 'Invoice/Packing List', 'woocommerce-pip' ); ?></h3>
					<ul>
						<?php

						foreach ( $this->get_actions() as $action => $name ) :

							$document_type = str_replace( array( 'wc_pip_print_', 'wc_pip_send_email_' ), '', $action );
							$action_type   = strpos( $action, 'print' ) ? 'print' : 'send_email';
							$http          = is_ssl() ? 'https://' : 'http://';

							$url = add_query_arg( array(
									'wc_pip_action' => $action_type,
									'wc_pip_document' => $document_type,
									'order_id' => $wc_order->id,
								),
								'send_email' === $action_type ? $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : admin_url()
							);

							?>
							<li>
								<a class="<?php echo sanitize_html_class( $action ); ?> wc-pip-document-tooltip-order-action"
								   href="<?php echo wp_nonce_url( $url, 'wc_pip_document' ); ?>"
								   target="<?php echo 'print' === $action_type ? '_blank' : '_self'; ?>">
									<?php echo esc_html( $name ); ?>
								</a>
							</li>
							<?php

						endforeach;

						?>
					</ul>
				</div>
			</div>
			<?php
		}
	}


	/**
	 * Get print status (whether a document had a print window open)
	 *
	 * @since 3.0.0
	 * @param WC_PIP_Document $document PIP Document object
	 * @return string
	 */
	private function get_print_status( $document ) {

		if ( ! $document ) {
			return '<strong>&ndash;</strong>';
		}

		return $document->get_print_status() ? '&#10004' : '<strong>&ndash;</strong>';
	}


	/**
	 * Adds order action icons to the Orders screen table
	 * for printing the invoice and packing list
	 *
	 * Processed via Ajax
	 *
	 * @since 3.0.0
	 * @param array $actions order actions
	 * @param int|WC_Order $order Order object or id
	 * @return array
	 */
	public function add_order_actions( $actions, $order ) {

		$wc_order = wc_get_order( $order );

		if ( ! $wc_order || ! wc_pip()->get_handler_instance()->current_admin_user_can_manage_documents() ) {
			return $actions;
		}

		return array_merge( $actions, array( array(
			'name'   => wc_pip()->get_plugin_name(),
			'action' => 'wc_pip_document',
			'url'    => sprintf( '#%s', $wc_order->id ),
		) ) );
	}


	/**
	 * Adds custom bulk actions to the Orders screen table bulk action drop-down
	 *
	 * @since 3.0.0
	 */
	public function add_order_bulk_actions() {
		global $post_type, $post_status;

		if ( $post_type === 'shop_order' && $post_status !== 'trash' ) :

			?>
			<script type="text/javascript">
				jQuery( document ).ready( function ( $ ) {
					$( 'select[name^=action]' ).append(
						<?php $index = count( $actions = $this->get_bulk_actions() ); ?>
						<?php foreach ( $actions as $action => $name ) : ?>
							$( '<option>' ).val( '<?php echo esc_js( $action ); ?>' ).text( '<?php echo esc_js( $name ); ?>' )
							<?php --$index; ?>
							<?php if ( $index ) { echo ','; } ?>
						<?php endforeach; ?>
					);
				} );
			</script>
			<?php

		endif;
	}


	/**
	 * Add order actions to the Edit Order screen
	 *
	 * @since 3.0.0
	 * @param array $actions
	 * @return array
	 */
	public function add_order_meta_box_actions( $actions ) {
		return array_merge( $actions, $this->get_actions() );
	}


	/**
	 * Display a dropdown to filter orders by print status
	 *
	 * @since 3.0.0
	 */
	public function filter_orders_by_print_status() {
		global $typenow;

		if ( 'shop_order' === $typenow ) :

			$options  = array(
				'invoice_not_printed'      => __( 'Invoice not printed', 'woocommerce-pip' ),
				'invoice_printed'          => __( 'Invoice printed', 'woocommerce-pip' ),
				'packing_list_not_printed' => __( 'Packing List not printed', 'woocommerce-pip' ),
				'packing_list_printed'     => __( 'Packing List printed', 'woocommerce-pip' ),
			);

			$selected = isset( $_GET['_shop_order_pip_print_status'] ) ? $_GET['_shop_order_pip_print_status'] : '';

			?>
			<select name="_shop_order_pip_print_status" id="dropdown_shop_order_pip_print_status">
				<option value=""><?php esc_html_e( 'Show all print statuses', 'woocommerce-pip' ); ?></option>
				<?php foreach ( $options as $option_value => $option_name ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $selected, $option_value ); ?>><?php echo esc_html( $option_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php

		endif;
	}


	/**
	 * Filter orders by print status query vars
	 *
	 * @since 3.0.0
	 * @param array $vars WP_Query vars
	 * @return array
	 */
	public function filter_orders_by_print_status_query( $vars ) {
		global $typenow;

		if ( 'shop_order' === $typenow && isset( $_GET['_shop_order_pip_print_status'] ) ) {

			$meta    = '';
			$compare = '';
			$value   = '';

			switch ( $_GET['_shop_order_pip_print_status'] ) {

				case 'invoice_not_printed' :

					$meta    = '_wc_pip_invoice_print_count';
					$compare = 'NOT EXISTS';

				break;

				case 'invoice_printed' :

					$meta    = '_wc_pip_invoice_print_count';
					$compare = '>';
					$value   = '0';

				break;

				case 'packing_list_not_printed' :

					$meta  = '_wc_pip_packing_list_print_count';
					$compare = 'NOT EXISTS';

				break;

				case 'packing_list_printed' :

					$meta    = '_wc_pip_packing_list_print_count';
					$compare = '>';
					$value   = '0';

				break;

			}

			if ( $meta && $compare ) {

				$vars['meta_key']     = $meta;
				$vars['meta_value']   = $value;
				$vars['meta_compare'] = $compare;
			}
		}

		return $vars;
	}


	/**
	 * Make invoice numbers searchable
	 *
	 * @since 3.0.0
	 * @param array $search_fields Existing search fields
	 * @return array
	 */
	public function make_invoice_numbers_searchable( $search_fields ) {
		return array_merge( $search_fields, array( '_pip_invoice_number' ) );
	}


}
