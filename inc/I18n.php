<?php

namespace Svea_Checkout_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class I18n
 */
class I18n {

	/**
	 * Init function
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'load_language_files' ], 1 );
	}

	/**
	 * Loads the language-files to be used throughout the plugin
	 *
	 * @return void
	 */
	public function load_language_files() {
		load_plugin_textdomain( 'svea-checkout-for-woocommerce', false, plugin_basename( SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR ) . '/languages' );
	}

}
