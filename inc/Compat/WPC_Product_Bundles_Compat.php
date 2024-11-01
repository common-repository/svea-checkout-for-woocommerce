<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with WPC_Product_Bundles_Compat
 */
class WPC_Product_Bundles_Compat {

	/**
	 * Name of product type
	 */
	public const PRODUCT_TYPE = 'woosb';

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_sco_part_pay_module_product_types', [ $this, 'add_product_type_to_module' ] );
	}

	/**
	 * Add the product type "Smart bundle" to the widget
	 *
	 * @param string[] $product_types
	 * @return string[]
	 */
	public function add_product_type_to_module( $product_types ) {
		$product_types[] = self::PRODUCT_TYPE;

		return $product_types;
	}
}
