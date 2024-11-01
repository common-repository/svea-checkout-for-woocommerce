<?php

namespace Svea_Checkout_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Scripts
 */
class Scripts {

	/**
	 * Init function
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Enqueues scripts for the admin
	 *
	 * @param string $hook
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		wp_enqueue_script( 'svea-checkout-for-woocommerce-admin', plugins_url( 'assets/js/backend/application.min.js', SVEA_CHECKOUT_FOR_WOOCOMMERCE_FILE ), [], Plugin::VERSION, true );

		$post_id = null;

		if ( $hook === 'woocommerce_page_wc-orders' && isset( $_GET['id'] ) && ! isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			global $post, $theorder;

			$post_id = $post->ID ?? $theorder->get_id();
		}

		wp_localize_script(
			'svea-checkout-for-woocommerce-admin',
			'wc_sco_params',
			[
				'security' => wp_create_nonce( 'wc-svea-checkout' ),
				'order_id' => $post_id,
				'i18n'     => [
					'status_has_changed'        => esc_html__( 'The status has changed in the background. Make sure the you don\'t overwrite the status when/if saving the order', 'svea-checkout-for-woocommerce' ),
					'status_is_unchanged'       => esc_html__( 'The order is still awaiting the final status', 'svea-checkout-for-woocommerce' ),
					'confirm_send_payment_link' => esc_html__( 'This will reload the page. Ensure all changes are saved before continuing', 'svea-checkout-for-woocommerce' ),
				],
			]
		);
	}

	/**
	 * Enqueues scripts and styles for the frontend
	 *
	 * @return  void
	 */
	public function enqueue_frontend_scripts() {
		// Enqueue part payment widget scrips on product-page if part payment widget is active
		if ( is_product() && WC_Gateway_Svea_Checkout::get_instance()->display_product_widget === 'yes' ) {
			wp_enqueue_style( 'wc-svea-checkout-part-payment-widget', plugins_url( 'assets/css/frontend/part-payment/part-payment-module.min.css', SVEA_CHECKOUT_FOR_WOOCOMMERCE_FILE ), [], Plugin::VERSION );
		}

		// Only enqueue frontend scripts on checkout page
		if (
			( ! function_exists( 'is_checkout' ) || ! is_checkout() ) &&
			( ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() )
		) {
			return;
		}

		wp_enqueue_style( 'svea-checkout-for-woocommerce', plugins_url( 'assets/css/frontend/application.min.css', SVEA_CHECKOUT_FOR_WOOCOMMERCE_FILE ), false, Plugin::VERSION );
		wp_enqueue_script( 'svea-checkout-for-woocommerce', plugins_url( 'assets/js/frontend/application.min.js', SVEA_CHECKOUT_FOR_WOOCOMMERCE_FILE ), [ 'jquery' ], Plugin::VERSION, false );

		$svea_checkout_gateway = WC_Gateway_Svea_Checkout::get_instance();

		wp_localize_script(
			'svea-checkout-for-woocommerce',
			'wc_sco_params',
			[
				'ajaxUrl'                             => admin_url( 'admin-ajax.php' ),
				'security'                            => wp_create_nonce( 'wc-svea-checkout' ),
				'refresh_sco_snippet_nonce'           => wp_create_nonce( 'update-order-review' ),
				'sco_checkout_order'                  => wp_create_nonce( 'sco-checkout-order' ),
				'update_sco_order_nshift_information' => wp_create_nonce( 'update-sco-order-nshift-information' ),
				'change_payment_method'               => wp_create_nonce( 'change-payment-method' ),
				'sco_heartbeat_nonce'                 => wp_create_nonce( 'sco_heartbeat' ),
				'sync_zip_code'                       => $svea_checkout_gateway->get_option( 'sync_zip_code' ) === 'yes',
				'default_customer_type'               => $svea_checkout_gateway->get_option( 'default_customer_type' ),
				'validation_failed'                   => esc_html__( 'Could not validate the order. Please try again', 'svea-checkout-for-woocommerces' ),
				'attribution_key'                     => $prefix = (string) apply_filters(
					'wc_order_attribution_tracking_field_prefix',
					'wc_order_attribution_'
				),
			]
		);
	}

}
