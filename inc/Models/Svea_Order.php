<?php
namespace Svea_Checkout_For_Woocommerce\Models;

use Svea\Checkout\CheckoutAdminClient;
use Svea\Checkout\CheckoutClient;
use Svea\Checkout\Transport\Connector;
use Svea_Checkout_For_Woocommerce\Helper;
use Svea_Checkout_For_Woocommerce\Models\Traits\Items_From_Order;
use Svea_Checkout_For_Woocommerce\Models\Traits\Logger;
use Svea_Checkout_For_Woocommerce\Session_Table;
use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;
use Svea_Checkout_For_Woocommerce\WC_Shipping_Svea_Nshift;

use WC_Order;

use function Svea_Checkout_For_Woocommerce\svea_checkout;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Svea_Order {
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
	 * @var \Svea\Checkout\CheckoutClient
	 */
	private $client;

	/**
	 * Svea checkout client
	 *
	 * @var \Svea\Checkout\CheckoutAdminClient
	 */
	private $admin_client;

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
	 * @param \WC_Cart $cart WooCommercecCart
	 * @param bool $admin Use the admin client
	 */
	public function __construct( $cart = null, $admin = false ) {
		$this->cart = $cart;
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();
		$this->setup_client( $cart, $admin );
	}

	/**
	 * Setup Svea connector
	 *
	 * @param \WC_Order|null $wc_order
	 * @param bool $admin
	 *
	 * @return void
	 */
	public function setup_client( $wc_order, $admin ) {
		$currency = '';
		$country = '';
		$is_instore = false;

		// If we're accessing an order, make sure the client is set with the correct information
		if ( is_a( $wc_order, 'WC_Order' ) ) {
			$currency = $wc_order->get_currency();
			$country = $wc_order->get_billing_country();

			if ( $admin && $wc_order->get_meta( '_sco_instore_payment_link' ) ) {
				$is_instore = true;
			}
		} elseif ( is_array( $wc_order ) ) {
			$currency = $wc_order['currency'];
			$country = $wc_order['country'];
		}

		$this->country_settings = $this->gateway->get_merchant_settings( $currency, $country );

		if ( $is_instore ) {
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

		// Set endpoint url. Eg. test or prod
		if ( $admin ) {
			$base_url = $this->country_settings['AdminBaseUrl'];
		} else {
			$base_url = $this->country_settings['BaseUrl'];
		}

		$this->connector = Connector::init( $checkout_merchant_id, $checkout_secret, $base_url );

		if ( $admin ) {
			$this->admin_client = new CheckoutAdminClient( $this->connector );
		} else {
			$this->client = new CheckoutClient( $this->connector );
		}
	}

	/**
	 * Get a checksum for the cart for change comparison
	 *
	 * @return string
	 * @phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	 */
	public function get_current_cart_hash() {
		/** @var \WC_Session_Handler $session */
		$session = WC()->session;
		return apply_filters(
			'woocommerce_sco_cart_hash',
			md5(
				serialize( WC()->cart->get_cart() ) .
				serialize( $session->get( 'chosen_shipping_methods' ) ) .
				serialize( WC()->cart->get_fees() ) .
				serialize( $session->get( 'sco_cookie_hash' ) ) .
				serialize( WC()->cart->get_total() ) .
				serialize( WC()->customer->get_billing_country() )
			)
		);
	}

	/**
	 * Get order from the admin interface
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public function get_order( $id ) {
		$data = [
			'OrderId' => intval( $id ),
		];

		return $this->admin_client->getOrder( apply_filters( 'woocommerce_sco_admin_get_order', $data ) );
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
		return $this->admin_client->cancelOrderAmount( $order_amount_data );
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
		return $this->admin_client->cancelOrder( $order_data );
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
		return $this->admin_client->creditNewOrderRow( $order_row_data );
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
		return $this->admin_client->addOrderRow( $order_row_data );
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
		return $this->admin_client->creditOrderAmount( $credit_order_data );
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
		return $this->admin_client->cancelOrderRow( $order_row_data );
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
		return $this->admin_client->updateOrderRow( $order_row_data );
	}

	/**
	 * Update token with new status
	 *
	 * @param string $token
	 * @param string $status
	 * @return void
	 */
	public function update_token( $token, $status ) {
		$data = [
			'Token'  => $token,
			'Status' => $status,
		];

		$token_data = apply_filters( 'woocommerce_sco_update_token', $data );
		$this->log_order( 'Updating token', $token_data );
		$this->client->updateToken( $token_data );
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
		$this->admin_client->deliverOrder( $deliver_data );
	}

	/**
	 * Get the module response
	 *
	 * @return array
	 */
	public function get_module() {
		// The client could not be setup with the current country and/or currency
		if ( $this->client === null ) {
			$msg = esc_html__( 'Could not connect to Svea. Please try again', 'svea-checkout-for-woocommerce' );

			if ( current_user_can( 'manage_woocommerce' ) ) {
				$currency = get_woocommerce_currency();
				$country = WC()->customer->get_billing_country();

				/* translators: %1$s = country, %2$s = currency */
				$format = esc_html__( 'The store does not have valid credentials for %1$s in combination with %2$s. This message is only visible to logged in store managers.', 'svea-checkout-for-woocommerce' );

				$msg = sprintf( $format, $country, $currency );
			}

			return $this->create_error_msg( $msg );
		}

		$sco_id = WC()->session->get( 'sco_order_id' );

		// Fetch checkout for existing order in Svea
		if ( $sco_id && WC()->customer->get_billing_country() === WC()->session->get( 'sco_order_country_code' ) ) {
			$current_hash = $this->get_current_cart_hash();
			$synced_hash = WC()->session->get( 'sco_latest_hash' );

			// Customer ID has updated, create a new order
			if ( WC()->session->get( 'sco_customer_id' ) !== WC()->session->get_customer_id() ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'WC Customer ID has changed from %s to %s, creating new checkout', WC()->session->get( 'sco_customer_id' ), WC()->session->get_customer_id() ) );
				$response = $this->create();
			} else if ( $current_hash !== $synced_hash ) {
				// The cart has been updated
				WC_Gateway_Svea_Checkout::log( sprintf( 'WC Cart hash has changed from %s to %s, updating the current checkout', $current_hash, $synced_hash ) );
				$response = $this->update( $sco_id );

			} else {
				// Fetch the existing cart
				$response = $this->get( $sco_id );

				// This one is old, make a new one
				if (
					$response &&
					(
						$response['Status'] === 'Cancelled' ||
						$response['Status'] === 'Final'
					)
				) {
					WC_Gateway_Svea_Checkout::log( sprintf( 'Status of current order is %s, creating new checkout forcefully', $response['Status'] ) );
					$response = $this->create( true );
				}
			}
		} else {
			// Create a new order in Svea from the cart
			$response = $this->create();
		}

		return $response;
	}

	/**
	 * Get an order from Svea
	 *
	 * @param int $sco_id
	 * @param string $token
	 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
	 * @return array
	 */
	public function get( $sco_id, $token = '' ) {
		$data = [
			'OrderId' => absint( $sco_id ),
		];

		if ( $token ) {
			$data['Token'] = $token;
		}

		try {
			$data = apply_filters( 'woocommerce_sco_get_order', $data );
			$response = $this->client->get( $data );
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when getting Svea order: %s with data: %s', $e->getMessage(), var_export( $data, true ) ) );

			if ( WC()->session ) {
				WC()->session->set( 'order_awaiting_payment', false );
			}

			return $this->create_error_msg( Helper::get_svea_error_message( $e ) );
		}

		return $response;
	}

	/**
	 * Create a new order in Svea from the cart
	 *
	 * @param bool $force
	 * @return array
	 */
	public function create( $force = false ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		$table_name = $wpdb->prefix . 'woocommerce_sessions';

		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_id FROM $table_name WHERE session_key = %s LIMIT 1 FOR UPDATE", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				WC()->session->get_customer_id()
			)
		);

		$current_token = Session_Table::get_token_by_session_key( WC()->session->get_customer_id() );

		if ( ! $force && ! empty( $current_token ) ) {
			$wpdb->query( 'COMMIT' );

			$cache_group = defined( 'WC_SESSION_CACHE_GROUP' ) ? \WC_SESSION_CACHE_GROUP : 'wc_session_id';
			wp_cache_delete( \WC_Cache_Helper::get_cache_prefix( $cache_group ) . WC()->session->get_customer_id(), $cache_group );
			WC()->session->init_session_cookie();

			if ( WC()->session->get( 'sco_order_id' ) && WC()->customer->get_billing_country() === WC()->session->get( 'sco_order_country_code' ) ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Found SCO ID (%s) in session to continue from', WC()->session->get( 'sco_order_id' ) ) );
				return $this->get( WC()->session->get( 'sco_order_id' ) );
			}
		}

		do {
			$token = wp_generate_password( 32, false );
		} while ( Session_Table::get_session_key_by_sco_token( $token ) );

		Session_Table::add( $token, WC()->session->get_customer_id() );

		$preset_values = [];
		$customer = WC()->customer;
		$user_email = $customer->get_billing_email();
		$user_zipcode = $customer->get_billing_postcode();
		$user_phone = $customer->get_billing_phone();

		// Set preset values
		if ( isset( $user_email ) && ! empty( $user_email ) ) {
			array_push(
				$preset_values,
				[
					'TypeName'   => 'EmailAddress',
					'Value'      => $user_email,
					'IsReadOnly' => $this->gateway->is_preset_email_read_only(),
				]
			);
		}

		if ( isset( $user_zipcode ) && ! empty( $user_zipcode ) ) {
			array_push(
				$preset_values,
				[
					'TypeName'   => 'PostalCode',
					'Value'      => $user_zipcode,
					'IsReadOnly' => $this->gateway->is_preset_zip_code_read_only(),
				]
			);
		}

		if ( isset( $user_phone ) && ! empty( $user_phone ) ) {
			array_push(
				$preset_values,
				[
					'TypeName'   => 'PhoneNumber',
					'Value'      => $user_phone,
					'IsReadOnly' => $this->gateway->is_preset_phone_read_only(),
				]
			);
		}

		try {
			$data = [
				'Cart' => [
					'Items' => $this->get_items(),
				],
			];
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when creating Svea order items: %s', $e->getMessage() ) );
			return $this->create_error_msg( esc_html__( 'Could not create order items', 'svea-checkout-for-woocommerce' ) . ': ' . $e->getMessage() );
		}

		// Get supported customer types in the store
		$customer_types = $this->gateway->get_customer_types();

		// Check if the checkout should limit the customer type selection
		if ( $customer_types === 'both' ) {
			$preset_values[] = [
				'TypeName'   => 'IsCompany',
				'Value'      => $this->gateway->is_company_default(),
				'IsReadOnly' => false,
			];
		} else {
			if ( $customer_types === 'company' ) {
				$preset_values[] = [
					'TypeName'   => 'IsCompany',
					'Value'      => true,
					'IsReadOnly' => true,
				];
			} elseif ( $customer_types === 'individual' ) {
				$preset_values[] = [
					'TypeName'   => 'IsCompany',
					'Value'      => false,
					'IsReadOnly' => true,
				];
			}
		}

		$data['IdentityFlags'] = [
			'HideNotYou'        => $this->gateway->should_hide_not_you(),
			'HideChangeAddress' => $this->gateway->should_hide_change_address(),
			'HideAnonymous'     => $this->gateway->should_hide_anonymous(),
		];

		$data['PresetValues'] = $preset_values;

		if ( ! $this->cart ) {
			$msg = esc_html__( 'Missing cart', 'svea-checkout-for-woocommerce' );
			$response['Gui'] = [ 'Snippet' => $msg ];
			return $response;
		}

		// Set partner key
		$data['PartnerKey'] = '1D8C75CE-06AC-43C8-B845-0283E100CEE1';

		$id = WC()->session->get_customer_id();

		// This temp id is later changed into the actual order id
		$temp_id = 'sco_con_' . substr( $id, 0, 13 ) . '_' . time();

		$data['ClientOrderNumber'] = sanitize_text_field( apply_filters( 'woocommerce_sco_client_order_number', $temp_id ) );
		$data['CountryCode'] = WC()->customer->get_billing_country() ? WC()->customer->get_billing_country() : wc_get_base_location()['country'];

		$data['Currency'] = get_woocommerce_currency();
		$data['Locale'] = Helper::get_svea_locale( get_locale() );
		$data['MerchantSettings'] = $this->get_merchant_settings( $token );
		$data['ShippingInformation'] = $this->get_shipping_information();

		$data['Recurring'] = $this->is_recurring();

		try {
			$order_data = apply_filters( 'woocommerce_sco_create_order', $data );

			$this->log_order( 'Creating order', $order_data );
			$response = $this->client->create( $order_data );
			WC_Gateway_Svea_Checkout::log( sprintf( 'Order creation resulted in the order %s', $response['OrderId'] ) );

			// Update the SCO ID
			WC()->session->set( 'sco_order_id', $response['OrderId'] );
			WC()->session->set( 'sco_customer_id', WC()->session->get_customer_id() );
			WC()->session->set( 'sco_currency', $data['Currency'] );
			WC()->session->set( 'sco_order_country_code', $response['CountryCode'] );
			WC()->session->set( 'sco_cookie_hash', $_COOKIE[ apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH ) ] );
			WC()->session->set( 'sco_latest_hash', $this->get_current_cart_hash() );
			WC()->session->set( 'order_awaiting_payment', null );

			WC()->session->save_data();

			$this->map_cart_items( $response );

			$wpdb->query( 'COMMIT' );

			return $response;
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when creating Svea order: %s', $e->getMessage() ) );
			if ( current_user_can( 'manage_options' ) ) {
				return $this->create_error_msg( esc_html__( 'Error message from Svea', 'svea-checkout-for-woocommerce' ) . ': ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Does the cart contain a recurring product?
	 *
	 * @return bool
	 */
	public function is_recurring() {
		if ( class_exists( '\WC_Subscriptions_Cart' ) ) {
			return \WC_Subscriptions_Cart::cart_contains_subscription();
		}

		return false;
	}

	/**
	 * Create a order via token as a recurring payment
	 *
	 * @param \WC_Order $wc_order
	 * @return bool
	 */
	public function create_recurring( $wc_order ) {
		$data = [
			'CountryCode'       => $wc_order->get_shipping_country(),
			'Currency'          => $wc_order->get_currency(),
			'ClientOrderNumber' => $wc_order->get_order_number(),
			'Cart'              => [
				'Items' => $this->get_items_from_order( $wc_order ),
			],
			'MerchantSettings'  => [
				'PushUri' => add_query_arg(
					[
						'wc_order_id' => $wc_order->get_id(),
						'key'         => $wc_order->get_order_key(),
					],
					home_url( 'wc-api/svea_checkout_push_recurring/' )
				),
			],
			'PartnerKey'        => '1D8C75CE-06AC-43C8-B845-0283E100CEE1',
			'Token'             => $wc_order->get_meta( '_svea_co_token' ),
		];

		try {
			$order_data = apply_filters( 'woocommerce_sco_create_recurring_order', $data );

			$this->log_order( 'Creating recurring order', $order_data );
			$response = $this->client->create( $order_data );

			$wc_order->update_meta_data( '_svea_co_token', $response['recurringToken'] );
			$wc_order->update_meta_data( '_svea_co_order_id', $response['orderId'] );

			// Save order row id based on mapping
			$mapping = $wc_order->get_meta( '_svea_co_order_item_mapping' );
			$wc_items = $wc_order->get_items( [ 'line_item', 'fee', 'shipping' ] );
			$cart_items = $response['cart']['items'];

			foreach ( $cart_items as $svea_item ) {
				// Get the corresponding key from the mapping
				$key = array_search( $svea_item['temporaryReference'], $mapping, true );

				if ( ! empty( $key ) ) {

					// Find the matching item in the order
					foreach ( $wc_items as $wc_item ) {
						if ( $wc_item->get_id() === $key ) {
							$wc_item->delete_meta_data( '_svea_co_cart_key' );
							$wc_item->update_meta_data( '_svea_co_order_row_id', $svea_item['rowNumber'] );
							$wc_item->save();
							break;
						}
					}
				}
			}

			$wc_order->save();

			return true;
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when creating Svea recurring order. Code: %s, Message: %s', $e->getCode(), $e->getMessage() ) );
		}

		return false;
	}

	/**
	 * Get merchant settings
	 *
	 * @param string $token
	 * @return array
	 */
	public function get_merchant_settings( $token ) {
		return apply_filters(
			'woocommerce_sco_merchant_data',
			[
				'TermsUri'                      => wc_get_page_permalink( 'terms' ),
				'CheckoutUri'                   => add_query_arg( [ 'callback' => 'svea' ], wc_get_checkout_url() ),
				'ConfirmationUri'               => $this->get_confirmation_uri( $token ),
				'PushUri'                       => $this->get_push_uri( $token ),
				'CheckoutValidationCallBackUri' => $this->get_validation_callback_uri( $token ),
				'WebhookUri'                    => $this->get_webhook_uri(),
			]
		);
	}

	/**
	 * Get Svea webhook shipping URI
	 *
	 * @return string
	 */
	public function get_webhook_uri() {
		return home_url( 'wc-api/svea_webhook/' );
	}

	/**
	 * Get Svea webhook callback URI
	 *
	 * @param string $token
	 * @return string
	 */
	public function get_validation_callback_uri( $token ) {
		return add_query_arg(
			[
				'svea_order_id' => '{checkout.order.uri}',
				'svea_token'    => $token,
			],
			home_url( 'wc-api/svea_validation_callback/' )
		);
	}

	/**
	 * Get Svea webhook confirmation URI
	 *
	 * @param string $token
	 * @return string
	 */
	public function get_confirmation_uri( $token ) {
		return add_query_arg(
			[
				'sco_redirect' => 'true',
				'sco_token'    => $token,
			],
			wc_get_checkout_url()
		);
	}

	/**
	 * Get the push URI
	 *
	 * @param string $token
	 * @return string
	 */
	public function get_push_uri( $token ) {
		return add_query_arg(
			[
				'svea_order_id' => '{checkout.order.uri}',
				'svea_token'    => $token,
			],
			home_url( 'wc-api/svea_checkout_push/' )
		);
	}

	/**
	 * Get shipping information
	 *
	 * @return array
	 */
	public function get_shipping_information() {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		return [
			'EnableShipping'  => $this->is_nshift_available(),
			'EnforceFallback' => false,
			'Weight'          => WC()->cart->get_cart_contents_weight() * 1000, // kg -> g phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// 'Tags' => [
			//  'bulky' => true
			// ],
			// 'FalbackOptions' => [
			//  [
			//      'id' => 1,
			//      'Carrier' => 'PostNord',
			//      'Name' => 'Hemleverans',
			//      'Price' => 2900
			//  ]
			// ]
		];
	}

	/**
	 * Update an existing order in Svea
	 *
	 * @param int $id
	 * @return array
	 */
	public function update( $id ) {
		try {
			// Check if a new order is needed or if the current order ID is viable
			try {

				$response = $this->get( $id );
				switch ( true ) {
					case isset( $response['Recurring'] ) && $response['Recurring'] !== $this->is_recurring():
					case ! isset( $response['CountryCode'] ) || $response['CountryCode'] !== WC()->customer->get_billing_country():
					case $response['Status'] !== 'Created':
						// Set session to 0 to prevent the order from being updated in the create-call
						WC()->session->set( 'sco_order_id', 0 );
						WC()->session->save_data();
						return $this->create();
				}
			} catch ( \Exception $e ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Could not get order from Svea. Creating new order. Message from Svea: %s', $e->getMessage() ) );
				return $this->create();
			}

			switch ( true ) {
				case $response['CountryCode'] !== WC()->customer->get_billing_country():
				case $response['Status'] === 'Final':
				case $response['Status'] === 'Cancelled':
					return $this->create();
			}

			$data = [
				'OrderId' => $id,
				'Cart'    => [
					'Items' => $this->get_items(),
				],
			];

			if ( WC()->cart->needs_shipping() ) {
				$data['ShippingInformation'] = $this->get_shipping_information();
			}

			$order_data = apply_filters( 'woocommerce_sco_update_order', $data );

			$this->log_order( 'Updating order', $order_data );

			try {
				$response = $this->client->update( $order_data );
				// Update the SCO ID
				WC()->session->set( 'sco_order_id', $response['OrderId'] );
				$current_hash = $this->get_current_cart_hash();
				WC()->session->set( 'sco_latest_hash', $current_hash );

				$this->map_cart_items( $response );
			} catch ( \Exception $e ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Order could not change status. Creating new order. Error from Svea: %s', $e->getMessage() ) );
				return $this->create_error_msg( $e->getMessage() );
			}
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when creating Svea order: %s', $e->getMessage() ) );
			return $this->create_error_msg( $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Map cart items with keys for later ref
	 *
	 * @param array $response
	 * @return void
	 */
	public function map_cart_items( $response ) {
		$svea_items = $response['Cart']['Items'];
		$mapping = [];

		if ( ! empty( $svea_items ) ) {
			foreach ( $svea_items as $item ) {
				if ( $item['TemporaryReference'] ) {
					$mapping[ $item['RowNumber'] ] = $item['TemporaryReference'];
				}
			}
		}

		WC()->session->set( 'sco_item_mapping', $mapping );
	}

	/**
	 * Map order items
	 *
	 * @return array
	 */
	public function map_items() {
		$cart_items = $this->cart->get_cart();
		$svea_cart_items = [];

		// Products
		if ( ! empty( $cart_items ) ) {
			foreach ( $cart_items as $key => $item ) {
				$svea_item = new Svea_Item();
				$svea_item->map_product( $item, $key );
				$svea_cart_items[] = $svea_item;
			}
		}

		// Shipping
		$packages = WC()->shipping()->get_packages();
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		foreach ( $packages as $package_key => $package ) {
			if ( isset( $chosen_shipping_methods[ $package_key ], $package['rates'][ $chosen_shipping_methods[ $package_key ] ] ) ) {
				$rate = $package['rates'][ $chosen_shipping_methods[ $package_key ] ];
				$key = $chosen_shipping_methods[ $package_key ];

				$svea_item = new Svea_Item();
				$svea_item->map_shipping( $rate, $package_key . '_' . $key );

				// Support for multiple different tax lines
				$items = $svea_item->get_shipping_items();
				foreach ( $items  as $item ) {
					$svea_cart_items[] = $item;
				}
			}
		}

		// Fees
		$fees = WC()->cart->get_fees();

		if ( ! empty( $fees ) ) {
			foreach ( $fees as $key => $fee ) {
				$svea_item = new Svea_Item();
				$svea_item->map_fee( $fee, $key );
				$svea_cart_items[] = $svea_item;
			}
		}

		/** @var Svea_Item[] $items */
		$items = apply_filters( 'woocommerce_sco_cart_items', $svea_cart_items, $this );

		$tot_diff = round(
			array_sum(
				array_map(
					function( $item ) {
						return $item->get_diff();
					},
					$items
				)
			) * 100
		) / 100;

		// Make a soft comparison to avoid float errors
		if ( $tot_diff != 0 ) { // phpcs:ignore
			$svea_item = new Svea_Item();
			$svea_item->map_rounding( $tot_diff );
			$items[] = $svea_item;
		}

		$items = apply_filters( 'woocommerce_sco_cart_items_after_rounding', $items, $this );

		return $items;
	}

	/**
	 * Create a error message in the Svea GUI Snippet format
	 *
	 * @param string $msg
	 *
	 * @return array
	 */
	public function create_error_msg( $msg ) {
		return [
			'Gui' => [ 'Snippet' => $msg ],
		];
	}

	/**
	 * Get items formatted for Svea
	 *
	 * @return array
	 */
	public function get_items() {
		$items = $this->map_items();

		$items = apply_filters(
			'woocommerce_sco_cart_items_from_cart',
			array_map(
				function( $item ) {
					/** @var Svea_Item $item  */
					return $item->get_svea_format();
				},
				$items
			)
		);

		if ( count( $items ) > self::MAX_NUM_ROWS ) {
			throw new \Exception( 'The order may only contain a maximum of ' . self::MAX_NUM_ROWS . ' rows', 1 ); //phpcs:ignore
		}

		return $items;
	}

	/**
	 * Is Nshift available?
	 *
	 * @return bool
	 */
	public function is_nshift_available() {
		$methods = WC()->session->get( 'chosen_shipping_methods', [] );
		$is_nshift = false;

		foreach ( $methods as $method ) {
			if ( explode( ':', $method )[0] === WC_Shipping_Svea_Nshift::METHOD_ID ) {
				$is_nshift = true;
				break;
			}
		}

		$is_svea = svea_checkout()->template_handler->is_svea();

		return WC_Gateway_Svea_Checkout::is_nshift_enabled() &&
			$is_svea &&
			$is_nshift &&
			WC()->cart->needs_shipping() &&
			! $this->is_recurring();
	}

}
