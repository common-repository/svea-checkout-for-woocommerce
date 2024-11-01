<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

use function Svea_Checkout_For_Woocommerce\svea_checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compatibility with WooCommerce EU VAT number plugin
 */
class EU_VAT_Compat {

	/**
	 * Field to add (if any)
	 *
	 * @var array
	 */
	private $field_to_add;

	/**
	 * Field name
	 *
	 * @var string
	 */
	private $field_name;

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_billing_fields', [ $this, 'check_for_billing_vat_field' ] );

		if ( \function_exists( 'wc_eu_vat_use_shipping_country' ) && wc_eu_vat_use_shipping_country() ) {
			add_filter( 'woocommerce_shipping_fields', [ $this, 'check_for_shipping_vat_field' ] );
		}

		add_filter( 'woocommerce_checkout_fields', [ $this, 'maybe_add_field' ] );

		add_filter( 'woocommerce_sco_update_order_info_keys', [ $this, 'add_vat_to_order_info_keys' ] );
		add_filter( 'woocommerce_sco_order_post_params', [ $this, 'maybe_use_other_shipping_address' ] );
	}

	/**
	 * Maybe use other shipping address
	 *
	 * @param array $data
	 * @return array
	 */
	public function maybe_use_other_shipping_address( $data ) {
		// If using shipping vat number "ship to different address" has to be set
		if ( isset( $data['shipping_vat_number'] ) ) {
			$data['ship_to_different_address'] = '1';
		}

		return $data;
	}

	/**
	 * Add VAT to order info keys
	 *
	 * @param array $keys
	 * @return array
	 */
	public function add_vat_to_order_info_keys( $keys ) {
		$keys[] = 'billing_vat_number';
		$keys[] = 'shipping_vat_number';

		return $keys;
	}

	/**
	 * Maybe add the field
	 *
	 * @param array $fields
	 * @return array
	 */
	public function maybe_add_field( $fields ) {
		if ( ! empty( $this->field_to_add ) ) {
			$fields['order'][ $this->field_name ] = $this->field_to_add;
		}

		return $fields;
	}

	/**
	 * Check for billing VAT field
	 *
	 * @param array $fields
	 * @return array
	 */
	public function check_for_billing_vat_field( $fields ) {
		if ( isset( $fields['billing_vat_number'] ) ) {
			$this->field_to_add = $fields['billing_vat_number'];
			$this->field_name = 'billing_vat_number';
			unset( $fields['billing_vat_number'] );
		}

		return $fields;
	}

	/**
	 * Check for shipping VAT field
	 *
	 * @param array $fields
	 * @return array
	 */
	public function check_for_shipping_vat_field( $fields ) {
		if ( isset( $fields['shipping_vat_number'] ) ) {
			$this->field_to_add = $fields['shipping_vat_number'];
			$this->field_name = 'shipping_vat_number';
			unset( $fields['shipping_vat_number'] );
		}

		return $fields;
	}
}
