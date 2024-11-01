<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

use Svea_Checkout_For_Woocommerce\Models\Svea_Item;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Compability with the WooCommerce Smart Coupons plugin
 */
class WC_Smart_Coupons_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_sco_cart_items', [ $this, 'maybe_add_coupons' ] );
		add_action( 'woocommerce_sco_before_push_calculate_totals', [ $this, 'calculate_discount' ] );
	}

	/**
	 * Calculate the discount on the order
	 *
	 * @param WC_Order $wc_order
	 * @return void
	 */
	public function calculate_discount( $wc_order ) {
		// Trick Smart coupons to calculate the discount
		$_POST['action'] = 'woocommerce_save_order_items';
		$wc_smart_coupons = \WC_Smart_Coupons::get_instance();
		$wc_smart_coupons->order_calculate_discount_amount( true, $wc_order );
	}

	/**
	 * Add coupons as a negative cost
	 *
	 * @param Svea_Item[] $items
	 * @return Svea_Item[]
	 */
	public function maybe_add_coupons( $items ) {
		foreach ( WC()->cart->get_coupons() as $wc_coupon ) {
			/** @var \WC_Coupon $wc_coupon */
			if ( $wc_coupon->get_discount_type() === 'smart_coupon' ) {
				$svea_item = new Svea_Item();

				$gift_card_name = esc_html__( 'Gift card', 'svea-checkout-for-woocommerce' ) . ' (' . $wc_coupon->get_code() . ')';

				$amount = WC()->cart->get_coupon_discount_amount( $wc_coupon->get_code() ) +
					WC()->cart->get_coupon_discount_tax_amount( $wc_coupon->get_code() );

				$svea_item->map_simple_fee( $gift_card_name, -$amount );

				$items[] = $svea_item;
			}
		}

		return $items;
	}

}
