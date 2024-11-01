<?php
namespace Svea_Checkout_For_Woocommerce\Models\Traits;

use Svea_Checkout_For_Woocommerce\WC_Gateway_Svea_Checkout;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Logger {

	/**
	 * Log order data
	 *
	 * @param string $msg Message to start the log line
	 * @param array $order_data
	 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
	 * @return void
	 */
	public function log_order( $msg, $order_data ) {
		$order_data = var_export( $order_data, true );
		WC_Gateway_Svea_Checkout::log( sprintf( $msg . ': %s', $order_data ) );
	}
}
