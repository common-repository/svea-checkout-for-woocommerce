<?php
namespace Svea_Checkout_For_Woocommerce\Models;

use Svea\Instore\Api\OrderApi;
use Svea\Instore\Configuration;
use Svea\Instore\DevConfiguration;
use Svea\Instore\Model\CreateOrderRequest;
use Svea\Instore\Model\CreateOrderResponse;
use Svea\Instore\Model\GetOrderStatusResponse;
use Svea_Checkout_For_Woocommerce\Models\Traits\Items_From_Order;
use Svea_Checkout_For_Woocommerce\Models\Traits\Logger;
use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;

use WC_Order;


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Svea_Instore {
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
	 * WooCommerce order
	 *
	 * @var \WC_Order
	 */
	private $wc_order = null;

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
	 * Api
	 *
	 * @var OrderApi
	 */
	private $api;

	/**
	 * Maximum number of order rows allowed by Svea
	 */
	const MAX_NUM_ROWS = 1000;

	/**
	 * Create the order
	 *
	 * @param \WC_Order $wc_order
	 */
	public function __construct( $wc_order = null ) {
		$this->wc_order = $wc_order;
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();
		$this->setup_client( $this->wc_order );
	}

	/**
	 * Setup Svea connector
	 *
	 * @param \WC_Order|null $wc_order
	 * @param bool $admin
	 *
	 * @return void
	 */
	public function setup_client( $wc_order ) {
		$currency = $wc_order->get_currency();
		$country = $wc_order->get_billing_country();

		$this->country_settings = $this->gateway->get_merchant_settings( $currency, $country );

		$instore_merchant_id = $this->country_settings['InstoreMerchantId'];
		$instore_password = $this->country_settings['InstorePassword'];

		// Check if merchant ID and secret is set, else display a message
		if ( empty( $instore_merchant_id ) || empty( $instore_password ) ) {
			$msg = esc_html__( 'Merchant ID and password must be set to use Svea Instore', 'svea-checkout-for-woocommerce' );
			WC_Gateway_Svea_Checkout::log( sprintf( 'Error when getting instore merchant: %s', $msg ) );
			return;
		}

		if ( $this->country_settings['TestMode'] ) {
			$config = DevConfiguration::getDefaultConfiguration();
		} else {
			$config = Configuration::getDefaultConfiguration();
		}

		$config->setUsername( $instore_merchant_id )->setPassword( $instore_password );

		$this->api = new OrderApi( null, $config );
	}

	/**
	 * Create a new order in Svea from the cart
	 *
	 * @return CreateOrderResponse|array
	 */
	public function create() {
		$preset_values = [];

		// Set preset values
		if ( ! empty( $this->wc_order->get_billing_email() ) ) {
			array_push(
				$preset_values,
				[
					'attributeName' => 'EmailAddress',
					'value'         => $this->wc_order->get_billing_email(),
					'isReadOnly'    => $this->gateway->is_preset_email_read_only(),
				]
			);
		}

		if ( ! empty( $this->wc_order->get_shipping_postcode() ) ) {
			array_push(
				$preset_values,
				[
					'attributeName' => 'PostalCode',
					'value'         => $this->wc_order->get_shipping_postcode(),
					'isReadOnly'    => $this->gateway->is_preset_zip_code_read_only(),
				]
			);
		}

		$gateway = WC_Gateway_Svea_Checkout::get_instance();
		$minutes_until_link_expires = $gateway->get_option( 'instore_link_expire_minutes', 30 ) ?: 30;

		$data = [
			'merchantOrderNumber'     => $this->wc_order->get_order_number(),
			'countryCode'             => $this->wc_order->get_billing_country(),
			'currency'                => $this->wc_order->get_currency(),
			'mobilePhoneNumber'       => $this->wc_order->get_billing_phone(),
			'orderItems'              => $this->get_items_from_order( $this->wc_order ),
			'callbackUri'             => $this->get_push_uri(),
			'termsUri'                => wc_get_page_permalink( 'terms' ),
			'partnerKey'              => '1D8C75CE-06AC-43C8-B845-0283E100CEE1',
			'deferredDelivery'        => true,
			'minutesUntilLinkExpires' => $minutes_until_link_expires,
			'presetValues'            => $preset_values,
			'merchantName'            => 'WooCommerce - ' . get_bloginfo( 'name' ),
		];

		$order_data = apply_filters( 'woocommerce_sco_create_instore_order', $data, $this->wc_order );
		$this->log_order( 'Creating instore order', $order_data );

		try {
			$response = $this->api->createOrder( new CreateOrderRequest( $order_data ) );
			return $response;
		} catch ( \Exception $e ) {
			return [
				'error' => $e->getMessage(),
			];
		}
	}

	/**
	 * Get status of the order
	 *
	 * @param string|int $merchant_id
	 * @return GetOrderStatusResponse|array
	 */
	public function get_status( $merchant_id ) {
		try {
			$response = $this->api->getOrderStatus( $merchant_id );
			return $response;
		} catch ( \Exception $e ) {
			return [
				'error' => $e->getMessage(),
			];
		}

	}

	/**
	 * Get the push URI
	 *
	 * @return string
	 */
	public function get_push_uri() {
		return add_query_arg(
			[
				'wc_order_id'  => $this->wc_order->get_id(),
				'wc_order_key' => $this->wc_order->get_order_key(),
			],
			home_url( 'wc-api/svea_checkout_instore_push/' )
		);
	}
}
