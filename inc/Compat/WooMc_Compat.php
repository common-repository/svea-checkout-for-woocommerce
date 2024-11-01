<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with the WooCommerce Multi-currency plugin
 */
class WooMc_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_sco_process_checkout_before', [ $this, 'change_currency' ] );
	}

	/**
	 * Change the currency in WooCommerce to the one on the order
	 *
	 * @param Svea_Order $svea_order
	 *
	 * @return void
	 */
	public function change_currency( $svea_order ) {
		add_filter(
			'woocommerce_multicurrency_override_currency',
			function( $currency ) use ( $svea_order ) {
				return $svea_order['Currency'];
			}
		);
	}
}
