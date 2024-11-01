<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with the WooCommerce currency switcher plugin
 */
class Woocs_Compat {

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
		global $WOOCS; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

		if ( method_exists( $WOOCS, 'set_currency' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$WOOCS->set_currency( $svea_order['Currency'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		}
	}
}
