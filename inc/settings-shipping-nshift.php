<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add fields to Svea settings page in WooCommerce
return apply_filters(
	'wc_svea_nshift_settings',
	[
		'title' => [
			'title'       => esc_html__( 'Method title', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => esc_html__( 'Let your customer choose shipping via nShift.', 'svea-checkout-for-woocommerce' ),
			'default'     => esc_html__( 'Svea nShift', 'svea-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		],
	]
);
