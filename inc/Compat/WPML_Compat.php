<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

use Svea_Checkout_For_Woocommerce\Webhook_Handler;
use WCML\MultiCurrency\Geolocation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with WPML
 */
class WPML_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_sco_create_order', [ $this, 'change_push_uri' ] );

		if ( isset( $_GET['svea_order_id'], $_GET['sco_currency'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_filter(
				'wcml_client_currency',
				function() {
					return $_GET['sco_currency']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			);
		}

		if ( isset( $_GET['svea_order_id'], $_GET['sco_geolocation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_filter(
				'wcml_geolocation_get_user_country',
				function() {
					return $_GET['sco_geolocation']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			);
		}
	}

	/**
	 * Change data callback URI to the correct language
	 *
	 * @param array $data
	 * @return array
	 */
	public function change_push_uri( $data ) {
		if ( ! empty( $data['MerchantSettings'] ) ) {
			$current_home_url = home_url( '/' );

			global $sitepress;
			$default_language_code = apply_filters( 'wpml_default_language', '' );
			$default_home_url = trailingslashit( $sitepress->language_url( $default_language_code ) );

			$args = [
				'sco_currency' => $data['Currency'],
			];

			if ( class_exists( '\WCML\MultiCurrency\Geolocation' ) && Geolocation::isUsed() ) {
				$args['sco_geolocation'] = Geolocation::getUserCountry();
			}

			$push_url = str_replace( $current_home_url, $default_home_url, $data['MerchantSettings']['PushUri'] ) . '&' . http_build_query( $args );

			$validation_callback = str_replace( $current_home_url, $default_home_url, $data['MerchantSettings']['CheckoutValidationCallBackUri'] ) . '&' . http_build_query( $args );

			$webhook_url = str_replace( $current_home_url, $default_home_url, $data['MerchantSettings']['WebhookUri'] ) . '&' . http_build_query( $args );

			$data['MerchantSettings']['PushUri'] = $push_url;
			$data['MerchantSettings']['CheckoutValidationCallBackUri'] = $validation_callback;
			$data['MerchantSettings']['WebhookUri'] = $webhook_url;
		}

		return $data;
	}

	/**
	 * Save data in the session for later use
	 *
	 * @return void
	 */
	public function save_data_in_session() {
		WC()->session->set( 'sco_lang', ICL_LANGUAGE_CODE );

		if ( class_exists( '\WCML\MultiCurrency\Geolocation' ) && Geolocation::isUsed() ) {
			WC()->session->set( 'sco_wpml_country_geolocation', Geolocation::getUserCountry() );
		} else {
			WC()->session->__unset( 'sco_wpml_country_geolocation' );
		}
	}
}
