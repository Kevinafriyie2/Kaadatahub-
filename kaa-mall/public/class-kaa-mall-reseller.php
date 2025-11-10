<?php

class Kaa_Mall_Reseller {

    public function __construct() {
        add_shortcode( 'kaa_reseller_portal', array( $this, 'render_reseller_portal' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_kaa_mall_save_reseller_prices', array( $this, 'save_reseller_prices' ) );
        add_action( 'wp_ajax_kaa_mall_save_shop_name', array( $this, 'save_shop_name' ) );
        add_action( 'wp_ajax_kaa_mall_request_withdrawal', array( $this, 'request_withdrawal' ) );
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

    public function save_shop_name() {
        check_ajax_referer( 'kaa_mall_reseller_nonce', 'nonce' );

        if ( ! current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $shop_name = sanitize_title( $_POST['shop_name'] );
        $reseller_id = get_current_user_id();

        // Check if shop name is unique
        $existing_user = get_users( array(
            'meta_key' => '_kaa_mall_shop_name',
            'meta_value' => $shop_name,
            'exclude' => array( $reseller_id ),
        ) );

        if ( ! empty( $existing_user ) ) {
            wp_send_json_error( array( 'message' => 'This shop name is already taken. Please choose another one.' ) );
        }

        update_user_meta( $reseller_id, '_kaa_mall_shop_name', $shop_name );

        $referral_link = esc_url( home_url( '/shop/' . $shop_name ) );

        wp_send_json_success( array(
            'message' => 'Shop name updated successfully.',
            'shop_name' => $shop_name,
            'referral_link' => $referral_link,
        ) );
    }

    public function request_withdrawal() {
        check_ajax_referer( 'kaa_mall_reseller_nonce', 'nonce' );

        if ( ! current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $amount = floatval( $_POST['amount'] );
        $payment_details = sanitize_textarea_field( $_POST['payment_details'] );
        $reseller_id = get_current_user_id();
        $profit_balance = $this->get_profit_wallet_balance( $reseller_id );

        if ( $amount <= 0 || $amount > $profit_balance ) {
            wp_send_json_error( array( 'message' => 'Invalid withdrawal amount.' ) );
        }

        // Create a new withdrawal request post
        $post_id = wp_insert_post( array(
            'post_title' => 'Withdrawal Request - ' . wc_price( $amount ),
            'post_type' => 'kaa_withdrawal',
            'post_status' => 'pending',
            'post_author' => $reseller_id,
        ) );

        if ( $post_id ) {
            update_post_meta( $post_id, '_withdrawal_amount', $amount );
            update_post_meta( $post_id, '_payment_details', $payment_details );

            // Deduct from profit wallet
            $new_profit_balance = $profit_balance - $amount;
            update_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', $new_profit_balance );

            wp_send_json_success( array( 'message' => 'Withdrawal request submitted successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Could not submit withdrawal request.' ) );
        }
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

        echo Kaa_Mall_Portal_Header::render();
        ?>
        <style>
            .kaa-mall-reseller-portal {
                padding: 20px;
                max-width: 1200px;
                margin: 0 auto;
                color: #333;
            }
            .reseller-header h2 {
                font-size: 2.5em;
                margin-bottom: 20px;
                color: #1a1a1a;
            }
            .reseller-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
            .reseller-card {
                background: #fff;
                border-radius: 12px;
                padding: 25px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.08);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .reseller-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            }
            .reseller-card h3 {
                font-size: 1.4em;
                margin-top: 0;
                margin-bottom: 15px;
                color: #1a1a1a;
            }
            .profit-balance {
                font-size: 2.5em;
                font-weight: bold;
                color: #4a90e2;
            }
            .reseller-card input[type="text"], .reseller-card input[type="number"] {
                width: 100%;
                padding: 10px;
                border: 1px solid #e6e6e6;
                border-radius: 8px;
            }
            .reseller-card table {
                width: 100%;
                border-collapse: collapse;
            }
            .reseller-card th, .reseller-card td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #e6e6e6;
            }
            .reseller-card button {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background-color: #4a90e2;
                color: #fff;
                font-weight: bold;
                cursor: pointer;
            }
        </style>

        <div class="kaa-mall-reseller-portal">
            <div class="reseller-header">
                <h2>Reseller Dashboard</h2>
            </div>

            <div class="reseller-grid">
                <div class="reseller-card">
                    <h3>Your Profit Wallet</h3>
                    <p>Your current profit balance is:</p>
                    <p class="profit-balance"><?php echo wc_price( $profit_balance ); ?></p>
                </div>

                <div class="reseller-card">
                    <h3>Request Withdrawal</h3>
                    <form id="kaa-mall-withdrawal-form">
                        <p>
                            <label for="withdrawal_amount">Amount</label>
                            <input type="number" name="amount" id="withdrawal_amount" step="0.01" min="1" max="<?php echo esc_attr($profit_balance); ?>" required>
                        </p>
                        <p>
                            <label for="payment_details">Payment Details (e.g., Mobile Money Number)</label>
                            <textarea name="payment_details" id="payment_details" rows="3" required></textarea>
                        </p>
                        <button type="submit">Request Withdrawal</button>
                    </form>
                </div>

                <div class="reseller-card">
                    <h3>Your Shop Name</h3>
                    <p>Set your unique shop name.</p>
                    <form id="kaa-mall-shop-name-form">
                        <input type="text" name="shop_name" value="<?php echo esc_attr( get_user_meta( $reseller_id, '_kaa_mall_shop_name', true ) ); ?>" placeholder="e.g., my-data-shop">
                        <button type="submit">Save Shop Name</button>
                    </form>
                </div>

                <div class="reseller-card">
                    <h3>Your Referral Link</h3>
                    <p>Share this link with your customers.</p>
                    <input type="text" value="<?php echo esc_url( home_url( '/shop/' . get_user_meta( $reseller_id, '_kaa_mall_shop_name', true ) ) ); ?>" readonly>
                </div>

                <div class="reseller-card" style="grid-column: 1 / -1;">
                    <h3>Set Your Bundle Prices</h3>
                    <p>Set your own selling price for each bundle.</p>

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

                <div class="reseller-card" style="grid-column: 1 / -1;">
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
