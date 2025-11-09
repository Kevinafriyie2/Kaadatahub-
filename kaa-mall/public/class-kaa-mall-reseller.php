<?php

class Kaa_Mall_Reseller {

    public function __construct() {
        add_shortcode( 'kaa_reseller_portal', array( $this, 'render_reseller_portal' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_kaa_mall_save_reseller_prices', array( $this, 'save_reseller_prices' ) );
    }

    public function enqueue_scripts() {
        if ( is_page() || is_single() ) { // Basic check to see if we are on a page that might contain the shortcode
            global $post;
            if ( has_shortcode( $post->post_content, 'kaa_reseller_portal' ) ) {
                $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-reseller.js';
                $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/kaa-mall-reseller.js' );
                wp_enqueue_script( 'kaa-mall-reseller', $js_file_url, array( 'jquery' ), $js_version, true );
                wp_localize_script( 'kaa-mall-reseller', 'kaa_mall_reseller_params', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'kaa_mall_reseller_nonce' ),
                ) );
            }
        }
    }

    public function save_reseller_prices() {
        check_ajax_referer( 'kaa_mall_reseller_nonce', 'nonce' );

        if ( ! current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $network = sanitize_text_field( $_POST['network'] );
        $prices = $_POST['prices'];
        $reseller_id = get_current_user_id();

        $reseller_prices = array();
        foreach( $prices as $bundle => $price ) {
            $reseller_prices[ sanitize_text_field( $bundle ) ] = floatval( $price );
        }

        update_user_meta( $reseller_id, '_kaa_mall_reseller_prices_' . $network, $reseller_prices );

        wp_send_json_success( array( 'message' => 'Prices updated successfully.' ) );
    }

    private function get_admin_prices( $network ) {
        $prices_str = get_option( 'kaa_mall_' . $network . '_prices' );
        $prices = array();
        if ( ! empty( $prices_str ) ) {
            $lines = explode( "\n", $prices_str );
            foreach ( $lines as $line ) {
                $parts = explode( '=', $line );
                if ( count( $parts ) == 2 ) {
                    $prices[ trim( $parts[0] ) ] = floatval( trim( $parts[1] ) );
                }
            }
        }
        return $prices;
    }

    private function get_reseller_prices( $reseller_id, $network ) {
        $prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_' . $network, true );
        return empty( $prices ) ? array() : $prices;
    }

    private function get_profit_wallet_balance( $user_id ) {
        $balance = get_user_meta( $user_id, '_kaa_mall_reseller_profit_balance', true );
        return empty( $balance ) ? 0.00 : floatval( $balance );
    }

    public function render_reseller_portal() {
        if ( ! current_user_can( 'reseller' ) ) {
            return 'You do not have permission to view this page.';
        }

        $reseller_id = get_current_user_id();
        $profit_balance = $this->get_profit_wallet_balance( $reseller_id );

        ob_start();
        ?>
        <div class="kaa-mall-reseller-portal">
            <h2>Reseller Dashboard</h2>

            <div class="reseller-section">
                <h3>Your Profit Wallet</h3>
                <p>Your current profit balance is:</p>
                <p class="profit-balance" style="font-size: 2em; font-weight: bold;"><?php echo wc_price( $profit_balance ); ?></p>
            </div>

            <div class="reseller-section">
                <h3>Your Referral Link</h3>
                <p>Share this link with your customers. Your custom prices will be shown to anyone who visits this link.</p>
                <input type="text" value="<?php echo esc_url( add_query_arg( 'ref', get_current_user_id(), get_option( 'kaa_mall_user_portal_url' ) ) ); ?>" readonly style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="reseller-section">
                <h3>Set Your Bundle Prices</h3>
                <p>Set your own selling price for each bundle. Your profit is the difference between your price and our base price.</p>

                <div class="network-tabs">
                    <button class="tab-link active" data-network="mtn">MTN</button>
                    <button class="tab-link" data-network="airteltigo">AirtelTigo</button>
                    <button class="tab-link" data-network="vodafone">Vodafone</button>
                </div>

                <?php foreach ( array('mtn', 'airteltigo', 'vodafone') as $network ) : ?>
                    <div id="reseller-prices-<?php echo $network; ?>" class="network-tab-content <?php echo $network === 'mtn' ? 'active' : ''; ?>">
                        <form class="reseller-prices-form" data-network="<?php echo $network; ?>">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Bundle</th>
                                        <th>Base Price</th>
                                        <th>Your Selling Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $admin_prices = $this->get_admin_prices( $network );
                                    $reseller_prices = $this->get_reseller_prices( $reseller_id, $network );
                                    foreach ( $admin_prices as $bundle => $price ) :
                                        $reseller_price = isset( $reseller_prices[ $bundle ] ) ? $reseller_prices[ $bundle ] : $price;
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $bundle ); ?></td>
                                            <td><?php echo wc_price( $price ); ?></td>
                                            <td><input type="number" name="prices[<?php echo esc_attr( $bundle ); ?>]" value="<?php echo esc_attr( $reseller_price ); ?>" step="0.01" min="<?php echo esc_attr( $price ); ?>"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit">Save <?php echo ucfirst($network); ?> Prices</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="reseller-section">
                <h3>Your Sales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Your Profit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
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
                                <td><?php echo wc_price( $order->get_meta( '_reseller_profit' ) ); ?></td>
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
