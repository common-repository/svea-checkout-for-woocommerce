<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with Polylang
 */
class Polylang_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_sco_create_order', [ $this, 'change_push_uri' ] );
		add_action( 'init', [ $this, 'maybe_set_language' ] );
	}

	/**
	 * Maybe set the language
	 *
	 * @return void
	 */
	public function maybe_set_language() {
		if (
			! \function_exists( 'PLL' ) ||
			! isset( $_GET['sco_lang'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! isset( $_GET['svea_order_id'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			return;
		}

		$lang = sanitize_text_field( $_GET['sco_lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$language = PLL()->model->get_language( $lang );

		if ( $language ) {
			PLL()->curlang = $language;
		}
	}

	/**
	 * Change data callback URI to the correct language
	 *
	 * @param array $data
	 * @return array
	 */
	public function change_push_uri( $data ) {
		if ( ! empty( $data['MerchantSettings'] ) && \function_exists( 'pll_current_language' ) ) {
			$language_slug = \pll_current_language();

			$args = [
				'sco_lang' => $language_slug,
			];

			$data['MerchantSettings']['PushUri'] = $data['MerchantSettings']['PushUri'] . '&' . http_build_query( $args );
			$data['MerchantSettings']['CheckoutValidationCallBackUri'] = $data['MerchantSettings']['CheckoutValidationCallBackUri'] . '&' . http_build_query( $args );
			$data['MerchantSettings']['WebhookUri'] = $data['MerchantSettings']['WebhookUri'] . '&' . http_build_query( $args );
		}

		return $data;
	}
}
