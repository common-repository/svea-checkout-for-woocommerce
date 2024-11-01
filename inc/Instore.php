<?php

namespace Svea_Checkout_For_Woocommerce;

use Svea_Checkout_For_Woocommerce\Models\Svea_Instore;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Basic
 */
class Instore {

	/**
	 * Init function
	 *
	 * @return void
	 */
	public function init() {
		// Maybe add a "Send payment link" button
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'add_send_payment_link_button' ] );

		add_action( 'admin_post_sco_send_payment_link', [ $this, 'send_payment_link' ] );

		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'set_svea_payment_link' ], 10, 2 );
	}

	/**
	 * Override the payment page URL to the Svea payment link
	 *
	 * @param string $url
	 * @param \WC_Order $wc_order
	 * @return string
	 */
	public function set_svea_payment_link( $url, $wc_order ) {
		if ( $wc_order->get_meta( '_sco_instore_payment_link' ) ) {
			$url = $wc_order->get_meta( '_sco_instore_payment_link' );
		}

		return $url;
	}

	/**
	 * Create instore order and send the payment link to the customer
	 *
	 * @return void
	 */
	public function send_payment_link() {
		if (
			! current_user_can( 'manage_woocommerce' ) ||
			empty( $_GET['order_id'] ) || // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			! isset( $_GET['_wpnonce'] ) || // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			! wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'sco_send_payment_link' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		) {
			return;
		}

		$order_id = sanitize_text_field( $_GET['order_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return;
		}

		$instore = new Svea_Instore( $wc_order );

		$result = $instore->create();

		$order_url = $wc_order->get_edit_order_url();

		if ( is_a( $result, '\Svea\Instore\Model\CreateOrderResponse' ) ) {
			$wc_order->add_order_note(
				sprintf(
					/* translators: %s is the order ID */
					esc_html__( 'Instore order created with Svea ID: %s', 'svea-checkout-for-woocommerce' ),
					$result->getPaymentOrderId()
				)
			);

			$wc_order->set_payment_method( WC_Gateway_Svea_Checkout::GATEWAY_ID );
			$wc_order->update_meta_data( '_sco_instore_payment_link', $result->getInstoreUiUri() );
			$wc_order->update_meta_data( '_sco_instore_sms_sent', $result->getSmsSentSuccessfully() );
			$wc_order->update_meta_data( '_svea_co_order_id', $result->getPaymentOrderId() );

			$index = 1;
			foreach ( $wc_order->get_items( [ 'line_item', 'shipping', 'fee' ] ) as $item ) {
				$item->update_meta_data( '_svea_co_order_row_id', $index );
				$item->save();
				$index++;
			}

			$wc_order->save();
			$order_url = add_query_arg(
				[
					'sco_status' => 'instore_success',
				],
				$order_url
			);
		} else if ( is_array( $result ) && isset( $result['error'] ) ) {
			$wc_order->add_order_note(
				sprintf(
					/* translators: %s is the error message */
					esc_html__( 'Error when creating instore order: %s', 'svea-checkout-for-woocommerce' ),
					$result['error']
				)
			);

			$order_url = add_query_arg(
				[
					'sco_status' => 'instore_error',
				],
				$order_url
			);
		}

		wp_safe_redirect( $order_url );
		exit;
	}

	/**
	 * Add "Send payment link" button to order
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function add_send_payment_link_button( $wc_order ) {
		if ( $this->can_create_instore_order( $wc_order ) ) {
			$url = add_query_arg(
				[
					'action'   => 'sco_send_payment_link',
					'order_id' => $wc_order->get_id(),
					'_wpnonce' => wp_create_nonce( 'sco_send_payment_link' ),
				],
				admin_url( 'admin-post.php' )
			);
			?>
			<div class="address send-payment-link">
				<p>
					<strong>Svea instore</strong>
					<a href="<?php echo esc_url( $url ); ?>" class="sco-send-payment-link button button-primary"><?php esc_html_e( 'Send Svea payment link', 'svea-checkout-for-woocommerce' ); ?></a>
				</p>	
			</div>
			<?php
		} else if ( ! empty( $wc_order->get_meta( '_sco_instore_payment_link' ) ) ) {
			?>
			<div class="address send-payment-link">
				<p>	
					<strong>Svea instore</strong>
					<?php echo esc_html__( 'Payment link is sent', 'svea-checkout-for-woocommerce' ); ?>
					<br>
					<?php
					if ( $wc_order->get_meta( '_sco_instore_sms_sent' ) ) {
						echo esc_html__( 'SMS sent successfully', 'svea-checkout-for-woocommerce' );
					} else {
						echo esc_html__( 'SMS could not be sent, see order notes for more information', 'svea-checkout-for-woocommerce' );
					}
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Can the order send a payment link via instore API
	 *
	 * @param \WC_Order $wc_order
	 * @return bool
	 */
	public function can_create_instore_order( $wc_order ) {
		$can_create = true;
		$gateway = WC_Gateway_Svea_Checkout::get_instance();

		// Send "n/a" so it doesn't use the store defaults
		$merchant_settings = $gateway->get_merchant_settings( $wc_order->get_currency() ?: 'n/a', $wc_order->get_billing_country() ?: 'n/a' );

		if (
			! empty( $wc_order->get_meta( '_sco_instore_payment_link' ) ) ||
			$wc_order->is_paid() ||
			$this->wc_order_is_missing_svea_order_id( $wc_order ) ||
			empty( $wc_order->get_billing_country() ) ||
			empty( $wc_order->get_items( [ 'line_item', 'shipping', 'fee' ] ) ) ||
			empty( wc_get_page_permalink( 'terms' ) ) ||
			empty( $merchant_settings['InstoreMerchantId'] )
		) {
			$can_create = false;
		}

		return apply_filters( 'svea_co_can_create_instore_order', $can_create, $wc_order );
	}

	/**
	 * Is the order missing a Svea order ID
	 *
	 * @param \WC_Order $wc_order
	 * @return bool
	 */
	public function wc_order_is_missing_svea_order_id( $wc_order ) {
		return ! empty( $wc_order->get_meta( '_svea_co_order_id' ) );
	}

}
