<?php
namespace Svea_Checkout_For_Woocommerce\Compat;

use Svea_Checkout_For_Woocommerce\Models\Svea_Item;
use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;
use YITH_YWGC_Backend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly

/**
 * Compability with the YITH gift cards plugin
 */
class Yith_Gift_Cards_Compat {

	/**
	 * Init function, add hooks
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_sco_cart_items', [ $this, 'maybe_add_coupons' ] );
		add_action( 'woocommerce_sco_after_push_order', [ $this, 'restore_fee_compatibility' ] );
		add_action( 'woocommerce_sco_checkout_send_checkout_result', [ $this, 'block_fee_compatibility' ] );
	}

	/**
	 * Restore the possibility for YITH to use gift cards as fees
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function restore_fee_compatibility( $wc_order ) {
		$wc_order->delete_meta_data( 'ywgc_gift_card_updated_as_fee' );
		$wc_order->save();
	}

	/**
	 * Remove the meta information that might not be true now and add some other
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function block_fee_compatibility( $wc_order ) {

		// When the validation has been made we assume that this meta should be removed
		$wc_order->delete_meta_data( '_ywgc_is_gift_card_amount_refunded' );

		// Prevent this from happening before the order is finalized
		$wc_order->update_meta_data( 'ywgc_gift_card_updated_as_fee', 'true' );
	}

	/**
	 * Get the applied gift cards from the cart
	 *
	 * @return array
	 */
	public function get_gift_cards() {
		return isset( WC()->cart->applied_gift_cards_amounts ) ? WC()->cart->applied_gift_cards_amounts : [];
	}

	/**
	 * Add coupons as a negative cost
	 *
	 * @param Svea_Item[] $items
	 * @return Svea_Item[]
	 */
	public function maybe_add_coupons( $items ) {
		$gift_cards = $this->get_gift_cards();

		if ( ! empty( $gift_cards ) ) {
			foreach ( $gift_cards as $name => $amount ) {
				$svea_item = new Svea_Item();

				$gift_card_name = esc_html__( 'Gift card', 'svea-checkout-for-woocommerce' ) . ' (' . $name . ')';
				$svea_item->map_simple_fee( $gift_card_name, -$amount )
					->set_merchant_data( 'yith_gift_card' );

				$items[] = $svea_item;
			}
		}

		return $items;
	}

}
