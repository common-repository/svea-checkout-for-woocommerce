<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

use Svea_Checkout_For_Woocommerce\Helper;
use Svea_Checkout_For_Woocommerce\Template_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checkout = WC()->checkout();
do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>
<section class="wc-svea-checkout-page">
    <div class="wc-svea-checkout-page-inner">
        <form name="checkout" method="post" class="svea-checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data"
			novalidate="novalidate">

            <div class="order-country-wrapper">
                <?php

                // Billing country selector
                woocommerce_form_field(
                    'billing_country',
                    [
                        'label'       => esc_html__( 'Country', 'svea-checkout-for-woocommerce' ),
                        'description' => '',
                        'required'    => true,
                        'type'        => 'country',
                    ],
                    WC()->customer->get_billing_country()
                );

                // Billing state selector
                woocommerce_form_field(
                    'billing_state',
                    [
                        'type' => 'state',
                        'label' => esc_html__( 'State', 'svea-checkout-for-woocommerce' ),
                        'required' => true,
                    ],
                    WC()->customer->get_billing_state(),
                );

                ?>
            </div>

            <div class="order-review-wrapper">

                <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
    
                <h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
    
                <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
    
                <div id="order_review" class="svea-order-review-wrapper">
                    <?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
    
                    <div class="woocommerce-checkout-review-order">
                        <?php do_action('woocommerce_checkout_order_review'); ?>
                    </div>
    
                    <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

                    <?php include Helper::sco_locate_template( 'checkout/additional-fields.php' ); ?>

                    <?php if ( count( WC()->payment_gateways->get_available_payment_gateways() ) > 1 ) : ?>
                        <a id="sco-change-payment" class="button" href="#"><?php esc_html_e( 'Other payment options', 'svea-checkout-for-woocommerce' ) ?></a>
                    <?php endif; ?>
                </div>

                <?php do_action( 'woocommerce_checkout_after_order_review_wrapper' ); ?>
                
                <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
            </div>


            <div class="order-checkout-wrapper" id="customer_details">
                <input id="billing_postcode" type="hidden" name="billing_postcode" value="<?php echo WC()->customer->get_billing_postcode(); ?>">

                <div class="wc-svea-checkout-checkout-module">
                    <div id="svea-checkout-iframe-container">
                        <div class="svea-skeleton-loader">
                            <div class="svea-skeleton-loader__heading"></div>
                            <div class="svea-skeleton-loader__input"></div>
                            <div class="svea-skeleton-loader__input"></div>
                            <div class="svea-skeleton-loader__button"></div>
                        </div>
                        <?php Template_Handler::get_svea_snippet(); ?>
                    </div>
                </div>
            </div>

			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
        </form>
    </div>
</section>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
<?php do_action( 'woocommerce_sco_after_checkout_page' );