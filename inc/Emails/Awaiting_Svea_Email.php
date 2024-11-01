<?php
/**
 * Class Awaiting_Svea_Email file.
 *
 * @package WooCommerce\Emails
 */

use Svea_Checkout_For_Woocommerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Awaiting_Svea_Email', false ) ) :

	/**
	 * Customer awaiting further info from Svea
	 */
	class Awaiting_Svea_Email extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'awaiting_svea';
			$this->customer_email = true;

			$this->title          = __( 'Awaiting status', 'svea-checkout-for-woocommerce' );
			$this->description    = __( 'This is an order notification sent to customers if the order is awaiting final status from Svea.', 'svea-checkout-for-woocommerce' );
			$this->template_html  = 'emails/customer-awaiting-svea.php';
			$this->template_plain = 'emails/plain/customer-awaiting-svea.php';
			$this->template_base  = SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR . '/templates/';

			$this->placeholders   = [
				'{order_date}'   => '',
				'{order_number}' => '',
			];

			// Trigger for this email.
			add_action( 'woocommerce_order_status_pending_to_' . Admin::AWAITING_ORDER_STATUS . '_notification', [ $this, 'trigger' ], 10, 2 );

			// Call parent constructor.
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Regarding your order at {site_title}', 'svea-checkout-for-woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Thank you for your order', 'svea-checkout-for-woocommerce' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int            $order_id The order ID.
		 * @param WC_Order|false $order Order object.
		 */
		public function trigger( $order_id, $order = false ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                         = $order;
				$this->recipient                      = $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				[
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				],
				'',
				$this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				[
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				],
				'',
				$this->template_base
			);
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @since 3.7.0
		 * @return string
		 */
		public function get_default_additional_content() {
			return __( 'Thanks for using {site_url}!', 'svea-checkout-for-woocommerce' );
		}
	}

endif;

return new Awaiting_Svea_Email();
