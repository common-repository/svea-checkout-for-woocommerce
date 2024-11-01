<?php

namespace Svea_Checkout_For_Woocommerce;

use Svea_Checkout_For_Woocommerce\Models\Svea_Checkout;
use Svea_Checkout_For_Woocommerce\Models\Svea_Payment_Admin;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Webhook_Handler
 */
class Webhook_Handler {

	/**
	 * Svea order id
	 *
	 * @var int
	 */
	private $svea_order_id = 0;

	/**
	 * Gateway instance
	 *
	 * @var WC_Gateway_Svea_Checkout
	 */
	private $gateway;

	/**
	 * Svea order response
	 *
	 * @var array
	 */
	private static $svea_order;

	/**
	 * Cart session
	 *
	 * @var array
	 */
	public static $cart_session;

	/**
	 * User id used in validation
	 *
	 * @var mixed
	 */
	public $user_id;

	/**
	 * Cache key
	 */
	public const SCO_CACHE_KEY = '_sco_session_cache_';

	/**
	 * Start time of request
	 *
	 * @var int
	 */
	public $start_time = 0;

	/**
	 * Init function
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_api_svea_validation_callback', [ $this, 'validate_order' ] );
		add_action( 'woocommerce_api_svea_checkout_push', [ $this, 'process_push' ] );
		add_action( 'woocommerce_api_svea_checkout_push_recurring', [ $this, 'process_push_recurring' ] );
		add_action( 'woocommerce_api_svea_checkout_instore_push', [ $this, 'process_instore_push' ] );
		add_action( 'woocommerce_api_svea_webhook', [ $this, 'handle_webhook' ] );

		// Temporary solution to only sync the invoice fee
		add_filter( 'woocommerce_sco_should_add_new_item', [ $this, 'only_sync_invoice_fee' ], 15, 2 );
	}

	/**
	 * Only sync invoice fee
	 *
	 * @param bool $should_sync
	 * @param array $svea_cart_item
	 * @return bool
	 */
	public function only_sync_invoice_fee( $should_sync, $svea_cart_item  ) {
		if ( $svea_cart_item['ArticleNumber'] !== '6eaceaec-fffc-41ad-8095-c21de609bcfd' ) {
			// This isn't an invoice fee, we shouldn't sync this
			$should_sync = false;
		}

		return $should_sync;
	}

	/**
	 * Get the svea order response
	 *
	 * @return array
	 */
	public static function get_svea_order() {
		return self::$svea_order;
	}

	/**
	 * Get caller IP
	 *
	 * @return string
	 */
	private function get_referer_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}

	/**
	 * Validate that the request comes from Svea servers
	 *
	 * @return void
	 */
	public function validate_referer() {
		if ( $this->gateway->get_option( 'use_ip_restriction' ) !== 'yes' ) {
			return;
		}
		$ip_address = $this->get_referer_ip();
		$ref_ip = ip2long( $ip_address );

		$high_ip_range_1 = ip2long( '193.13.207.255' );
		$low_ip_range_1 = ip2long( '193.13.207.0' );
		$high_ip_range_2 = ip2long( '193.105.138.255' );
		$low_ip_range_2 = ip2long( '193.105.138.0' );

		$in_range_1 = ( $ref_ip <= $high_ip_range_1 && $ref_ip >= $low_ip_range_1 );
		$in_range_2 = ( $ref_ip <= $high_ip_range_2 && $ref_ip >= $low_ip_range_2 );

		if ( ! $in_range_1 && ! $in_range_2 ) {
			status_header( 403 );

			WC_Gateway_Svea_Checkout::log( 'A non allowed IP tried to make a webhook request: ' . $ip_address );

			\wc_add_notice( esc_html__( 'IP not allowed', 'svea-checkout-for-woocommerce' ), 'error' );
			$this->send_response( wc_print_notices( true ) );
		}
	}

	/**
	 * Handle the webhook sent from Svea
	 *
	 * @return void
	 */
	public function handle_webhook() {
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		$raw_webhook = file_get_contents( 'php://input' );

		if ( empty( $raw_webhook ) ) {
			$this->gateway::log( 'Empty webhook, no content' );
			exit;
		}

		$webhook_data = json_decode( $raw_webhook );
		$webhook_data->description = json_decode( $webhook_data->description );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->gateway::log( sprintf( 'Webhook callback received for Svea cart (%s) received', $webhook_data->orderId ) );

		$this->validate_referer();

		if ( empty( $webhook_data->orderId ) ) {
			$this->gateway::log( 'No orderID was found. Aborting' );
			exit;
		}

		$wc_order = self::get_order_by_svea_id( $webhook_data->orderId );

		$svea_order = new Svea_Checkout( false );
		$svea_order->setup_client( $wc_order->get_currency(), $wc_order->get_billing_country() );

		try {
			self::$svea_order = $svea_order->get( $webhook_data->orderId );
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Error in push webhook when getting order id %s. Message from Svea: %s' ), $this->svea_order_id, $e->getMessage() );
			status_header( 404 );
			exit;
		}

		if ( isset( self::$svea_order['ShippingInformation']['ShippingProvider']['ShippingOption']['ShippingFee'] ) ) {
			// We've got the fee, now verify that it's the same
			$shipping_fee = self::$svea_order['ShippingInformation']['ShippingProvider']['ShippingOption']['ShippingFee'];
			$addons = self::$svea_order['ShippingInformation']['ShippingProvider']['ShippingOption']['Addons'];
			if ( ! empty( $addons ) ) {
				foreach ( $addons as $addon ) {
					$shipping_fee += $addon['shippingFee'];
				}
			}

			$shipping_fee = $shipping_fee / 100;

			$current_shipping = (float) $wc_order->get_shipping_total() + (float) $wc_order->get_shipping_tax();

			// If the shipping has changed (or the visitor tampered with the data) a re-calculation has to be made
			if ( (float) $current_shipping !== (float) $shipping_fee ) {
				$this->sync_shipping_fee( $wc_order, $shipping_fee );

				$wc_order->calculate_totals();

				do_action( 'woocommerce_sco_before_webhook_update_shipping', $wc_order );

				$wc_order->save();

				do_action( 'woocommerce_sco_after_webhook_update_shipping', $wc_order );
			}
		}

		if ( empty( $wc_order ) ) {
			$this->gateway::log( sprintf( 'Received webhook but the order does not exists (%s)', $webhook_data->orderId ) );
			exit;
		}

		// Save the data
		if ( ! empty( $webhook_data->type ) ) {
			$wc_order->update_meta_data( '_sco_nshift_type', $webhook_data->type );
		}

		if ( ! empty( $webhook_data->description->tmsReference ) ) {
			$wc_order->update_meta_data( '_sco_nshift_tms_ref', $webhook_data->description->tmsReference );
		}

		if ( ! empty( $webhook_data->description->selectedShippingOption ) ) {
			$wc_order->update_meta_data( '_sco_nshift_carrier_id', $webhook_data->description->selectedShippingOption->id );
			$wc_order->update_meta_data( '_sco_nshift_carrier_name', $webhook_data->description->selectedShippingOption->carrier );
		}

		// Save the whole thing as a complete string
		$wc_order->update_meta_data( '_sco_nshift_data', $webhook_data );

		$wc_order->save();

		exit;
	}

	/**
	 * Validate order
	 *
	 * @return void
	 */
	public function validate_order() {
		// Set start time in order to see how long the request takes
		$this->start_time = time();

		// Ensure nothing gets printed
		ob_start();
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		if ( ! isset( $_GET['svea_order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$this->svea_order_id = sanitize_text_field( $_GET['svea_order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->gateway::log( sprintf( 'Validation callback for Svea order ID (%s) received', $this->svea_order_id ) );

		$this->validate_referer();

		do_action( 'woocommerce_sco_validation_get_order_before', self::$svea_order );

		// Get the order
		$wc_order = self::get_order_by_svea_id( $this->svea_order_id );

		if ( empty( $wc_order ) ) {
			$this->gateway::log( sprintf( 'Could not find order with Svea ID: %s', $this->svea_order_id ) );
			$this->send_response( esc_html__( 'Could not find order', 'svea-checkout-for-woocommerce' ) );
		}

		$svea_checkout = new Svea_Checkout( false );
		$svea_checkout->setup_client( $wc_order->get_currency(), $wc_order->get_billing_country() );
		$svea_order = $svea_checkout->get( $this->svea_order_id );

		// Check if sums match
		$wc_order_total = round( $wc_order->get_total(), 2 );
		$svea_order_total = 0;

		foreach ( $svea_order['Cart']['Items'] as $svea_cart_item ) {
			$svea_order_total += ( $svea_cart_item['UnitPrice'] / 100 ) * ( $svea_cart_item['Quantity'] / 100 );
		}

		// Round Svea order total to the same amount of decimals as the WC order total
		$svea_order_total = round( $svea_order_total, 2 );

		if ( (float) $svea_order_total !== (float) $wc_order_total ) { // phpcs:ignore
			$this->gateway::log( sprintf( 'Order total does not match in server validation callback. Svea sum: %s. WC sum: %s', (float) $svea_order_total, (float) $wc_order_total ) );
			$this->send_response( esc_html__( 'Order total does not match', 'svea-checkout-for-woocommerce' ) );
		}

		$should_do_mapping_validation = apply_filters( 'woocommerce_sco_should_do_cart_items_mapping_validation', true );

		if ( $should_do_mapping_validation ) {
			$has_error = false;
			$item_mapping = $wc_order->get_meta( '_svea_co_cart_mapping' );

			// Map row ID's in Svea to WooCommerce
			if ( ! empty( $wc_order ) && is_array( $item_mapping ) ) {
				$wc_items = $wc_order->get_items( [ 'line_item', 'fee', 'shipping' ] );

				foreach ( $wc_items as $item ) {
					$key = $item->get_meta( '_svea_co_cart_key' );
					$svea_row_number = array_search( $key, $item_mapping ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict

					if ( $svea_row_number !== false ) {
						unset( $item_mapping[ $svea_row_number ] );

						$item->delete_meta_data( '_svea_co_cart_key' );
						$item->save();
					} else {
						$this->gateway::log( sprintf( 'Item mapping missmatch. Svea Order ID: %s. WC-ID: %s. Item without match: %s', $this->svea_order_id, $wc_order->get_id(), $key ), 'error' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
						$has_error = true;
					}
				}
			}

			if ( ! empty( $item_mapping ) ) {
				$this->gateway::log( sprintf( 'Item mapping missmatch. Svea Order ID: %s. WC-ID: %s. Remaining items: %s', $this->svea_order_id, $wc_order->get_id(), var_export( $item_mapping, true ) ), 'error' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$has_error = true;
			}

			if ( $has_error ) {
				$this->send_response( esc_html__( 'Order is out of sync, please reload the page', 'svea-checkout-for-woocommerce' ) );
			}
		}

		$this->send_response( 'success', true, $wc_order->get_order_number() );
	}

	/**
	 * Get an order based on Svea order ID
	 *
	 * @param int $svea_order_id
	 * @return \WC_Order|false
	 */
	public static function get_order_by_svea_id( int $svea_order_id ) {
		$args = [
			'status'     => 'any',
			'limit'      => 1,
			'meta_key'   => '_svea_co_order_id',
			'meta_value' => $svea_order_id,
		];

		$orders = wc_get_orders( $args );

		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		return false;
	}

	/**
	 * Send validation response
	 *
	 * @param string $msg Message
	 * @param string $valid Valid or not
	 * @param string $order_number Client order number
	 * @return void
	 */
	public function send_response( $msg, $valid = false, $order_number = '' ) {
		$response = [
			'Valid'             => $valid,
			'Message'           => $msg,
			'ClientOrderNumber' => $order_number,
		];

		$this->gateway::log( sprintf( 'Sending validation response: %s', var_export( $response, true ) ) ); // phpcs:ignore

		$ob = ob_get_clean();
		if ( ! empty( $ob ) ) {
			$this->gateway::log( sprintf( 'Output buffer catched the following: %s', var_export( $ob, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		wp_send_json( $response );
		die;
	}

	/**
	 * Get the order from PaymentAdmin
	 *
	 * @param \WC_Order $wc_order
	 * @param bool $retry_pending Should we retry once if the order is pending
	 * @return array|false
	 */
	public function get_svea_order_by_payment_admin( $wc_order, $retry_pending = false ) {
		$payment_admin = new Svea_Payment_Admin( $wc_order );

		// Max 10 tries
		for ( $i = 0; $i < 10; $i++ ) {
			try {
				$pa_order = $payment_admin->get( $this->svea_order_id );
				break;
			} catch ( \Exception $e ) {
				// Could not fetch the order but it could simlpy be that the order was just created
			}

			sleep( 1 );
		}

		// If the order is pending it's a small risk that we're in the middle of a race condition, get the once again to be sure
		if ( $retry_pending && isset( $pa_order['OrderStatus'] ) && strtoupper( $pa_order['OrderStatus'] ) === 'PENDING' ) {
			sleep( 1 );
			$this->gateway::log( sprintf( 'Order is pending, retrying order #%s to make sure it\'s not a old one', $this->svea_order_id ) );
			$pa_order = $payment_admin->get( $this->svea_order_id );
		}

		return $pa_order ?? false;
	}

	/**
	 * Process push notifications for the order
	 *
	 * @return void
	 */
	public function process_push() {
		$this->svea_order_id = isset( $_GET['svea_order_id'] ) ? sanitize_text_field( $_GET['svea_order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		if ( ! $this->svea_order_id ) {
			status_header( 400 );
			esc_html_e( 'Missing params', 'svea-checkout-for-woocommerce' );
			die;
		}

		if ( $this->gateway->get_option( 'use_ip_restriction' ) === 'yes' ) {
			$this->validate_referer();
		}

		$this->gateway::log( sprintf( 'Receieved push for order %s', $this->svea_order_id ) );

		$wc_order = $this->get_order_by_svea_id( $this->svea_order_id );

		if ( empty( $wc_order ) ) {
			$msg = sprintf( 'Received push for order we don\'t have yet. Standing by (%s)', $this->svea_order_id );
			$this->gateway::log( $msg );
			status_header( 200 );
			echo esc_html( $msg );
			exit;
		}

		// Recognize that a push was made
		update_option( 'sco_last_push', time() );

		do_action( 'woocommerce_sco_process_push_before', $wc_order );

		// Get order from Svea Payment Admin
		self::$svea_order = $this->get_svea_order_by_payment_admin( $wc_order, true );

		if ( empty( self::$svea_order ) ) {
			$this->gateway::log( 'Tried fetching order from PaymentAdmin but failed. Aborting' );
			echo 'Order is not found in Payment Admin'; // phpcs:ignore
			status_header( 404 );
			exit;
		}

		// Open order from Payment Admin means it's finalized
		switch ( isset( self::$svea_order['OrderStatus'] ) && strtoupper( self::$svea_order['OrderStatus'] ) ) {
			case 'OPEN':
				$this->finalize_order( $wc_order );
				break;

			case 'FAILED':
			case 'CANCELLED':
			case 'EXPIRED':
				$this->cancel_order( $wc_order );
				break;
		}

		do_action( 'woocommerce_sco_process_push_after', $wc_order );
	}

	/**
	 * Process push notifications for the order
	 *
	 * @return void
	 */
	public function process_instore_push() {
		$wc_order_id = isset( $_GET['wc_order_id'] ) ? sanitize_text_field( $_GET['wc_order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = isset( $_GET['wc_order_key'] ) ? sanitize_text_field( $_GET['wc_order_key'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		if ( ! $wc_order_id ) {
			status_header( 400 );
			esc_html_e( 'Missing params', 'svea-checkout-for-woocommerce' );
			die;
		}

		if ( $this->gateway->get_option( 'use_ip_restriction' ) === 'yes' ) {
			$this->validate_referer();
		}

		$wc_order = wc_get_order( $wc_order_id );

		if ( empty( $wc_order ) ) {
			$msg = sprintf( 'Received instore push but could not find order. (Order ID: %s)', $wc_order_id );
			$this->gateway::log( $msg );
			status_header( 404 );
			echo esc_html( $msg );
			exit;
		}

		if ( $order_key !== $wc_order->get_order_key() ) {
			$msg = sprintf( 'Could not verify order key for order &s', $wc_order_id );
			$this->gateway::log( $msg );
			status_header( 401 );
			echo esc_html( $msg );
			exit;
		}

		// Recognize that a push was made (same as regular pushes)
		update_option( 'sco_last_push', time() );

		do_action( 'woocommerce_sco_process_instore_push_before', $wc_order );

		// Get order from Svea Payment Admin
		$this->svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );
		self::$svea_order = $this->get_svea_order_by_payment_admin( $wc_order, true );

		if ( empty( self::$svea_order ) ) {
			$this->gateway::log( 'Tried fetching order from PaymentAdmin but failed. Aborting' );
			status_header( 404 );
			exit;
		}

		switch ( strtoupper( self::$svea_order['OrderStatus'] ) ) {
			case 'OPEN':
			case 'DELIVERED':
				$this->finalize_order( $wc_order );
				break;

			case 'CANCELLED':
				$this->cancel_order( $wc_order );
				break;
		}
	}

	/**
	 * Process the push for a recurring order
	 *
	 * @return void
	 */
	public function process_push_recurring() {
		$order_id = isset( $_GET['wc_order_id'] ) ? sanitize_text_field( $_GET['wc_order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->gateway = WC_Gateway_Svea_Checkout::get_instance();

		if ( $order_id === 0 ) {
			$this->gateway::log( 'Missing order ID in recurring push webhook' );
			status_header( 400 );
			exit;
		}

		$wc_order = wc_get_order( $order_id );

		if ( empty( $wc_order ) ) {
			$this->gateway::log( sprintf( 'Could not find order %s in recurring push webhook', $order_id ) );
			status_header( 404 );
			exit;
		}

		if ( $this->gateway->get_option( 'use_ip_restriction' ) === 'yes' ) {
			$this->validate_referer();
		}

		$token = $wc_order->get_meta( '_svea_co_token' );

		// Check for token
		if ( empty( $token ) ) {
			$this->gateway::log( sprintf( 'Could not find token for order %s', $order_id ) );
			status_header( 404 );
			exit;
		}

		// Check if key matches key from order
		if ( $key !== $wc_order->get_order_key() ) {
			$this->gateway::log( sprintf( 'Could not verify key for order %s', $order_id ) );
			status_header( 404 );
			exit;
		}

		// Get order from Svea Payment Admin
		$this->svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );
		self::$svea_order = $this->get_svea_order_by_payment_admin( $wc_order );

		if ( empty( self::$svea_order ) ) {
			$this->gateway::log( 'Tried fetching order from PaymentAdmin but failed. Aborting' );
			echo 'Order is not found in Payment Admin'; // phpcs:ignore
			status_header( 404 );
			exit;
		}

		$wc_order->set_transaction_id( self::$svea_order['orderId'] );
		$wc_order->save();
	}

	/**
	 * Maybe sync the order
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function maybe_sync_order( $wc_order ) {
		if ( apply_filters( 'use_svea_order_sync', true ) ) {
			$this->sync_order_rows( $wc_order );
		}
	}

	/**
	 * The order is cancelled
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function cancel_order( $wc_order ) {
		$this->gateway::log( sprintf( 'Push callback. Cancelling order. %s', $wc_order->get_id() ) );

		if ( ! $wc_order->is_paid() ) {
			$wc_order->set_status( 'cancelled' );
			$wc_order->update_meta_data( '_svea_co_order_cancelled', true );
			$wc_order->save();
		} else {
			$wc_order->add_order_note( esc_html__( 'The order got a request to be cancelled in Svea. Please check the order in PaymentAdmin to ensure that the order is being captured properly', 'svea-checkout-for-woocommerce' ) );
		}

		do_action( 'woocommerce_sco_after_push_order', $wc_order, self::$svea_order );
		do_action( 'woocommerce_sco_after_push_order_cancel', $wc_order, self::$svea_order );

		echo 'Order cancelled';
		die;
	}

	/**
	 * Finalize the order
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function finalize_order( $wc_order ) {
		$this->gateway::log( sprintf( 'Finalizing order: %s', $this->svea_order_id ) );
		$current_status = $wc_order->get_status();

		// Complete the payment in WooCommerce
		if ( ! $wc_order->is_paid() ) {
			$this->maybe_sync_order( $wc_order );

			$svea_payment_type = strtoupper( sanitize_text_field( self::$svea_order['PaymentType'] ) );

			// Check if Payment method is set and exists in array
			$method_name = $this->gateway->get_payment_method_name( $svea_payment_type );

			if ( ! empty( $method_name ) ) {
				$wc_order->set_payment_method_title(
					sprintf( '%s (%s)', $this->gateway->get_title(), $method_name )
				);

				$wc_order->update_meta_data( '_svea_co_payment_type', $method_name );
			}

			// Make sure the name gets saved
			if ( self::$svea_order['IsCompany'] ) {
				Admin::save_refs( $wc_order, self::$svea_order );
			}

			$wc_order->update_meta_data( '_svea_co_is_company', (bool) self::$svea_order['IsCompany'] );
			$wc_order->update_meta_data( '_svea_co_order_final', current_time( 'timestamp', true ) );

			$this->gateway::log( sprintf( 'Push callback finalized order. Svea ID:%s OrderID: %s', $this->svea_order_id, $wc_order->get_id() ) );

			// Check system status
			if ( strtoupper( self::$svea_order['SystemStatus'] ) === 'PENDING' ) {
				$wc_order->set_status( Admin::AWAITING_ORDER_STATUS );
				// Check again later
				wp_schedule_single_event( time() + apply_filters( 'woocommerce_sco_pending_status_retry_time', HOUR_IN_SECONDS ), 'sco_check_pa_order_status', [ $this->svea_order_id ] );
			} else {
				$wc_order->payment_complete( $this->svea_order_id );
			}

			$wc_order->save();

			// Check if order is part of subscription
			if ( function_exists( 'wcs_get_subscriptions_for_order' ) && wcs_order_contains_subscription( $wc_order ) ) {
				// Get the token from the checkout
				$svea_checkout = new Svea_Checkout( false );
				$svea_checkout->setup_client( $wc_order->get_currency(), $wc_order->get_billing_country() );
				$svea_order = $svea_checkout->get( $this->svea_order_id );

				if ( $svea_order['Recurring'] && isset( $svea_order['RecurringToken'] ) ) {
					$wc_order->update_meta_data( '_svea_co_token', $svea_order['RecurringToken'] );
					$wc_order->save();

					$subscriptions = wcs_get_subscriptions_for_order( $wc_order );

					// Save token on subscriptions
					foreach ( $subscriptions as $subscription ) {
						$subscription->update_meta_data( '_svea_co_token', $svea_order['RecurringToken'] );
						$subscription->save();
					}
				}
			}
		} else {
			$this->gateway::log( sprintf( 'Svea order %s (WC order %s) is already finalized', $this->svea_order_id, $wc_order->get_id() ) );
		}

		// If the order was set to completed in this go, make sure the order get delivered
		if (
			$current_status !== 'completed' && // Old status
			$wc_order->get_status() === 'completed' && // New status
			$wc_order->get_meta( '_svea_co_deliver_date' ) === ''
		) {
			$this->gateway->deliver_order( $wc_order->get_id() );
		}

		do_action( 'woocommerce_sco_after_push_order', $wc_order, self::$svea_order );
		do_action( 'woocommerce_sco_after_push_order_final', $wc_order, self::$svea_order );

		status_header( 200 );
		echo 'Order finalized';
		die;
	}

	/**
	 * Sync order rows
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function sync_order_rows( $wc_order ) {
		$this->gateway::log( sprintf( 'Syncing order rows. Order ID: %s', $wc_order->get_id() ) );

		$svea_cart_items = self::$svea_order['OrderRows'];

		$svea_wc_order_item_row_ids = [];

		$rounding_order_id = $wc_order->get_meta( '_svea_co_rounding_order_row_id' );

		foreach ( $wc_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item_key => $order_item ) {
			$svea_wc_order_item_row_ids[] = intval( $order_item->get_meta( '_svea_co_order_row_id' ) );
		}

		// We might not need to calculate if the order is already in sync
		$calc_totals = false;

		// Only add new items
		foreach ( $svea_cart_items as $svea_cart_item ) {
			if (
				! in_array( $svea_cart_item['OrderRowId'], $svea_wc_order_item_row_ids, true ) &&
				(int) $rounding_order_id !== $svea_cart_item['OrderRowId']
			) {
				if ( ! apply_filters( 'woocommerce_sco_should_add_new_item', true, $svea_cart_item ) ) {
					continue;
				}

				$this->gateway::log( sprintf( 'Adding new item to order %s', $this->svea_order_id ) );

				$product_id = wc_get_product_id_by_sku( $svea_cart_item['ArticleNumber'] );

				if ( $product_id ) {
					$this->gateway::log( sprintf( 'New item found by SKU: %s with ID: %s. Svea order %s, WC order: %s', $svea_cart_item['ArticleNumber'], $product_id, $this->svea_order_id, $wc_order->get_id() ) );
					$order_item = new WC_Order_Item_Product();
					$product = wc_get_product( $product_id );

					if ( $product->is_type( 'product_variation' ) ) {
						$variation_id = $product_id;
						$order_item->set_variation_id( $variation_id );
						$product_id = $product->get_parent_id();
						$this->gateway::log( sprintf( 'New item is a variation. Variation ID: %s Parent: %s. Svea order: %s, WC order: %s', $variation_id, $product_id, $this->svea_order_id, $wc_order->get_id() ) );
					}

					$order_item->set_product_id( $product_id );
				} else {
					$this->gateway::log( sprintf( 'New item added as fee. Svea: %s. WC: %s', $this->svea_order_id, $wc_order->get_id() ) );
					$order_item = new \WC_Order_Item_Fee();
				}

				$quantity = $svea_cart_item['Quantity'] / 100;
				$total = ( $svea_cart_item['UnitPrice'] / 100 ) * $quantity;

				$order_item->set_props(
					[
						'quantity'  => $quantity,
						'name'      => $svea_cart_item['Name'],
						'total'     => $total / ( $svea_cart_item['VatPercent'] / 10000 + 1 ),
						'total_tax' => $total - ( $total / ( $svea_cart_item['VatPercent'] / 10000 + 1 ) ),
					]
				);

				$calc_totals = true;
				$wc_order->add_item( $order_item );
				$order_item->update_meta_data( '_svea_co_order_row_id', $svea_cart_item['OrderRowId'] );
				$order_item->save();

				$this->gateway::log( sprintf( 'Added item %s (ID %s) to order %s (WC: %s)', $order_item->get_name(), $product_id, $this->svea_order_id, $wc_order->get_id() ) );
			}
		}

		if ( $calc_totals ) {
			do_action( 'woocommerce_sco_before_push_calculate_totals', $wc_order );

			$wc_order->calculate_totals();

			do_action( 'woocommerce_sco_before_push_update_order_items', $wc_order );

			$wc_order->save();

			do_action( 'woocommerce_sco_after_push_update_order_items', $wc_order );
		}
	}

	/**
	 * Sync the shipping fee
	 *
	 * @param \WC_Order $wc_order
	 * @param float $shipping_fee
	 * @return void
	 */
	public function sync_shipping_fee( $wc_order, $shipping_fee ) {
		/** @var \WC_Order_Item_Product[] $line_items */
		$line_items = $wc_order->get_items();

		// Mimic the same structure as cart items so that the nShift calculations can be with same functions
		$items = [];
		$total = 0;

		if ( ! empty( $line_items ) ) {
			foreach ( $line_items as $key => $item ) {
				$data = $item->get_data();

				$items[] = [
					'line_total' => $data['total'],
					'line_tax'   => $data['total_tax'],
					'data'       => $item->get_product(),
				];

				$total += $wc_order->get_line_total( $item );
			}
		}

		$taxes = WC_Shipping_Svea_Nshift::get_taxes_amounts_and_percent( $items, $total );
		$data = WC_Shipping_Svea_Nshift::get_tax_fractions( $taxes, $shipping_fee );
		$actual_taxes = WC_Shipping_Svea_Nshift::get_real_taxes( $data );
		$shipping_fee -= array_sum( $actual_taxes );

		$shipping_items = $wc_order->get_items( 'shipping' );
		/** @var \WC_Order_Item_Shipping $shipping_item */
		$shipping_item = reset( $shipping_items );

		// Update the cost and taxes
		$shipping_item->set_total( $shipping_fee );
		$shipping_item->set_taxes( $actual_taxes );
		$shipping_item->delete_meta_data( 'nshift_taxes' );
		$shipping_item->add_meta_data( 'nshift_taxes', $actual_taxes );
		$shipping_item->save_meta_data();
		$shipping_item->save();

		// Send these changes back to Svea
		$payment_admin = new Svea_Payment_Admin( $wc_order );
		$shipping_id = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
		$svea_order_id = self::$svea_order['OrderId'];

		try {
			$pa_order = $payment_admin->get( $svea_order_id );
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Could not get order to update shipping fee. Message from Svea: %s', $e->getMessage() ) );
			return;
		}

		if ( ! in_array( 'CanUpdateOrderRow', $pa_order['Actions'], true ) ) {
			// Order can't be updated
			WC_Gateway_Svea_Checkout::log( 'Order can\'t be updated when updating shippingFee' );
			return;
		}

		$order_rows = $pa_order['OrderRows'];
		$update_order_rows = [];

		if ( ! empty( $order_rows ) ) {
			foreach ( $order_rows as $order_row ) {
				if ( $order_row['ArticleNumber'] === $shipping_id ) {
					foreach ( $data as $percent => $ammounts ) {
						if ( $order_row['VatPercent'] / 100 === $percent ) {
							$order_row['UnitPrice'] = $ammounts['cost'] * 100;
							$update_order_rows[] = $order_row;
						}
					}
				}
			}
		}

		if ( ! empty( $update_order_rows ) ) {
			foreach ( $update_order_rows as $update_row ) {
				try {
					$payment_admin->update_order_row( $svea_order_id, $update_row['OrderRowId'], $update_row );
				} catch ( \Exception $e ) {
					WC_Gateway_Svea_Checkout::log( sprintf( 'Could not update shipping fee. Message from Svea: %s', $e->getMessage() ) );
				}
			}
		}
	}

}
