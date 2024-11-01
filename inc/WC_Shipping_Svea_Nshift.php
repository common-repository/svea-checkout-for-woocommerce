<?php

namespace Svea_Checkout_For_Woocommerce;

use WC_Tax;

defined( 'ABSPATH' ) || exit;


/**
 * Shipping via nShift integration with Svea
 */
class WC_Shipping_Svea_Nshift extends \WC_Shipping_Method {

	/**
	 * Method ID
	 *
	 * @var string
	 */
	const METHOD_ID = 'svea_nshift';

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = esc_html__( 'Svea nShift', 'svea-checkout-for-woocommerce' );
		$this->method_description = esc_html__( 'Lets your customer choose a shipping method via nShift.', 'svea-checkout-for-woocommerce' );
		$this->supports           = [
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		];

		$this->init();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_cart_emptied', [ $this, 'clear_session' ] );
	}

	/**
	 * Init user set variables.
	 */
	public function init() {
		$this->instance_form_fields = include 'settings-shipping-nshift.php';
		$this->title                = $this->get_option( 'title' );
		$this->tax_status           = 'taxable';
	}

	/**
	 * Clear the session in order to recalculate the shipping cost for next order by the same user
	 *
	 * @return void
	 */
	public function clear_session() {
		WC()->session->__unset( 'sco_nshift_price' );
		WC()->session->__unset( 'sco_nshift_name' );
	}

	/**
	 * Get the cost
	 *
	 * @return float
	 */
	private function get_cost() {
		$cost = WC()->session->get( 'sco_nshift_price', false );

		if ( $cost === false ) {
			return 0;
		}

		return $cost;
	}

	/**
	 * Get the label of the rate
	 *
	 * @return string
	 */
	public function get_label() {
		$chosen_shipper = WC()->session->get( 'sco_nshift_name', '' );

		return ( ! empty( $chosen_shipper ) ) ? $chosen_shipper : $this->title;
	}

	/**
	 * Get taxes amount
	 *
	 * @param array $items
	 * @param float $total
	 * @return array
	 */
	public static function get_taxes_amounts_and_percent( $items, $total ) {
		$taxes = [];

		if ( ! empty( $items ) ) {
			foreach ( $items as $key => $item ) {
				if ( $item['line_total'] == 0 ) { // phpcs:ignore
					continue;
				}

				$tax_percent = round( ( $item['line_tax'] / $item['line_total'] ) * 100 );

				if ( ! isset( $taxes[ $tax_percent ] ) ) {
					/** @var \WC_Product $prod */
					$prod = $item['data'];

					$taxes[ $tax_percent ] = [
						'name'   => $prod->get_tax_class(),
						'amount' => 0,
					];
				}

				$taxes[ $tax_percent ]['amount'] += round( $item['line_total'] * 100 ) / 100;
			}
		}

		if ( ! empty( $taxes ) ) {
			foreach ( $taxes as $percent => $tax_data ) {
				$taxes[ $percent ] = [
					'name'                 => $tax_data['name'],
					'amount'               => $tax_data['amount'],
					'percent_of_total'     => round( ( $tax_data['amount'] / $total ) * 100 ),
					'percent_of_total_raw' => $tax_data['amount'] / $total,
				];
			}
		}

		return $taxes;
	}

	/**
	 * Get fractions of all the cost and taxes based on percentage of total
	 *
	 * @param array $taxes
	 * @param float $cost
	 * @return array
	 */
	public static function get_tax_fractions( $taxes, $cost ) {
		$data = [];

		if ( ! empty( $taxes ) && $cost > 0 ) {
			foreach ( $taxes as $percent => $tax ) {
				$fraction_total = $tax['percent_of_total'] / 100;
				$fraction_percent = ( $percent / 100 ) + 1;

				$cost_part = ( $cost * $fraction_total );
				$tax_amount = $cost_part - ( $cost_part / $fraction_percent );

				$data[ $percent ] = [
					'cost' => $cost_part,
					'tax'  => $tax_amount,
					'name' => $tax['name'],
				];
			}
		}

		return $data;
	}

	/**
	 * Get the real taxes with proper naming
	 *
	 * @param array $data
	 * @return array
	 */
	public static function get_real_taxes( $data ) {
		$actual_taxes = [];

		foreach ( $data as $percent => $tax ) {
			$actual_taxes[ key( WC_Tax::get_rates( $tax['name'] ) ) ] = round( $tax['tax'] * 100 ) / 100;
		}

		return $actual_taxes;
	}


	/**
	 * Calculate the shipping costs.
	 *
	 * @param array $package Package of items from cart.
	 * @return void
	 */
	public function calculate_shipping( $package = [] ) {
		if ( ! WC_Gateway_Svea_Checkout::is_nshift_enabled() ) {
			return;
		}

		$total = $package['contents_cost'];

		$taxes = self::get_taxes_amounts_and_percent( $package['contents'], $total );

		$cost = $this->get_cost();
		$data = self::get_tax_fractions( $taxes, $cost );

		$actual_taxes = self::get_real_taxes( $data );

		$cost -= array_sum( $actual_taxes );

		// The tax rate will diff from what's okay in Svea but will be corrected in the order model
		$rate = [
			'id'        => $this->get_rate_id(),
			'label'     => $this->get_label(),
			'cost'      => $cost,
			'taxes'     => $actual_taxes,
			'meta_data' => [
				'nshift_data'  => $data,
				'nshift_taxes' => $actual_taxes,
			],
			'package'   => $package,
		];

		$this->add_rate( $rate );
	}

	/**
	 * @inheritDoc
	 */
	public function is_available( $package ) {
		$available = parent::is_available( $package );

		if ( $available === false ) {
			return $available;
		}

		if ( ! WC_Gateway_Svea_Checkout::is_nshift_enabled() ) {
			return false;
		}

		$cart_has_subscription = false;

		if ( class_exists( '\WC_Subscriptions_Cart' ) ) {
			$cart_has_subscription = \WC_Subscriptions_Cart::cart_contains_subscription();
		}

		$available = svea_checkout()->template_handler->is_svea() && ! $cart_has_subscription;

		return apply_filters( 'woocommerce_sco_shipping_' . $this->id . '_is_available', $available );
	}

}
