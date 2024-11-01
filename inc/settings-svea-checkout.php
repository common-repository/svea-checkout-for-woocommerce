<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add fields to Svea settings page in WooCommerce
return apply_filters(
	'wc_svea_checkout_settings',
	[
		'enabled'                         => [
			'title'       => esc_html__( 'Enable/Disable', 'svea-checkout-for-woocommerce' ),
			'label'       => esc_html__( 'Enable Svea Checkout', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'yes',
		],
		'title'                           => [
			'title'   => esc_html__( 'Title', 'svea-checkout-for-woocommerce' ),
			'type'    => 'text',
			'default' => esc_html__( 'Svea Checkout', 'svea-checkout-for-woocommerce' ),
		],
		'customer_types'                  => [
			'title'       => esc_html__( 'Customer Types', 'svea-checkout-for-woocommerce' ),
			'type'        => 'select',
			'options'     => [
				'both'       => esc_html__( 'Companies and individuals', 'svea-checkout-for-woocommerce' ),
				'company'    => esc_html__( 'Companies', 'svea-checkout-for-woocommerce' ),
				'individual' => esc_html__( 'Individuals', 'svea-checkout-for-woocommerce' ),
			],
			'default'     => 'both',
			'description' => __( 'Select which customer types you want to accept in your store.', 'svea-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'default_customer_type'           => [
			'title'       => esc_html__( 'Default Customer Type', 'svea-checkout-for-woocommerce' ),
			'type'        => 'select',
			'options'     => [
				'individual' => esc_html__( 'Individual', 'svea-checkout-for-woocommerce' ),
				'company'    => esc_html__( 'Company', 'svea-checkout-for-woocommerce' ),
			],
			'default'     => 'individual',
			'description' => __( 'Select which customer type you want to be selected by default. Only applicable if the store accepts companies and individuals.', 'svea-checkout-for-woocommerce' ),
		],
		'preset_value_email_read_only'    => [
			'title'       => esc_html__( 'E-mail read-only when logged in', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'default'     => 'yes',
			'description' => __( 'Choose whether or not the e-mail address should be read only for logged in users.', 'svea-checkout-for-woocommerce' ),
		],
		'preset_value_phone_read_only'    => [
			'title'       => esc_html__( 'Phone read-only when logged in', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'default'     => 'no',
			'description' => __( 'Choose whether or not the phonenumber should be read only for logged in users.', 'svea-checkout-for-woocommerce' ),
		],
		'preset_value_zip_code_read_only' => [
			'title'       => esc_html__( 'Zip code read-only when logged in', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'default'     => 'no',
			'description' => __( 'Choose whether or not the zip code should be read only for logged in users.', 'svea-checkout-for-woocommerce' ),
		],
		'merchant_sweden_hr'              => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'merchant_sweden'                 => [
			'title' => esc_html__( 'Sweden', 'svea-checkout-for-woocommerce' ) . ' (SEK)',
			'type'  => 'title',
		],
		'merchant_id_se'                  => [
			'title'       => esc_html__( 'Merchant ID - Sweden', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Merchant ID for Sweden. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_se'                       => [
			'title'       => esc_html__( 'Secret - Sweden', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Secret for Sweden. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_merchant_id_se'          => [
			'title'       => esc_html__( 'Instore Merchant ID - Sweden', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Instore Merchant ID for Sweden. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_password_se'             => [
			'title'       => esc_html__( 'Instore password - Sweden', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore password for Sweden. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_pa_secret_se'            => [
			'title'       => esc_html__( 'Instore PaymentAdmin secret - Sweden', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore PaymentAdmin secret for Sweden. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'testmode_se'                     => [
			'title'       => esc_html__( 'Testmode - Sweden', 'svea-checkout-for-woocommerce' ),
			'label'       => esc_html__( 'Enable testmode in Sweden', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'merchant_norway_hr'              => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'merchant_norway'                 => [
			'title' => esc_html__( 'Norway', 'svea-checkout-for-woocommerce' ) . ' (NOK)',
			'type'  => 'title',
		],
		'merchant_id_no'                  => [
			'title'       => esc_html__( 'Merchant ID - Norway', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Merchant ID for Norway. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_no'                       => [
			'title'       => esc_html__( 'Secret - Norway', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Secret for Norway. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_merchant_id_no'          => [
			'title'       => esc_html__( 'Instore Merchant ID - Norway', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Instore Merchant ID for Norway. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_password_no'             => [
			'title'       => esc_html__( 'Instore password - Norway', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore password for Norway. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_pa_secret_no'            => [
			'title'       => esc_html__( 'Instore PaymentAdmin secret - Norway', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore PaymentAdmin secret for Norway. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'testmode_no'                     => [
			'title'       => esc_html__( 'Testmode - Norway', 'svea-checkout-for-woocommerce' ),
			'label'       => esc_html__( 'Enable testmode in Norway', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'merchant_finland_hr'             => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'merchant_finland'                => [
			'title' => esc_html__( 'Finland', 'svea-checkout-for-woocommerce' ) . ' (EUR + FI)',
			'type'  => 'title',
		],
		'merchant_id_fi'                  => [
			'title'       => esc_html__( 'Merchant ID - Finland', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Merchant ID for Finland. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_fi'                       => [
			'title'       => esc_html__( 'Secret - Finland', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Secret for Finland. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_merchant_id_fi'          => [
			'title'       => esc_html__( 'Instore Merchant ID - Finland', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Instore Merchant ID for Finland. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_password_fi'             => [
			'title'       => esc_html__( 'Instore password - Finland', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore password for Finland. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_pa_secret_fi'            => [
			'title'       => esc_html__( 'Instore PaymentAdmin secret - Finland', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore PaymentAdmin secret for Finland. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'testmode_fi'                     => [
			'title'       => esc_html__( 'Testmode - Finland', 'svea-checkout-for-woocommerce' ),
			'label'       => esc_html__( 'Enable testmode in Finland', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'merchant_denmark_hr'             => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'merchant_denmark'                => [
			'title' => esc_html__( 'Denmark', 'svea-checkout-for-woocommerce' ) . ' (DKK)',
			'type'  => 'title',
		],
		'merchant_id_dk'                  => [
			'title'       => esc_html__( 'Merchant ID - Denmark', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Merchant ID for Denmark. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_dk'                       => [
			'title'       => esc_html__( 'Secret - Denmark', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Secret for Denmark. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_merchant_id_dk'          => [
			'title'       => esc_html__( 'Instore Merchant ID - Denmark', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Instore Merchant ID for Denmark. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_password_dk'             => [
			'title'       => esc_html__( 'Instore password - Denmark', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore password for Denmark. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_pa_secret_dk'            => [
			'title'       => esc_html__( 'Instore PaymentAdmin secret - Denmark', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore PaymentAdmin secret for Denmark. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'testmode_dk'                     => [
			'title'       => esc_html__( 'Testmode - Denmark', 'svea-checkout-for-woocommerce' ),
			'label'       => esc_html__( 'Enable testmode in Denmark', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'merchant_global_hr'              => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'merchant_global'                 => [
			'title' => esc_html__( 'Global merchant', 'svea-checkout-for-woocommerce' ) . ' (EUR)',
			'type'  => 'title',
		],
		'merchant_id_global'              => [
			'title'       => esc_html__( 'Merchant ID - global', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Merchant ID for the global. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_global'                   => [
			'title'       => esc_html__( 'Secret - global', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Secret for the global checkout. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_merchant_id_global'      => [
			'title'       => esc_html__( 'Instore Merchant ID - global', 'svea-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your Svea Instore Merchant ID for the global market. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_password_global'         => [
			'title'       => esc_html__( 'Instore password - global', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore password for the global market. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'instore_pa_secret_global'        => [
			'title'       => esc_html__( 'Instore PaymentAdmin secret - Global', 'svea-checkout-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Please enter your Svea Instore PaymentAdmin secret for the global market. Leave blank to disable.', 'svea-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'testmode_global'                 => [
			'title'       => esc_html__( 'Testmode - global', 'svea-checkout-for-woocommerce' ),
			'label'       => esc_html__( 'Enable testmode in the global checkout', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'instore_settings_hr'             => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'instore_settings'                => [
			'title'       => esc_html__( 'Instore settings', 'svea-checkout-for-woocommerce' ),
			'type'        => 'title',
			'description' => esc_html__( 'Please note that you need to enable Instore via Svea for this functionality to work.', 'svea-checkout-for-woocommerce' ),
		],
		'instore_link_expire_minutes'     => [
			'title'       => esc_html__( 'Instore link expire minutes', 'svea-checkout-for-woocommerce' ),
			'type'        => 'number',
			'description' => __( 'Enter the number of minutes the Instore link will be valid for', 'svea-checkout-for-woocommerce' ),
			'default'     => 30,
		],
		'nshift_hr'                       => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'nshift_settings'                 => [
			'title'       => esc_html__( 'nShift settings', 'svea-checkout-for-woocommerce' ),
			'type'        => 'title',
			'description' => esc_html__( 'Enable the nShift integration with the settings below. Please note that you need an agreement with both Svea and nShift for this functionality to work. When activated a shipping option named "Svea nShift" can be added.', 'svea-checkout-for-woocommerce' ),
		],
		'enable_nshift'                   => [
			'title'   => esc_html__( 'Enable nShift', 'svea-checkout-for-woocommerce' ),
			'label'   => esc_html__( 'Enable the nShift integration for the checkout (this requires an agreement with both Svea and nShift)', 'svea-checkout-for-woocommerce' ),
			'type'    => 'checkbox',
			'default' => 'no',
		],
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found 
		// 'nshift_use_fallback'             => [
		//  'title'   => esc_html__( 'Enable nShift fallback options', 'svea-checkout-for-woocommerce' ),
		//  'label'   => esc_html__( 'Enable fallback shipping options via nShift. This will be used if the connection to nShift fails', 'svea-checkout-for-woocommerce' ),
		//  'type'    => 'checkbox',
		//  'default' => 'no',
		// ],
		// 'nshift_always_use_fallback'      => [
		//  'title'   => esc_html__( 'Always show fallback option', 'svea-checkout-for-woocommerce' ),
		//  'label'   => esc_html__( 'Enable this setting to always show the fallback options regardless of the connection to nShift fails or not', 'svea-checkout-for-woocommerce' ),
		//  'type'    => 'checkbox',
		//  'default' => 'no',
		// ],
		// 'nshift_fallback_options'         => [
		//  'title'  => esc_html__( 'Fallback options', 'svea-checkout-for-woocommerce' ),
		//  'label'  => esc_html__( 'Add the diferent shipping options', 'svea-checkout-for-woocommerce' ),
		//  'type'   => 'nshift_shipping_options',
		//  'fields' => [
		//      'carrier[%index%]' => [
		//          'title' => esc_html__( 'Carrier', 'svea-checkout-for-woocommerce' ),
		//          'type'  => 'text',
		//      ],
		//      'name[%index%]'    => [
		//          'title' => esc_html__( 'Name', 'svea-checkout-for-woocommerce' ),
		//          'type'  => 'text',
		//      ],
		//      'price[%index%]'   => [
		//          'title' => esc_html__( 'Price', 'svea-checkout-for-woocommerce' ),
		//          'type'  => 'text',
		//      ],
		//  ],
		// ],
		'other_hr'                        => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'product_widget_title'            => [
			'title' => esc_html__( 'Part payment module', 'svea-checkout-for-woocommerce' ),
			'type'  => 'title',
		],
		'display_product_widget'          => [
			'title'       => esc_html__( 'Display product part payment module', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Display a module on the product page which suggests a part payment plan for the customer to use to buy the product.', 'svea-checkout-for-woocommerce' ),
			'default'     => 'no',
		],
		'product_widget_position'         => [
			'title'       => esc_html__( 'Product part payment module position', 'svea-checkout-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'The position of the part payment module on the product page. Is only displayed if the module is activated.', 'svea-checkout-for-woocommerce' ),
			'default'     => 15,
			'options'     => [
				'15' => esc_html__( 'Between price and excerpt', 'svea-checkout-for-woocommerce' ),
				'25' => esc_html__( 'Between excerpt and add to cart', 'svea-checkout-for-woocommerce' ),
				'35' => esc_html__( 'Between add to cart and product meta', 'svea-checkout-for-woocommerce' ),
			],
		],
		'checkout_settings_hr'            => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'checkout_settings_title'         => [
			'title' => esc_html__( 'Checkout settings', 'svea-checkout-for-woocommerce' ),
			'type'  => 'title',
		],
		'hide_not_you'                    => [
			'title'       => esc_html__( 'Hide "Not you?"', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Hide the "Not you?" button in the Svea iframe.', 'svea-checkout-for-woocommerce' ),
			'default'     => 'no',
		],
		'hide_change_address'             => [
			'title'       => esc_html__( 'Hide "Change address"', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Hide the "Change address" button in from the Svea iframe.', 'svea-checkout-for-woocommerce' ),
			'default'     => 'no',
		],
		'hide_anonymous'                  => [
			'title'       => esc_html__( 'Hide the anonymous flow', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Hide the anonymous flow, forcing users to identify with their national id to perform a purchase.', 'svea-checkout-for-woocommerce' ),
			'default'     => 'no',
		],
		'sync_zip_code'                   => [
			'title'       => esc_html__( 'Sync ZIP code from Svea', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Enable ZIP code sync from the Svea Checkout iframe to WooCommerce, this enables usage of ZIP code specific shipping methods. <br />
						<strong>Do not touch this if you do not know what you are doing</strong>.',
				'svea-checkout-for-woocommerce'
			),
			'default'     => 'yes',
		],
		'zero_sum_orders'                 => [
			'title'       => esc_html__( 'Zero sum orders', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Allow orders with a cost of 0 to go through Svea Checkout.<br />
				<strong>This needs to be enabled on your Svea account. Get in contact with Svea if you\'d like to enable it.</strong>',
				'svea-checkout-for-woocommerce'
			),
			'default'     => 'no',
		],
		'sync_settings_hr'                => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'sync_settings_title'             => [
			'title' => esc_html__( 'Sync settings', 'svea-checkout-for-woocommerce' ),
			'type'  => 'title',
		],
		'sync_order_completion'           => [
			'title'       => esc_html__( 'Sync order completion', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Enable automatic sync of completed orders from WooCommerce to Svea. <br />
						<strong>Do not touch this if you do not know what you are doing</strong>.',
				'svea-checkout-for-woocommerce'
			),
			'default'     => 'yes',
		],
		'sync_order_cancellation'         => [
			'title'       => esc_html__( 'Sync order cancellation', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Enable automatic sync of cancelled orders from WooCommerce to Svea. <br />
						<strong>Do not touch this if you do not know what you are doing</strong>.',
				'svea-checkout-for-woocommerce'
			),
			'default'     => 'yes',
		],
		'sync_order_rows'                 => [
			'title'       => esc_html__( 'Sync order rows', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Enable automatic sync of order rows changed after purchase from WooCommerce to Svea. <br />
						This functionality only works on payment methods where payment is not made at the time of the purchase. <br />
						<strong>Do not touch this if you do not know what you are doing</strong>.',
				'svea-checkout-for-woocommerce'
			),
			'default'     => 'yes',
		],
		'use_ip_restriction'              => [
			'title'       => esc_html__( 'Use IP restriction', 'svea-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __(
				'Verify the IP to one of Sveas server.<br />
						This functionality only works if you do not use a reverse proxy on your server. <br />
						<strong>Do not touch this if you do not know what you are doing</strong>.',
				'svea-checkout-for-woocommerce'
			),
			'default'     => 'yes',
		],
		'other_settings_hr'               => [
			'title' => '<hr>',
			'type'  => 'title',
		],
		'other_settings_title'            => [
			'title' => esc_html__( 'Other settings', 'svea-checkout-for-woocommerce' ),
			'type'  => 'title',
		],
		'log'                             => [
			'title'   => esc_html__( 'Logging', 'svea-checkout-for-woocommerce' ),
			'label'   => esc_html__( 'Enable logs for Svea Checkout', 'svea-checkout-for-woocommerce' ),
			'type'    => 'checkbox',
			'default' => 'no',
		],
	]
);
