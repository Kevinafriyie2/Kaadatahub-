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
        add_action( 'wp_ajax_nopriv_kaa_mall_purchase_bundle_paystack', array( $this, 'purchase_bundle_paystack' ) );
        add_action( 'wp_ajax_kaa_mall_get_bundle_prices', array( $this, 'get_bundle_prices' ) );
        add_action( 'wp_ajax_kaa_mall_afa_registration', array( $this, 'afa_registration' ) );
        add_action( 'wp_ajax_kaa_mall_get_recent_orders', array( $this, 'get_recent_orders' ) );
        add_action( 'wp_ajax_kaa_mall_get_wallet_balance', array( $this, 'ajax_get_wallet_balance' ) );
    }

    public function init_session() {
        if ( class_exists('WooCommerce') && ! is_admin() && ! defined( 'DOING_AJAX' ) ) {
            if ( WC()->session && ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }
        }
    }

    public function enqueue_scripts() {
        $js_file_path = plugin_dir_path( __FILE__ ) . 'js/kaa-mall-public.js';
        $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js';
        $js_version = filemtime( $js_file_path );

        wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), $this->version, false );
        wp_enqueue_script( 'kaa-mall-public', $js_file_url, array( 'jquery', 'paystack-inline' ), $js_version, false );

        $user = wp_get_current_user();
        $params = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'paystack_public_key' => get_option( 'kaa_mall_paystack_public_key' ),
            'nonce' => wp_create_nonce( 'kaa_mall_nonce' ),
            'is_user_logged_in' => is_user_logged_in(),
            'user_email' => '',
            'currency' => 'GHS', // Default currency
        );

        if ( is_user_logged_in() ) {
            $params['user_email'] = $user->user_email;
            if ( function_exists('get_woocommerce_currency') ) {
                $params['currency'] = get_woocommerce_currency();
            }
        }

        wp_localize_script( 'kaa-mall-public', 'kaa_mall_params', $params );
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

        $admin_prices_str = get_option( 'kaa_mall_' . $network . '_prices' );
        $admin_prices = array();
        if ( ! empty( $admin_prices_str ) ) {
            $lines = explode( "\n", $admin_prices_str );
            foreach ( $lines as $line ) {
                $parts = explode( '=', $line );
                if ( count( $parts ) == 2 ) {
                    $admin_prices[ trim( $parts[0] ) ] = floatval( trim( $parts[1] ) );
                }
            }
        }

        $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
        if ( $reseller_id ) {
            $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_' . $network, true );
            if ( ! empty( $reseller_prices ) ) {
                // Merge reseller prices with admin prices, ensuring all bundles are available
                $final_prices = array_merge( $admin_prices, $reseller_prices );
                wp_send_json_success( $final_prices );
                return;
            }
        }

        wp_send_json_success( $admin_prices );
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

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to make a purchase using your wallet.' ) );
            return;
        }

        $network = sanitize_text_field( $_POST['network'] );
        $bundle = sanitize_text_field( $_POST['bundle'] );
        $phone_number = sanitize_text_field( $_POST['phone_number'] );

        $admin_prices_str = get_option( 'kaa_mall_' . $network . '_prices' );
        $admin_prices = array();
        if ( ! empty( $admin_prices_str ) ) {
            $lines = explode( "\n", $admin_prices_str );
            foreach ( $lines as $line ) {
                $parts = explode( '=', $line );
                if ( count( $parts ) == 2 ) {
                    $admin_prices[ trim( $parts[0] ) ] = floatval( trim( $parts[1] ) );
                }
            }
        }

        if ( ! isset( $admin_prices[ $bundle ] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid bundle selected.' ) );
        }

        $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
        $final_price = $admin_prices[ $bundle ];

        if ( $reseller_id ) {
            $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_' . $network, true );
            if ( ! empty( $reseller_prices ) && isset( $reseller_prices[ $bundle ] ) ) {
                $final_price = $reseller_prices[ $bundle ];
            }
        }

        $user_id = get_current_user_id();
        $wallet_balance = $this->get_wallet_balance( $user_id );

        if ( $wallet_balance < $final_price ) {
            wp_send_json_error( array( 'message' => 'Insufficient wallet balance.' ) );
        }

        $new_balance = $wallet_balance - $final_price;
        update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

        $product = $this->get_product_by_name( 'Data Bundle' );
        if ( $product ) {
            $order = wc_create_order();
            $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
            $order->set_total( $final_price );
            $order->set_status( 'processing' );
            $order->update_meta_data( 'Network', $network );
            $order->update_meta_data( 'Bundle', $bundle );
            $order->update_meta_data( 'Phone Number', $phone_number );

            if ( $reseller_id ) {
                $order->update_meta_data( '_reseller_id', $reseller_id );
                $profit = $final_price - $admin_prices[ $bundle ];
                if ( $profit > 0 ) {
                    $order->update_meta_data( '_reseller_profit', $profit );
                    $current_profit_balance = get_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', true );
                    $new_profit_balance = floatval($current_profit_balance) + $profit;
                    update_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', $new_profit_balance );
                }
            }

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
        $email = is_user_logged_in() ? wp_get_current_user()->user_email : sanitize_email( $_POST['email'] );

        if ( ! is_user_logged_in() && ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'A valid email is required for guest checkout.' ) );
            return;
        }

        $admin_prices_str = get_option( 'kaa_mall_' . $network . '_prices' );
        $admin_prices = array();
        if ( ! empty( $admin_prices_str ) ) {
            $lines = explode( "\n", $admin_prices_str );
            foreach ( $lines as $line ) {
                $parts = explode( '=', $line );
                if ( count( $parts ) == 2 ) {
                    $admin_prices[ trim( $parts[0] ) ] = floatval( trim( $parts[1] ) );
                }
            }
        }

        if ( ! isset( $admin_prices[ $bundle ] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid bundle selected.' ) );
        }

        $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
        $final_price = $admin_prices[ $bundle ];

        if ( $reseller_id ) {
            $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_' . $network, true );
            if ( ! empty( $reseller_prices ) && isset( $reseller_prices[ $bundle ] ) ) {
                $final_price = $reseller_prices[ $bundle ];
            }
        }

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
            $user_id = is_user_logged_in() ? get_current_user_id() : 0;

            $product = $this->get_product_by_name( 'Data Bundle' );
            if ( $product ) {
                $order = wc_create_order();

                if ( $user_id ) {
                    $order->set_customer_id( $user_id );
                } else {
                    $order->set_billing_email( $email );
                }

                $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
                $order->set_total( $final_price );
                $order->set_status( 'processing' );
                $order->update_meta_data( 'Network', $network );
                $order->update_meta_data( 'Bundle', $bundle );
                $order->update_meta_data( 'Phone Number', $phone_number );

                if ( $reseller_id ) {
                    $order->update_meta_data( '_reseller_id', $reseller_id );
                    $profit = $final_price - $admin_prices[ $bundle ];
                    if ( $profit > 0 ) {
                        $order->update_meta_data( '_reseller_profit', $profit );
                        $current_profit_balance = get_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', true );
                        $new_profit_balance = floatval($current_profit_balance) + $profit;
                        update_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', $new_profit_balance );
                    }
                }

                $order->save();
            }

            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => 'Transaction verification failed.' ) );
        }
    }

    public function afa_registration() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to perform this action.' ) );
            return;
        }

        $full_name = sanitize_text_field( $_POST['full_name'] );
        $phone_number = sanitize_text_field( $_POST['phone_number'] );
        $location = sanitize_text_field( $_POST['location'] );
        $ghana_card = sanitize_text_field( $_POST['ghana_card'] );

        $afa_fee = 13.00; // This is a fixed admin price
        $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
        $final_price = $afa_fee;

        if($reseller_id) {
            $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_afa', true );
            if ( ! empty( $reseller_prices ) && isset( $reseller_prices['registration'] ) ) {
                $final_price = $reseller_prices['registration'];
            }
        }

        $user_id = get_current_user_id();
        $wallet_balance = $this->get_wallet_balance( $user_id );

        if ( $wallet_balance < $final_price ) {
            wp_send_json_error( array( 'message' => 'Insufficient wallet balance.' ) );
        }

        $new_balance = $wallet_balance - $final_price;
        update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

        $product = $this->get_product_by_name( 'AFA Registration' );
        if ( $product ) {
            $order = wc_create_order();
            $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
            $order->set_total( $final_price );
            $order->set_status( 'processing' );
            $order->update_meta_data( 'Full Name', $full_name );
            $order->update_meta_data( 'Phone Number', $phone_number );
            $order->update_meta_data( 'Location', $location );
            $order->update_meta_data( 'Ghana Card', $ghana_card );

            if ( $reseller_id ) {
                $order->update_meta_data( '_reseller_id', $reseller_id );
                $profit = $final_price - $afa_fee;
                if( $profit > 0 ) {
                    $order->update_meta_data( '_reseller_profit', $profit );
                    $current_profit_balance = get_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', true );
                    $new_profit_balance = floatval($current_profit_balance) + $profit;
                    update_user_meta( $reseller_id, '_kaa_mall_reseller_profit_balance', $new_profit_balance );
                }
            }

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
        if ( isset( $_GET['ref'] ) ) {
            $reseller_id = intval( $_GET['ref'] );
            if ( get_user_by( 'id', $reseller_id ) && function_exists('WC') && WC()->session ) {
                WC()->session->set( 'kaa_mall_reseller_id', $reseller_id );
            }
        }

        if ( ! is_user_logged_in() ) {
            ob_start();
            ?>
            <style>
                #loginform label {
                    color: #e0e0e0;
                }
                #loginform input[type="text"],
                #loginform input[type="password"] {
                    background-color: #2c2c2c;
                    border: 1px solid #333;
                    color: #e0e0e0;
                    width: 100%;
                    padding: 10px;
                    border-radius: 8px;
                    margin-bottom: 15px;
                }
                #loginform input[type="submit"] {
                    background-color: #ffc107;
                    color: #121212;
                    border: none;
                    font-weight: bold;
                    width: 100%;
                    padding: 12px;
                    border-radius: 8px;
                    cursor: pointer;
                }
                #loginform .forgetmenot label {
                    color: #e0e0e0;
                }
                #loginform a {
                    color: #ffc107;
                }
                .login-remember {
                    margin-bottom: 15px;
                }
            </style>
            <div class="kaa-mall-portal" style="max-width: 400px; margin: 40px auto; padding: 20px; background-color: #1e1e1e; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.5);">
                <h3 style="text-align: center; color: #ffffff; margin-bottom: 20px;">Please log in to access the portal.</h3>
                <?php wp_login_form( array('redirect' => get_permalink()) ); ?>
                <p style="text-align: center; margin-top: 20px; color: #e0e0e0;">
                    Don't have an account? <a href="<?php echo esc_url( home_url( '/auth' ) ); ?>" style="color: #ffc107;">Register here</a>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();

        if ( is_user_logged_in() ) {
            echo Kaa_Mall_Portal_Header::render();
        }

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

            .purchase-history {
                overflow-x: auto;
            }

            .purchase-history table {
                width: 100%;
                border-collapse: collapse;
                min-width: 600px;
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

            <?php if ( is_user_logged_in() ) : ?>
            <div class="kaa-mall-card wallet-balance">
                <div class="wallet-balance-details">
                    <span>Wallet Balance</span>
                    <span class="balance-amount"></span>
                </div>
                <button class="top-up-wallet-btn">Top Up Wallet</button>
            </div>
            <?php endif; ?>

            <div class="kaa-mall-card data-bundles">
                <div class="network-tabs">
                    <button class="tab-link active" data-network="mtn">MTN</button>
                    <button class="tab-link" data-network="airteltigo">AirtelTigo</button>
                    <button class="tab-link" data-network="vodafone">Vodafone</button>
                </div>
                <div id="mtn" class="network-tab-content active">
                    <form class="bundle-form" data-network="mtn">
                        <?php if ( ! is_user_logged_in() ) : ?>
                            <label>Your Email</label>
                            <input type="email" name="email" placeholder="Enter your email" required>
                        <?php endif; ?>
                        <label>MTN Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="0241234567" required>
                        <label>Select Bundle</label>
                        <select name="bundle" required></select>
                        <label>Payment Method</label>
                        <?php if ( is_user_logged_in() ) : ?>
                        <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                        <?php endif; ?>
                        <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                        <button type="submit">Buy MTN Bundle</button>
                    </form>
                </div>
                <div id="airteltigo" class="network-tab-content">
                    <form class="bundle-form" data-network="airteltigo">
                        <?php if ( ! is_user_logged_in() ) : ?>
                            <label>Your Email</label>
                            <input type="email" name="email" placeholder="Enter your email" required>
                        <?php endif; ?>
                        <label>AirtelTigo Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="0241234567" required>
                        <label>Select Bundle</label>
                        <select name="bundle" required></select>
                        <label>Payment Method</label>
                        <?php if ( is_user_logged_in() ) : ?>
                        <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                        <?php endif; ?>
                        <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                        <button type="submit">Buy AirtelTigo Bundle</button>
                    </form>
                </div>
                <div id="vodafone" class="network-tab-content">
                    <form class="bundle-form" data-network="vodafone">
                        <?php if ( ! is_user_logged_in() ) : ?>
                            <label>Your Email</label>
                            <input type="email" name="email" placeholder="Enter your email" required>
                        <?php endif; ?>
                        <label>Vodafone Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="0241234567" required>
                        <label>Select Bundle</label>
                        <select name="bundle" required></select>
                        <label>Payment Method</label>
                        <?php if ( is_user_logged_in() ) : ?>
                        <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                        <?php endif; ?>
                        <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                        <button type="submit">Buy Vodafone Bundle</button>
                    </form>
                </div>
            </div>

            <?php if ( is_user_logged_in() ) : ?>
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
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Bundle</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <?php endif; ?>

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
