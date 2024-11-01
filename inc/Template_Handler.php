<?php

namespace Svea_Checkout_For_Woocommerce;

use Svea_Checkout_For_Woocommerce\Models\Svea_Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Checkout shortcode
 *
 * Used on the checkout page to display the checkout
 *
 */
class Template_Handler {

	/**
	 * Init function
	 */
	public function init() {
		add_shortcode( 'svea_checkout', [ $this, 'display_svea_checkout_page' ] );

		add_action( 'wc_ajax_refresh_sco_snippet', [ $this, 'refresh_sco_snippet' ] );

		add_action( 'wc_ajax_update_sco_order_nshift_information', [ $this, 'update_order_nshift_information' ] );
		add_action( 'wc_ajax_sco_change_payment_method', [ $this, 'change_payment_method' ], 100 );

		add_action( 'wc_ajax_sco_checkout_order', [ $this, 'sco_checkout_order' ] );

		add_action( 'woocommerce_thankyou', [ $this, 'display_thank_you_box' ] );

		add_filter( 'wc_get_template', [ $this, 'maybe_override_checkout_template' ], 10, 2 );

		if ( isset( $_GET['sco_redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'wp', [ $this, 'maybe_redirect_to_thankyou' ] );
		}

		if ( isset( $_GET['callback'] ) && $_GET['callback'] === 'svea' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( 'init', [ $this, 'handle_checkout_error' ] );
		}

		add_action( 'woocommerce_checkout_before_order_review', [ $this, 'modify_checkout_page_hooks' ] );

		// Remove checkout fields if they are not needed
		add_action( 'woocommerce_checkout_before_order_review', [ $this, 'maybe_remove_checkout_fields' ] );
	}

	/**
	 * Checkout the current order
	 *
	 * @return void
	 */
	public function sco_checkout_order() {
		check_ajax_referer( 'sco-checkout-order', 'security' );
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		// We want to use billing country and postcode from the checkout
		if ( isset( $_POST['billing_country'] ) ) {
			unset( $_POST['billing_country'] );
		}

		if ( isset( $_POST['billing_postcode'] ) ) {
			unset( $_POST['billing_postcode'] );
		}

		// This can't be accessed from the checkout, use the same as billing
		if ( isset( $_POST['billing_state'] ) ) {
			$_POST['shipping_state'] = $_POST['billing_state'];
		}

		// Get the current checkout
		$svea_checkout = new Svea_Checkout();
		$svea_checkout_data = $svea_checkout->get();

		// Map data from the checkout to the $_POST and $_REQUEST variables as well
		$fields = WC_Gateway_Svea_Checkout::get_checkout_fields_mapping();

		foreach ( $fields as $wc_name => $svea_field_name ) {
			$value = Helper::delimit_address( $svea_checkout_data, $svea_field_name );
			self::set_request_val( $wc_name, $value );
		}

		if ( isset( $svea_checkout_data['Customer']['IsCompany'] ) && $svea_checkout_data['Customer']['IsCompany'] ) {
			// Company customers should not have to fill in all fields
			add_filter( 'woocommerce_checkout_fields', [ $this, 'company_mark_checkout_fields_as_optional' ], 100000 );
		}

		WC()->checkout()->process_checkout();
		wp_die( 0 );
	}

	/**
	 * Set a value in the $_POST and $_REQUEST variables
	 *
	 * @param string $wc_name
	 * @param mixed $value
	 * @return void
	 */
	public static function set_request_val( $wc_name, $value ) {
		$_REQUEST[ $wc_name ] = $value;
		$_POST[ $wc_name ] = $value;
	}

	/**
	 * Modify hooks on the checkout page
	 *
	 * @return void
	 */
	public function modify_checkout_page_hooks() {
		if ( $this->is_svea() ) {
			// Don't display the payment method(s) on the Svea checkout page
			remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

			// Terms and conditions are displayed in the Svea iframe
			add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', '__return_empty_string', 20 );

			// Remove privacy policy and terms and conditions since they are in the iframe
			remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
			remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
		}
	}

	/**
	 * Remove checkout fields if they are not needed
	 *
	 * @return void
	 */
	public function maybe_remove_checkout_fields() {
		if ( $this->is_svea() ) {
			// Remove all default fields from WooCommerce to see what gets added by third party plugins
			add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_billing_and_shipping_fields' ], PHP_INT_MAX );
			add_filter( 'woocommerce_checkout_fields', [ $this, 'maybe_remove_order_comments_field' ] );
		}
	}

	/**
	 * Mark checkout fields as optional for company customers
	 *
	 * @param array $fields
	 * @return array
	 */
	public function company_mark_checkout_fields_as_optional( $fields ) {
		if ( isset( $fields['billing']['billing_first_name'] ) ) {
			$fields['billing']['billing_first_name']['required'] = false;
		}

		if ( isset( $fields['billing']['billing_last_name'] ) ) {
			$fields['billing']['billing_last_name']['required'] = false;
		}

		if ( isset( $fields['shipping']['shipping_first_name'] ) ) {
			$fields['shipping']['shipping_first_name']['required'] = false;
		}

		if ( isset( $fields['shipping']['shipping_last_name'] ) ) {
			$fields['shipping']['shipping_last_name']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Mark all default fields from WooCommerce to be removed. By not removing them directly we don't envounter issues with third party plugins
	 *
	 * @param array $fields
	 * @return array
	 */
	public function mark_to_remove( $fields ) {
		foreach ( $fields['billing'] as $key => &$field ) {
			$field['svea_checkout_remove'] = true;
		}

		foreach ( $fields['shipping'] as $key => &$field ) {
			$field['svea_checkout_remove'] = true;
		}

		return $fields;
	}

	/**
	 * Remove marked billing and shipping fields
	 *
	 * @param array $fields
	 * @return array
	 */
	public function remove_billing_and_shipping_fields( $fields ) {
		$should_remove = apply_filters( 'woocommerce_sco_should_remove_default_fields', true );

		if ( ! $should_remove ) {
			return $fields;
		}

		$default_fields = [
			'first_name',
			'last_name',
			'company',
			'country',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'phone',
			'email',
		];

		foreach ( $default_fields as $key ) {
			if ( isset( $fields['billing'][ 'billing_' . $key ] ) ) {
				unset( $fields['billing'][ 'billing_' . $key ] );
			}

			if ( isset( $fields['shipping'][ 'shipping_' . $key ] ) ) {
				unset( $fields['shipping'][ 'shipping_' . $key ] );
			}
		}

		return $fields;
	}

	/**
	 * Maybe remove order comments field
	 *
	 * @param array $fields
	 * @return array
	 */
	public function maybe_remove_order_comments_field( $fields ) {
		// Maybe remove order comments
		if ( ! apply_filters( 'woocommerce_enable_order_notes_field', get_option( 'woocommerce_enable_order_comments', 'yes' ) === 'yes' )
			&& isset( $fields['order']['order_comments'] ) ) {
			unset( $fields['order']['order_comments'] );
		}

		return $fields;
	}

	/**
	 * Returns 'yes'
	 *
	 * @return string
	 */
	public static function _return_yes() {
		return 'yes';
	}

	/**
	 * The checkout redirected the user back. Allow plugins to handle errors
	 *
	 * @return void
	 */
	public function handle_checkout_error() {
		$svea_id = WC()->session->get( 'sco_order_id' );
		$wc_id = WC()->session->get( 'order_awaiting_payment' );

		do_action( 'woocommerce_sco_checkout_error_order', $wc_id, $svea_id );
	}

	/**
	 * Change payment method
	 *
	 * @return void
	 */
	public function change_payment_method() {
		$use_svea = isset( $_POST['svea'] ) && sanitize_text_field( $_POST['svea'] ) === 'true'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Allow plugins and themes to understand that we're in the checkout
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		// Calculate shipping in order for dependent payment gateways to work
		WC()->cart->calculate_shipping();

		/** @var \WC_Payment_Gateway[] $available_gateways */
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! empty( $available_gateways ) ) {
			foreach ( $available_gateways as $key => $gateway ) {
				if ( $use_svea && WC_Gateway_Svea_Checkout::GATEWAY_ID === $key ) {
					WC()->session->set( 'chosen_payment_method', $key );
					wp_send_json_success( 'Ok' );
				} else if ( ! $use_svea && WC_Gateway_Svea_Checkout::GATEWAY_ID !== $key ) {
					WC()->session->set( 'chosen_payment_method', $key );
					wp_send_json_success( 'Ok' );
				}
			}
		}

		wp_send_json_error();
	}

	/**
	 * Maybe redirect to the thank you page
	 *
	 * @return void
	 */
	public function maybe_redirect_to_thankyou() {
		if ( is_checkout() && $_GET['sco_redirect'] === 'true' ) { // phpcs:ignore
			$svea_order_id = WC()->session->get( 'sco_order_id' );

			$args = [
				'status'     => 'any',
				'limit'      => 1,
				'meta_key'   => '_svea_co_order_id',
				'meta_value' => $svea_order_id,
			];

			$wc_orders = wc_get_orders( $args );

			if ( ! empty( $wc_orders ) ) {
				// Check in checkout if the order is completed
				$svea_checkout = new Svea_Checkout();
				$svea_checkout_module = $svea_checkout->get();

				// Check if status is final
				if ( strtoupper( $svea_checkout_module['Status'] ) !== 'FINAL' ) {
					WC_Gateway_Svea_Checkout::log( sprintf( 'Order %s is not in final state, status: %s', $svea_order_id, $svea_checkout_module['Status'] ) );
					wp_safe_redirect( wc_get_checkout_url() );
					exit;
				}

				/** @var \Automattic\WooCommerce\Admin\Overrides\Order $order */
				$wc_order = $wc_orders[0];

				WC_Gateway_Svea_Checkout::log( sprintf( 'Redirecting order %s to the thankyou page', $wc_order->get_id() ) );
				$wc_order->delete_meta_data( '_svea_co_token' );
				$wc_order->save();

				wp_safe_redirect( $wc_order->get_checkout_order_received_url() );
				die;
			}
		}
	}

	/**
	 * Get the iframe snippet from Svea
	 *
	 * @return string
	 */
	public static function get_svea_snippet() {
		$svea_checkout_module = self::get_svea_checkout_module();
		echo isset( $svea_checkout_module['Gui']['Snippet'] ) ? $svea_checkout_module['Gui']['Snippet'] : esc_html__( 'Could not load Svea Checkout', 'svea-checkout-for-woocommerce' ); // phpcs:ignore
	}

	/**
	 * Is the current checkout using svea?
	 *
	 * @return bool
	 */
	public function is_svea() {
		if ( ! WC()->payment_gateways() ) {
			return false;
		}

		$is_svea = false;
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		// Is Svea checkout available?
		if (
			isset( $available_gateways[ WC_Gateway_Svea_Checkout::GATEWAY_ID ] ) &&
			$available_gateways[ WC_Gateway_Svea_Checkout::GATEWAY_ID ]->is_available()
		) {
			$chosen_payment_method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';

			if ( $chosen_payment_method ) {
				if ( $chosen_payment_method === WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
					// User has actually chosen Svea Checkout
					$is_svea = true;
				}
			} else if ( Helper::array_key_first( $available_gateways ) === WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
				// User hasn't chosen but the first option is Svea checkout
				$is_svea = true;
			}
		}

		return $is_svea;
	}

	/**
	 * If the customer chose Svea Checkout as payment method, we'll change the whole template
	 *
	 * @param string $template      Template path
	 * @param string $template_name Name of template
	 * @return string
	 */
	public function maybe_override_checkout_template( $template, $template_name ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $template;
		}

		if ( $template_name === 'checkout/form-checkout.php' && $this->is_svea() ) {
			// Look for template in theme
			$template = locate_template( 'woocommerce/svea-checkout.php' );

			if ( ! $template ) {
				$template = SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR . '/templates/svea-checkout.php';
			}
		}

		return $template;
	}

	/**
	 * Display iframe on order received page
	 *
	 * @param int $order_id ID of the order being displayed
	 * @return void
	 */
	public function display_thank_you_box( $order_id ) {
		$wc_order = wc_get_order( $order_id );

		if ( ! $wc_order ) {
			return;
		}

		if ( $wc_order->get_payment_method() !== WC_Gateway_Svea_Checkout::GATEWAY_ID ) {
			return;
		}

		$svea_order_id = $wc_order->get_meta( '_svea_co_order_id' );

		if ( ! $svea_order_id ) {
			return;
		}

		$svea_order_id = absint( $svea_order_id );

		// Load the Svea_Order to make sure the checkout client goes to the correct country
		$svea_order = new Svea_Checkout();
		$response = $svea_order->get( $svea_order_id );
		?>
		<div class="wc-svea-checkout-thank-you-box">
			<?php echo $response['Gui']['Snippet']; // phpcs:ignore ?>
		</div>
		<?php
	}

	/**
	 * Update the session data for later use
	 *
	 * @return void
	 */
	public function update_order_nshift_information() {
		if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( $_REQUEST['security'], 'update-sco-order-nshift-information' ) ) {
			echo 'Nonce fail';
			exit;
		}

		if ( ! isset( $_POST['price'], $_POST['name'] ) ) {
			echo 'Missing params';
			exit;
		}

		// Well verify from Svea later
		WC()->session->set( 'sco_nshift_name', sanitize_text_field( $_POST['name'] ) );
		WC()->session->set( 'sco_nshift_price', sanitize_text_field( $_POST['price'] ) );

		// Clear the shipping cache
		$packages = WC()->cart->get_shipping_packages();

		foreach ( $packages as $key => $_value ) {
			unset( WC()->session->{ 'shipping_for_package_' . $key } );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$svea_checkout = new Svea_Checkout();
		$svea_checkout->update();

		$fragments = $this->get_cart_fragments();

		wp_send_json(
			[
				'fragments' => $fragments,
			]
		);
	}

	/**
	 * Update order and refresh the Svea Checkout snippet
	 *
	 * @return void
	 */
	public function refresh_sco_snippet() {
		// Use same name as WooCommerce to avoid conflicts
		check_ajax_referer( 'update-order-review', 'security' );

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		if ( WC()->cart->is_empty() && ! is_customize_preview() && apply_filters( 'woocommerce_checkout_update_order_review_expired', true ) ) {
			wp_send_json(
				[
					'fragments' => apply_filters(
						'woocommerce_update_order_review_fragments',
						[
							'form.woocommerce-checkout' => wc_print_notice(
								esc_html__( 'Sorry, your session has expired.', 'woocommerce' ) . ' <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" class="wc-backward">' . esc_html__( 'Return to shop', 'woocommerce' ) . '</a>',
								'error',
								[],
								true
							),
						]
					),
				]
			);
		}

		do_action( 'woocommerce_checkout_update_order_review', isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		$posted_shipping_methods = isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : [];

		// Check if we're switching to or from nShift before any other updates
		$nshift_updated = false;

		if ( ! empty( $posted_shipping_methods ) && $chosen_shipping_methods !== $posted_shipping_methods ) {
			// If switching to nShift we'll force an update
			if ( strpos( current( $posted_shipping_methods ), WC_Shipping_Svea_Nshift::METHOD_ID ) !== false ) {
				$nshift_updated = true;

			} else if ( is_array( $chosen_shipping_methods ) && strpos( current( $chosen_shipping_methods ), WC_Shipping_Svea_Nshift::METHOD_ID ) !== false ) {
				// Check if we're going away from nShift
				$nshift_updated = true;
			}
		}

		if ( is_array( $posted_shipping_methods ) ) {
			foreach ( $posted_shipping_methods as $i => $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				$chosen_shipping_methods[ $i ] = $value;
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		do_action( 'woocommerce_sco_session_data' );

		$country_changed = ( WC()->customer->get_billing_country() !== $_POST['billing_country'] ?: '' ) ? true : false;

		$props = [
			'billing_country'  => isset( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : '',
			'shipping_country' => isset( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : '',
		];

		// "••••" would be a known customer and we'll use the postcode from the checkout instead
		if ( isset( $_POST['billing_postcode'] ) && $_POST['billing_postcode'] !== '••••' ) {
			$props['billing_postcode'] = sanitize_text_field( $_POST['billing_postcode'] );
			$props['shipping_postcode'] = sanitize_text_field( $_POST['billing_postcode'] );
		}

		WC()->customer->set_props( $props );

		// Fetch the current checkout
		$svea_checkout = new Svea_Checkout();
		$svea_checkout_module = $svea_checkout->get();

		// Update customer information based on given information
		if ( isset( $svea_checkout_module['BillingAddress'] ) ) {
			$billing_info = $svea_checkout_module['BillingAddress'];
			$shipping_info = $svea_checkout_module['ShippingAddress'];

			// Ensure the checkout doesn't get overwritten by old data still present in the checkout
			if ( isset( $props['billing_postcode'] ) ) {
				$billing_info['PostalCode'] = $props['billing_postcode'];
				$shipping_info['PostalCode'] = $props['billing_postcode'];
			}

			$billing_postcode = $billing_info['PostalCode'] ?? null;
			$shipping_postcode = $shipping_info['PostalCode'] ?? $billing_postcode;

			$billing_city = $billing_info['City'] ?? null;
			$shipping_city = $shipping_info['City'] ?? $billing_city;

			$billing_address_1 = $billing_info['StreetAddress'] ?? null;
			$shipping_address_1 = $shipping_info['StreetAddress'] ?? $billing_address_1;

			$billing_address_2 = $billing_info['CoAddress'] ?? null;
			$shipping_address_2 = $shipping_info['CoAddress'] ?? $billing_address_2;

			$is_company = isset( $svea_checkout_module['Customer']['IsCompany'] ) && $svea_checkout_module['Customer']['IsCompany'];

			// Update the information that is being provided inside the checkout
			WC()->customer->set_props(
				[
					'billing_postcode'   => $billing_postcode,
					'billing_city'       => $billing_city,
					'billing_address_1'  => $billing_address_1,
					'billing_address_2'  => $billing_address_2,
					'billing_company'    => $is_company ? $billing_info['FullName'] : '',
					'shipping_postcode'  => $shipping_postcode,
					'shipping_city'      => $shipping_city,
					'shipping_address_1' => $shipping_address_1,
					'shipping_address_2' => $shipping_address_2,
					'shipping_company'   => $is_company ? $shipping_info['FullName'] : '',
				]
			);
		}

		if ( isset( $_POST['billing_state'] ) ) {
			WC()->customer->set_billing_state( sanitize_text_field( $_POST['billing_state'] ) );
			WC()->customer->set_shipping_state( sanitize_text_field( $_POST['billing_state'] ) );
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		// Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		// Check if the current order needs to wither be created anew or updated
		if ( $svea_checkout->needs_new( $svea_checkout_module ) ) {
			$svea_checkout_module = $svea_checkout->create();
		} else if ( $svea_checkout->needs_update() || $nshift_updated ) {
			$svea_checkout_module = $svea_checkout->update();
		}

		// Get messages if reload checkout is not true
		$messages = '';

		if ( ! isset( WC()->session->reload_checkout ) ) {
			ob_start();
			wc_print_notices();
			$messages = ob_get_clean();
		}

		// If cart total is 0, reload the page, this will instead show the regular WooCommerce checkout
		$reload = isset( WC()->session->reload_checkout );

		if ( ! $reload && ! WC_Gateway_Svea_Checkout::is_zero_sum_enabled() ) {
			$reload = WC()->cart->total <= 0 ? true : false;
		}

		if ( ! $reload && ! in_array( WC_Gateway_Svea_Checkout::GATEWAY_ID, array_keys( WC()->payment_gateways()->get_available_payment_gateways() ), true ) ) {
			$reload = true;
		}

		$reload = apply_filters( 'woocommerce_sco_refresh_snippet_reload', $reload );

		$fragments = [];

		if ( $reload !== true ) {
			$fragments = $this->get_cart_fragments();
		}

		$current_id = WC()->session->get( 'sco_order_id' );

		// If force is present check the string, otherwise it's false
		$force = isset( $_POST['force'] ) ? wc_string_to_bool( $_POST['force'] ) : false;

		if (
			$force ||
			$country_changed ||
			$nshift_updated ||
			(
				isset( $svea_checkout_module['OrderId'] ) &&
				$current_id !== $svea_checkout_module['OrderId'] &&
				isset( $svea_checkout_module['Gui']['Snippet'] )
			)
		) {
			if ( isset( $svea_checkout_module['OrderId'] ) ) {
				WC_Gateway_Svea_Checkout::log( sprintf( 'Updating iframe for order %s, previous order %s', $svea_checkout_module['OrderId'], $current_id ) );
				WC()->session->set( 'sco_order_id', $svea_checkout_module['OrderId'] );
			}

			$fragments['#svea-checkout-iframe-container'] = $svea_checkout_module['Gui']['Snippet'];
		}

		wp_send_json(
			[
				'result'    => empty( $messages ) ? 'success' : 'failure',
				'messages'  => $messages,
				'reload'    => $reload,
				'fragments' => $fragments,
			]
		);
	}

	/**
	 * Get the cart fragments
	 *
	 * @return array
	 */
	public function get_cart_fragments() {
		// Get order review fragment
		$fragments = [];

		$template = apply_filters( 'woocommerce_sco_review_order_template', wc_locate_template( 'checkout/review-order.php' ) );

		ob_start();

		include $template;

		$fragments['.woocommerce-checkout-review-order'] = ob_get_clean();

		return $fragments;
	}

	/**
	 * This function includes the template for the Svea Checkout
	 *
	 * @deprecated 2.0.0 Use the regular [woocommerce_checkout] instead
	 * @return string Template to display the checkout
	 */
	public function display_svea_checkout_page() {
		_deprecated_function( __FUNCTION__, '2.0.0', esc_html__( 'Use [woocommerce_checkout] shortcode', 'svea-checkout-for-woocommerce' ) );

		ob_start();
		echo apply_filters( 'the_content', '[woocommerce_checkout]' ); // phpcs:ignore
		return ob_get_clean();
	}

	/**
	 * This function returns the Svea Checkout module.
	 *
	 * @param \WC_Cart $cart WooCommerce cart
	 * @return array|string The Svea Checkout snippet
	 */
	public static function get_svea_checkout_module() {
		$svea_order = new Svea_Checkout();

		return $svea_order->get_module();
	}
}
