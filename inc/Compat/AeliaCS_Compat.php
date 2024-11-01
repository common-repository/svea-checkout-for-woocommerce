<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with the Aelia currency switcher
 */
class AeliaCS_Compat {

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
	 * @return void
	 */
	public function change_currency( $svea_order ) {
		add_filter(
			'wc_aelia_cs_selected_currency',
			function( $selected_currency ) use ( $svea_order ) {
				$selected_currency = $svea_order['Currency'];
				return $selected_currency;
			},
			0
		);
	}
}
