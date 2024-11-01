<?php
namespace Svea_Checkout_For_Woocommerce\Models\Traits;

use Svea_Checkout_For_Woocommerce\Models\Svea_Item;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for getting items from WooCommerce order
 */
trait Items_From_Order {

	/**
	 * Get items from WooCommerce order
	 *
	 * @param \WC_Order $wc_order
	 * @return array
	 */
	public function get_items_from_order( $wc_order ) {
		$order_items = $wc_order->get_items( 'line_item' );
		$order_fees = $wc_order->get_items( 'fee' );
		$order_shipping = $wc_order->get_items( 'shipping' );

		$items = [];
		$mapping = [];

		foreach ( $order_items as $product ) {
			$svea_item = new Svea_Item();
			$svea_item->map_order_item_product( $product, null, true );

			$mapping[ $product->get_id() ] = $svea_item->temporary_reference;
			$items[] = $svea_item;
		}

		foreach ( $order_fees as $fee ) {
			$svea_item = new Svea_Item();
			$svea_item->map_order_item_fee( $fee, true );
			$mapping[ $fee->get_id() ] = $svea_item->temporary_reference;
			$items[] = $svea_item;
		}

		foreach ( $order_shipping as $shipping ) {
			$svea_item = new Svea_Item();
			$svea_item->map_order_item_shipping( $shipping, true );
			$mapping[ $shipping->get_id() ] = $svea_item->temporary_reference;
			$items[] = $svea_item;
		}

		// Save to later map order row IDs
		$wc_order->update_meta_data( '_svea_co_order_item_mapping', $mapping );

		/** @var Svea_Item[] $items */
		$items = apply_filters( 'woocommerce_sco_order_items', $items, $this );

		if ( count( $items ) > self::MAX_NUM_ROWS ) {
			throw new \Exception( 'The order may only contain a maximum of ' . self::MAX_NUM_ROWS . ' rows', 1 ); //phpcs:ignore
		}

		$items = apply_filters(
			'woocommerce_sco_cart_items_from_order',
			array_map(
				function( $item ) {
					/** @var Svea_Item $item  */
					return $item->get_svea_format();
				},
				$items
			)
		);

		return $items;
	}
}