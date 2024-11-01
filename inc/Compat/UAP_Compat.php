<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;

use function Svea_Checkout_For_Woocommerce\svea_checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with Ultimate affiliate pro
 */
class UAP_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		// Register field in woocommerce checkout
		add_filter( 'woocommerce_checkout_fields', [ $this, 'add_field_to_checkout' ] );
		add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'populate_cookie_var' ], 5 );

		add_filter( 'woocommerce_sco_update_order_info_keys', [ $this, 'add_select_box_field' ] );

		add_action( 'woocommerce_checkout_process', [ $this, 'spoof_early' ], 5 );

	}

	/**
	 * Spoof data early
	 *
	 * @return void
	 */
	public function spoof_early() {
		if ( ! isset( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			svea_checkout()->webook_handler->setup_post_data( [] );
			svea_checkout()->webook_handler->spoof_global_post();
		}
	}

	/**
	 * Add select box field as a value to save
	 *
	 * @param string[] $keys
	 * @return string[]
	 */
	public function add_select_box_field( $keys ) {
		$keys[] = 'uap_affiliate_username';
		$keys[] = 'uap_affiliate_username_text';

		return $keys;
	}

	/**
	 * Populate the cookie variable in order for the field to be saved
	 *
	 * @return void
	 */
	public function populate_cookie_var() {
		if ( isset( $_POST['_sco_uap_referral'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$_COOKIE['uap_referral'] = $_POST['_sco_uap_referral']; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}

	/**
	 * Add field to checkout
	 *
	 * @param array $fields
	 * @return array
	 */
	public function add_field_to_checkout( $fields ) {
		$fields['order']['_sco_uap_referral'] = [
			'clear'   => true,
			'type'    => 'hidden',
			'default' => $_COOKIE['uap_referral'] ?? '',
		];

		return $fields;
	}


}
