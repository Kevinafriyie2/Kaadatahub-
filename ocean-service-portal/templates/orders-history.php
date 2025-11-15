<?php
// Orders history template
?>

<form method="get">
    <input type="text" name="search" placeholder="<?php echo esc_attr__( 'Search by Beneficiary or Order ID', 'ocean-service-portal' ); ?>" value="<?php echo isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : ''; ?>">
    <input type="submit" value="<?php echo esc_attr__( 'Search', 'ocean-service-portal' ); ?>">
</form>

<table class="widefat">
    <thead>
        <tr>
            <th><?php echo esc_html__( 'Order ID', 'ocean-service-portal' ); ?></th>
            <th><?php echo esc_html__( 'Beneficiary', 'ocean-service-portal' ); ?></th>
            <th><?php echo esc_html__( 'Status', 'ocean-service-portal' ); ?></th>
            <th><?php echo esc_html__( 'Date + Time', 'ocean-service-portal' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array_keys( wc_get_order_statuses() ),
        );

        if ( ! current_user_can( 'manage_options' ) ) {
            $args['customer_id'] = get_current_user_id();
        }

        if ( isset( $_GET['search'] ) && ! empty( $_GET['search'] ) ) {
            $search = sanitize_text_field( $_GET['search'] );
            if ( is_numeric( $search ) ) {
                $args['post__in'] = array( $search );
            } else {
                $args['meta_query'] = array(
                    array(
                        'key'     => '_kaa_beneficiary_number',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                );
            }
        }

        $orders = wc_get_orders( $args );

        if ( $orders ) {
            foreach ( $orders as $order ) {
                ?>
                <tr>
                    <td><?php echo $order->get_order_number(); ?></td>
                    <td><?php echo $order->get_meta( '_kaa_beneficiary_number' ); ?></td>
                    <td><?php echo wc_get_order_status_name( $order->get_status() ); ?></td>
                    <td><?php echo $order->get_date_created()->format( 'Y-m-d H:i:s' ); ?></td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="4"><?php echo esc_html__( 'No orders found.', 'ocean-service-portal' ); ?></td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
