<?php if ( $is_awaiting_status ) : ?>
    <p>
        <strong><?php esc_html_e( 'Awaiting status', 'svea-checkout-for-woocommerce' ); ?></strong><br>
        <?php esc_html_e( 'This order is awaiting the final status from Svea and will update itself automatically. You do not need to do anything with this order but if you would like to check the status right now you can do so by clicking the button below', 'svea-checkout-for-woocommerce' ); ?>
    </p>
    <a href="#" id="sco-check-svea-status" data-loading-text="<?php esc_html_e( 'Fetching status', 'svea-checkout-for-woocommerce' ); ?>..." class="button"><?php esc_html_e( 'Check status now', 'svea-checkout-for-woocommerce' ); ?></a>
    <hr>
<?php endif; ?>

<?php if ( ! empty( $fields ) ) : ?>
    <p>
        <strong><?php esc_html_e( 'Order information', 'svea-checkout-for-woocommerce' ); ?></strong>
    </p>
    <table style="width:100%; font-size: 80%;">
        <?php foreach ( $fields as $key => $val ) : ?>
            <tr>
                <td><?php echo esc_html( $key ); ?>:</td>
                <td><i><?php echo esc_html( $val ); ?></i></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if ( ! empty( $nshift_fields ) ) : ?>
    <p>
        <strong><?php echo esc_html_e( 'nShift data', 'svea-checkout-for-woocommerce'); ?></strong>
    </p>
    <pre>
        <?php echo esc_html( wp_json_encode( $nshift_fields, JSON_PRETTY_PRINT ) ); ?>
    </pre>
<?php endif; ?>