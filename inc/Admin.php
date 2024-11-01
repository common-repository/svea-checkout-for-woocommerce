<?php

namespace Svea_Checkout_For_Woocommerce;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Svea_Checkout_For_Woocommerce\Models\Svea_Item;
use Svea_Checkout_For_Woocommerce\Models\Svea_Payment_Admin;
use WC_Order_Item;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles actions related to the admin interface such as editing orders, refunding etc
 */
class Admin {

	/**
	 * Awaiting Svea order status
	 */
	const AWAITING_ORDER_STATUS = 'awaiting-svea';

	/**
	 * Awaiting Svea order status
	 */
	const AWAITING_ORDER_STATUS_FULL = 'wc-awaiting-svea';

	/**
	 * Init function
	 *
	 * @return void
	 */
	public function init() {
		$svea_checkout_gateway = WC_Gateway_Svea_Checkout::get_instance();

		// Instantiate admin functionality
		if ( $svea_checkout_gateway->get_option( 'sync_order_completion' ) === 'yes' ) {
			add_action( 'woocommerce_order_status_completed', [ $this, 'deliver_order' ] );
		}

		if ( $svea_checkout_gateway->get_option( 'sync_order_cancellation' ) === 'yes' ) {
			add_action( 'woocommerce_order_status_cancelled', [ $this, 'cancel_order' ] );
		}

		if ( is_admin() && $svea_checkout_gateway->get_option( 'sync_order_rows' ) === 'yes' ) {
			add_action( 'woocommerce_new_order_item', [ $this, 'admin_add_order_item' ], 10, 3 );
			add_action( 'woocommerce_saved_order_items', [ $this, 'start_admin_update_order' ], 100, 2 );

			// Use before to get information about the order before it's removed
			add_action( 'woocommerce_before_delete_order_item', [ $this, 'admin_remove_order_item' ] );
		}

		// Hide Svea Checkout order item meta
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_svea_checkout_order_item_meta' ] );

		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );

		add_action( 'woocommerce_order_item_get_taxes', [ $this, 'correct_taxes' ], 10, 2 );

		add_filter( 'woocommerce_register_shop_order_post_statuses', [ $this, 'register_order_status' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_order_status' ] );
		add_filter( 'woocommerce_email_classes', [ $this, 'add_email' ] );

		add_filter( 'woocommerce_email_actions', [ $this, 'allow_email_trigger' ] );

		// Allow emails to be sent when going from awaiting svea to processing
		add_action( 'woocommerce_order_status_' . self::AWAITING_ORDER_STATUS . '_to_processing_notification', [ $this, 'add_processing_email_trigger' ], 10, 2 );
		add_action( 'woocommerce_order_status_' . self::AWAITING_ORDER_STATUS . '_to_completed_notification', [ $this, 'add_completed_email_trigger' ], 10, 2 );

		add_action( 'sco_check_pa_order_status', [ $this, 'check_pa_order_status' ] );

		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', [ $this, 'allow_awaiting_svea_status_to_be_completed' ] );

		add_action( 'wp_ajax_sco-check-order-status', [ $this, 'ajax_check_order_status' ] );
		add_action( 'wp_ajax_sco-dismiss-server-violation-message', [ $this, 'dismiss_server_violation_message' ] );

		add_action( 'admin_notices', [ $this, 'maybe_show_admin_notice' ] );
	}

	/**
	 * Dismiss the server viloation admin notice
	 *
	 * @return void
	 */
	public function dismiss_server_violation_message() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		update_option( 'sco_request_violation', false );
		wp_send_json_success();
		die;
	}

	/**
	 * Maybe show an admin notice about to slow server
	 *
	 * @return void
	 */
	public function maybe_show_admin_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$violation = get_option( 'sco_request_violation', false );
		$viloation_days = apply_filters( 'svea_co_viloation_days', 2 );

		if ( is_array( $violation ) && $violation['time'] > time() - ( $viloation_days * DAY_IN_SECONDS ) ) {
			$hours = $viloation_days * 24;
			// translators: %s is the number of seconds the server has been responding within
			$message = sprintf( __( 'In the last 48 hours, the site has not been responding within the expected time frame for Svea. Svea Checkout expects a response within 8 seconds, but your server has been recorded responding in %s seconds. This delay could potentially cause issues with the checkout process. We recommend reaching out to your hosting provider or contacting a developer who can investigate why your server response time is longer than 8 seconds.', 'svea-checkout-for-woocommerce' ), $hours, $violation['diff'] );
			?>
				<div id="sco-server-violation-error-message" class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php
		}

		if ( isset( $_GET['sco_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $_GET['sco_status'] === 'instore_error' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$message = esc_html__( 'Something went wront when creating an Instore order. See order notes for more information', 'svea-checkout-for-woocommerce' );
				?>
					<div id="sco-instore-error-message" class="notice notice-error is-dismissible">
						<p><?php echo esc_html( $message ); ?></p>
					</div>
				<?php
			} else if ( $_GET['sco_status'] === 'instore_success' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$message = esc_html__( 'Instore order created successfully', 'svea-checkout-for-woocommerce' );
				?>
					<div id="sco-instore-success-message" class="notice notice-success is-dismissible">
						<p><?php echo esc_html( $message ); ?></p>
					</div>
				<?php
			}
		}
	}

	/**
	 * Check order status via ajax
	 *
	 * @return void
	 */
	public function ajax_check_order_status() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-svea-checkout', 'security', false ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'svea-checkout-for-woocommerce' ) );
			exit;
		} else if ( ! isset( $_GET['order_id'] ) ) {
			wp_send_json_error( __( 'Missing order ID', 'svea-checkout-for-woocommerce' ) );
			exit;
		} else if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( __( 'You do not have permission to do this', 'svea-checkout-for-woocommerce' ) );
			exit;
		}

		$wc_order_id = sanitize_text_field( $_GET['order_id'] );

		if ( empty( $wc_order_id ) ) {
			wp_send_json_error( __( 'Missing order ID', 'svea-checkout-for-woocommerce' ) );
			exit;
		}

		$status = $this->check_pa_order_status( wc_get_order( $wc_order_id ) );

		$message = esc_html__( 'An error occurred or the order has already been updated, please reload the page', 'svea-checkout-for-woocommerce' );

		if ( $status === 'PENDING' ) {
			$message = esc_html__( 'The order is still waiting for a final status', 'svea-checkout-for-woocommerce' );
		} else if ( strlen( $status ) > 1 ) {
			$message = esc_html__( 'The order has gotten the final status and have been updated in the background. Make sure you don\'t overwrite the status when/if saving.', 'svea-checkout-for-woocommerce' );
		}

		wp_send_json_success( $message );
		exit;
	}

	/**
	 * Allow processing email to be sent when going from awaiting svea to processing
	 *
	 * @return void
	 */
	public function add_processing_email_trigger( $order_id, $order = false ) {
		do_action( 'woocommerce_order_status_pending_to_processing_notification', $order_id, $order );
	}

	/**
	 * Allow completed email to be sent when going from awaiting svea to completed
	 *
	 * @return void
	 */
	public function add_completed_email_trigger( $order_id, $order = false ) {
		do_action( 'woocommerce_order_status_pending_to_completed_notification', $order_id, $order );
	}

	/**
	 * Allow the awaiting svea status to be completed
	 *
	 * @param string[] $statuses
	 * @return string[]
	 */
	public function allow_awaiting_svea_status_to_be_completed( $statuses ) {
		$statuses[] = self::AWAITING_ORDER_STATUS;
		return $statuses;
	}

	/**
	 * Check order in PaymentAdmin if the status has been updated
	 *
	 * @param int|\WC_Order $order
	 * @return string
	 */
	public function check_pa_order_status( $order ) {
		if ( is_numeric( $order ) ) {
			/** @var \WC_Order $wc_order */
			$wc_order = Webhook_Handler::get_order_by_svea_id( $order );
			$svea_order_id = $order;
		} else {
			/** @var \WC_Order $wc_order */
			$wc_order = $order;
			$svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );
		}

		if (
			empty( $wc_order ) ||
			$wc_order->get_status() !== self::AWAITING_ORDER_STATUS
		) {
			return '';
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		try {
			$pa_order = $payment_admin->get( $svea_order_id );
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when fetching information from Svea: %s', $e->getMessage() ) );
			return '';
		}

		if ( ! isset( $pa_order ) ) {
			WC_Gateway_Svea_Checkout::log( 'Tried fetching order from PaymentAdmin but failed. Aborting' );
			return '';
		}

		$status = strtoupper( $pa_order['SystemStatus'] );

		// Still pending, retry in an hour
		if ( $status === 'PENDING' ) {
			wp_schedule_single_event( time() + apply_filters( 'woocommerce_sco_pending_status_retry_time', HOUR_IN_SECONDS ), 'sco_check_pa_order_status', [ $svea_order_id ] );
			return $status;
		}

		// Check system status
		if ( $status === 'CLOSED' ) {
			$wc_order->update_status( 'cancelled', esc_html__( 'Payment not accepted in Svea', 'svea-checkout-for-woocommerce' ) );
		} else {
			$wc_order->payment_complete( $svea_order_id );
			wp_clear_scheduled_hook( 'sco_check_pa_order_status', [ $svea_order_id ] );
		}

		$wc_order->save();

		return $status;
	}

	/**
	 * Allow our action to be triggered
	 *
	 * @param string[] $actions
	 * @return string[]
	 */
	public function allow_email_trigger( $actions ) {
		// From pending to awaiting
		$actions[] = 'woocommerce_order_status_pending_to_' . self::AWAITING_ORDER_STATUS;

		// From awaiting to processing
		$actions[] = 'woocommerce_order_status_' . self::AWAITING_ORDER_STATUS . '_to_processing';

		// From awaiting to completed
		$actions[] = 'woocommerce_order_status_' . self::AWAITING_ORDER_STATUS . '_to_completed';

		return $actions;
	}

	/**
	 * Add order status to WooCommerce
	 *
	 * @param string[] $statuses
	 * @return string[]
	 */
	public function add_order_status( $statuses ) {
		$statuses[ self::AWAITING_ORDER_STATUS_FULL ] = esc_html__( 'Awaiting status', 'svea-checkout-for-woocommerce' );

		return $statuses;
	}

	/**
	 * Add email
	 *
	 * @param array $emails
	 * @return array
	 */
	public function add_email( $emails ) {
		$emails['WC_Email_Customer_Awaiting_Svea'] = include __DIR__ . '/Emails/Awaiting_Svea_Email.php';

		return $emails;
	}

	/**
	 * Register order status for orders awaiting Svea
	 *
	 * @param array $statuses
	 * @return array
	 */
	public function register_order_status( $statuses ) {
		$statuses[ self::AWAITING_ORDER_STATUS_FULL ] = [
			'label'                     => esc_html__( 'Awaiting status', 'svea-checkout-for-woocommerce' ),
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			/* translators: %s is the number of posts found */
			'label_count'               => _n_noop( 'Awaiting status <span class="count">(%s)</span>', 'Awaiting status <span class="count">(%s)</span>' ),
		];

		return $statuses;
	}

	/**
	 * Get the correct taxes for the shipping row
	 *
	 * @param array $val
	 * @param mixed $item
	 * @return array
	 */
	public function correct_taxes( $val, $item ) {
		$nshift_taxes = $item->get_meta( 'nshift_taxes' );

		return ! empty( $nshift_taxes ) ? [ 'total' => $nshift_taxes ] : $val;
	}

	/**
	 * Save some refs
	 *
	 * @param \Automattic\WooCommerce\Admin\Overrides\Order $wc_order
	 * @param array $svea_order
	 * @return void
	 */
	public static function save_refs( $wc_order, $svea_order ) {
		$ref = wc_clean( $svea_order['CustomerReference'] );

		if ( ! empty( $ref ) ) {
			$wc_order->update_meta_data( '_svea_co_customer_reference', $ref );
		}

		if ( $svea_order['PaymentType'] === 'Invoice' && ! empty( $svea_order['BillingReferences'] ) ) {
			// Get billing reference name
			$gateway = WC_Gateway_Svea_Checkout::get_instance();
			foreach ( $svea_order['BillingReferences'] as $reference ) {
				$svea_ref = $gateway->get_billing_reference_value( $reference['Type'] );

				if ( ! empty( $svea_ref ) ) {
					$wc_order->update_meta_data( '_svea_co_billing_reference_' . $svea_ref['key'], wc_clean( $reference['Value'] ) );
				}
			}
		}

		$ref_list = [
			'invoice_reference',
			'invoice_recipient',
			'contact_person',
		];
		foreach ( $ref_list as $key ) {
			$val = $wc_order->get_meta( '_svea_co_billing_reference_' . $key );
			if ( ! empty( $val ) ) {
				$ref = $val;
				break;
			}
		}

		$reg_nr = trim( $svea_order['NationalId'] );
		$wc_order->update_meta_data( '_svea_co_company_reg_number', $reg_nr );

		// Save reference as name
		$name = Helper::split_customer_name( $ref );

		$wc_order->set_billing_first_name( $name['first_name'] );
		$wc_order->set_billing_last_name( $name['last_name'] );

		$wc_order->set_shipping_first_name( $name['first_name'] );
		$wc_order->set_shipping_last_name( $name['last_name'] );

		$wc_order->set_billing_company( wc_clean( $svea_order['BillingAddress']['FullName'] ) );
		$wc_order->set_shipping_company( wc_clean( $svea_order['ShippingAddress']['FullName'] ) );

		// Order isn't saved but should be in other places
	}

	/**
	 * Adds a metabox with essetial information and for debugging
	 *
	 * @return void
	 */
	public function add_metabox() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		add_meta_box(
			'sveacheckout-metabox',
			esc_html__( 'Svea', 'sveacheckout-for-woocommerce' ),
			[ $this, 'render_metabox' ],
			$screen,
			'side'
		);
	}

	/**
	 * Renders the metabox
	 *
	 * @param \WP_post|\WC_Order $name
	 * @return void
	 */
	public function render_metabox( $post_or_order_object ) {
		/** @var \WC_Order */
		$wc_order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		$is_awaiting_status = $wc_order->get_status() === self::AWAITING_ORDER_STATUS;

		$fields = [
			esc_html__( 'Order ID', 'svea-checkout-for-woocommerce' ) => $wc_order->get_id(),
			esc_html__( 'Order Number', 'svea-checkout-for-woocommerce' ) => $wc_order->get_order_number(),
			esc_html__( 'Svea order ID', 'svea-checkout-for-woocommerce' ) => $wc_order->get_meta( '_svea_co_order_id' ),
			esc_html__( 'Payment type', 'svea-checkout-for-woocommerce' ) => $wc_order->get_meta( '_svea_co_payment_type' ),
			esc_html__( 'Currency', 'svea-checkout-for-woocommerce' ) => $wc_order->get_currency(),
			esc_html__( 'Order final', 'svea-checkout-for-woocommerce' ) => $wc_order->get_meta( '_svea_co_order_final' ) ?: __( 'No' ),
			esc_html__( 'Order final (formatted)', 'svea-checkout-for-woocommerce' ) => $wc_order->get_meta( '_svea_co_order_final' ) ? date_i18n( 'Y-m-d H:i:s', $wc_order->get_meta( '_svea_co_order_final' ) ) : '-',
		];

		$nshift_fields = null;
		if ( $wc_order->get_meta( '_sco_nshift_data' ) ) {
			$nshift_fields = $wc_order->get_meta( '_sco_nshift_data' );
		}

		include SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR . '/templates/backend/metabox.php';
	}

	/**
	 * Hide meta field from admin view
	 *
	 * @param string[] $hidden_order_itemmeta
	 * @return string[]
	 */
	public function hide_svea_checkout_order_item_meta( $hidden_order_itemmeta ) {
		$hidden_order_itemmeta[] = '_svea_co_order_row_id';

		return $hidden_order_itemmeta;
	}

	/**
	 * Get order row data
	 *
	 * @param \WC_Order_Item $order_item
	 * @return void
	 */
	public function get_order_row_data( $order_item ) {
		$order_row_data = [];

		if ( $order_item->is_type( 'line_item' ) ) {
			/** @var \WC_Order_Item_Product $order_item */
			$_product = $order_item->get_product();

			if ( $_product && $_product->exists() && $order_item->get_quantity() ) {
				$item = new Svea_Item();
				$item->map_order_item_product( $order_item, $_product );
				$order_row_data = $item->get_svea_format();
			}
		} else if ( $order_item->is_type( 'fee' ) ) {
			$item = new Svea_Item();
			$item->map_order_item_fee( $order_item );
			$order_row_data = $item->get_svea_format();

		} else if ( $order_item->is_type( 'shipping' ) ) {
			$item = new Svea_Item();
			$item->map_order_item_shipping( $order_item );
			$order_row_data = $item->get_svea_format();

		}

		return $order_row_data;
	}

	/**
	 * Sync order items removed in admin to Svea
	 *
	 * @param $order_item_id int
	 * @return void
	 */
	public function admin_remove_order_item( $order_item_id ) {
		$data_store = \WC_Data_Store::load( 'order-item' );

		$order_id = $data_store->get_order_id_by_order_item_id( $order_item_id );

		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order || ! is_a( $wc_order, 'WC_Order' ) || $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		$svea_order_id = intval( $wc_order->get_meta( '_svea_co_order_id' ) );

		if ( ! $svea_order_id ) {
			return;
		}

		// Only edit orders which are final in Svea
		if ( ! $wc_order->meta_exists( '_svea_co_order_final' ) ) {
			return;
		}

		WC_Gateway_Svea_Checkout::log( 'Syncing admin edited order item to Svea.' );

		$svea_order_item_id = $data_store->get_metadata( $order_item_id, '_svea_co_order_row_id' );

		if ( ! $svea_order_item_id ) {
			return;
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		$admin_response = wp_cache_get( $svea_order_id, 'svea_co_admin_svea_orders' );

		if ( $admin_response === false ) {
			try {
				WC_Gateway_Svea_Checkout::log( 'Trying to get with admin checkout client.' );
				$admin_response = $payment_admin->get( $svea_order_id );

				wp_cache_set( $svea_order_id, $admin_response, 'svea_co_admin_svea_orders' );

			} catch ( \Exception $e ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when fetching information from Svea admin: %s', $e->getMessage() ) );

				return;
			}
		}

		// Check if the actions variable is set, otherwise return here
		if ( ! isset( $admin_response['Actions'] ) ) {
			return;
		}

		if ( in_array( 'CanCancelOrderRow', $admin_response['Actions'], true ) ) {
			try {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Remove order row %s from order %s', $svea_order_item_id, $svea_order_id ) );
				$payment_admin->cancel_order_row( $svea_order_id, $svea_order_item_id );
				wp_cache_delete( $svea_order_id, 'svea_co_admin_svea_orders' );
			} catch ( \Exception $e ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when removing order row in Svea admin: %s', $e->getMessage() ) );
				return;
			}
		}
	}

	/**
	 * Gate to see if the function needs to be fired after tax calculations have been made
	 *
	 * @param int $order_id
	 * @param array $order_items
	 * @return void
	 */
	public function start_admin_update_order( $order_id, $order_items ) {
		// Update order items
		if ( did_action( 'wp_ajax_woocommerce_save_order_items' ) ) {
			$this->admin_update_order( false, wc_get_order( $order_id ) );

		} else {
			// Recount the order totals and taxes
			add_action( 'woocommerce_order_after_calculate_totals', [ $this, 'admin_update_order' ], 10, 2 );
		}
	}

	/**
	 * Add rounding if order amount does not match in Svea and WooCommerce
	 *
	 * @param bool $_and_taxes
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function admin_update_order( $_and_taxes, $wc_order ) {
		if ( ! $wc_order || ! is_a( $wc_order, 'WC_Order' ) || $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		$svea_order_id = intval( $wc_order->get_meta( '_svea_co_order_id' ) );

		if ( ! $svea_order_id ) {
			return;
		}

		// Only edit orders which are final in Svea
		if ( ! $wc_order->meta_exists( '_svea_co_order_final' ) ) {
			return;
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		// Always get a fresh order
		try {
			$admin_response = $payment_admin->get( $svea_order_id );
			wp_cache_set( $svea_order_id, $admin_response, 'svea_co_admin_svea_orders' );
		} catch ( \Exception $e ) {
			WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when fetching information from Svea admin: %s', $e->getMessage() ) );
			return;
		}

		if ( ! isset( $admin_response['Actions'] ) || ! in_array( 'CanUpdateOrderRow', $admin_response['Actions'], true ) ) {
			return;
		}

		$svea_order_items = $admin_response['OrderRows'];
		$all_order_items = $wc_order->get_items( [ 'line_item', 'fee', 'shipping' ] );

		if ( ! empty( $all_order_items ) ) {
			foreach ( $all_order_items as $order_item ) {
				// Get the data formatted for Svea
				$order_row_data = $this->get_order_row_data( $order_item );

				$svea_order_item_id = $order_item->get_meta( '_svea_co_order_row_id' );
				$svea_order_row_needs_syncing = false;

				foreach ( $svea_order_items as $svea_order_item ) {
					if ( $svea_order_item['OrderRowId'] === (int) $svea_order_item_id ) {
						if ( ! in_array( 'CanUpdateRow', $svea_order_item['Actions'], true ) ) {
							break;
						}

						// Sync if article number has changed
						if ( $svea_order_item['ArticleNumber'] !== $order_row_data['ArticleNumber'] ) {
							WC_Gateway_Svea_Checkout::log( 'Article Number has changed' );
							$svea_order_row_needs_syncing = true;
							break;
						}

						// Sync if name has changed
						if ( $svea_order_item['Name'] !== $order_row_data['Name'] ) {
							WC_Gateway_Svea_Checkout::log( 'Name has changed' );
							$svea_order_row_needs_syncing = true;
							break;
						}

						// Sync if quantity has changed
						if ( $svea_order_item['Quantity'] !== $order_row_data['Quantity'] ) {
							WC_Gateway_Svea_Checkout::log( 'Quantity has changed' );
							$svea_order_row_needs_syncing = true;
							break;
						}

						// Sync if unit price has changed
						if ( $svea_order_item['UnitPrice'] !== $order_row_data['UnitPrice'] ) {
							WC_Gateway_Svea_Checkout::log( 'Unit price has changed' );
							$svea_order_row_needs_syncing = true;
							break;
						}

						// Sync if quantity has changed
						if ( $svea_order_item['VatPercent'] !== $order_row_data['VatPercent'] ) {
							WC_Gateway_Svea_Checkout::log( 'Vat percent has changed' );
							$svea_order_row_needs_syncing = true;
							break;
						}
					}
				}

				if ( $svea_order_row_needs_syncing ) {
					try {
						WC_Gateway_Svea_Checkout::log( 'Trying to update order row with the admin checkout client.' );
						$payment_admin->update_order_row( $svea_order_id, $svea_order_item_id, $order_row_data );
						$total_order_item_count = count( $svea_order_items );

						for ( $i = 0;$i < $total_order_item_count;++$i ) {
							$svea_order_item = $svea_order_items[ $i ];

							if ( (int) $svea_order_item['OrderRowId'] === (int) $svea_order_item_id ) {
								// Update data in cache
								$svea_order_items[ $i ]['UnitPrice'] = $order_row_data['UnitPrice'];
								$svea_order_items[ $i ]['Quantity'] = $order_row_data['Quantity'];
								$svea_order_items[ $i ]['VatPercent'] = $order_row_data['VatPercent'];
								break;
							}
						}

						// Update admin response with new order items
						$admin_response['OrderRows'] = $svea_order_items;

						wp_cache_set( $svea_order_id, $admin_response, 'svea_co_admin_svea_orders' );
					} catch ( \Exception $e ) {
						WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when fetching information from Svea admin: %s', $e->getMessage() ) );
					}
				}
			}
		}

		// Cash rounding
		$rounding_order_row_id = (int) $wc_order->get_meta( '_svea_co_rounding_order_row_id' );
		$svea_total = 0;

		// Calculate diff for total
		foreach ( $svea_order_items as $svea_order_item ) {
			if ( $svea_order_item['OrderRowId'] === $rounding_order_row_id || $svea_order_item['IsCancelled'] ) {
				continue;
			}

			$svea_total += $svea_order_item['UnitPrice'] * ( $svea_order_item['Quantity'] / 100 );
		}

		$total_diff = (int) ( $wc_order->get_total() * 100 ) - $svea_total;

		// There is a order row for rounding but there is no diff no more. Remove it
		if ( $total_diff == 0 && $rounding_order_row_id ) { // phpcs:ignore
			// Remove current rounding order row
			if ( in_array( 'CanCancelOrderRow', $admin_response['Actions'], true ) ) {
				try {
					$payment_admin->cancel_order_row( $svea_order_id, $rounding_order_row_id );

					$total_order_item_count = count( $svea_order_items );

					for ( $i = 0; $i < $total_order_item_count; ++$i ) {
						$svea_order_item = $svea_order_items[ $i ];

						if ( $svea_order_item['OrderRowId'] === $rounding_order_row_id ) {
							// Update data in cache
							$svea_order_items[ $i ]['IsCancelled'] = true;
							break;
						}
					}

					// Update admin response with new order items
					$admin_response['OrderRows'] = $svea_order_items;

					wp_cache_set( $svea_order_id, $admin_response, 'svea_co_admin_svea_orders' );

					// Delete the meta data on the order
					$wc_order->delete_meta_data( '_svea_co_rounding_order_row_id' );
					$wc_order->save();
				} catch ( \Exception $e ) {
					WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when removing order row in Svea admin: %s', $e->getMessage() ) );
				}
			}
		} else if ( $total_diff != 0 ) { // phpcs:ignore
			// We've got a diff and a previous rounding item
			$rounding_cart_item = [
				'UnitPrice'  => $total_diff,
				'Name'       => esc_html__( 'Cash rounding', 'svea-checkout-for-woocommerce' ),
				'Quantity'   => 100,
				'VatPercent' => 0,
			];

			if ( $rounding_order_row_id ) {
				// Update the current rounding
				try {
					$admin_add_row_response = $payment_admin->update_order_row( $svea_order_id, $rounding_order_row_id, $rounding_cart_item );
				} catch ( \Exception $e ) {
					WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when updating order row in Svea admin: %s', $e->getMessage() ) );
				}
			} else {
				// Add a new order row with the rounding
				$rounding_cart_item['ArticleNumber'] = 'ROUNDING';

				try {
					$admin_add_row_response = $payment_admin->add_order_row( $svea_order_id, $rounding_cart_item );
				} catch ( \Exception $e ) {
					WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when adding order row in Svea admin: %s', $e->getMessage() ) );
				}
			}

			if ( isset( $admin_add_row_response['OrderRowId'][0] ) ) {
				$wc_order->update_meta_data( '_svea_co_rounding_order_row_id', absint( $admin_add_row_response['OrderRowId'][0] ) );
				$wc_order->save();

				wp_cache_delete( $svea_order_id, 'svea_co_admin_svea_orders' );
			}
		}
	}

	/**
	 * Sync order items added in admin to Svea
	 *
	 * @param int $order_item_id
	 * @param \WC_Order_Item $order_item
	 * @param int $order_id
	 * @return void
	 */
	public function admin_add_order_item( $order_item_id, $order_item, $order_id ) {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order || ! is_a( $wc_order, 'WC_Order' ) || $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		$svea_order_id = intval( $wc_order->get_meta( '_svea_co_order_id' ) );

		if ( ! $svea_order_id ) {
			return;
		}

		// Only edit orders which are final in Svea
		if ( ! $wc_order->meta_exists( '_svea_co_order_final' ) ) {
			return;
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		$admin_response = wp_cache_get( $svea_order_id, 'svea_co_admin_svea_orders' );

		if ( $admin_response === false ) {
			try {
				$admin_response = $payment_admin->get( $svea_order_id );

				wp_cache_set( $svea_order_id, $admin_response, 'svea_co_admin_svea_orders' );
			} catch ( \Exception $e ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when fetching information from Svea admin: %s', $e->getMessage() ) );

				return;
			}
		}

		if ( ! isset( $admin_response['Actions'] ) || ! in_array( 'CanAddOrderRow', $admin_response['Actions'], true ) ) {
			return;
		}

		// Get the data formatted for Svea
		$order_row_data = $this->get_order_row_data( $order_item );

		if ( ! empty( $order_row_data ) ) {
			try {
				$admin_add_row_response = $payment_admin->add_order_row( $svea_order_id, $order_row_data );

				wp_cache_delete( $svea_order_id, 'svea_co_admin_svea_orders' );
				if ( isset( $admin_add_row_response['OrderRowId'][0] ) ) {
					wc_update_order_item_meta( $order_item_id, '_svea_co_order_row_id', absint( $admin_add_row_response['OrderRowId'][0] ) );
				}
			} catch ( \Exception $e ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Received error when fetching information from Svea admin: %s', $e->getMessage() ) );
			}
		}
	}

	/**
	 * Process refund of an order
	 *
	 * @TODO We'll pause this function since it might be confusing together with the part-credit option
	 * @param int $order_id ID of the order being refunded
	 * @return void
	 */
	public function refund_order( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		if ( $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		WC_Gateway_Svea_Checkout::get_instance()->refund_order( $wc_order->get_id() );
	}

	/**
	 * Process delivery of an order
	 *
	 * @param int $order_id ID of the order being delivered
	 * @return void
	 */
	public function deliver_order( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		if ( $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		WC_Gateway_Svea_Checkout::get_instance()->deliver_order( $wc_order->get_id() );
	}

	/**
	 * Process cancellation of an order
	 *
	 * @param int $order_id ID of the order being cancelled
	 * @return void
	 */
	public function cancel_order( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		if ( $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		WC_Gateway_Svea_Checkout::get_instance()->cancel_order( $wc_order->get_id() );
	}

}
