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
        add_action( 'wp_ajax_nopriv_kaa_mall_verify_paystack_transaction', array( $this, 'verify_paystack_transaction' ) );
        add_action( 'wp_ajax_kaa_mall_purchase_bundle', array( $this, 'purchase_bundle' ) );
        add_action( 'wp_ajax_kaa_mall_purchase_bundle_paystack', array( $this, 'purchase_bundle_paystack' ) );
        add_action( 'wp_ajax_kaa_mall_get_bundle_prices', array( $this, 'get_bundle_prices' ) );
        add_action( 'wp_ajax_kaa_mall_afa_registration', array( $this, 'afa_registration' ) );
        add_action( 'wp_ajax_kaa_mall_get_recent_orders', array( $this, 'get_recent_orders' ) );
        add_action( 'wp_ajax_kaa_mall_get_wallet_balance', array( $this, 'ajax_get_wallet_balance' ) );
    }

    public function enqueue_scripts() {
        $js_file_path = plugin_dir_path( __FILE__ ) . 'js/kaa-mall-public.js';
        $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js';
        $js_version = filemtime( $js_file_path );

        wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), $this->version, false );
        wp_enqueue_script( 'kaa-mall-public', $js_file_url, array( 'jquery', 'paystack-inline' ), $js_version, false );

        $user = wp_get_current_user();
        wp_localize_script( 'kaa-mall-public', 'kaa_mall_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'paystack_public_key' => get_option( 'kaa_mall_paystack_public_key' ),
            'user_email' => $user->user_email,
            'nonce' => wp_create_nonce( 'kaa_mall_nonce' ),
        ) );
    }

    private function get_wallet_balance( $user_id ) {
        $balance = get_user_meta( $user_id, '_kaa_mall_wallet_balance', true );
        return empty( $balance ) ? 0.00 : floatval( $balance );
    }

    public function ajax_get_wallet_balance() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        $user_id = get_current_user_id();
        $balance = $this->get_wallet_balance( $user_id );
        wp_send_json_success( number_format( $balance, 2 ) );
    }

    private function get_product_by_name( $product_name ) {
        $product = get_page_by_title( $product_name, OBJECT, 'product' );
        if ( $product ) {
            return wc_get_product( $product->ID );
        }
        return null;
    }

    public function get_bundle_prices() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
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
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        $reference = sanitize_text_field( $_POST['reference'] );

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
            $amount = $result->data->amount / 100; // Convert from pesewas
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
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
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

    public function purchase_bundle_paystack() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        $network = sanitize_text_field( $_POST['network'] );
        $bundle = sanitize_text_field( $_POST['bundle'] );
        $phone_number = sanitize_text_field( $_POST['phone_number'] );
        $reference = sanitize_text_field( $_POST['reference'] );

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
        } else {
            wp_send_json_error( array( 'message' => 'Transaction verification failed.' ) );
        }
    }

    public function afa_registration() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
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
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
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

        ob_start();
        ?>
        <style>
            :root {
                --primary-color: #ffc107;
                --secondary-color: #8a2be2;
                --background-color: #121212;
                --card-background-color: #1e1e1e;
                --text-color: #e0e0e0;
                --heading-color: #ffffff;
                --border-color: #333333;
                --shadow-color: rgba(0, 0, 0, 0.5);
            }

            .kaa-mall-portal {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                background-color: var(--background-color);
                color: var(--text-color);
                padding: 20px;
                max-width: 500px;
                margin: 0 auto;
            }

            .kaa-mall-header {
                background: linear-gradient(135deg, #ff8c00, #ffc107);
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 12px;
                margin-bottom: 20px;
            }

            .kaa-mall-header h1 {
                margin: 0;
                font-size: 1.8em;
            }

            .kaa-mall-header p {
                margin: 5px 0 0;
                font-size: 1em;
            }

            .kaa-mall-card {
                background: var(--card-background-color);
                border-radius: 12px;
                box-shadow: 0 5px 15px var(--shadow-color);
                padding: 20px;
                margin-bottom: 20px;
            }

            .wallet-balance {
                background: linear-gradient(135deg, #8a2be2, #4a00e0);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .wallet-balance-details span {
                display: block;
                color: white;
            }

            .wallet-balance-details .balance-amount {
                font-size: 2em;
                font-weight: bold;
            }

            .top-up-wallet-btn {
                background-color: rgba(255, 255, 255, 0.2);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
            }

            .data-bundles .network-tabs {
                display: flex;
                justify-content: space-around;
                margin-bottom: 20px;
            }

            .data-bundles .tab-link {
                background: none;
                border: none;
                color: var(--text-color);
                cursor: pointer;
                padding: 10px;
                font-size: 1em;
            }

            .data-bundles .tab-link.active {
                border-bottom: 2px solid var(--primary-color);
                color: var(--primary-color);
            }

            .network-tab-content {
                display: none;
            }

            .network-tab-content.active {
                display: block;
            }

            .bundle-form label {
                display: block;
                margin-bottom: 5px;
            }

            .bundle-form input,
            .bundle-form select,
            .bundle-form button {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                background-color: #2c2c2c;
                color: var(--text-color);
                font-size: 1em;
                box-sizing: border-box;
            }

            .bundle-form button {
                background-color: var(--primary-color);
                color: #121212;
                font-weight: bold;
                cursor: pointer;
            }

            .payment-method label {
                display: inline-block;
                margin-right: 20px;
            }

            .afa-registration {
                background: linear-gradient(135deg, #9b59b6, #8e44ad);
            }

            .afa-registration h3,
            .afa-registration p {
                color: white;
                text-align: center;
            }

            #kaa-mall-afa-form label {
                display: block;
                margin-bottom: 5px;
                color: white;
            }

            #kaa-mall-afa-form input {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: none;
                border-radius: 8px;
                background-color: rgba(255, 255, 255, 0.2);
                color: white;
                font-size: 1em;
                box-sizing: border-box;
            }

            #kaa-mall-afa-form button {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background-color: var(--primary-color);
                color: #121212;
                font-weight: bold;
                cursor: pointer;
            }

            .registration-fee {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
                color: white;
            }

            .purchase-history table {
                width: 100%;
                border-collapse: collapse;
            }

            .purchase-history th,
            .purchase-history td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid var(--border-color);
            }

            .purchase-history th {
                font-weight: bold;
            }

            .need-help {
                text-align: center;
            }

            .contact-admin-btn {
                display: inline-block;
                background-color: #25d366;
                color: white;
                padding: 10px 20px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: bold;
            }
        </style>
        <div class="kaa-mall-portal">
            <div class="kaa-mall-header">
                <h1>Data Bundle Store</h1>
                <p>Instant Data Top-up for All Networks</p>
            </div>

            <div class="kaa-mall-card wallet-balance">
                <div class="wallet-balance-details">
                    <span>Wallet Balance</span>
                    <span class="balance-amount"></span>
                </div>
                <button class="top-up-wallet-btn">Top Up Wallet</button>
            </div>

            <div class="kaa-mall-card data-bundles">
                <div class="network-tabs">
                    <button class="tab-link active" data-network="mtn">MTN</button>
                    <button class="tab-link" data-network="airteltigo">AirtelTigo</button>
                    <button class="tab-link" data-network="vodafone">Vodafone</button>
                </div>
                <div id="mtn" class="network-tab-content active">
                    <form class="bundle-form" data-network="mtn">
                        <label>MTN Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="0241234567" required>
                        <label>Select Bundle</label>
                        <select name="bundle" required></select>
                        <label>Payment Method</label>
                        <div class="payment-method">
                            <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                            <label><input type="radio" name="payment_method" value="paystack"> Paystack (Card/Mobile Money)</label>
                        </div>
                        <button type="submit">Buy MTN Bundle</button>
                    </form>
                </div>
                <div id="airteltigo" class="network-tab-content">
                    <form class="bundle-form" data-network="airteltigo">
                        <label>AirtelTigo Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="0241234567" required>
                        <label>Select Bundle</label>
                        <select name="bundle" required></select>
                        <label>Payment Method</label>
                        <div class="payment-method">
                            <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                            <label><input type="radio" name="payment_method" value="paystack"> Paystack (Card/Mobile Money)</label>
                        </div>
                        <button type="submit">Buy AirtelTigo Bundle</button>
                    </form>
                </div>
                <div id="vodafone" class="network-tab-content">
                    <form class="bundle-form" data-network="vodafone">
                        <label>Vodafone Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="0241234567" required>
                        <label>Select Bundle</label>
                        <select name="bundle" required></select>
                        <label>Payment Method</label>
                        <div class="payment-method">
                            <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                            <label><input type="radio" name="payment_method" value="paystack"> Paystack (Card/Mobile Money)</label>
                        </div>
                        <button type="submit">Buy Vodafone Bundle</button>
                    </form>
                </div>
            </div>

            <div class="kaa-mall-card afa-registration">
                <h3>AFA Bundle Registration</h3>
                <p>Register for AFA bundles and get amazing benefits!</p>
                <form id="kaa-mall-afa-form">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                    <label>Location</label>
                    <input type="text" name="location" placeholder="Enter your location" required>
                    <label>ID Ghana Card Number</label>
                    <input type="text" name="ghana_card" placeholder="GHA-XXXXXXXXX-X" required>
                    <div class="registration-fee">
                        <span>Registration Fee</span>
                        <span>GH₵13.00</span>
                    </div>
                    <button type="submit">Register Now - GH₵13</button>
                </form>
            </div>

            <div class="kaa-mall-card purchase-history">
                <h3>Purchase History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bundle</th>
                            <th>Phone</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="kaa-mall-card need-help">
                <h3>Need Help?</h3>
                <p>Our support team is here to help you 24/7</p>
                <a href="#" class="contact-admin-btn">Contact the Admin</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
