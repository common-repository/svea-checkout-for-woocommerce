<?php
namespace Svea_Checkout_For_Woocommerce\Models;

use Svea\Checkout\CheckoutAdminClient;
use Svea\Checkout\Transport\Connector;
use Svea_Checkout_For_Woocommerce\Models\Traits\Items_From_Order;
use Svea_Checkout_For_Woocommerce\Models\Traits\Logger;
use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;

use WC_Order;


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Svea_Payment_Admin {
	use Items_From_Order, Logger;

	/**
	 * Error messages
	 *
	 * @var string[]
	 */
	public $errors = [];

	/**
	 * Order rows on order
	 *
	 * @var Svea_Item[]
	 */
	private $items = [];

	/**
	 * WooCommerce cart
	 *
	 * @var \WC_Cart
	 */
	private $cart = null;

	/**
	 * Svea connector
	 *
	 * @var \Svea\Checkout\Transport\Connector
	 */
	private $connector;

	/**
	 * Svea checkout client
	 *
	 * @var \Svea\Checkout\CheckoutAdminClient
	 */
	private $client;

	/**
	 * WooCommerce gateway
	 *
	 * @var WC_Gateway_Svea_Checkout
	 */
	public $gateway;

	/**
	 * Settings for the current country
	 *
	 * @var array
	 */
	public $country_settings;

	/**
	 * Maximum number of order rows allowed by Svea
	 */
	const MAX_NUM_ROWS = 1000;

	/**
	 * Create the order
	 *
	 * @param \WC_Order $wc_order WooCommerce order
	 * @param bool $admin Use the admin client
	 */
	public function __construct( $wc_order = null ) {
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		if ( $wc_order ) {
			// If the order is created via the admin interface, we need to use the instore settings
			$use_instore = ! empty( $wc_order->get_meta( '_sco_instore_payment_link' ) );

			$this->setup_client( $wc_order->get_currency(), $wc_order->get_shipping_country(), $use_instore );
		}
	}

	/**
	 * Setup client
	 *
	 * @param string $currency
	 * @param string $country
	 * @param bool $use_instore
	 * @return void
	 */
	public function setup_client( $currency, $country, $use_instore = false ) {
		$this->country_settings = $this->gateway->get_merchant_settings( $currency, $country );

		if ( $use_instore ) {
			$checkout_merchant_id = $this->country_settings['InstoreMerchantId'];
			$checkout_secret = $this->country_settings['InstorePASecret'];
		} else {
			$checkout_merchant_id = $this->country_settings['MerchantId'];
			$checkout_secret = $this->country_settings['Secret'];
		}

		// Check if merchant ID and secret is set, else display a message
		if ( ! isset( $checkout_merchant_id[0] ) || ! isset( $checkout_secret[0] ) ) {
			$msg = esc_html__( 'Merchant ID and secret must be set to use Svea Checkout', 'svea-checkout-for-woocommerce' );
			WC_Gateway_Svea_Checkout::log( sprintf( 'Error when getting merchant: %s', $msg ) );
			return;
		}

		// Set endpoint url
		$base_url = $this->country_settings['AdminBaseUrl'];

		$this->connector = Connector::init( $checkout_merchant_id, $checkout_secret, $base_url );
		$this->client = new CheckoutAdminClient( $this->connector );
	}

	/**
	 * Get order from the admin interface
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public function get( $id ) {
		$data = [
			'OrderId' => intval( $id ),
		];

		return $this->client->getOrder( apply_filters( 'woocommerce_sco_admin_get_order', $data ) );
	}

	/**
	 * Cancel order amount
	 *
	 * @param int $id
	 * @param float $amount
	 *
	 * @return array
	 */
	public function cancel_order_amount( $id, $amount ) {
		$data = [
			'OrderId'         => intval( $id ),
			'CancelledAmount' => $amount,
		];

		$order_amount_data = apply_filters( 'woocommerce_sco_credit_order_amount', $data );
		$this->log_order( 'Cancel order amount', $order_amount_data );
		return $this->client->cancelOrderAmount( $order_amount_data );
	}

	/**
	 * Cancel order in Svea
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public function cancel_order( $id ) {
		$data = [
			'OrderId' => intval( $id ),
		];

		$order_data = apply_filters( 'woocommerce_sco_cancel_order', $data );
		$this->log_order( 'Cancel order', $order_data );
		return $this->client->cancelOrder( $order_data );
	}

	/**
	 * Credit new order row
	 *
	 * @param int $id
	 * @param int $delivery_id
	 * @param array $row_data
	 *
	 * @return array
	 */
	public function credit_new_order_row( $id, $delivery_id, $row_data ) {
		$data = [
			'OrderId'      => intval( $id ),
			'DeliveryId'   => intval( $delivery_id ),
			'NewCreditRow' => $row_data,
		];

		$order_row_data = apply_filters( 'woocommerce_sco_credit_new_order_row', $data );
		$this->log_order( 'Crediting new order row', $order_row_data );
		return $this->client->creditNewOrderRow( $order_row_data );
	}

	/**
	 * Credit new order row - multiple
	 *
	 * @param int $id
	 * @param int $delivery_id
	 * @param array $rows_data
	 *
	 * @return array
	 */
	public function credit_new_order_rows( $id, $delivery_id, $rows_data ) {
		$data = [
			'OrderId'       => intval( $id ),
			'DeliveryId'    => intval( $delivery_id ),
			'NewCreditRows' => $rows_data,
		];

		$order_row_data = apply_filters( 'woocommerce_sco_credit_new_order_rows', $data );
		$this->log_order( 'Crediting new order rows', $order_row_data );
		return $this->client->creditNewOrderRow( $order_row_data );
	}

	/**
	 * Add order row to existing order
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function add_order_row( $id, $row_data ) {
		$data = [
			'OrderId'  => absint( $id ),
			'OrderRow' => $row_data,
		];

		$order_row_data = apply_filters( 'woocommerce_sco_add_order_row', $data );
		$this->log_order( 'Adding order row', $order_row_data );
		return $this->client->addOrderRow( $order_row_data );
	}

	/**
	 * Credit order amount
	 *
	 * @param int $id
	 * @param int $delivery_id
	 * @param int $amount
	 * @return array
	 */
	public function credit_order_amount( $id, $delivery_id, $amount ) {
		$data = [
			'OrderId'        => intval( $id ),
			'DeliveryId'     => intval( $delivery_id ),
			'CreditedAmount' => $amount,
		];

		$credit_order_data = apply_filters( 'woocommerce_sco_credit_order_amount', $data );
		$this->log_order( 'Crediting amount', $credit_order_data );
		return $this->client->creditOrderAmount( $credit_order_data );
	}

	/**
	 * Remove a order row
	 *
	 * @param int $id
	 * @param int $row_id
	 * @return array
	 */
	public function cancel_order_row( $id, $row_id ) {
		$data = [
			'OrderId'    => absint( $id ),
			'OrderRowId' => absint( $row_id ),
		];

		$order_row_data = apply_filters( 'woocommerce_sco_remove_order_row', $data );
		$this->log_order( 'Canceling order row', $order_row_data );
		return $this->client->cancelOrderRow( $order_row_data );
	}

	/**
	 * Update order row
	 *
	 * @param int $id
	 * @param int $row_id
	 * @param array $cart_item
	 * @return array
	 */
	public function update_order_row( $id, $row_id, $cart_item ) {
		$data = [
			'OrderId'    => absint( $id ),
			'OrderRowId' => absint( $row_id ),
			'OrderRow'   => $cart_item,
		];

		$order_row_data = apply_filters( 'woocommerce_sco_update_order_row', $data );
		$this->log_order( 'Updating order row', $order_row_data );
		return $this->client->updateOrderRow( $order_row_data );
	}

	/**
	 * Deliver order in Svea
	 *
	 * @param int $id
	 * @param array $data
	 * @return array
	 */
	public function deliver_order( $id, $data ) {
		$deliver_data = [
			'OrderId'     => intval( $id ),
			'OrderRowIds' => $data,
		];

		$deliver_data = apply_filters( 'woocommerce_sco_deliver_order', $deliver_data );
		$this->log_order( 'Delivering order', $deliver_data );
		$this->client->deliverOrder( $deliver_data );
	}
}
