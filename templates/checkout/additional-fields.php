<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$checkout = WC()->checkout();
?>
<div class="woocommerce-additional-fields">
        
    <?php wc_get_template( 'checkout/terms.php' ); ?>
    
    <?php do_action( 'woocommerce_review_order_before_submit' ); ?>
    
    <?php /* No need for actual button but we'll keep the hooks */ ?>

    <?php do_action( 'woocommerce_review_order_after_submit' ); ?>

    <?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
        <div class="woocommerce-account-fields">
            <?php if ( ! $checkout->is_registration_required() ) : ?>

                <p class="form-row form-row-wide create-account">
                    <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                        <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" /> <span><?php esc_html_e( 'Create an account?', 'woocommerce' ); ?></span>
                    </label>
                </p>

            <?php endif; ?>

            <?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

            <?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>

                <div class="create-account">
                    <?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
                        <?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
                    <?php endforeach; ?>
                    <div class="clear"></div>
                </div>

            <?php endif; ?>

            <?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
        </div>
    <?php endif; ?>

    <?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php if ( ! WC()->cart->needs_shipping() || wc_ship_to_billing_address_only() ) : ?>

		<h3><?php esc_html_e( 'Additional information', 'woocommerce' ); ?></h3>

	<?php endif; ?>

	<div class="woocommerce-additional-fields__field-wrapper">
		<?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) : ?>
			<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
		<?php endforeach; ?>
	</div>

    <?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>

    <?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

    <?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>
            
    <?php
        $fields = $checkout->get_checkout_fields( 'billing' );
        $shipping_fields = $checkout->get_checkout_fields( 'shipping' );
        
        if ( ! empty( $billing_fields ) ) {
            $fields = array_merge( $fields, $billing_fields );
        }
        if ( ! empty( $shipping_fields ) ) {
            $fields = array_merge( $fields, $shipping_fields );
        }

        foreach ( $fields as $key => $field ) {
            woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
        }
    ?>
    <?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>

    <?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>