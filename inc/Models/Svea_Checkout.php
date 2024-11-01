<?php
namespace Svea_Checkout_For_Woocommerce\Models;

use Svea\Checkout\CheckoutClient;
use Svea\Checkout\Transport\Connector;
use Svea_Checkout_For_Woocommerce\Helper;
use Svea_Checkout_For_Woocommerce\Models\Traits\Items_From_Order;
use Svea_Checkout_For_Woocommerce\Models\Traits\Logger;
use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;
use Svea_Checkout_For_Woocommerce\WC_Shipping_Svea_Nshift;

use WC_Order;

use function Svea_Checkout_For_Woocommerce\svea_checkout;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Svea_Checkout {
	use Items_From_Order, Logger;

	/**
	 * Error messages
	 *
	 * @var string[]
	 */
	public $errors = [];

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
	 * @param bool $setup WooCommercecCart
	 * @return void
	 */
	public function __construct( $setup = true ) {
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		if ( $setup ) {
			$this->setup_client(
				get_woocommerce_currency(),
				WC()->customer->get_billing_country()
			);
		}
	}

	/**
	 * Setup Svea connector
	 *
	 * @param \WC_Cart $cart
	 * @return void
	 */
	public function setup_client( $currency, $country ) {
		$this->country_settings = $this->gateway->get_merchant_settings( $currency, $country );

		$checkout_merchant_id = $this->country_settings['MerchantId'];
		$checkout_secret = $this->country_settings['Secret'];

		// Check if merchant ID and secret is set, else display a message
		if ( ! isset( $checkout_merchant_id[0] ) || ! isset( $checkout_secret[0] ) ) {
			$msg = esc_html__( 'Merchant ID and secret must be set to use Svea Checkout', 'svea-checkout-for-woocommerce' );
			WC_Gateway_Svea_Checkout::log( sprintf( 'Error when getting merchant: %s', $msg ) );
			return;
		}

		// Set endpoint url. Eg. test or prod
		$base_url = $this->country_settings['BaseUrl'];

		$this->connector = Connector::init( $checkout_merchant_id, $checkout_secret, $base_url );
		$this->client = new CheckoutClient( $this->connector );
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

		$response = false;

		if ( $sco_id ) {
			// Country changed, we need a new checkout
			if ( WC()->customer->get_billing_country() !== WC()->session->get( 'sco_order_country_code' ) ) {
				return $this->create();
			}

			// Check if the current checkout is valid
			$response = $this->get( $sco_id );

			if ( $this->needs_new( $response ) ) {
				$response = $this->create();
			} else if ( $this->needs_update() ) {
				$response = $this->update( $sco_id );
			}
		}

		if ( empty( $response ) ) {
			$response = $this->create();
		}

		return $response;
	}

	/**
	 * Does this checkout need a new order?
	 *
	 * @param array $checkout_data
	 * @return bool
	 */
	public function needs_new( $checkout_data ) {
		$need_new = false;

		switch ( true ) {
			case isset( $checkout_data['Recurring'] ) && $checkout_data['Recurring'] !== $this->is_recurring():
			case ! isset( $checkout_data['CountryCode'] ) || $checkout_data['CountryCode'] !== WC()->customer->get_billing_country():
			case $checkout_data['Status'] !== 'Created':
			case $checkout_data['CountryCode'] !== WC()->customer->get_billing_country():
			case $checkout_data['Status'] === 'Final':
			case $checkout_data['Status'] === 'Cancelled':
			case ! isset( $checkout_data['MerchantSettings']['UseClientSideValidation'] ) || $checkout_data['MerchantSettings']['UseClientSideValidation'] !== true:
				$need_new = true;
				break;
		}

		return apply_filters( 'woocommerce_sco_needs_new_checkout', $need_new, $checkout_data );
	}

	/**
	 * Does the current checkout need an update?
	 *
	 * @return bool
	 */
	public function needs_update() {
		$current_hash = $this->get_current_cart_hash();
		$synced_hash = WC()->session->get( 'sco_latest_hash' );

		return $current_hash !== $synced_hash;
	}

	/**
	 * Get an order from Svea
	 *
	 * @param int $sco_id Leave empty to use current session
	 * @param string $token
	 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
	 * @return array
	 */
	public function get( $sco_id = 0, $token = '' ) {
		if ( $sco_id === 0 ) {
			$sco_id = WC()->session->get( 'sco_order_id' );
		}

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
	public function create() {
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

		// Set partner key
		$data['PartnerKey'] = '1D8C75CE-06AC-43C8-B845-0283E100CEE1';

		$country_code = WC()->customer->get_billing_country() ? WC()->customer->get_billing_country() : wc_get_base_location()['country'];

		// Round to nearest 10 seconds
		$rounded_time = round( time() / 10 ) * 10;

		$id = WC()->session->get_customer_id();

		$temp_id_parts = [
			'sco_con',
			$country_code,
			get_woocommerce_currency(),
			substr( $id, 0, 5 ),
			$rounded_time,
		];

		// This temp id is later changed into the actual order id
		$data['ClientOrderNumber'] = sanitize_text_field( apply_filters( 'woocommerce_sco_client_order_number', strtolower( implode( '_', $temp_id_parts ) ) ) );
		$data['CountryCode'] = $country_code;

		$data['Currency'] = get_woocommerce_currency();
		$data['Locale'] = Helper::get_svea_locale( get_locale(), $country_code );
		$data['MerchantSettings'] = $this->get_merchant_settings();
		$data['ShippingInformation'] = $this->get_shipping_information();

		$data['Recurring'] = $this->is_recurring();

		try {
			$order_data = apply_filters( 'woocommerce_sco_create_order', $data );

			$this->log_order( 'Creating order', $order_data );
			$response = $this->client->create( $order_data );
			WC_Gateway_Svea_Checkout::log( sprintf( 'Order creation resulted in the order %s', $response['OrderId'] ) );

			// Update the SCO ID
			WC()->session->set( 'sco_order_id', $response['OrderId'] );
			WC()->session->set( 'sco_order_country_code', $response['CountryCode'] );
			WC()->session->set( 'sco_latest_hash', $this->get_current_cart_hash() );
			WC()->session->set( 'sco_customer_id', WC()->session->get_customer_id() );
			WC()->session->save_data();

			$this->map_cart_items( $response );
			return $response;
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when creating Svea order: %s', $e->getMessage() ) );

			if ( current_user_can( 'manage_options' ) ) {
				return $this->create_error_msg( esc_html__( 'Error message from Svea', 'svea-checkout-for-woocommerce' ) . ': ' . $e->getMessage() );
			}

			return $this->create_error_msg( esc_html__( 'Checkout could not be loaded, please contact the store owner', 'svea-checkout-for-woocommerce' ) );
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

			$wc_order->payment_complete( $response['orderId'] );

			// Check for invoice fee in payment admin
			$payment_admin = new Svea_Payment_Admin( $wc_order );
			$pa_order = false;

			// Max 10 tries
			for ( $i = 0; $i < 10; $i++ ) {
				try {
					$pa_order = $payment_admin->get( $response['orderId'] );
					break;
				} catch ( \Exception $e ) {
					// Could not fetch the order but it could simply be that the order was just created
				}

				sleep( 3 );
			}

			if ( isset( $pa_order['OrderRows'] ) ) {
				foreach ( $pa_order['OrderRows'] as $row ) {
					if ( $row['ArticleNumber'] === '6eaceaec-fffc-41ad-8095-c21de609bcfd' ) {
						$quantity = $row['Quantity'] / 100;
						$total = ( $row['UnitPrice'] / 100 ) * $quantity;

						$fee = new \WC_Order_Item_Fee();

						$vat_decimal = $row['VatPercent'] / 10000;
						$fee->set_props(
							[
								'quantity'  => $quantity,
								'name'      => $row['Name'],
								'total'     => $total / ( $vat_decimal + 1 ),
								'total_tax' => $total - ( $total / ( $vat_decimal + 1 ) ),
							]
						);

						$fee->save();
						$wc_order->add_item( $fee );
						$wc_order->calculate_totals();

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
	public function get_merchant_settings() {
		return apply_filters(
			'woocommerce_sco_merchant_data',
			[
				'UseClientSideValidation'             => true,
				'RequireClientSideValidationResponse' => true,
				'TermsUri'                            => wc_get_page_permalink( 'terms' ),
				'CheckoutUri'                         => add_query_arg( [ 'callback' => 'svea' ], wc_get_checkout_url() ),
				'ConfirmationUri'                     => $this->get_confirmation_uri(),
				'PushUri'                             => $this->get_push_uri(),
				'CheckoutValidationCallBackUri'       => $this->get_validation_callback_uri(),
				'WebhookUri'                          => $this->get_webhook_uri(),
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
	 * @return string
	 */
	public function get_validation_callback_uri() {
		return add_query_arg(
			[
				'svea_order_id' => '{checkout.order.uri}',
			],
			home_url( 'wc-api/svea_validation_callback/' )
		);
	}

	/**
	 * Get Svea webhook confirmation URI
	 *
	 * @return string
	 */
	public function get_confirmation_uri() {
		return add_query_arg(
			[
				'sco_redirect' => 'true',
			],
			wc_get_checkout_url()
		);
	}

	/**
	 * Get the push URI
	 *
	 * @return string
	 */
	public function get_push_uri() {
		return add_query_arg(
			[
				'svea_order_id' => '{checkout.order.uri}',
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
	 * Update an existing checkout in Svea
	 *
	 * @param int $id Leave empty to use the current session
	 * @return array|false
	 */
	public function update( $id = 0 ) {
		if ( empty( $id ) ) {
			$id = WC()->session->get( 'sco_order_id' );
		}

		$data = [
			'OrderId' => $id,
			'Cart'    => [
				'Items' => $this->get_items(),
			],
		];

		$data['ShippingInformation'] = $this->get_shipping_information();

		$order_data = apply_filters( 'woocommerce_sco_update_order', $data );

		$this->log_order( 'Updating order', $order_data );

		try {
			$response = $this->client->update( $order_data );

			// Update the SCO ID
			WC()->session->set( 'sco_order_id', $response['OrderId'] );
			WC()->session->set( 'sco_latest_hash', $this->get_current_cart_hash() );

			$this->map_cart_items( $response );
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Order could not change status. Error from Svea: %s', $e->getMessage() ) );
		}

		return $response ?? false;
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
	 * Create a error message in the Svea GUI Snippet format
	 *
	 * @param string $msg
	 * @return array
	 */
	public function create_error_msg( $msg ) {
		return [
			'Gui' => [ 'Snippet' => $msg ],
		];
	}

	/**
	 * Get hash based on information sent to Svea
	 *
	 * @return string
	 */
	public function get_current_cart_hash() {
		return apply_filters(
			'woocommerce_sco_cart_hash',
			md5(
				wp_json_encode(
					[
						$this->get_items(),
						WC()->cart->get_total(),
					]
				)
			)
		);
	}

	/**
	 * Get items formatted for Svea
	 *
	 * @return array
	 */
	public function get_items() {
		$cart_items = WC()->cart->get_cart();
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

		$items = apply_filters(
			'woocommerce_sco_cart_items_from_cart',
			array_map(
				function( $item ) {
					/** @var Svea_Item $item  */
					return $item->get_svea_format( $this->is_nshift_available() );
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
