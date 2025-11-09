<?php

class Kaa_Mall_Public {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_shortcode( 'kaa_user_portal', array( $this, 'render_user_portal' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_kaa_mall_verify_paystack_transaction', array( $this, 'verify_paystack_transaction' ) );
        add_action( 'wp_ajax_kaa_mall_purchase_bundle', array( $this, 'purchase_bundle' ) );
        add_action( 'wp_ajax_kaa_mall_get_bundle_prices', array( $this, 'get_bundle_prices' ) );
        add_action( 'wp_ajax_kaa_mall_afa_registration', array( $this, 'afa_registration' ) );
        add_action( 'wp_ajax_kaa_mall_get_recent_orders', array( $this, 'get_recent_orders' ) );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'kaa-mall-public', plugin_dir_url( __FILE__ ) . 'css/kaa-mall-public.css', array(), $this->version, 'all' );
        wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), $this->version, false );
        wp_enqueue_script( 'kaa-mall-public', plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js', array( 'jquery', 'paystack-inline' ), $this->version, false );

        $user = wp_get_current_user();
        wp_localize_script( 'kaa-mall-public', 'kaa_mall_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'paystack_public_key' => get_option( 'kaa_mall_paystack_public_key' ),
            'user_email' => $user->user_email,
        ) );
    }

    private function get_wallet_balance( $user_id ) {
        $balance = get_user_meta( $user_id, '_kaa_mall_wallet_balance', true );
        return empty( $balance ) ? 0.00 : floatval( $balance );
    }

    private function get_product_by_name( $product_name ) {
        $product_id = post_exists( $product_name, '', '', 'product' );
        if ( $product_id ) {
            return wc_get_product( $product_id );
        }
        return null;
    }

    public function get_bundle_prices() {
        $network = sanitize_text_field( $_POST['network'] );
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
        wp_send_json_success( $prices );
    }

    public function verify_paystack_transaction() {
        $reference = sanitize_text_field( $_POST['reference'] );
        $amount = floatval( $_POST['amount'] );

        $secret_key = get_option( 'kaa_mall_paystack_secret_key' );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: Bearer $secret_key",
                "cache-control: no-cache"
            ],
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            wp_send_json_error( array( 'message' => 'An error occurred while verifying the transaction.' ) );
        }

        $result = json_decode($response);
        if ( 'success' === $result->data->status ) {
            $user_id = get_current_user_id();
            $current_balance = $this->get_wallet_balance( $user_id );
            $new_balance = $current_balance + $amount;
            update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

            $product = $this->get_product_by_name( 'Wallet Top-up' );
            if ( $product ) {
                $order = wc_create_order();
                $order->set_customer_id( $user_id );
                $order->add_product( $product, 1, array( 'subtotal' => $amount, 'total' => $amount ) );
                $order->set_total( $amount );
                $order->set_status( 'completed' );
                $order->save();
            }

            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => 'Transaction verification failed.' ) );
        }
    }

    public function purchase_bundle() {
        $network = sanitize_text_field( $_POST['network'] );
        $bundle = sanitize_text_field( $_POST['bundle'] );
        $phone_number = sanitize_text_field( $_POST['phone_number'] );

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

        if ( ! isset( $prices[ $bundle ] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid bundle selected.' ) );
        }
        $bundle_price = $prices[ $bundle ];

        $user_id = get_current_user_id();
        $wallet_balance = $this->get_wallet_balance( $user_id );

        if ( $wallet_balance < $bundle_price ) {
            wp_send_json_error( array( 'message' => 'Insufficient wallet balance.' ) );
        }

        $new_balance = $wallet_balance - $bundle_price;
        update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

        $product = $this->get_product_by_name( 'Data Bundle' );
        if ( $product ) {
            $order = wc_create_order();
            $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $bundle_price, 'total' => $bundle_price ) );
            $order->set_total( $bundle_price );
            $order->set_status( 'processing' ); // Or your preferred status
            $order->update_meta_data( 'Network', $network );
            $order->update_meta_data( 'Bundle', $bundle );
            $order->update_meta_data( 'Phone Number', $phone_number );
            $order->save();
        }

        wp_send_json_success();
    }

    public function afa_registration() {
        $full_name = sanitize_text_field( $_POST['full_name'] );
        $phone_number = sanitize_text_field( $_POST['phone_number'] );
        $location = sanitize_text_field( $_POST['location'] );
        $ghana_card = sanitize_text_field( $_POST['ghana_card'] );

        $afa_fee = 13.00;

        $user_id = get_current_user_id();
        $wallet_balance = $this->get_wallet_balance( $user_id );

        if ( $wallet_balance < $afa_fee ) {
            wp_send_json_error( array( 'message' => 'Insufficient wallet balance.' ) );
        }

        $new_balance = $wallet_balance - $afa_fee;
        update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

        $product = $this->get_product_by_name( 'AFA Registration' );
        if ( $product ) {
            $order = wc_create_order();
            $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $afa_fee, 'total' => $afa_fee ) );
            $order->set_total( $afa_fee );
            $order->set_status( 'processing' ); // Or your preferred status
            $order->update_meta_data( 'Full Name', $full_name );
            $order->update_meta_data( 'Phone Number', $phone_number );
            $order->update_meta_data( 'Location', $location );
            $order->update_meta_data( 'Ghana Card', $ghana_card );
            $order->save();
        }

        wp_send_json_success();
    }

    public function get_recent_orders() {
        $user_id = get_current_user_id();
        $args = array(
            'customer_id' => $user_id,
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        $orders = wc_get_orders( $args );

        $data = array();
        foreach ( $orders as $order ) {
            $data[] = array(
                'reference' => $order->get_id(),
                'date' => $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ),
                'network' => $order->get_meta( 'Network' ),
                'bundle' => $order->get_meta( 'Bundle' ),
                'phone' => $order->get_meta( 'Phone Number' ),
                'amount' => $order->get_total(),
                'status' => wc_get_order_status_name( $order->get_status() ),
            );
        }

        wp_send_json_success( $data );
    }

    public function render_user_portal() {
        if ( ! is_user_logged_in() ) {
            return 'Please log in to access the portal.';
        }

        $user_id = get_current_user_id();
        $wallet_balance = $this->get_wallet_balance( $user_id );

        ob_start();
        ?>
        <div class="kaa-mall-portal">
            <div class="kaa-mall-header">
                <h2>Welcome to Kaa Mall Portal</h2>
            </div>
            <div class="kaa-mall-cards">
                <div class="kaa-mall-card wallet-balance">
                    <h3>Your Wallet Balance</h3>
                    <p>₵<?php echo number_format( $wallet_balance, 2 ); ?></p>
                    <form id="kaa-mall-topup-form">
                        <input type="number" step="0.01" min="1.00" name="topup_amount" placeholder="Amount" required>
                        <button type="submit" class="top-up-wallet">Top-up Wallet</button>
                    </form>
                </div>
                <div class="kaa-mall-card data-bundles">
                    <h3>Buy Data Bundles</h3>
                    <form id="kaa-mall-bundle-form">
                        <select name="network" required>
                            <option value="">Select Network</option>
                            <option value="mtn">MTN</option>
                            <option value="airteltigo">AirtelTigo</option>
                            <option value="vodafone">Vodafone</option>
                        </select>
                        <select name="bundle" required>
                            <!-- Options will be populated by JS -->
                        </select>
                        <input type="tel" name="phone_number" placeholder="Phone Number" required>
                        <button type="submit">Purchase Bundle</button>
                    </form>
                </div>
                <div class="kaa-mall-card afa-registration">
                    <h3>AFA Registration (₵13.00)</h3>
                    <form id="kaa-mall-afa-form">
                        <input type="text" name="full_name" placeholder="Full Name" required>
                        <input type="tel" name="phone_number" placeholder="Phone Number" required>
                        <input type="text" name="location" placeholder="Location" required>
                        <input type="text" name="ghana_card" placeholder="Ghana Card Number" required>
                        <button type="submit">Register for AFA</button>
                    </form>
                </div>
            </div>
            <div class="recent-orders">
                <h3>Recent Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Network</th>
                            <th>Bundle</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Orders will be populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
