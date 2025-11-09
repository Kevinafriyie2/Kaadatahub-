<?php

class Kaa_Mall_Reseller {

    public function __construct() {
        add_shortcode( 'kaa_reseller_portal', array( $this, 'render_reseller_portal' ) );
    }

    public function render_reseller_portal() {
        if ( ! current_user_can( 'reseller' ) ) {
            return 'You do not have permission to view this page.';
        }

        ob_start();
        ?>
        <div class="kaa-mall-reseller-portal">
            <h2>Reseller Dashboard</h2>
            <div class="reseller-section">
                <h3>Your Referral Link</h3>
                <p>Share this link with your customers to earn commissions on their purchases.</p>
                <input type="text" value="<?php echo esc_url( add_query_arg( 'ref', get_current_user_id(), get_option( 'kaa_mall_user_portal_url' ) ) ); ?>" readonly>
            </div>
            <div class="reseller-section">
                <h3>Your Sales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Commission</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $reseller_id = get_current_user_id();
                        $args = array(
                            'post_type' => 'shop_order',
                            'post_status' => 'any',
                            'meta_key' => '_reseller_id',
                            'meta_value' => $reseller_id,
                            'numberposts' => -1,
                        );
                        $orders = get_posts( $args );

                        foreach ( $orders as $order_post ) {
                            $order = wc_get_order( $order_post->ID );
                            if ( ! $order ) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><?php echo $order->get_id(); ?></td>
                                <td><?php echo $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ); ?></td>
                                <td><?php echo $order->get_formatted_order_total(); ?></td>
                                <td><?php echo wc_price( $order->get_meta( '_commission_amount' ) ); ?></td>
                                <td><?php echo wc_get_order_status_name( $order->get_status() ); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
