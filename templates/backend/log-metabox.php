<p>
    <i>This is visible while logging is active</i>
</p>

<?php if ( ! empty( $fields ) ) : ?>
<table style="width:100%; font-size: 80%;">
    <?php foreach ( $fields as $key => $val ) : ?>
        <tr>
            <td><?php echo esc_html( $key ); ?>:</td>
            <td><i><?php echo esc_html( $val ); ?></i></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>