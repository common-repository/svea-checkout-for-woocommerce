<?php

namespace Svea_Checkout_For_Woocommerce;

use Svea\Checkout\CheckoutAdminClient;
use Svea\Checkout\Transport\Connector;
use Svea_Checkout_For_Woocommerce\Models\Svea_Checkout;
use Svea_Checkout_For_Woocommerce\Models\Svea_Payment_Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WC_Gateway_Svea_Checkout
 */
class WC_Gateway_Svea_Checkout extends \WC_Payment_Gateway {

	/**
	 * Format of the transition for part payment campaigns
	 *
	 * @var string
	 */
	const PART_PAYMENT_TRANSIENT_FORMAT = 'sco_part_pay_campaigns_%s';

	/**
	 * Gateway ID
	 *
	 * @var string
	 */
	const GATEWAY_ID = 'svea_checkout';

	/**
	 * List of Svea payment methods
	 *
	 * @var string[]
	 */
	private $svea_payment_methods;

	/**
	 * Billing references for Svea
	 *
	 * @var array
	 */
	private $svea_billing_references;

	/**
	 * List of activated customer types
	 *
	 * @var array
	 */
	private $customer_types;

	/**
	 * Secret for Sweden
	 *
	 * @var string
	 */
	private $secret_se;

	/**
	 * Instore password for Instore merchant in Sweden
	 *
	 * @var string
	 */
	private $instore_password_se;

	/**
	 * Instore PA secret for Instore merchant in Sweden
	 *
	 * @var string
	 */
	private $instore_pa_secret_se;

	/**
	 * Instore merchant for Sweden
	 *
	 * @var string
	 */
	private $instore_merchant_id_se;

	/**
	 * Merchant for Sweden
	 *
	 * @var string
	 */
	private $merchant_id_se;

	/**
	 * Whether or not testmode for Sweden is activated
	 *
	 * @var bool
	 */
	private $testmode_se;

	/**
	 * Secret for Norway
	 *
	 * @var string
	 */
	private $secret_no;

	/**
	 * Instore password for Instore merchant in Norway
	 *
	 * @var string
	 */
	private $instore_password_no;

	/**
	 * Instore PA secret for Instore merchant in Norway
	 *
	 * @var string
	 */
	private $instore_pa_secret_no;

	/**
	 * Instore merchant for Norway
	 *
	 * @var string
	 */
	private $instore_merchant_id_no;

	/**
	 * Merchant for Norway
	 *
	 * @var string
	 */
	private $merchant_id_no;

	/**
	 * Whether or not testmode for Norway is activated
	 *
	 * @var bool
	 */
	private $testmode_no;

	/**
	 * Secret for Finland
	 *
	 * @var string
	 */
	private $secret_fi;

	/**
	 * Instore password for Instore merchant in Finland
	 *
	 * @var string
	 */
	private $instore_password_fi;

	/**
	 * Instore PA secret for Instore merchant in Finland
	 *
	 * @var string
	 */
	private $instore_pa_secret_fi;

	/**
	 * Instore merchant for Finland
	 *
	 * @var string
	 */
	private $instore_merchant_id_fi;

	/**
	 * Merchant for Finland
	 *
	 * @var string
	 */
	private $merchant_id_fi;

	/**
	 * Whether or not testmode for Finland is activated
	 *
	 * @var bool
	 */
	private $testmode_fi;

	/**
	 * Secret for Denmark
	 *
	 * @var string
	 */
	private $secret_dk;

	/**
	 * Instore password for Instore merchant in Denmark
	 *
	 * @var string
	 */
	private $instore_password_dk;

	/**
	 * Instore PA secret for Instore merchant in Denmark
	 *
	 * @var string
	 */
	private $instore_pa_secret_dk;

	/**
	 * Instore merchant for Denmark
	 *
	 * @var string
	 */
	private $instore_merchant_id_dk;

	/**
	 * Merchant for Denmark
	 *
	 * @var string
	 */
	private $merchant_id_dk;

	/**
	 * Whether or not testmode for Denmark is activated
	 *
	 * @var bool
	 */
	private $testmode_dk;

	/**
	 * Secret for global market
	 *
	 * @var string
	 */
	private $secret_global;

	/**
	 * Instore password for Instore merchant in Global market
	 *
	 * @var string
	 */
	private $instore_password_global;

	/**
	 * Instore PA secret for Instore merchant in Sweden
	 *
	 * @var string
	 */
	private $instore_pa_secret_global;

	/**
	 * Instore merchant for Global market
	 *
	 * @var string
	 */
	private $instore_merchant_id_global;

	/**
	 * Merchant for global market
	 *
	 * @var string
	 */
	private $merchant_id_global;

	/**
	 * Wheter or not testmode for the global market is activated
	 *
	 * @var bool
	 */
	private $testmode_global;

	/**
	 * How long the instore link is valid
	 *
	 * @var int
	 */
	private $instore_link_expire_minutes;

	/**
	 * @var bool Whether or not to display the product widget
	 */
	public $display_product_widget;

	/**
	 * Is logging enabled?
	 *
	 * @var boolean
	 */
	private static $log_enabled = false;

	/**
	 * Logger class
	 *
	 * @var \WC_Logger
	 */
	private static $log = null;

	/**
	 * Static instance of this class
	 *
	 * @var WC_Gateway_Svea_Checkout
	 */
	private static $instance = null;

	/**
	 * Are hooks enabled
	 *
	 * @var bool
	 */
	private static $hooks_enabled = false;

	/**
	 * Is the nShift integration enabled?
	 *
	 * @var bool
	 */
	private static $nshift_enabled = false;

	/**
	 * Does the checkout allow order with 0 cost?
	 *
	 * @var bool
	 */
	private static $zero_sum_enabled = false;

	/**
	 * Get single instance
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new WC_Gateway_Svea_Checkout();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( is_null( self::$instance ) ) {
			self::$instance = $this;
		}

		$this->id = self::GATEWAY_ID;
		$this->method_title = esc_html__( 'Svea Checkout', 'svea-checkout-for-woocommerce' );
		$this->method_description = esc_html__( 'Svea Checkout provides a fully featured checkout solution that speeds up the checkout process for your customers.', 'svea-checkout-for-woocommerce' );

		// This shows for visitors
		$this->description = esc_html__( 'Pay with Svea Checkout. Redirecting...', 'svea-checkout-for-woocommerce' );

		$this->has_fields = true;
		$this->view_transaction_url = '';
		$this->supports = [
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// 'subscription_payment_method_change',
			// 'subscription_payment_method_change_customer',
			// 'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'tokenization',
		];

		$this->svea_payment_methods = [
			'INVOICE'          => esc_html__( 'Invoice', 'svea-checkout-for-woocommerce' ),
			'PAYMENTPLAN'      => esc_html__( 'Payment Plan', 'svea-checkout-for-woocommerce' ),
			'CARD'             => esc_html__( 'Card Payment', 'svea-checkout-for-woocommerce' ),
			'SVEACARDPAY'      => esc_html__( 'Card Payment', 'svea-checkout-for-woocommerce' ),
			'SVEACARDPAY_PF'   => esc_html__( 'Card Payment', 'svea-checkout-for-woocommerce' ),
			'ACCOUNT'          => esc_html__( 'Account Credit', 'svea-checkout-for-woocommerce' ),
			'ACCOUNTCREDIT'    => esc_html__( 'Account Credit', 'svea-checkout-for-woocommerce' ),
			'TRUSTLY'          => esc_html__( 'Trustly', 'svea-checkout-for-woocommerce' ),
			'BANKAXESS'        => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBAKTIAFI'        => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBALANDSBANKENFI' => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBDANSKEBANKSE'   => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBNORDEAFI'       => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBNORDEASE'       => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBPOHJOLAFI'      => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBSAMPOFI'        => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBSEBSE'          => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBSEBFTGSE'       => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBSHBSE'          => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBSPANKKIFI'      => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBSWEDBANKSE'     => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'DBTAPIOLAFI'      => esc_html__( 'Direct bank', 'svea-checkout-for-woocommerce' ),
			'SWISH'            => esc_html__( 'Swish', 'svea-checkout-for-woocommerce' ),
			'SWISH_PF'         => esc_html__( 'Swish', 'svea-checkout-for-woocommerce' ),
			'VIPPS'            => esc_html__( 'Vipps', 'svea-checkout-for-woocommerce' ),
			'MOBILEPAY'        => esc_html__( 'Mobilepay', 'svea-checkout-for-woocommerce' ),
			'ZEROSUM'          => esc_html__( 'Zero sum', 'svea-checkout-for-woocommerce' ),
		];

		$this->svea_billing_references = [
			'InvoiceReference' => [
				'label' => esc_html__( 'Invoice reference', 'svea-checkout-for-woocommerce' ),
				'key'   => 'invoice_reference',
			],
			'InvoiceRecipient' => [
				'label' => esc_html__( 'Invoice recipient', 'svea-checkout-for-woocommerce' ),
				'key'   => 'invoice_recipient',
			],
			'ContactPerson'    => [
				'label' => esc_html__( 'Contact person', 'svea-checkout-for-woocommerce' ),
				'key'   => 'contact_person',
			],
			'Purchase'         => [
				'label' => esc_html__( 'Purchase/requisition number', 'svea-checkout-for-woocommerce' ),
				'key'   => 'purchase',
			],
			'CostCenter'       => [
				'label' => esc_html__( 'Cost center', 'svea-checkout-for-woocommerce' ),
				'key'   => 'cost_center',
			],
			'LetterNumber'     => [
				'label' => esc_html__( 'Letter number', 'svea-checkout-for-woocommerce' ),
				'key'   => 'letter_number',
			],
			'AccountingNumber' => [
				'label' => esc_html__( 'Accounting number', 'svea-checkout-for-woocommerce' ),
				'key'   => 'accounting_number',
			],
			'Undefined'        => [
				'label' => esc_html__( 'Undefined', 'svea-checkout-for-woocommerce' ),
				'key'   => 'undefined',
			],
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->enabled = apply_filters( 'woocommerce_sco_settings_enabled', $this->get_option( 'enabled' ) );
		$this->title = apply_filters( 'woocommerce_sco_settings_title', $this->get_option( 'title' ) );
		self::$log_enabled = $this->get_option( 'log' ) === 'yes';
		self::$nshift_enabled = $this->get_option( 'enable_nshift' ) === 'yes';
		self::$zero_sum_enabled = $this->get_option( 'zero_sum_orders' ) === 'yes';

		$this->customer_types = apply_filters( 'woocommerce_sco_settings_customer_types', $this->get_option( 'customer_types' ) );

		// Sweden
		$this->secret_se = apply_filters( 'woocommerce_sco_settings_secret_se', $this->get_option( 'secret_se' ) );
		$this->merchant_id_se = apply_filters( 'woocommerce_sco_settings_merchant_id_se', $this->get_option( 'merchant_id_se' ) );
		$this->instore_merchant_id_se = apply_filters( 'woocommerce_sco_settings_instore_merchant_id_se', $this->get_option( 'instore_merchant_id_se' ) );
		$this->instore_password_se = apply_filters( 'woocommerce_sco_settings_instore_password_se', $this->get_option( 'instore_password_se' ) );
		$this->instore_pa_secret_se = apply_filters( 'woocommerce_sco_settings_instore_pa_secret_se', $this->get_option( 'instore_pa_secret_se' ) );
		$this->testmode_se = apply_filters( 'woocommerce_sco_settings_testmode_se', $this->get_option( 'testmode_se' ) );

		// Norway
		$this->secret_no = apply_filters( 'woocommerce_sco_settings_secret_no', $this->get_option( 'secret_no' ) );
		$this->merchant_id_no = apply_filters( 'woocommerce_sco_settings_merchant_id_no', $this->get_option( 'merchant_id_no' ) );
		$this->instore_merchant_id_no = apply_filters( 'woocommerce_sco_settings_instore_merchant_id_no', $this->get_option( 'instore_merchant_id_no' ) );
		$this->instore_password_no = apply_filters( 'woocommerce_sco_settings_instore_password_no', $this->get_option( 'instore_password_no' ) );
		$this->instore_pa_secret_no = apply_filters( 'woocommerce_sco_settings_instore_pa_secret_no', $this->get_option( 'instore_pa_secret_no' ) );
		$this->testmode_no = apply_filters( 'woocommerce_sco_settings_testmode_no', $this->get_option( 'testmode_no' ) );

		// Finland
		$this->secret_fi = apply_filters( 'woocommerce_sco_settings_secret_fi', $this->get_option( 'secret_fi' ) );
		$this->merchant_id_fi = apply_filters( 'woocommerce_sco_settings_merchant_id_fi', $this->get_option( 'merchant_id_fi' ) );
		$this->instore_merchant_id_fi = apply_filters( 'woocommerce_sco_settings_instore_merchant_id_fi', $this->get_option( 'instore_merchant_id_fi' ) );
		$this->instore_password_fi = apply_filters( 'woocommerce_sco_settings_instore_password_fi', $this->get_option( 'instore_password_fi' ) );
		$this->instore_pa_secret_fi = apply_filters( 'woocommerce_sco_settings_instore_pa_secret_fi', $this->get_option( 'instore_pa_secret_fi' ) );
		$this->testmode_fi = apply_filters( 'woocommerce_sco_settings_testmode_fi', $this->get_option( 'testmode_fi' ) );

		// Denmark
		$this->secret_dk = apply_filters( 'woocommerce_sco_settings_secret_dk', $this->get_option( 'secret_dk' ) );
		$this->merchant_id_dk = apply_filters( 'woocommerce_sco_settings_merchant_id_dk', $this->get_option( 'merchant_id_dk' ) );
		$this->instore_merchant_id_dk = apply_filters( 'woocommerce_sco_settings_instore_merchant_id_dk', $this->get_option( 'instore_merchant_id_dk' ) );
		$this->instore_password_dk = apply_filters( 'woocommerce_sco_settings_instore_password_dk', $this->get_option( 'instore_password_dk' ) );
		$this->instore_pa_secret_dk = apply_filters( 'woocommerce_sco_settings_instore_pa_secret_dk', $this->get_option( 'instore_pa_secret_dk' ) );
		$this->testmode_dk = apply_filters( 'woocommerce_sco_settings_testmode_dk', $this->get_option( 'testmode_dk' ) );

		// Global/European
		$this->secret_global = apply_filters( 'woocommerce_sco_settings_secret_global', $this->get_option( 'secret_global' ) );
		$this->merchant_id_global = apply_filters( 'woocommerce_sco_settings_merchant_id_global', $this->get_option( 'merchant_id_global' ) );
		$this->instore_merchant_id_global = apply_filters( 'woocommerce_sco_settings_instore_merchant_id_global', $this->get_option( 'instore_merchant_id_global' ) );
		$this->instore_password_global = apply_filters( 'woocommerce_sco_settings_instore_password_global', $this->get_option( 'instore_password_global' ) );
		$this->instore_pa_secret_global = apply_filters( 'woocommerce_sco_settings_instore_pa_secret_global', $this->get_option( 'instore_pa_secret_global' ) );
		$this->testmode_global = apply_filters( 'woocommerce_sco_settings_testmode_global', $this->get_option( 'testmode_global' ) );

		$this->instore_link_expire_minutes = apply_filters( 'woocommerce_sco_settings_instore_link_expire_minutes', $this->get_option( 'instore_link_expire_minutes' ) );

		$this->display_product_widget = apply_filters( 'woocommerce_sco_settings_product_widget', $this->get_option( 'display_product_widget' ) );

		// Prevent duplicate hooks
		if ( ! self::$hooks_enabled ) {
			$this->add_hooks();
		}
	}

	/**
	 * Is the nShift integration enabled?
	 *
	 * @return bool
	 */
	public static function is_nshift_enabled() {
		return self::$nshift_enabled;
	}

	/**
	 * Is zero sum enabled?
	 *
	 * @return bool
	 */
	public static function is_zero_sum_enabled() {
		return self::$zero_sum_enabled;
	}

	/**
	 * Get the shipping options html
	 *
	 * @param string $key
	 * @param array $value
	 * @param array $field
	 * @return string
	 */
	public function generate_nshift_shipping_options_html( string $key, $field_settings ) {
		$fields = $field_settings['fields'] ?? [];
		if ( empty( $fields ) ) return '';

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo wp_kses_post( $field_settings['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="nshift-shipping-wrapper">
					<div class="rows"></div>

					<div class="buttons">
						<div class="remove button">-</div>
						<div class="add button button-primary">+</div>
					</div>
				</div>
				<script type="text/html" id="nshift-shipping-row">
					<?php echo wp_kses_post( $this->generate_settings_html( $fields ) ); ?>
				</script>
			</td>
		</tr>
		<?php

		$html = ob_get_clean();
		return $html;
	}

	/**
	 * Get the name of the payment method
	 *
	 * @param string $key
	 * @return string
	 */
	public function get_payment_method_name( $key ) {
		$method = '';

		if ( isset( $this->svea_payment_methods[ $key ] ) ) {
			$method = $this->svea_payment_methods[ $key ];
		}

		return apply_filters( 'woocommerce_sco_payment_method_name', $method, $key, $this );
	}

	/**
	 * Get the name of the billing reference
	 *
	 * @param string $key
	 * @return string
	 */
	public function get_billing_reference_value( $key ) {
		$method = '';

		if ( isset( $this->svea_billing_references[ $key ] ) ) {
			$method = $this->svea_billing_references[ $key ];
		}

		return apply_filters( 'woocommerce_sco_billing_reference_name', $method, $key, $this );
	}

	/**
	 * Add hooks
	 *
	 * @return void
	 */
	public function add_hooks() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'svea_co_display_extra_admin_order_meta' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'svea_co_display_extra_order_meta' ], 11 );
		add_action( 'woocommerce_email_after_order_table', [ $this, 'svea_co_display_extra_admin_order_meta' ], 11 );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'clear_merchant_validation_cache' ] );

		// Old shortcode for part payments on product pages
		add_shortcode( 'svea_checkout_part_payment_widget', [ $this, 'product_part_payment_widget_shortcode' ] );

		// Use new name "module" instead of "widget" since it's not an actual WordPress widget and can lead to confusion
		add_shortcode( 'svea_checkout_part_payment_module', [ $this, 'product_part_payment_widget_shortcode' ] );

		// If option 'display_product_widget' is checked in settings, display widget for part payment plans
		if ( $this->display_product_widget === 'yes' ) {
			// Get position of the widget from settings
			$product_widget_position = intval( $this->get_option( 'product_widget_position' ) );

			// Set a default position
			if ( $product_widget_position <= 0 ) {
				$product_widget_position = 11;
			}

			add_action( 'woocommerce_single_product_summary', [ $this, 'product_part_payment_widget' ], $product_widget_position, 1 );
		}

		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'append_cart_item_key_to_order_row' ], 10, 4 );
		add_action( 'woocommerce_checkout_create_order_fee_item', [ $this, 'append_cart_item_key_to_order_row' ], 10, 4 );
		add_action( 'woocommerce_checkout_create_order_shipping_item', [ $this, 'append_cart_item_key_to_order_row_shipping' ], 10, 4 );

		add_filter( 'woocommerce_cart_needs_payment', [ $this, 'maybe_force_payment' ], 10, 2 );

		// Subcription related hooks
		add_filter( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
		add_filter( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID, [ $this, 'cancel_subscription' ] );

		self::$hooks_enabled = true;
	}

	/**
	 * Cancel the subscription by disabling the token
	 *
	 * @param \WC_Order $wc_order
	 * @return void
	 */
	public function cancel_subscription( $wc_order ) {
		if ( empty( $wc_order->get_meta( '_svea_co_token' ) ) ) {
			return false;
		}

		try {
			$svea_checkout = new Svea_Checkout( false );
			$svea_checkout->setup_client( $wc_order->get_currency(), $wc_order->get_billing_country() );

			$svea_checkout->update_token(
				$wc_order->get_meta( '_svea_co_token' ),
				'Cancelled'
			);

			$wc_order->delete_meta_data( '_svea_co_token' );
			self::log( sprintf( 'Subscription cancelled for order %s (ID: %d). ', $wc_order->get_order_number(), $wc_order->get_id() ) );
		} catch ( \Exception $e ) {
			self::log( sprintf( 'Error when creating recurring order. Message: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Handle the recurring payment
	 *
	 * @param float $amount_to_charge
	 * @param \WC_Order $renewal_order
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$svea_checkout = new Svea_Checkout( false );
		$svea_checkout->setup_client( $renewal_order->get_currency(), $renewal_order->get_billing_country() );
		$success = $svea_checkout->create_recurring( $renewal_order );

		if ( $success ) {
			self::log( sprintf( 'Created a new order from subscription with value %d. Order: %s (ID: %s)', $amount_to_charge, $renewal_order->get_order_number(), $renewal_order->get_id() ) );
		} else {
			\WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order );
			return;
		}

		// Payment complete
		if ( class_exists( '\WC_Subscriptions_Manager' ) ) {
			\WC_Subscriptions_Manager::process_subscription_payments_on_order( $renewal_order );
		}
	}

	/**
	 * Zero sums order will have to be forced as a payment since webhooks won't receive correct data otherwise
	 *
	 * @param bool $needs_payment
	 * @param \WC_Cart $cart
	 * @return bool
	 */
	public function maybe_force_payment( $needs_payment, $cart ) {
		if ( WC()->session->get( 'chosen_payment_method' ) === self::GATEWAY_ID ) {
			$needs_payment = true;
		}

		return $needs_payment;
	}

	/**
	 * Append the cart items key to the order row so we can link the products between Svea and WooCommerce
	 *
	 * @param \WC_Order_Item $item
	 * @param string $cart_item_key
	 * @param array $values
	 * @param \WC_Order $wc_order
	 * @return \WC_Order_Item
	 */
	public function append_cart_item_key_to_order_row( $item, $cart_item_key, $values, $wc_order ) {
		if ( $wc_order->get_payment_method() === WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			$item->update_meta_data( '_svea_co_cart_key', $cart_item_key );
		}

		return $item;
	}

	/**
	 * Append the shipping id + instance id
	 *
	 * @param \WC_Order_Item_Shipping $item
	 * @param string $package_key
	 * @param array $package
	 * @param \WC_Order $wc_order
	 * @return \WC_Order_Item_Shipping
	 */
	public function append_cart_item_key_to_order_row_shipping( $item, $package_key, $package, $wc_order ) {
		$key = '';
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( isset( $chosen_shipping_methods[ $package_key ], $package['rates'][ $chosen_shipping_methods[ $package_key ] ] ) ) {
			$rate = $package['rates'][ $chosen_shipping_methods[ $package_key ] ];
			$key = $rate->get_id();
		}

		return $this->append_cart_item_key_to_order_row( $item, $package_key . '_' . $key, [], $wc_order );
	}

	/**
	 * Map the checkout fields against Svea
	 *
	 * @return string[]
	 */
	public static function get_checkout_fields_mapping() {
		$map = [
			'billing_first_name'  => 'BillingAddress:FirstName,ShippingAddress:FirstName',
			'billing_last_name'   => 'BillingAddress:LastName,ShippingAddress:LastName',
			'billing_address_1'   => 'BillingAddress:StreetAddress,ShippingAddress:StreetAddress',
			'billing_address_2'   => 'BillingAddress:CoAddress,ShippingAddress:CoAddress',

			'billing_postcode'    => 'BillingAddress:PostalCode,ShippingAddress:PostalCode',
			'billing_city'        => 'BillingAddress:City,ShippingAddress:City',
			'billing_country'     => 'BillingAddress:CountryCode,ShippingAddress:CountryCode',

			'billing_email'       => 'EmailAddress',
			'billing_phone'       => 'PhoneNumber',

			'shipping_first_name' => 'ShippingAddress:FirstName',
			'shipping_last_name'  => 'ShippingAddress:LastName',
			'shipping_address_1'  => 'ShippingAddress:StreetAddress',
			'shipping_address_2'  => 'ShippingAddress:CoAddress',
			'shipping_postcode'   => 'ShippingAddress:PostalCode',
			'shipping_city'       => 'ShippingAddress:City',
			'shipping_country'    => 'ShippingAddress:CountryCode',
		];

		return apply_filters( 'woocommerce_sco_checkout_fields_mapping', $map );
	}

	/**
	 * Get payment plans by country
	 *
	 * @param array $merchant_data Merchant data to fetch part payment plans for
	 * @return array|WP_Error List of payment plan campaigns
	 */
	public function get_part_payment_plans( $merchant_data ) {

		// Get campaigns from cache to save bandwidth and loading time
		$campaigns = get_transient( sprintf( self::PART_PAYMENT_TRANSIENT_FORMAT, $merchant_data['CountryCode'] ) );

		// If no transient is saved, make new request
		if ( ! $campaigns ) {
			$checkout_merchant_id = $merchant_data['MerchantId'];
			$checkout_secret = $merchant_data['Secret'];
			$base_url = $merchant_data['BaseUrl'];
			$conn = \Svea\Checkout\Transport\Connector::init( $checkout_merchant_id, $checkout_secret, $base_url );
			$ceckout_client = new \Svea\Checkout\CheckoutClient( $conn );

			$data = [
				'IsCompany' => false,
			];

			try {
				// Get available part payment plans from Svea
				$campaigns = $ceckout_client->getAvailablePartPaymentCampaigns( $data );
				// Save response in transient to save loading time
				set_transient( sprintf( self::PART_PAYMENT_TRANSIENT_FORMAT, $merchant_data['CountryCode'] ), $campaigns, HOUR_IN_SECONDS );
			} catch ( \Exception $e ) {
				self::log( sprintf( 'Cannot fetch part payment plans from Svea. Message: %s', $e->getMessage() ) );

				return new \WP_Error( 'svea_error', __( 'Error when getting part payment plans from Svea. Please try again.', 'svea-checkout-for-woocommerce' ) );
			}
		}

		return $campaigns;
	}

	/**
	 * Shortcode for part payment module
	 *
	 * @return string
	 */
	public function product_part_payment_widget_shortcode() {
		if ( ! is_product() ) {
			return '';
		}

		ob_start();
		$this->product_part_payment_widget();
		return ob_get_clean();
	}

	/**
	 * Part payment widget used on the product page if activated
	 *
	 * @return void
	 */
	public function product_part_payment_widget() {
		global $product;

		// get merchant settings
		$country_data = $this->get_merchant_settings( get_woocommerce_currency(), WC()->customer->get_billing_country() );

		if ( empty( $country_data['MerchantId'] ) ) {
			return;
		}

		$product_types = apply_filters( 'woocommerce_sco_part_pay_module_product_types', [ 'simple', 'variable' ] );
		$product_types = apply_filters( 'woocommerce_sco_part_pay_widget_product_types', $product_types );

		// Check if product is any of the specified product types
		if ( ! $product->is_type( $product_types ) ) {
			return;
		}

		// Get part payment plans from Svea
		$campaigns = $this->get_part_payment_plans( $country_data );

		if ( ! is_array( $campaigns ) || empty( $campaigns ) ) {
			return;
		}

		// Get price of current product
		$price = floatval( $product->get_price() );

		// Filter out suitable campaigns
		$campaigns = array_values(
			array_filter(
				$campaigns,
				function( $campaign ) use ( $price ) {
					return ( isset( $campaign['PaymentPlanType'] ) && intval( $campaign['PaymentPlanType'] ) !== 2 )
					&& $price >= $campaign['FromAmount'] && $price <= $campaign['ToAmount'];
				}
			)
		);

		$lowest_price_per_month = false;

		// Find the lowest campaign price
		foreach ( $campaigns as $campaign ) {
			$campaign_price = $price * $campaign['MonthlyAnnuityFactor'] + $campaign['NotificationFee'];

			if ( $lowest_price_per_month === false ) {
				$lowest_price_per_month = $campaign_price;
			} else {
				// Get the cost per month from current plan
				$lowest_price_per_month = min( $lowest_price_per_month, $campaign_price );
			}
		}

		if ( $lowest_price_per_month === false || $lowest_price_per_month <= 0 ) {
			return;
		}

		// Get logo for current country
		$svea_icon = $this->get_svea_part_pay_logo_by_country( $country_data['CountryCode'] );

		?>
			<p class="svea-part-payment-module">
				<img src="<?php echo esc_url( $svea_icon ); ?>">
				<?php
					echo wp_kses_post(
						sprintf(
						/* translators: %s is the price */
							esc_html__( 'Part pay from %s/month', 'svea-checkout-for-woocommerce' ),
							wc_price( round( $lowest_price_per_month ) )
						)
					);
				?>
			</p>
		<?php
	}

	/**
	 * Get Svea Part Pay logo depending on country
	 *
	 * @param string $country
	 * @return string URL of the part pay logo
	 */
	public function get_svea_part_pay_logo_by_country( $country = '' ) {
		// Set default logo
		$logo = 'https://cdn.svea.com/webpay/Svea_Primary_RGB_medium.png';

		$country = strtoupper( $country );

		// Get logos from Sveas cdn
		$logos = [
			'SE' => 'https://cdn.svea.com/webpay/Svea_Primary_RGB_medium.png',
			'NO' => 'https://cdn.svea.com/webpay/Svea_Primary_RGB_medium.png',
			'FI' => 'https://cdn.svea.com/webpay/Svea_Primary_RGB_medium.png',
			'DE' => 'https://cdn.svea.com/webpay/Svea_Primary_RGB_medium.png',
		];

		// Set logo for current country
		if ( isset( $logos[ $country ] ) ) {
			$logo = $logos[ $country ];
		}

		return apply_filters( 'woocommerce_sco_part_pay_icon', $logo, $country );
	}

	/**
	 * Check if company customer type is default
	 *
	 * @return bool Whether or not company customer type is default
	 */
	public function is_company_default() {
		return apply_filters( 'woocommerce_sco_settings_default_customer_type', $this->get_option( 'default_customer_type' ) ) === 'company';
	}

	/**
	 * Check if preset email is read only
	 *
	 * @return bool Whether or not preset email is read only
	 */
	public function is_preset_email_read_only() {
		return apply_filters( 'woocommerce_sco_settings_preset_value_email_read_only', $this->get_option( 'preset_value_email_read_only' ) ) === 'yes';
	}

	/**
	 * Check if preset phone is read only
	 *
	 * @return bool Whether or not preset phone is read only
	 */
	public function is_preset_phone_read_only() {
		return apply_filters( 'woocommerce_sco_settings_preset_value_phone_read_only', $this->get_option( 'preset_value_phone_read_only' ) ) === 'yes';
	}

	/**
	 * Check if preset zip code is read only
	 *
	 * @return bool Whether or not preset zip code is read only
	 */
	public function is_preset_zip_code_read_only() {
		return apply_filters( 'woocommerce_sco_settings_preset_value_zip_code_read_only', $this->get_option( 'preset_value_zip_code_read_only' ) ) === 'yes';
	}

	/**
	 * Check if "change address" should be hidden in the iframe
	 *
	 * @return bool Whether or not "change address" should be hidden in the iframe
	 */
	public function should_hide_change_address() {
		return apply_filters( 'woocommerce_sco_settings_hide_change_address', $this->get_option( 'hide_change_address' ) ) === 'yes';
	}

	/**
	 * Check if "not you?" should be hidden in the iframe
	 *
	 * @return bool Whether or not "not you?" should be hidden in the iframe
	 */
	public function should_hide_not_you() {
		return apply_filters( 'woocommerce_sco_settings_hide_not_you', $this->get_option( 'hide_not_you' ) ) === 'yes';
	}

	/**
	 * Check if the anonymous flow should be hidden in the iframe
	 *
	 * @return bool Whether or not the anonymous flow should be hidden in the iframe
	 */
	public function should_hide_anonymous() {
		return apply_filters( 'woocommerce_sco_settings_hide_anonymous', $this->get_option( 'hide_anonymous' ) ) === 'yes';
	}

	/**
	 * Get a list of base countries
	 *
	 * @return array List of base countries
	 */
	public static function get_base_countries() {
		$base_countries = [
			'SE'     => esc_html__( 'Sweden', 'svea-checkout-for-woocommerce' ),
			'NO'     => esc_html__( 'Norway', 'svea-checkout-for-woocommerce' ),
			'FI'     => esc_html__( 'Finland', 'svea-checkout-for-woocommerce' ),
			'DK'     => esc_html__( 'Denmark', 'svea-checkout-for-woocommerce' ),
			'GLOBAL' => esc_html__( 'Global', 'svea-checkout-for-woocommerce' ),
		];

		return $base_countries;
	}

	/**
	 * Print admin options page
	 *
	 * @return void
	 */
	public function admin_options() {
		$this->validate_merchant_credentials();

		parent::admin_options();
	}

	/**
	 * Validate the entered merchant credentials, displaying an error if they are invalid
	 *
	 * @return void
	 */
	private function validate_merchant_credentials() {
		$countries_status_codes = [];

		$base_countries = self::get_base_countries();

		foreach ( $base_countries as $country => $country_label ) {
			$country_status_code = 200;

			$cached_status_code = get_transient( 'woocommerce_sco_country_credentials_status_code_' . strtolower( $country ) );

			if ( $cached_status_code !== false ) {
				$countries_status_codes[ $country ] = $cached_status_code;
				continue;
			}

			// Test fetching an order that doesn't exist
			$country_settings = $this->get_country_settings( $country );

			$checkout_merchant_id = $country_settings['MerchantId'];
			$checkout_secret = $country_settings['Secret'];

			if ( empty( $checkout_merchant_id ) || empty( $checkout_secret ) ) {
				$countries_status_codes[ $country ] = 0;
				continue;
			}

			$admin_base_url = $country_settings['AdminBaseUrl'];
			$admin_connector = Connector::init( $checkout_merchant_id, $checkout_secret, $admin_base_url );
			$admin_checkout_client = new CheckoutAdminClient( $admin_connector );

			$data['OrderId'] = -1;

			try {
				$admin_checkout_client->getOrder( $data );
			} catch ( \Exception $e ) {
				$country_status_code = $e->getCode();
			}

			$countries_status_codes[ $country ] = $country_status_code;

			// Cache request until credentials are changed
			set_transient( 'woocommerce_sco_country_credentials_status_code_' . strtolower( $country ), $country_status_code );
		}

		$error_messages = [
			'401' => esc_html__( 'Merchant ID and secret are either incorrect or does not have permission from Svea to connect. You might have entered test credentials in production mode or vice versa.', 'svea-checkout-for-woocommerce' ),
		];

		$errors_to_display = [];

		if ( ! empty( $countries_status_codes ) ) {
			foreach ( $countries_status_codes as $country_code => $country_status_code ) {
				if ( ! isset( $error_messages[ $country_status_code ] ) ) {
					continue;
				}

				$error_message = $error_messages[ $country_status_code ];

				$errors_to_display[] = '<strong>' . $country_code . '</strong>: ' . $error_message;
			}

			if ( count( $errors_to_display ) > 0 ) {
				?>
					<div class="error woocommerce-sco-error">
						<ul>
							<?php foreach ( $errors_to_display as $error ) : ?>
								<li><?php echo wp_kses_post( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php
			}
		}
	}

	/**
	 * Clear the merchant validation transient cache when options are changed
	 *
	 * @return void
	 */
	public static function clear_merchant_validation_cache() {
		$base_countries = self::get_base_countries();

		foreach ( $base_countries as $country => $country_label ) {
			delete_transient( 'woocommerce_sco_country_credentials_status_code_' . strtolower( $country ) );
		}
	}

	/**
	 * Logging method.
	 *
	 * @param string $message One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 * @param string $level
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( is_null( self::$log ) ) {
				self::$log = new \WC_Logger();
			}

			// Get process ID in order to follow the flow more easily
			$process_id = function_exists( 'getmypid' ) ? 'PID: #' . getmypid() . ' - ' : '';
			$message = $process_id . $message;

			self::$log->log( $level, $message, [ 'source' => self::GATEWAY_ID ] );
		}
	}

	/**
	 * Check if this payment gateway is available to be used
	 * in the WooCommerce Checkout.
	 *
	 * @return bool Whether this payment gateway is available or not
	 */
	public function is_available() {
		if ( $this->enabled !== 'yes' ) {
			return false;
		}

		$enabled = true;

		if ( is_checkout() && ! self::is_zero_sum_enabled() && did_action( 'woocommerce_after_calculate_totals' ) ) {
			// Svea won't accept orders with 0 in value
			$enabled = ( $this->get_order_total() > 0 );
		}

		return apply_filters( 'woocommerce_sco_gateway_enabled', $enabled, $this );
	}

	/**
	 * Get the customer types (both|company|individual)
	 *
	 * @return string[]
	 */
	public function get_customer_types() {
		return $this->customer_types;
	}

	/**
	 * This function returns merchants based on currency and country
	 *
	 * @param string $currency Currency to get merchant settings for
	 * @param string $country Country to get merchant settings for
	 * @return array $settings Returns current country settings with country specific information.
	 */
	public function get_merchant_settings( $currency, $country ) {
		$currency = strtoupper( $currency );
		$country  = strtoupper( $country );

		switch ( $currency ) {
			case 'SEK':
				$settings = [
					'CountryCode'       => 'SE',
					'Currency'          => 'SEK',
					'Locale'            => 'sv-SE',
					'Secret'            => $this->secret_se,
					'MerchantId'        => $this->merchant_id_se,
					'InstoreMerchantId' => $this->instore_merchant_id_se,
					'InstorePassword'   => $this->instore_password_se,
					'InstorePASecret'   => $this->instore_pa_secret_se,
					'BaseUrl'           => $this->testmode_se === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl'      => $this->testmode_se === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
					'TestMode'          => $this->testmode_se === 'yes',
				];
				break;
			case 'NOK':
				$settings = [
					'CountryCode'       => 'NO',
					'Currency'          => 'NOK',
					'Locale'            => 'nn-NO',
					'Secret'            => $this->secret_no,
					'MerchantId'        => $this->merchant_id_no,
					'InstoreMerchantId' => $this->instore_merchant_id_no,
					'InstorePassword'   => $this->instore_password_no,
					'InstorePASecret'   => $this->instore_pa_secret_no,
					'BaseUrl'           => $this->testmode_no === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl'      => $this->testmode_no === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
					'TestMode'          => $this->testmode_no === 'yes',
				];
				break;
			case 'DKK':
				$settings = [
					'CountryCode'       => 'DK',
					'Currency'          => 'DKK',
					'Locale'            => 'da-DK',
					'Secret'            => $this->secret_dk,
					'MerchantId'        => $this->merchant_id_dk,
					'InstoreMerchantId' => $this->instore_merchant_id_dk,
					'InstorePassword'   => $this->instore_password_dk,
					'InstorePASecret'   => $this->instore_pa_secret_dk,
					'BaseUrl'           => $this->testmode_dk === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl'      => $this->testmode_dk === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
					'TestMode'          => $this->testmode_dk === 'yes',
				];
				break;
			case 'EUR':
				// If multiple countries has this currency you can give other credentials for it
				switch ( $country ) {
					case 'FI':
						$settings = [
							'CountryCode'       => 'FI',
							'Currency'          => 'EUR',
							'Locale'            => 'fi-FI',
							'Secret'            => $this->secret_fi,
							'InstoreMerchantId' => $this->instore_merchant_id_fi,
							'InstorePassword'   => $this->instore_password_fi,
							'InstorePASecret'   => $this->instore_pa_secret_fi,
							'MerchantId'        => $this->merchant_id_fi,
							'BaseUrl'           => $this->testmode_fi === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
							'AdminBaseUrl'      => $this->testmode_fi === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
							'TestMode'          => $this->testmode_fi === 'yes',
						];
						break;
					default:
						$settings = [
							'CountryCode'       => $country,
							'Currency'          => 'EUR',
							'Locale'            => 'en-GB',
							'Secret'            => $this->secret_global,
							'MerchantId'        => $this->merchant_id_global,
							'InstoreMerchantId' => $this->instore_merchant_id_global,
							'InstorePassword'   => $this->instore_password_global,
							'InstorePASecret'   => $this->instore_pa_secret_global,
							'BaseUrl'           => $this->testmode_global === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
							'AdminBaseUrl'      => $this->testmode_global === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
							'TestMode'          => $this->testmode_global === 'yes',
						];
						break;
				}

				break;
			default:
				$settings = [
					'CountryCode'       => '',
					'Currency'          => '',
					'Locale'            => '',
					'Secret'            => '',
					'MerchantId'        => '',
					'InstoreMerchantId' => '',
					'InstorePassword'   => '',
					'InstorePASecret'   => '',
					'BaseUrl'           => '',
					'TestMode'          => false,
				];
		}

		// If we fail on FI we'll try the global settings
		if ( $currency === 'EUR' && $country === 'FI' && empty( $settings['CountryCode'] ) && isset( WC()->customer ) ) {
			return $this->get_merchant_settings( $currency, WC()->customer->get_billing_country() );
		}

		return $settings;
	}

	/**
	 * This function returns merchants based solely on country
	 *
	 * @param string $country Country to get merchant settings for
	 * @return array $settings Returns current country settings with country specific information.
	 */
	public function get_country_settings( $country = '' ) {
		if ( empty( $country ) ) {
			$country = WC()->customer->get_billing_country();
		}

		$country = strtoupper( $country );

		switch ( $country ) {
			case 'SE':
				$settings = [
					'CountryCode'  => 'SE',
					'Currency'     => 'SEK',
					'Locale'       => 'sv-SE',
					'Secret'       => $this->secret_se,
					'MerchantId'   => $this->merchant_id_se,
					'BaseUrl'      => $this->testmode_se === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl' => $this->testmode_se === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
				];
				break;
			case 'FI':
				$settings = [
					'CountryCode'  => 'FI',
					'Currency'     => 'EUR',
					'Locale'       => 'fi-FI',
					'Secret'       => $this->secret_fi,
					'MerchantId'   => $this->merchant_id_fi,
					'BaseUrl'      => $this->testmode_fi === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl' => $this->testmode_fi === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
				];

				break;
			case 'NO':
				$settings = [
					'CountryCode'  => 'NO',
					'Currency'     => 'NOK',
					'Locale'       => 'nn-NO',
					'Secret'       => $this->secret_no,
					'MerchantId'   => $this->merchant_id_no,
					'BaseUrl'      => $this->testmode_no === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl' => $this->testmode_no === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
				];
				break;
			case 'DK':
				$settings = [
					'CountryCode'  => 'DK',
					'Currency'     => 'DKK',
					'Locale'       => 'da-DK',
					'Secret'       => $this->secret_dk,
					'MerchantId'   => $this->merchant_id_dk,
					'BaseUrl'      => $this->testmode_dk === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl' => $this->testmode_dk === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
				];
				break;
			case 'GLOBAL':
				$settings = [
					'CountryCode'  => 'DK',
					'Currency'     => 'DKK',
					'Locale'       => 'da-DK',
					'Secret'       => $this->secret_dk,
					'MerchantId'   => $this->merchant_id_dk,
					'BaseUrl'      => $this->testmode_dk === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl' => $this->testmode_dk === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
				];
				break;
			default:
				$settings = [
					'CountryCode'  => 'N/A',
					'Currency'     => 'EUR',
					'Locale'       => 'en-GB',
					'Secret'       => $this->secret_global,
					'MerchantId'   => $this->merchant_id_global,
					'BaseUrl'      => $this->testmode_global === 'yes' ? Connector::TEST_BASE_URL : Connector::PROD_BASE_URL,
					'AdminBaseUrl' => $this->testmode_global === 'yes' ? Connector::TEST_ADMIN_BASE_URL : Connector::PROD_ADMIN_BASE_URL,
					'TestMode'     => $this->testmode_global === 'yes',
				];
		}

		return $settings;
	}

	/**
	 * Check for valid VAT percentage
	 *
	 * @param string $country
	 * @param float $vat_percentage
	 * @return bool
	 */
	public static function is_valid_vat_percentage( $country, $vat_percentage ) {
		$country = strtoupper( $country );

		$vat_percentages = [
			'SE' => [ 0, 6, 12, 25 ],
			'FI' => [ 0, 10, 15, 24 ],
			'NO' => [ 0, 8, 10, 11.11, 15, 24, 25 ],
			'DK' => [ 0, 25 ],
		];

		if ( ! isset( $vat_percentages[ $country ] ) ) {
			return false;
		}

		return in_array( $vat_percentage, $vat_percentages[ $country ] ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		self::log( sprintf( 'Starting processing order in payment method for ID: %s', $order_id ) );

		/** @var \WC_Order $wc_order */
		$wc_order = wc_get_order( $order_id );

		$sco_id = WC()->session->get( 'sco_order_id' );

		if ( empty( $sco_id ) ) {
			wp_send_json(
				[
					'Valid'             => false,
					'Message'           => esc_html__( 'Something went wrong, please try again', 'svea-checkout-for-woocommerce' ),
					'ClientOrderNumber' => '',
				]
			);
			exit;
		}

		// Default to success
		$result = 'success';

		$item_mapping = WC()->session->get( 'sco_item_mapping' );

		$wc_order->update_meta_data( '_svea_co_validation_time', time() );

		// Check for older orders that no longer should be syncing with Svea
		$prev_order = Webhook_Handler::get_order_by_svea_id( $sco_id );

		if ( ! empty( $prev_order ) && $prev_order->get_id() !== $wc_order->get_id() ) {
			$prev_order->update_meta_data( '_svea_co_order_id', $sco_id . '-old' );
			$prev_order->save();
		}

		$wc_order->update_meta_data( '_svea_co_order_id', $sco_id );
		$wc_order->save();

		// Whether or not to do mapping validation
		$should_do_mapping_validation = apply_filters( 'woocommerce_sco_should_do_cart_items_mapping_validation', true );
		$wc_order->update_meta_data( '_svea_co_cart_mapping', $item_mapping );

		$has_error = false;

		// Map row ID's in Svea to WooCommerce
		if ( ! empty( $wc_order ) && is_array( $item_mapping ) ) {
			$wc_items = $wc_order->get_items( [ 'line_item', 'fee', 'shipping' ] );

			foreach ( $wc_items as $item ) {
				$key = $item->get_meta( '_svea_co_cart_key' );
				$svea_row_number = array_search( $key, $item_mapping ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict

				if ( $svea_row_number !== false ) {
					unset( $item_mapping[ $svea_row_number ] );

					$item->update_meta_data( '_svea_co_order_row_id', $svea_row_number );
					$item->save();
				} else {
					if ( $should_do_mapping_validation ) {
						self::log( sprintf( 'Item mapping missmatch. Svea Order ID: %s. WC-ID: %s. Item without match: %s', $sco_id, $order_id, $key ), 'error' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					}

					$has_error = true;
				}
			}
		}

		if ( $should_do_mapping_validation ) {
			if ( ! empty( $item_mapping ) ) {
				self::log( sprintf( 'Item mapping missmatch. Svea Order ID: %s. WC-ID: %s. Remaining items: %s', $sco_id, $order_id, var_export( $item_mapping, true ) ), 'error' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$has_error = true;
			}

			if ( $has_error ) {
				$result = 'fail';
				wc_add_notice( esc_html__( 'Cart is out of sync. Please reload the checkout and try again', 'svea-checkout-for-woocommerce' ), 'notice' );
			}
		}

		$svea_checkout = new Svea_Checkout();
		$svea_order = $svea_checkout->get();

		foreach ( $svea_order['Cart']['Items'] as $svea_item ) {
			if ( $svea_item['ArticleNumber'] === 'ROUNDING' ) {
				$wc_order->update_meta_data( '_svea_co_rounding_order_row_id', $svea_item['RowNumber'] );
				break;
			}
		}

		do_action( 'woocommerce_sco_checkout_send_checkout_result', $wc_order, $sco_id );

		$wc_order->save();

		$result = [
			'result'       => $result,
			'order_number' => $wc_order->get_order_number(),
			'order_id'     => $order_id,
		];

		return $result;
	}

	/**
	 * Save the request time to perhaps show a message to the admin
	 *
	 * @return void
	 */
	private function save_request_time() {
		$diff = time() - svea_checkout()->webook_handler->start_time;

		if ( $diff >= 8 ) {
			update_option(
				'sco_request_violation',
				[
					'time' => time(),
					'diff' => $diff,
				]
			);
		}
	}

	/**
	 * Display reference name and payment method on the order edit page
	 *
	 * @param \WC_Order $order The order which the meta will be displayed on
	 * @return void
	 */
	public function svea_co_display_extra_admin_order_meta( $order ) {
		$payment_type = $order->get_meta( '_svea_co_payment_type' );
		$registration_number = $order->get_meta( '_svea_co_company_reg_number' );
		$customer_reference = $order->get_meta( '_svea_co_customer_reference' );
		?>
		<div class="address">
			<?php if ( $order->get_meta( '_svea_co_is_company' ) ) : ?>
				<?php if ( ! empty( $customer_reference ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Svea Payment reference:', 'svea-checkout-for-woocommerce' ); ?></strong>
						<?php echo esc_html( $customer_reference ); ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $registration_number ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Organisation number:', 'svea-checkout-for-woocommerce' ); ?></strong>
						<?php echo esc_html( $registration_number ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( ! empty( $payment_type ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Svea Payment method:', 'svea-checkout-for-woocommerce' ); ?></strong>
					<?php echo esc_html( $payment_type ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display reference name on the order edit page
	 *
	 * @param \WC_Order $order the order which the meta will be displayed on
	 * @return void
	 */
	public function svea_co_display_extra_order_meta( $order ) {
		$customer_reference = $order->get_meta( '_svea_co_customer_reference' );

		if ( ! $customer_reference ) {
			return;
		}
		?>

		<h2 class="woocommerce-order-details__title">
			<?php esc_html_e( 'Payment information', 'svea-checkout-for-woocommerce' ); ?>
		</h2>
		<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">
			<tbody>
				<?php if ( $customer_reference ) : ?>
					<tr>
						<th><?php esc_html_e( 'Payment reference', 'svea-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $customer_reference ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Process refunds for orders
	 *
	 * @param int    $order_id ID of the order being credited
	 * @param float  $amount Amount being refunded
	 * @param string $reason Reason for the refund
	 * @return bool|\WP_Error whether or not the refund was processed
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return false;
		}

		$svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );

		if ( ! $svea_order_id ) {
			return new \WP_Error( 'no_sco_order_id', esc_html__( 'Svea order id is not set for this order', 'svea-checkout-for-woocommerce' ) );
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		try {
			$response = $payment_admin->get( $svea_order_id );
		} catch ( \Exception $e ) {
			self::log( 'Error when getting order from Svea: ' . $e->getMessage() );
			return new \WP_Error( 'svea_error', esc_html__( 'Error when getting order from Svea. Please try again.', 'svea-checkout-for-woocommerce' ) );
		}

		if ( ! isset( $response['Actions'] ) ) {
			self::log( 'Actions were not available for the order "' . $order_id . '""' );
			return new \WP_Error( 'svea_no_actions', esc_html__( 'Svea has no actions available for this order.', 'svea-checkout-for-woocommerce' ) );
		}

		// Cancel order amount if action is available
		if ( in_array( 'CanCancelAmount', $response['Actions'], true ) ) {
			$cancelled_amount = 0;

			if ( isset( $response['CancelledAmount'] ) ) {
				$cancelled_amount = intval( $response['CancelledAmount'] );
			}

			// Increment already credited amount with the new credit amount
			$cancelled_amount += intval( round( $amount * 100 ) );

			try {
				$response = $payment_admin->cancel_order_amount( $svea_order_id, $cancelled_amount );
			} catch ( \Exception $e ) {
				self::log( 'Error when cancelling order amount in Svea. Please try again. Error message: ' . $e->getMessage() );
				return new \WP_Error( 'svea_error', esc_html__( 'Error when cancelling order amount in Svea. Please try again.', 'svea-checkout-for-woocommerce' ) );
			}

			$wc_order->add_order_note(
				/* translators: %s is the price */
				sprintf( esc_html__( 'Cancelled %s in Svea.', 'svea-checkout-for-woocommerce' ), wc_price( $amount ) )
			);

			/* translators: %s is the amount */
			self::log( sprintf( esc_html__( 'Cancelled %s in Svea.' ), $amount ) );

			return true;
		} else if ( ! isset( $response['Deliveries'][0] ) ) {
			self::log( 'No deliveries were found on the order "' . $order_id . '""' );
			return new \WP_Error( 'svea_no_deliveries', esc_html__( 'No deliveries were found on this order. You can only credit if the order has been delivered.', 'svea-checkout-for-woocommerce' ) );
		}

		$delivery = $response['Deliveries'][0];

		if ( in_array( 'CanCreditNewRow', $delivery['Actions'], true ) ) {
			$order_rows = $delivery['OrderRows'];

			$delivery_total = $delivery['DeliveryAmount'];
			$credited_total = $delivery['CreditedAmount'];

			// Calculate amount left that can be credited
			$amount_left = $delivery_total - $credited_total;

			$order_rows_total = 0.0;

			$tax_amounts = [];

			// Calculate the tax amounts
			if ( ! empty( $order_rows ) ) {
				foreach ( $order_rows as $order_row ) {
					$tax_rate = intval( $order_row['VatPercent'] / 100 );

					$row_total = ( $order_row['UnitPrice'] / 100 ) * ( $order_row['Quantity'] / 100 );

					// If there is a discount, add it to the calculation
					$row_total *= ( 100 - ( $order_row['DiscountPercent'] / 100 ) ) / 100;

					$order_rows_total += $row_total;

					if ( isset( $tax_amounts[ $tax_rate ] ) ) {
						$tax_amounts[ $tax_rate ] += $row_total;
					} else {
						$tax_amounts[ $tax_rate ] = $row_total;
					}
				}
			}

			$total_credit_amount = 0;

			// Order rows to credit
			$credit_order_rows = [];

			foreach ( $tax_amounts as $tax_rate => $tax_amount ) {
				if ( $tax_amount > 0 ) {
					$tax_part = min( 1.0, $tax_amount / $order_rows_total );

					$credit_amount = round( $tax_part * $amount * 100 ) / 100;

					// Skip 0 credits
					if ( $credit_amount <= 0 ) {
						continue;
					}

					$total_credit_amount += $credit_amount;

					/*
						Handle cases where we have a negative rounding and are trying to credit
						more than the total order value
					 */
					if ( $total_credit_amount > $amount_left / 100 ) {
						$total_credit_diff = $total_credit_amount - ( $amount_left / 100 );

						$total_credit_amount -= $total_credit_diff;
						$credit_amount       -= $total_credit_diff;
					}

					/* translators: %s is the tax rate */
					$credit_name = sprintf( esc_html__( 'Credit (%s)', 'svea-checkout-for-woocommerce' ), $tax_rate . '%' );

					if ( ! empty( $reason ) ) {
						/* translators: %s is the reason for crediting */
						$credit_name .= ', ' . sprintf( esc_html__( 'reason: %s', 'svea-checkout-for-woocommerce' ), $reason );

						if ( function_exists( 'mb_strlen' ) ) {
							if ( mb_strlen( $credit_name ) > 40 ) {
								$credit_name = trim( mb_substr( $credit_name, 0, 37 ) ) . '...';
							}
						} elseif ( strlen( $credit_name ) > 40 ) {
							$credit_name = trim( substr( $credit_name, 0, 37 ) ) . '...';
						}
					}

					$credit_data = [
						'Name'       => $credit_name,
						'Quantity'   => 100,
						'VatPercent' => intval( $tax_rate ) * 100,
						'UnitPrice'  => round( $credit_amount * 100 ),
					];

					$credit_order_rows[] = $credit_data;
				}
			}

			if ( ! empty( $credit_order_rows ) ) {
				try {
					$response = $payment_admin->credit_new_order_rows( $svea_order_id, $delivery['Id'], $credit_order_rows );
				} catch ( \Exception $e ) {
					/* translators: %s is a message from Svea */
					$message = sprintf( esc_html__( 'Error when trying to credit in Svea. Message: %s', 'svea-checkout-for-woocommerce' ), $e->getMessage() );
					$wc_order->add_order_note( $message );

					self::log( sprintf( 'Error when trying to credit in Svea. Credit data: %s Message: %s', var_export( $credit_order_rows, true ), $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					return new \WP_Error( 'svea_error', esc_html__( 'Error when crediting in Svea. Please try again.', 'svea-checkout-for-woocommerce' ) );
				}
			}

			$wc_order->add_order_note(
				/* translators: %s is a price */
				sprintf( esc_html__( 'Credited %s in Svea.', 'svea-checkout-for-woocommerce' ), wc_price( $amount ) )
			);

			/* translators: %s is a price */
			self::log( sprintf( esc_html__( 'Credited %s in Svea.' ), $amount ) );

			return true;
		} else if ( in_array( 'CanCreditAmount', $delivery['Actions'], true ) ) {
			$credited_amount = 0;

			if ( isset( $delivery['CreditedAmount'] ) ) {
				$credited_amount = intval( $delivery['CreditedAmount'] );
			}

			// Increment already credited amount with the new credit amount
			$credit_amount = $credited_amount + ( intval( round( $amount * 100 ) ) );

			try {
				$response = $payment_admin->credit_order_amount( $svea_order_id, $delivery['Id'], $credit_amount );
			} catch ( \Exception $e ) {
				self::log( sprintf( 'Error when trying to credit in Svea. Message: %s', $e->getMessage() ) );
				return new \WP_Error( 'svea_error', esc_html__( 'Error when crediting in Svea. Please try again.', 'svea-checkout-for-woocommerce' ) );
			}

			$wc_order->add_order_note(
				/* translators: %s is a price */
				sprintf( esc_html__( 'Credited %s in Svea.', 'svea-checkout-for-woocommerce' ), wc_price( $amount ) )
			);

			/* translators: %s is a price */
			$message = sprintf( esc_html__( 'Credited %s in Svea.', 'svea-checkout-for-woocommerce' ), wc_price( $amount ) );

			$wc_order->add_order_note( $message );
			self::log( $message );

			return true;
		} else {
			self::log( 'The order "' . $order_id . '" can not be credited.' );
			return new \WP_Error( 'svea_error', esc_html__( 'This order can not be credited.', 'svea-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Process delivery for order
	 *
	 * @param int $order_id ID of the order being delivered
	 * @return mixed whether or not the order was delivered in Svea
	 */
	public function deliver_order( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		$svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );

		if ( ! $svea_order_id ) {
			return false;
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		try {
			$get_response = $payment_admin->get( $svea_order_id );
		} catch ( \Exception $e ) {
			self::log( 'Error when getting order from Svea.' );
			return new \WP_Error( 'svea_error', esc_html__( 'Error when getting order from Svea. Please try again.', 'svea-checkout-for-woocommerce' ) );
		}

		// Check if status is already synced
		if ( isset( $get_response['OrderStatus'] ) && strtoupper( $get_response['OrderStatus'] ) === 'DELIVERED' ) {
			$wc_order->add_order_note(
				esc_html__( 'Order is already delivered in Svea. No action needed.', 'svea-checkout-for-woocommerce' )
			);

			return true;
		}

		$deliver_data = []; // Deliver everything

		try {
			$payment_admin->deliver_order( $svea_order_id, $deliver_data );
		} catch ( \Exception $e ) {
			$wc_order->add_order_note(
				/* translators: %s is the message from Svea */
				sprintf( esc_html__( 'Error received when trying to deliver order in Svea: %s' ), $e->getMessage() )
			);

			return false;
		}

		$wc_order->add_order_note(
			esc_html__( 'Order was delivered in Svea.', 'svea-checkout-for-woocommerce' )
		);

		self::log( 'Order ID:' . $wc_order->get_id() . ' was delivered in Svea.' );

		$wc_order->update_meta_data( '_svea_co_deliver_date', date_i18n( 'Y-m-d H:i:s' ) );
		$wc_order->save();

		return true;
	}

	/**
	 * Process cancellation of order
	 *
	 * @param int $order_id ID of the order being cancelled
	 * @return bool Whether or not the order was cancelled in Svea
	 */
	public function cancel_order( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		$svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );

		if (
			! $svea_order_id ||
			! is_numeric( $svea_order_id ) ||
			$wc_order->meta_exists( '_svea_co_order_cancelled' ) ||
			! $wc_order->meta_exists( '_svea_co_order_final' )
		) {
			return false;
		}

		$client_settings = $this->get_merchant_settings( $wc_order->get_currency(), $wc_order->get_billing_country() );

		$checkout_merchant_id = $client_settings['MerchantId'];
		$checkout_secret      = $client_settings['Secret'];

		// Check if merchant ID and secret is set
		if ( ! isset( $checkout_merchant_id[0] ) || ! isset( $checkout_secret[0] ) ) {
			return false;
		}

		$payment_admin = new Svea_Payment_Admin( $wc_order );

		try {
			$payment_admin->cancel_order( $svea_order_id );

		} catch ( \Exception $e ) {
			$wc_order->add_order_note(
				/* translators: %s is the message from Svea */
				sprintf( esc_html__( 'Error received when trying to cancel order in Svea: %s' ), $e->getMessage() )
			);

			return false;
		}

		$wc_order->add_order_note(
			esc_html__( 'Order was cancelled in Svea.', 'svea-checkout-for-woocommerce' )
		);

		$wc_order->update_meta_data( '_svea_co_cancel_date', date_i18n( 'Y-m-d H:i:s' ) );
		$wc_order->save();

		return true;
	}

	/**
	 * Process refund of order
	 *
	 * @param int $order_id ID of the order being cancelled
	 * @return bool Whether or not the order was refunded in Svea
	 */
	public function refund_order( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order || ( ! is_a( $wc_order, 'WC_Order' ) ) ) {
			return false;
		}

		$refund_amount = $wc_order->get_total() - $wc_order->get_total_refunded();

		return $this->process_refund( $order_id, $refund_amount );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include 'settings-svea-checkout.php';
	}

}
