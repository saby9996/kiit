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
 * @package   WC-Print-Invoices-Packing-Lists/Emails
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * PIP Invoice Email
 *
 * Invoices can be sent by email when an order is paid
 */
class WC_PIP_Email_Invoice extends WC_Email {


	/** @var string $document_type WC_PIP_Document type for this email */
	protected $document_type = '';


	/**
	 * Email constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		$this->id             = 'pip_email_invoice';
		$this->document_type  = 'invoice';
		$this->title          = __( 'Invoice', 'woocommerce-pip' );
		$this->description    = __( 'If enabled, invoice emails are sent when an order is paid and the order status is processing or completed.', 'woocommerce-pip' );
		/* translators: Placeholders: %1$s - merge tag for the shop name, %2$s merge tag for generated invoice number, %3$s merge tag for order number, %4$s merge tag for date of the order */
		$this->subject        = sprintf( __( '[%1$s] Invoice %2$s for order %3$s from %4$s', 'woocommerce-pip' ), '{site_title}', '{invoice_number}', '{order_number}', '{order_date}' );
		// leave these blank to use our common template in templates/pip
		$this->template_html  = '';
		$this->template_plain = '';

		// triggers
		add_action( 'wc_pip_invoice_email_trigger', array( $this, 'trigger' ) );
		add_action( 'wc_pip_send_email_invoice',    array( $this, 'trigger' ) );

		// trigger on new paid orders
		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_failed_to_processing_notification',  array( $this, 'trigger' ) );

		// call parent constructor
		parent::__construct();

		// enforce HTML emails only
		$this->email_type = $this->get_email_type();
	}


	/**
	 * Is customer email
	 *
	 * @since 3.0.0
	 * @return true
	 */
	public function is_customer_email() {
		return true;
	}


	/**
	 * Get email type
	 *
	 * Override parent method to return html emails only
	 *
	 * @return string
	 */
	public function get_email_type() {
		return 'html';
	}


	/**
	 * Get subject
	 *
	 * Overrides parent method with new filter
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_subject() {

		/**
		 * Filter the invoice email subject.
		 *
		 * @since 3.0.0
		 * @param string $subject The email subject
		 * @param WC_PIP_Document $document The document object
		 */
		return apply_filters( 'wc_pip_invoice_email_subject', $this->format_string( $this->subject ), $this->object );
	}


	/**
	 * Email settings form fields
	 *
	 * Overrides parent method
	 *
	 * @since 3.0.0
	 */
	public function init_form_fields() {

		$this->form_fields    = array(

			'enabled'         => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-pip' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable this email notification', 'woocommerce-pip' ),
				'default'     => 'yes'
			),

			'subject'         => array(
				'title'       => __( 'Subject', 'woocommerce' ),
				'type'        => 'text',
				/* translators: Placeholder: %s - Default email subject */
				'description' => sprintf( __( 'Defaults to <code>%s</code>', 'woocommerce-pip' ), $this->subject ),
				'placeholder' => '',
				'default'     => $this->get_subject(),
			),
		);
	}


	/**
	 * Email trigger
	 *
	 * @see WC_PIP_Document::send_email()
	 * @see WC_PIP_Email_Invoice::__construct()
	 *
	 * @since 3.0.0
	 * @param null|int|WC_Order|WC_PIP_Document $object Email object passed by hooks
	 */
	public function trigger( $object ) {

		// if not a PIP document, grab the order first
		if ( is_int( $object ) || $object instanceof WC_Order ) {

			$wc_order = wc_get_order( $object );

			// sanity check, bail out early if we still don't have an order to begin with
			if ( ! $wc_order ) {
				return;
			}

			// now get the PIP document
			$object = wc_pip()->get_document( 'invoice', array( 'order' => $wc_order ) );
		}

		// bail if there is no document, customer doesn't have a valid email address, or the email is disabled
		if ( ! $object || ! isset( $object->order ) || ! isset( $object->order->billing_email ) || ! $object->order->billing_email || ! is_email( $object->order->billing_email ) || ! $this->is_enabled() ) {
			return;
		}

		// set the email object and the recipient
		$this->object    = $object;
		$this->recipient = $object->order->billing_email;

		// replace merge tags
		$this->find['order-date']        = '{order_date}';
		$this->find['order-number']      = '{order_number}';
		$this->find['invoice-number']    = '{invoice_number}';
		$this->replace['order-date']     = $this->object->order instanceof WC_Order ? date_i18n( wc_date_format(), strtotime( $this->object->order->order_date ) ) : '';
		$this->replace['order-number']   = $this->object->order instanceof WC_Order ? $this->object->order->get_order_number() : '';
		$this->replace['invoice-number'] = $this->object instanceof WC_PIP_Document ? $this->object->get_invoice_number() : '';

		// send mail
		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

		/**
		 * Fires after the document's email is sent.
		 *
		 * @since 3.0.0
		 * @param string $type PIP Document type
		 * @param WC_PIP_Document $object PIP Document object
		 * @param WC_Order $order Order object
		 */
		do_action( 'wc_pip_send_email', $this->object->type, $this->object->order_id, $this->object->order_ids );
	}


	/**
	 * Get email template content HTML
	 *
	 * @since 3.0.0
	 * @return string HTML
	 */
	public function get_content_html() {

		if ( ! $this->object instanceof WC_PIP_Document ) {
			return '';
		}

		ob_start();

		$this->object->output_template( array( 'action' => 'send_email' ) );

		return ob_get_clean();
	}


}
