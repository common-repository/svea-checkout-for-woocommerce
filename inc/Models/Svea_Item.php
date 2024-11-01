<?php
namespace Svea_Checkout_For_Woocommerce\Models;

use Svea_Checkout_For_Woocommerce\WC_Shipping_Svea_Nshift;

if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Exit if accessed directly
class Svea_Item {

	const SVEA_MAX_NAME_LENGTH = 40;

	/**
	 * SKU
	 *
	 * @var string
	 */
	public $sku;

	/**
	 * Item name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Quantity
	 *
	 * @var int
	 */
	private $quantity;

	/**
	 * Unit price (price each)
	 *
	 * @var int
	 */
	public $unit_price;

	/**
	 * Tax percentage, 25, 12, 6 etc
	 *
	 * @var int
	 */
	private $tax_percentage;

	/**
	 * Discount percent (0-100)
	 *
	 * @var integer
	 */
	private $discount_percent = 0;

	/**
	 * Diff from WooCommerce
	 *
	 * @var float
	 */
	private $sum_diff = 0;

	/**
	 * Temporary reference
	 *
	 * @var string
	 */
	public $temporary_reference = '';

	/**
	 * Does the item require shipping?
	 *
	 * @var bool
	 */
	private $needs_shipping = false;

	/**
	 * Weight in grams
	 *
	 * @var int
	 */
	private $weight = 0;

	/**
	 * Length in mm
	 *
	 * @var int
	 */
	private $length = 0;

	/**
	 * Height in mm
	 *
	 * @var int
	 */
	private $height = 0;

	/**
	 * Width in mm
	 *
	 * @var int
	 */
	private $width = 0;

	/**
	 * Row type
	 *
	 * @var string
	 */
	private $row_type = 'Row';

	/**
	 * Merchant data
	 *
	 * @var string
	 */
	private $merchant_data = '';

	/**
	 * Duplicate items of same item
	 *
	 * @var array
	 */
	private $self_container = [];

	/**
	 * Round the price at a small currency level
	 *
	 * @param float $price
	 * @return float
	 */
	public function round_price( $price ) {
		return round( $price * 100 ) / 100;
	}

	/**
	 * Get the diff from WooCommerce
	 *
	 * @return float
	 */
	public function get_diff() {
		return $this->sum_diff;
	}

	/**
	 * Map item as shipping
	 *
	 * @param \WC_Shipping_Rate $shipping
	 * @param string $key
	 * @return void
	 */
	public function map_shipping( $shipping, $key = '' ) {
		$shipping_meta = $shipping->get_meta_data();

		if ( $shipping->get_method_id() === WC_Shipping_Svea_Nshift::METHOD_ID && ! empty( $shipping_meta['nshift_data'] ) ) {
			$this->map_nshift_shipping( $shipping, $key );
			return;
		}

		$total_tax = array_sum( $shipping->get_taxes() );

		$unit_price = ( $shipping->get_cost() + $total_tax );

		// Prevent division by 0
		if ( $shipping->get_cost() > 0 ) {
			$tax_percentage = ( $total_tax / $shipping->get_cost() ) * 100;
		}

		$this->sku = $shipping->get_id();
		$this->name = $shipping->get_label();
		$this->quantity = 1;
		$this->unit_price = $unit_price;
		$this->tax_percentage = $tax_percentage ?? 0;
		$this->row_type = 'ShippingFee';

		if ( $key ) {
			$this->temporary_reference = $key;
		}
	}

	/**
	 * Map nShift shipping
	 *
	 * @param \WC_Shipping_Rate $shipping
	 * @param string $key
	 * @return void
	 */
	public function map_nshift_shipping( $shipping, $key = '' ) {
		$meta_data = $shipping->get_meta_data();

		if ( ! isset( $meta_data['nshift_data'] ) ) {
			return;
		}

		if ( ! empty( $meta_data['nshift_data'] ) ) {
			foreach ( $meta_data['nshift_data'] as $percent => $amounts ) {
				/** @var Svea_Item $shipping_row */
				$shipping_row = new $this();

				$shipping_row->sku = $shipping->get_id();
				$shipping_row->name = $shipping->get_label() . ' - ' . $percent . '%';
				$shipping_row->quantity = 1;
				$shipping_row->unit_price = $amounts['cost'];
				$shipping_row->tax_percentage = $percent;
				$shipping_row->row_type = 'ShippingFee';

				if ( $key ) {
					$shipping_row->temporary_reference = $key;
				}

				$this->self_container[] = $shipping_row;
			}
		}
	}

	/**
	 * Maybe return this as multiple rows
	 *
	 * @return array
	 */
	public function get_shipping_items() {
		return ! empty( $this->self_container ) ? $this->self_container : [ $this ];
	}

	/**
	 * Map item as a product
	 *
	 * @param array $cart_item
	 * @param string $key
	 * @return self
	 */
	public function map_product( $cart_item, $key = '' ) {
		/** @var \WC_Product $wc_product */
		$wc_product = $cart_item['data'];

		$wc_total = $this->round_price( $cart_item['line_total'] + $cart_item['line_tax'] );

		$unit_price = $wc_total / $cart_item['quantity'];

		// Prevent division by 0
		if ( $cart_item['line_subtotal'] > 0 ) {
			$tax_percentage = round( ( $cart_item['line_subtotal_tax'] / $cart_item['line_subtotal'] ) * 100 );
		}

		$this->sku = $wc_product->get_sku() ? $wc_product->get_sku() : $wc_product->get_id();
		$this->name = $wc_product->get_name();
		$this->quantity = $cart_item['quantity'];
		$this->unit_price = $unit_price;
		$this->tax_percentage = $tax_percentage ?? 0;

		$svea_total = $this->round_price( $unit_price ) * $this->quantity;
		$diff = $wc_total - $svea_total;

		// String convert since float numbers have bad accuracy
		if ( (string) $wc_total !== (string) $svea_total && $diff ) {
			$this->sum_diff = $diff;
		}

		if ( $key ) {
			$this->temporary_reference = $key;
		}

		/** @var \WC_Product_Simple $wc_product */
		$this->needs_shipping = $wc_product->needs_shipping();

		if ( $this->needs_shipping ) {
			$this->weight = (float) $wc_product->get_weight() * 1000; // kg => g
			$this->length = (float) $wc_product->get_length() * 10; // cm => mm
			$this->width = (float) $wc_product->get_width() * 10; // cm => mm
			$this->height = (float) $wc_product->get_height() * 10; // cm => mm
		}

		return $this;
	}

	/**
	 * Map item as a simple fee
	 *
	 * @param string $name
	 * @param float $amount
	 * @param int $tax_percentage
	 * @param string $key
	 * @return self
	 */
	public function map_simple_fee( $name, $amount, $tax_percentage = 0, $key = '' ) {
		$this->sku = sanitize_title( $name );
		$this->name = $name;
		$this->quantity = 1;
		$this->unit_price = $this->round_price( $amount );
		$this->tax_percentage = $tax_percentage;

		if ( $key ) {
			$this->temporary_reference = $key;
		}

		return $this;
	}
	/**
	 * Map item as a fee
	 *
	 * @param object $fee
	 * @param string $key
	 * @return self
	 */
	public function map_fee( $fee, $key = '' ) {
		$tax = $fee->taxable ? $this->round_price( $fee->tax / $fee->total ) * 100 : 0;
		$cost = $fee->total + $fee->tax;

		$this->sku = $key;
		$this->name = $fee->name;
		$this->quantity = 1;
		$this->unit_price = $this->round_price( $cost );
		$this->tax_percentage = $tax;

		if ( $key ) {
			$this->temporary_reference = $key;
		}

		return $this;
	}

	/**
	 * Map order item
	 *
	 * @param \WC_Order_Item_Product $item
	 * @param \WC_Product|null $wc_product
	 * @param bool $set_ref
	 * @return self
	 */
	public function map_order_item_product( $item, $wc_product = null, $set_ref = false ) {
		if ( ! $wc_product ) {
			$wc_product = $item->get_product();
		}

		$unit_price = ( $item->get_total() + $item->get_total_tax() ) / $item->get_quantity();

		$tax_percentage = 0;
		if ( $item->get_total_tax() ) {
			$tax_percentage = $this->round_price( $item->get_total_tax() / $item->get_total() ) * 100;
		}

		$this->sku = $wc_product->get_sku() ? $wc_product->get_sku() : $wc_product->get_id();
		$this->name = $wc_product->get_name();
		$this->quantity = $item->get_quantity();
		$this->unit_price = $unit_price;
		$this->tax_percentage = $tax_percentage ?? 0;

		if ( $set_ref ) {
			$this->temporary_reference = uniqid( $this->sku . '_' );
		}

		return $this;
	}

	/**
	 * Map order item fee
	 *
	 * @param \WC_Order_Item_Fee $item
	 * @param bool $set_ref
	 * @return self
	 */
	public function map_order_item_fee( $item, $set_ref = false ) {
		$unit_price = ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $item->get_quantity();
		$tax_percentage = ( (float) $item->get_total_tax() / (float) $item->get_total() ) * 100;

		$this->sku = sanitize_title( $item->get_name() );
		$this->name = $item->get_name();
		$this->quantity = $item->get_quantity();
		$this->unit_price = $unit_price;
		$this->tax_percentage = $tax_percentage ?? 0;

		if ( $set_ref ) {
			$this->temporary_reference = uniqid( $this->sku . '_' );
		}

		return $this;
	}

	/**
	 * Map order item shipping
	 *
	 * @param \WC_Order_Item_Shipping $item
	 * @param bool $set_ref
	 * @return self
	 */
	public function map_order_item_shipping( $item, $set_ref = false ) {
		$unit_price = ( $item->get_total() + $item->get_total_tax() ) / $item->get_quantity();

		$total_tax = floatval( $item->get_total_tax() );
		$total = floatval( $item->get_total() );

		$tax_percentage = 0;

		if ( ! empty( $total_tax ) ) {
			$tax_percentage = ( $total_tax / $total ) * 100;
		}

		$sku = $item->get_meta( 'method_id' ) . ':' . $item->get_meta( 'instance_id' );

		$this->sku = $sku;
		$this->name = $item->get_name();
		$this->quantity = $item->get_quantity();
		$this->unit_price = $unit_price;
		$this->tax_percentage = $tax_percentage ?? 0;

		if ( $set_ref ) {
			$this->temporary_reference = uniqid( $this->sku . '_' );
		}

		return $this;
	}

	/**
	 * Map a non taxable entry used as a rounding error margin
	 *
	 * @param float $diff
	 * @return void
	 */
	public function map_rounding( $diff ) {
		$this->sku = 'ROUNDING';
		$this->name = esc_html__( 'Cash rounding', 'svea-checkout-for-woocommerce' );
		$this->quantity = 1;
		$this->unit_price = $diff;
		$this->tax_percentage = 0;
	}

	/**
	 * Get the nShift shipping information
	 *
	 * @return array
	 */
	private function get_nshift_shipping_information() {
		return [
			'Weight'     => $this->weight ?: 0,
			'Dimensions' => [
				'Length' => $this->length ?: 0,
				'Height' => $this->height ?: 0,
				'Width'  => $this->width ?: 0,
			],
		];
	}

	/**
	 * Set the merchant data
	 *
	 * @param string $merchant_data
	 * @return self
	 */
	public function set_merchant_data( $merchant_data ) {
		$this->merchant_data = $merchant_data;

		return $this;
	}

	/**
	 * Get the array value of the item in the format Svea uses
	 *
	 * @param bool $with_shipping_information
	 * @return array
	 */
	public function get_svea_format( $with_shipping_information = false ) {
		$substr_function = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';

		$item = [
			'ArticleNumber'      => $this->sku,
			'Name'               => strlen( $this->name ) > self::SVEA_MAX_NAME_LENGTH ? $substr_function( $this->name, 0, self::SVEA_MAX_NAME_LENGTH - 3 ) . '...' : $this->name,
			'Quantity'           => intval( $this->quantity * 100 ),
			'UnitPrice'          => intval( round( $this->unit_price * 100 ) ),
			'DiscountPercent'    => intval( $this->discount_percent * 100 ),
			'VatPercent'         => intval( round( $this->tax_percentage ) * 100 ),
			'TemporaryReference' => $this->temporary_reference,
			'RowType'            => $this->row_type,
			'MerchantData'       => $this->merchant_data,
		];

		if ( $with_shipping_information ) {
			$item['ShippingInformation'] = $this->get_nshift_shipping_information();
		}

		return apply_filters( 'woocommerce_sco_cart_item', $item, $this );
	}
}
