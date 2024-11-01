<?php

namespace Svea_Checkout_For_Woocommerce;

use Svea_Checkout_For_Woocommerce\Compat\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * @wordpress-plugin
 * Plugin Name: Svea Checkout for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/svea-checkout-for-woocommerce/
 * Description: Process payments in WooCommerce via Svea Checkout.
 * Version: 3.0.7
 * Requires Plugins: woocommerce
 * Author: The Generation AB
 * Author URI: https://thegeneration.se
 * Text Domain: svea-checkout-for-woocommerce
 * Domain Path: languages
 * WC requires at least: 8.0.0
 * WC tested up to: 9.1.2
 */

/**
 * Define an absolute constant to be used in the plugin files
 */
if ( ! defined( 'SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR' ) ) {
	define( 'SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR', __DIR__ );
}

if ( ! defined( 'SVEA_CHECKOUT_FOR_WOOCOMMERCE_FILE' ) ) {
	define( 'SVEA_CHECKOUT_FOR_WOOCOMMERCE_FILE', __FILE__ );
}

if ( ! class_exists( 'Svea_Checkout_For_Woocommerce\\Plugin' ) ) {

	class Plugin {

		/**
		 * Name of plugin
		 */
		const PLUGIN_NAME = 'svea-checkout-for-woocommerce';

		/**
		 * Version of plugin
		 */
		const VERSION = '3.0.7';

		/**
		 * @var string
		 */
		private $plugin_description;

		/**
		 * @var string
		 */
		private $plugin_label;

		/**
		 * Admin class
		 *
		 * @var Admin
		 */
		public $admin;

		/**
		 * Instore class
		 *
		 * @var Instore
		 */
		public $instore;

		/**
		 * Session_Table class
		 *
		 * @var Session_Table
		 */
		public $session_table;

		/**
		 * Translation class
		 * @var I18n
		 */
		public $i18n;

		/**
		 * Scripts class
		 * @var Scripts
		 */
		public $scripts;

		/**
		 * Template handler class
		 * @var Template_Handler
		 */
		public $template_handler;

		/**
		 * Webhook handler class
		 * @var Webhook_Handler
		 */
		public $webook_handler;

		/**
		 * Compatability class
		 * @var Compat
		 */
		public $compat;

		/**
		 * Svea_Checkout_For_Woocommerce constructor.
		 */
		public function __construct() {
			$this->load_dependencies();
			$this->init_modules();

			$this->plugin_description = esc_html__( 'Svea Checkout for WooCommerce', 'svea-checkout-for-woocommerce' );
			$this->plugin_label = esc_html__( 'Process payments in WooCommerce via Svea Checkout', 'svea-checkout-for-woocommerce' );

			if ( ! self::is_woocommerce_installed() ) {
				if ( isset( $_GET['action'] ) && ! in_array( $_GET['action'], [ 'activate-plugin', 'upgrade-plugin', 'activate', 'do-plugin-upgrade' ], true ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
					return;
				}

				self::add_admin_notice(
					'error',
					__( 'WooCommerce Svea Checkout Gateway has been deactivated because WooCommerce is not installed. Please install WooCommerce and re-activate.', 'svea-checkout-for-woocommerce' )
				);

				add_action( 'admin_init', [ $this, 'deactivate_plugin' ] );
				return;
			}

			$this->add_hooks();
		}

		/**
		 * Add admin notices to be displayed
		 *
		 * @param string $type The type of message
		 * @param string $message The message to be displayed
		 * @return boolean whether or not the notices were saved
		 */
		public static function add_admin_notice( $type, $message ) {
			$notices = get_option( 'svea_checkout_admin_notices', [] );

			$notice = [
				'type'    => $type,
				'message' => $message,
			];

			if ( in_array( $notice, $notices, true ) ) {
				return false;
			}

			$notices[] = $notice;

			return update_option( 'svea_checkout_admin_notices', $notices );
		}

		/**
		 * Display admin notices
		 *
		 * @return void
		 */
		public function display_admin_notices() {
			$notices = get_option( 'svea_checkout_admin_notices' );

			if ( ! empty( $notices ) ) {
				foreach ( $notices as $notice ) {
					echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . wp_kses_post( $notice['message'] ) . '</p></div>';
				}

				delete_option( 'svea_checkout_admin_notices' );
			}
		}

		/**
		 * Check if WooCommerce is installed and activated
		 *
		 * @return boolean whether or not WooCommerce is installed
		 */
		public static function is_woocommerce_installed() {
			/**
			 * Get a list of active plugins
			 */
			$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

			/**
			 * Loop through the active plugins
			 */
			foreach ( $active_plugins as $plugin ) {
				/**
				 * If the plugin name matches WooCommerce
				 * it means that WooCommerce is active
				 */
				if ( preg_match( '/.+\/woocommerce\.php/', $plugin ) ) {
					return true;
				}
			}

			// Get a list of network activated plugins
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$active_plugins = get_site_option( 'active_sitewide_plugins' );

				// Get keys from active plugins array
				if ( is_array( $active_plugins ) ) {
					$active_plugins = array_keys( $active_plugins );
				} else {
					$active_plugins = [];
				}

				foreach ( $active_plugins as $plugin ) {

					/**
					 * If the plugin name matches WooCommerce
					 * it means that WooCommerce is active
					 */
					if ( preg_match( '/.+\/woocommerce\.php/', $plugin ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Add function hooks
		 *
		 * @return void
		 */
		public function add_hooks() {
			add_action( 'plugins_loaded', [ $this, 'init_gateways' ] );
			add_filter( 'woocommerce_shipping_methods', [ $this, 'init_shipping_methods' ] );
			add_action( 'plugins_loaded', [ $this, 'init_admin' ], 15 );
			add_action( 'admin_init', [ $this, 'check_compatibility' ] );
			add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
		}

		/**
		 * Initialize the shipping method
		 *
		 * @param array $methods
		 * @return array
		 */
		public function init_shipping_methods( $methods ) {
			$methods['svea_nshift'] = __NAMESPACE__ . '\\WC_Shipping_Svea_Nshift';

			return $methods;
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
		}

		/**
		 * Init the admin option
		 */
		public function init_admin() {
			$this->admin = new Admin();
			$this->admin->init();

			$this->instore = new Instore();
			$this->instore->init();
		}

		/**
		 * Add the gateway WC_Gateway_Svea_Checkout to Woocommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
			$methods[] = __NAMESPACE__ . '\\WC_Gateway_Svea_Checkout';

			return $methods;
		}

		/**
		 * Check if the shop meets the requirements
		 *
		 * @return void
		 */
		public function check_compatibility() {
			$wc_price_num_decimals = get_option( 'woocommerce_price_num_decimals' );

			if ( $wc_price_num_decimals !== false && $wc_price_num_decimals < 2 ) {
				self::add_admin_notice(
					'error',
					sprintf(
						/* translators: %d = number of decimals, %s = example code */
						esc_html__(
							'WooCommerce decimals is set to %1$d, lower than 2 which is the required setting for Svea Checkout to work properly. If you want to hide decimals altogether, add this snippet to your functions.php: %2$s If you have just changed the setting, you can ignore this message.',
							'svea-checkout-for-woocommerce'
						),
						$wc_price_num_decimals,
						'<br /><br /><code>/**<br />&nbsp;&nbsp;* Trim zeros in price decimals<br />&nbsp;&nbsp;*/<br />add_filter( \'woocommerce_price_trim_zeros\', \'__return_true\' );</code><br /><br />'
					)
				);
			}

			// Check if company name is mandatory and Svea is set to sell to B2C
			$wc_checkout_company_field = get_option( 'woocommerce_checkout_company_field', 'optional' );
			$svea_gateway = WC_Gateway_Svea_Checkout::get_instance();
			$svea_customer_types = $svea_gateway->get_customer_types();

			// If the field is required and we're not only selling B2B
			if ( $wc_checkout_company_field === 'required' && $svea_customer_types !== 'company' ) {
				self::add_admin_notice(
					'error',
					esc_html__(
						'The store is currently set to require company name, but Svea Checkout is set to sell to individuals as well. This will cause an error in the checkout. To fix this, please remove the requirement for company name in the WooCommerce settings under Appearance > Customize > WooCommerce > Checkout > Company name field.',
						'svea-checkout-for-woocommerce'
					)
				);
			}

			// If the field is required and we're selling to B2B
			if ( $wc_checkout_company_field === 'hidden' && $svea_customer_types !== 'individual' ) {
				self::add_admin_notice(
					'error',
					esc_html__(
						'The store is currently set not to use "Company name", but Svea Checkout is set to sell to companies. This will prevent this field from being saved on orders made. To fix this, please set the field "Company name" to "Optional" in the WooCommerce settings under Appearance > Customize > WooCommerce > Checkout > Company name field.',
						'svea-checkout-for-woocommerce'
					)
				);
			}

			// Check if address line 2 is mandatory since Svea doesn't support it
			$wc_checkout_address_2_field = get_option( 'woocommerce_checkout_address_2_field', 'optional' );
			if ( $wc_checkout_address_2_field === 'required' ) {
				self::add_admin_notice(
					'error',
					esc_html__(
						'The store is currently set to require "Address line 2", which is a field that isn\'t present in all cases in Svea Checkout. This will cause an error in the checkout. To fix this, please remove the requirement for address line 2 in the WooCommerce settings under Appearance > Customize > WooCommerce > Checkout > Address line 2 field.',
						'svea-checkout-for-woocommerce'
					)
				);
			} else if ( $wc_checkout_address_2_field === 'hidden' ) {
				self::add_admin_notice(
					'error',
					esc_html__(
						'The store is currently set not to use "Address line 2". This will prevent this field from being saved on orders made. To fix this, please set the field "Address line 2" to "Optional" in the WooCommerce settings under Appearance > Customize > WooCommerce > Checkout > Address line 2 field.',
						'svea-checkout-for-woocommerce'
					)
				);
			}

			// Make sure phone field isn't hidden
			$wc_checkout_phone_field = get_option( 'woocommerce_checkout_phone_field', 'required' );

			if ( $wc_checkout_phone_field === 'hidden' ) {
				self::add_admin_notice(
					'error',
					esc_html__(
						'The store is currently set not to use "Phone". This will prevent phone numbers to be saved on orders made. To fix this, please set the field "Phone" to "Required" in the WooCommerce settings under Appearance > Customize > WooCommerce > Checkout > Phone field.',
						'svea-checkout-for-woocommerce'
					)
				);
			}
		}

		/**
		 * Require all the classes we need
		 *
		 * @return void
		 */
		public function load_dependencies() {
			// Composer packages
			require_once SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR . '/vendor/autoload.php';
		}

		/**
		 * Deactivate this plugin
		 *
		 * @return void
		 */
		public function deactivate_plugin() {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				return;
			}

			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		/**
		 * Initialize plugin modules
		 *
		 * @return void
		 */
		public function init_modules() {
			$this->i18n = new I18n();
			$this->i18n->init();

			$this->scripts = new Scripts();
			$this->scripts->init();

			$this->template_handler = new Template_Handler();
			$this->template_handler->init();

			$this->webook_handler = new Webhook_Handler();
			$this->webook_handler->init();

			$this->compat = new Compat();
			$this->compat->init();
		}
	}

	if ( ! function_exists( 'svea_checkout' ) ) {
		/**
		 * Svea checkout instance
		 *
		 * @return Plugin
		 */
		function svea_checkout() {
			static $instance;

			if ( $instance === null ) {
				$instance = new Plugin();
			}

			return $instance;
		}
	}


	// Enable HPOS
	add_action(
		'before_woocommerce_init',
		function() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	svea_checkout();
}
