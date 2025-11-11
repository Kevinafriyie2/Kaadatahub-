<?php

class Kaa_Mall_Public {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_shortcode( 'kaa_user_portal', array( $this, 'render_user_portal' ) );
        add_shortcode( 'kaa_mall_history_portal', array( $this, 'render_history_portal' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_kaa_mall_verify_paystack_transaction', array( $this, 'verify_paystack_transaction' ) );
        add_action( 'wp_ajax_nopriv_kaa_mall_verify_paystack_transaction', array( $this, 'verify_paystack_transaction' ) );
        add_action( 'wp_ajax_kaa_mall_purchase_bundle', array( $this, 'purchase_bundle' ) );
        add_action( 'wp_ajax_kaa_mall_purchase_bundle_paystack', array( $this, 'purchase_bundle_paystack' ) );
        add_action( 'wp_ajax_nopriv_kaa_mall_purchase_bundle_paystack', array( $this, 'purchase_bundle_paystack' ) );
        add_action( 'wp_ajax_kaa_mall_get_bundle_prices', array( $this, 'get_bundle_prices' ) );
        add_action( 'wp_ajax_nopriv_kaa_mall_get_bundle_prices', array( $this, 'get_bundle_prices' ) );
        add_action( 'wp_ajax_kaa_mall_afa_registration', array( $this, 'afa_registration' ) );
        add_action( 'wp_ajax_kaa_mall_get_recent_orders', array( $this, 'get_recent_orders' ) );
        add_action( 'wp_ajax_kaa_mall_get_all_orders', array( $this, 'get_all_orders' ) );
        add_action( 'wp_ajax_kaa_mall_get_wallet_balance', array( $this, 'ajax_get_wallet_balance' ) );
        add_action( 'wp_ajax_kaa_mall_get_wallet_transactions', array( $this, 'get_wallet_transactions' ) );
    }

    public function get_wallet_transactions() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        global $wpdb;
        $table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';
        $user_id = get_current_user_id();

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ) );

        wp_send_json_success( $results );
    }

    public function init_session() {
        if ( class_exists('WooCommerce') && ! is_admin() && ! defined( 'DOING_AJAX' ) ) {
            if ( WC()->session && ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }
        }
    }

    public function enqueue_scripts() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ( ! has_shortcode( $post->post_content, 'kaa_user_portal' ) && ! has_shortcode( $post->post_content, 'kaa_mall_history_portal' ) ) ) {
            return;
        }

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
            'wallet_topup_fee' => get_option( 'kaa_mall_wallet_topup_fee', 0 ),
            'wallet_purchase_fee' => get_option( 'kaa_mall_wallet_purchase_fee', 0 ),
            'paystack_topup_fee' => get_option( 'kaa_mall_paystack_topup_fee', 0 ),
            'paystack_purchase_fee' => get_option( 'kaa_mall_paystack_purchase_fee', 0 ),
        );

        if ( is_user_logged_in() ) {
            $params['user_email'] = $user->user_email;
            if ( function_exists('get_woocommerce_currency') ) {
                $params['currency'] = get_woocommerce_currency();
            }
        }

        wp_localize_script( 'kaa-mall-public', 'kaa_mall_params', $params );
    }

    public function ajax_get_wallet_balance() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        $user_id = get_current_user_id();
        $balance = Kaa_Mall_Wallet::get_balance( $user_id );
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
        $network = sanitize_text_field( $_POST['network'] );

        if ( get_option( 'kaa_mall_' . $network . '_out_of_stock' ) ) {
            wp_send_json_success( array() );
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
            $total_amount_paid = $result->data->amount / 100; // Convert from pesewas
            $fee = floatval( get_option( 'kaa_mall_paystack_topup_fee', 0 ) );
            $top_up_amount = $total_amount_paid - $fee;

            $user_id = get_current_user_id();
            // Log the fee as a separate transaction for clarity
            if ( $fee > 0 ) {
                Kaa_Mall_Wallet::update_balance_and_log( $user_id, -$fee, 'fee', 'Paystack Top-up Fee. Reference: ' . $reference );
            }
            Kaa_Mall_Wallet::update_balance_and_log( $user_id, $top_up_amount, 'top-up', 'Paystack Top-up. Reference: ' . $reference );

            $product = $this->get_product_by_name( 'Wallet Top-up' );
            if ( $product ) {
                $order = wc_create_order();
                $order->set_customer_id( $user_id );
                $order->add_product( $product, 1, array( 'subtotal' => $top_up_amount, 'total' => $top_up_amount ) );
                $order->set_total( $top_up_amount );
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
        $wallet_balance = Kaa_Mall_Wallet::get_balance( $user_id );
        $fee = floatval( get_option( 'kaa_mall_wallet_purchase_fee', 0 ) );
        $total_cost = $final_price + $fee;

        if ( $wallet_balance < $total_cost ) {
            wp_send_json_error( array( 'message' => 'Insufficient wallet balance.' ) );
        }

        // Deduct the bundle price first
        Kaa_Mall_Wallet::update_balance_and_log( $user_id, -$final_price, 'purchase', "{$bundle} for {$phone_number}" );
        // Then deduct the fee
        if ( $fee > 0 ) {
            Kaa_Mall_Wallet::update_balance_and_log( $user_id, -$fee, 'fee', "Service fee for {$bundle}" );
        }

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

        $this->check_and_send_low_balance_alert( $user_id );
        wp_send_json_success();
    }

    private function check_and_send_low_balance_alert( $user_id ) {
        if ( ! get_option( 'kaa_mall_enable_low_balance_alerts' ) ) {
            return;
        }

        $threshold = floatval( get_option( 'kaa_mall_low_balance_threshold', '5' ) );
        $balance = Kaa_Mall_Wallet::get_balance( $user_id );

        if ( $balance < $threshold ) {
            $user = get_user_by( 'id', $user_id );
            $subject = 'Your Wallet Balance is Low';
            $message = 'Dear ' . $user->display_name . ",\n\nYour wallet balance is running low. Your current balance is " . wc_price( $balance ) . ".\n\nPlease top up your wallet to continue enjoying our services.\n\nThank you,\nKaa Mall";
            wp_mail( $user->user_email, $subject, $message );
        }
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
            $fee = floatval( get_option( 'kaa_mall_paystack_purchase_fee', 0 ) );
            $user_id = is_user_logged_in() ? get_current_user_id() : 0;

            if ( $user_id && $fee > 0 ) {
                 // We don't need to deduct from wallet, just log it for the user's records if they are logged in
                 // This assumes the fee was already included in the Paystack charge amount
                Kaa_Mall_Wallet::update_balance_and_log( $user_id, -$fee, 'fee', "Paystack service fee for {$bundle}" );
            }

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

        $afa_fee = floatval( get_option( 'kaa_mall_afa_registration_fee', '13' ) );
        $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
        $final_price = $afa_fee;

        if($reseller_id) {
            $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_afa', true );
            if ( ! empty( $reseller_prices ) && isset( $reseller_prices['registration'] ) ) {
                $final_price = $reseller_prices['registration'];
            }
        }

        $user_id = get_current_user_id();
        $wallet_balance = Kaa_Mall_Wallet::get_balance( $user_id );

        if ( $wallet_balance < $final_price ) {
            wp_send_json_error( array( 'message' => 'Insufficient wallet balance.' ) );
        }

        Kaa_Mall_Wallet::update_balance_and_log( $user_id, -$final_price, 'purchase', 'AFA Registration' );

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

        $this->check_and_send_low_balance_alert( $user_id );
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

    public function get_all_orders() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        $user_id = get_current_user_id();
        $args = array(
            'customer_id' => $user_id,
            'limit' => -1, // Get all orders
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

    public function render_history_portal() {
        if ( ! is_user_logged_in() ) {
            return 'Please log in to view your history.';
        }

        ob_start();

        echo Kaa_Mall_Portal_Header::render();
        ?>
        <style>
            :root {
                --primary-color: #ffc107;
                --secondary-color: #8a2be2;
                --text-color: #ffffff;
                --heading-color: #ffffff;
                --border-color: rgba(255, 255, 255, 0.2);
                --shadow-color: rgba(0, 0, 0, 0.5);
            }

            body {
                padding: 0;
                margin: 0;
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                background-attachment: fixed;
            }

            .kaa-mall-portal {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                color: var(--text-color);
                padding: 20px;
                width: 100%;
                box-sizing: border-box;
            }

            .kaa-mall-card {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 16px;
                box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid var(--border-color);
                padding: 20px;
                margin-bottom: 20px;
            }

            .purchase-history, .wallet-transactions {
                overflow-x: auto;
            }

            .purchase-history table, .wallet-transactions table {
                width: 100%;
                border-collapse: collapse;
                min-width: 600px;
            }

            .purchase-history th, .purchase-history td,
            .wallet-transactions th, .wallet-transactions td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid var(--border-color);
            }

            .purchase-history th, .wallet-transactions th {
                font-weight: bold;
            }
        </style>
        <div class="kaa-mall-portal">
            <div id="history">
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

                <div class="kaa-mall-card wallet-transactions">
                    <h3>Wallet History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Details</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_user_portal() {
        if ( isset( $_GET['ref_shop'] ) ) {
            $shop_name = sanitize_title( $_GET['ref_shop'] );
            $users = get_users( array(
                'meta_key'   => '_kaa_mall_shop_name',
                'meta_value' => $shop_name,
                'number'     => 1,
                'fields'     => 'ID',
            ) );

            if ( ! empty( $users ) ) {
                $reseller_id = $users[0];
                if ( function_exists('WC') && WC()->session ) {
                    WC()->session->set( 'kaa_mall_reseller_id', $reseller_id );
                }
            }
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
                --text-color: #ffffff;
                --heading-color: #ffffff;
                --border-color: rgba(255, 255, 255, 0.2);
                --shadow-color: rgba(0, 0, 0, 0.5);
            }

            body {
                padding: 0;
                margin: 0;
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                background-attachment: fixed;
            }

            .kaa-mall-portal {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                color: var(--text-color);
                padding: 0;
                width: 100%;
            }

            .kaa-mall-header {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 16px;
                box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid var(--border-color);
                color: white;
                padding: 20px;
                text-align: center;
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
                background: rgba(0, 0, 0, 0.2);
                border-radius: 16px;
                box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid var(--border-color);
                padding: 20px;
                margin-bottom: 20px;
            }

            .wallet-balance {
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
                transition: all 0.3s ease;
            }

            .top-up-wallet-btn:hover {
                transform: scale(1.02);
                opacity: 0.9;
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
                transition: all 0.3s ease;
            }

            .data-bundles .tab-link:hover {
                color: var(--primary-color);
                transform: translateY(-2px);
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
                background-color: rgba(0, 0, 0, 0.2);
                color: var(--text-color);
                font-size: 1em;
                box-sizing: border-box;
            }

            .bundle-form button {
                background-color: var(--primary-color);
                color: #121212;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .bundle-form button:hover {
                transform: scale(1.02);
                opacity: 0.9;
            }

            .payment-method-header {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
            }
            .payment-method-header img {
                width: 24px;
                height: 24px;
                margin-right: 10px;
            }
            .payment-method-header span {
                font-weight: bold;
                color: var(--heading-color);
            }
            .payment-options {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            .payment-option {
                display: flex;
                align-items: center;
                background-color: rgba(0, 0, 0, 0.2);
                padding: 15px;
                border-radius: 8px;
                border: 1px solid var(--border-color);
                cursor: pointer;
                transition: border-color 0.2s ease;
            }
            .payment-option:hover {
                border-color: var(--primary-color);
            }
            .payment-option input[type="radio"] {
                display: none;
            }
            .payment-option .radio-custom {
                width: 20px;
                height: 20px;
                border-radius: 50%;
                border: 2px solid #555;
                margin-right: 15px;
                display: flex;
                justify-content: center;
                align-items: center;
                transition: border-color 0.2s ease;
            }
            .payment-option .radio-custom .radio-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background-color: var(--primary-color);
                transform: scale(0);
                transition: transform 0.2s ease;
            }
            .payment-option input[type="radio"]:checked + .radio-custom {
                border-color: var(--primary-color);
            }
            .payment-option input[type="radio"]:checked + .radio-custom .radio-dot {
                transform: scale(1);
            }
            .payment-option label {
                font-weight: 500;
                color: var(--text-color);
            }

            .afa-registration {
                /* The background is now handled by .kaa-mall-card */
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
                transition: all 0.3s ease;
            }

            #kaa-mall-afa-form button:hover {
                transform: scale(1.02);
                opacity: 0.9;
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
                transition: all 0.3s ease;
            }

            .contact-admin-btn:hover {
                transform: scale(1.02);
                opacity: 0.9;
            }

            @media screen and (max-width: 600px) {
                .kaa-mall-portal {
                    padding-left: 15px;
                    padding-right: 15px;
                }
                .wallet-balance {
                    flex-direction: column;
                    gap: 15px;
                    align-items: flex-start;
                }
                .data-bundles .network-tabs {
                    gap: 10px;
                }
                .data-bundles .tab-link {
                    padding: 8px;
                    font-size: 0.9em;
                }
            }
        </style>
        <div class="kaa-mall-portal">
            <div class="kaa-mall-header">
                <h1>Data Bundle Store</h1>
                <p>Instant Data Top-up for All Networks</p>
            </div>

            <div class="kaa-mall-card">
                <h3>Broadcast Messages</h3>
                <?php
                $broadcasts = get_posts( array(
                    'post_type' => 'kaa_mall_broadcast',
                    'numberposts' => 5,
                ) );
                ?>
                <ul>
                    <?php if ( ! empty( $broadcasts ) ) : ?>
                        <?php foreach ( $broadcasts as $broadcast ) : ?>
                            <li>
                                <strong><?php echo esc_html( $broadcast->post_title ); ?></strong> - <?php echo esc_html( $broadcast->post_content ); ?>
                                <small>(<?php echo get_the_date( '', $broadcast ); ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li>No recent broadcasts.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="kaa-mall-card">
                <h3>Join Our Community</h3>
                <p>Stay updated with the latest news and offers by joining our WhatsApp community.</p>
                <?php
                $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
                $whatsapp_group_link = 'https://chat.whatsapp.com/JZEJZlNO3DV8VCyeTwzYgt'; // Default link
                if ( $reseller_id ) {
                    $reseller_whatsapp_group_link = get_user_meta( $reseller_id, '_kaa_mall_whatsapp_group_link', true );
                    if ( ! empty( $reseller_whatsapp_group_link ) ) {
                        $whatsapp_group_link = $reseller_whatsapp_group_link;
                    }
                }
                ?>
                <a href="<?php echo esc_url( $whatsapp_group_link ); ?>" class="contact-admin-btn" style="background-color: #25D366; text-align: center; display: block;">Join Now</a>
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
                        <div class="price-breakdown" style="padding: 10px 0;"></div>
                        <div class="payment-method-header">
                            <img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/credit-card.svg'; ?>" alt="">
                            <span>Payment Method</span>
                        </div>
                        <div class="payment-options">
                            <?php if ( is_user_logged_in() ) : ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="wallet" checked>
                                <span class="radio-custom"><span class="radio-dot"></span></span>
                                Wallet Balance
                            </label>
                            <?php endif; ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>>
                                <span class="radio-custom"><span class="radio-dot"></span></span>
                                Paystack (Card/Mobile Money)
                            </label>
                        </div>
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
                        <div class="payment-method-header">
                            <img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/credit-card.svg'; ?>" alt="">
                            <span>Payment Method</span>
                        </div>
                        <div class="payment-options">
                            <?php if ( is_user_logged_in() ) : ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="wallet" checked>
                                <span class="radio-custom"><span class="radio-dot"></span></span>
                                Wallet Balance
                            </label>
                            <?php endif; ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>>
                                <span class="radio-custom"><span class="radio-dot"></span></span>
                                Paystack (Card/Mobile Money)
                            </label>
                        </div>
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
                        <div class="payment-method-header">
                            <img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/credit-card.svg'; ?>" alt="">
                            <span>Payment Method</span>
                        </div>
                        <div class="payment-options">
                            <?php if ( is_user_logged_in() ) : ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="wallet" checked>
                                <span class="radio-custom"><span class="radio-dot"></span></span>
                                Wallet Balance
                            </label>
                            <?php endif; ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>>
                                <span class="radio-custom"><span class="radio-dot"></span></span>
                                Paystack (Card/Mobile Money)
                            </label>
                        </div>
                        <button type="submit">Buy Vodafone Bundle</button>
                    </form>
                </div>
            </div>

            <?php if ( (is_user_logged_in() || WC()->session->get( 'kaa_mall_reseller_id' )) && ! get_option( 'kaa_mall_afa_out_of_stock' ) ) : ?>
            <div class="kaa-mall-card afa-registration">
                <h3>AFA Bundle Registration</h3>
                <p>Register for AFA bundles and get amazing benefits!</p>
                <form id="kaa-mall-afa-form">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" placeholder="Enter your phone number" required>
                    <label>Location</label>
                    <input type="text" name="location" placeholder="Enter your location" required>
                    <label>ID Ghana Card Number</label>
                    <input type="text" name="ghana_card" placeholder="GHA-XXXXXXXXX-X" required>
                    <?php
                    $afa_fee = floatval( get_option( 'kaa_mall_afa_registration_fee', '13' ) );
                    $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
                    if ( $reseller_id ) {
                        $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_afa', true );
                        if ( ! empty( $reseller_prices ) && isset( $reseller_prices['registration'] ) ) {
                            $afa_fee = $reseller_prices['registration'];
                        }
                    }
                    ?>
                    <div class="registration-fee">
                        <span>Registration Fee</span>
                        <span><?php echo wc_price( $afa_fee ); ?></span>
                    </div>
                    <button type="submit">Register Now - <?php echo wc_price( $afa_fee ); ?></button>
                </form>
            </div>

            <?php endif; ?>

            <div class="kaa-mall-card need-help">
                <h3>Need Help?</h3>
                <p>Our support team is here to help you 24/7</p>
                <?php
                $whatsapp_number = get_option( 'kaa_mall_whatsapp_number', '233201858375' );
                $reseller_id = WC()->session->get( 'kaa_mall_reseller_id' );
                if ( $reseller_id ) {
                    $reseller_whatsapp = get_user_meta( $reseller_id, '_kaa_mall_whatsapp_number', true );
                    if ( ! empty( $reseller_whatsapp ) ) {
                        $whatsapp_number = $reseller_whatsapp;
                    }
                }
                ?>
                <a href="https://wa.me/<?php echo esc_attr( $whatsapp_number ); ?>" class="contact-admin-btn">Contact Support</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
