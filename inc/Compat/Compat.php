<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

class Compat {

	/**
	 * Yith WooCommerce Gift Cards compatibility class
	 *
	 * @var Yith_Gift_Cards_Compat
	 */
	public $gift_cards;

	/**
	 * Polylang compatibility class
	 *
	 * @var Polylang_Compat
	 */
	public $polylang;

	/**
	 * WPML compatibility class
	 *
	 * @var WPML_Compat
	 */
	public $wpml;

	/**
	 * WPC Product Bundles compatibility class
	 *
	 * @var WPC_Product_Bundles_Compat
	 */
	public $wpc_product_bundles;

	/**
	 * Ingrid compat file
	 *
	 * @var Ingrid_Compat
	 */
	public $ingrid;

	/**
	 * WC Smart Coupons compatibility class
	 *
	 * @var WC_Smart_Coupons_Compat
	 */
	public $wc_smart_coupons;

	/**
	 * EU VAT compatibility class
	 *
	 * @var EU_VAT_Compat
	 */
	public $eu_vat;

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'check_for_plugins' ], 1 );
		add_action( 'plugins_loaded', [ $this, 'check_for_plugins_early' ], 1 );
	}

	/**
	 * Check for plugins that might need compatibility
	 *
	 * @return void
	 */
	public function check_for_plugins() {
		if ( function_exists( 'YITH_YWGC' ) ) {
			$this->gift_cards = new Yith_Gift_Cards_Compat();
			$this->gift_cards->init();
		}

		if ( defined( 'POLYLANG_ROOT_FILE' ) ) {
			$this->polylang = new Polylang_Compat();
			$this->polylang->init();
		}

		if ( defined( 'WOOSB_VERSION' ) ) {
			$this->wpc_product_bundles = new WPC_Product_Bundles_Compat();
			$this->wpc_product_bundles->init();
		}

		if ( defined( 'WC_SC_PLUGIN_FILE' ) ) {
			$this->wc_smart_coupons = new WC_Smart_Coupons_Compat();
			$this->wc_smart_coupons->init();
		}

		if ( defined( 'WC_EU_VAT_VERSION' ) ) {
			$this->eu_vat = new EU_VAT_Compat();
			$this->eu_vat->init();
		}
	}

	/**
	 * Check for plugins that might need compatibility on an early hook
	 *
	 * @return void
	 */
	public function check_for_plugins_early() {
		if ( defined( 'INGRID_PLUGIN_VERSION' ) ) {
			$this->ingrid = new Ingrid_Compat();
			$this->ingrid->init();
		}
	}
}
