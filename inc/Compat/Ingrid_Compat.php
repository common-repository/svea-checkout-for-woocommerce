<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with Ingrid
 */
class Ingrid_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_sco_cart_hash', [ $this, 'update_order_on_ingrid_change' ] );
		add_filter( 'save_ingrid_address_data_to_wc_customer', '__return_false' );

		add_filter( 'ingrid_ajax_events_available_for_update', [ $this, 'allow_hook' ] );
	}

	/**
	 * Allow hook to update Iingrid
	 *
	 * @param string[] $hooks
	 * @return string[]
	 */
	public function allow_hook( $hooks ) {
		$hooks[] = 'refresh_sco_snippet';
		return $hooks;
	}

	/**
	 * Update the order based on ingrid session data
	 *
	 * @return string
	 */
	public function update_order_on_ingrid_change( $hash ) {
		/** @var \WC_Session_Handler $session */
		$session = WC()->session;
		$ingrid_data = $session->get( 'ingrid_shipping' );
		$suffix = '';

		if ( ! empty( $ingrid_data ) ) {
			$suffix = '_' . $ingrid_data['category_name'];
		}

		return $hash . sanitize_title( $suffix );
	}


}
