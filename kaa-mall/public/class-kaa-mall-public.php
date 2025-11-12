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
        add_action( 'wp_ajax_kaa_mall_download_history', array( $this, 'download_history' ) );
        add_action( 'wp_ajax_kaa_mall_apply_coupon', array( $this, 'apply_coupon' ) );
        add_action( 'wp_ajax_nopriv_kaa_mall_apply_coupon', array( $this, 'apply_coupon' ) );

        // Hooks for dynamic content loading
        add_action( 'wp_ajax_kaa_mall_get_data_bundle_form', array( $this, 'ajax_get_data_bundle_form' ) );
        add_action( 'wp_ajax_kaa_mall_get_afa_registration_form', array( $this, 'ajax_get_afa_registration_form' ) );
        add_action( 'wp_ajax_kaa_mall_get_wallet_view', array( $this, 'ajax_get_wallet_view' ) );
        add_action( 'wp_ajax_kaa_mall_get_referral_view', array( $this, 'ajax_get_referral_view' ) );

        add_action( 'init', array( $this, 'init_session' ) );
    }

    public function ajax_get_referral_view() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        echo $this->render_referral_view();
        wp_die();
    }

    // AJAX handlers for dynamic content
    public function ajax_get_data_bundle_form() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        $network = sanitize_text_field($_POST['network']);
        echo $this->render_data_bundle_form($network);
        wp_die();
    }

    public function ajax_get_afa_registration_form() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        echo $this->render_afa_registration_form();
        wp_die();
    }

    public function ajax_get_wallet_view() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );
        echo $this->render_wallet_view();
        wp_die();
    }


    public function render_data_bundle_form( $network ) {
        ob_start();
        $network_name = ucfirst($network);
        if ($network === 'vodafone') {
            $network_name = 'Telecel';
        }
        ?>
        <div class="kaa-mall-dynamic-content-wrapper">
            <h3>Buy <?php echo esc_html($network_name); ?> Bundle</h3>
            <form id="kaa-mall-bundle-purchase-form" class="kaa-mall-form" data-network="<?php echo esc_attr($network); ?>">
                <div class="form-group">
                    <label for="bundle-options">Select Bundle</label>
                    <select id="bundle-options" name="bundle" required>
                        <option value="">Loading bundles...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="phone-number">Phone Number</label>
                    <input type="tel" id="phone-number" name="phone_number" required>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="payment-method-options">
                        <label><input type="radio" name="payment_method" value="wallet" checked> Wallet</label>
                        <label><input type="radio" name="payment_method" value="paystack"> Pay with MTN Momo</label>
                    </div>
                </div>
                 <?php if ( ! is_user_logged_in() ): ?>
                <div class="form-group" id="guest-email-field" style="display: none;">
                    <label for="guest-email">Email</label>
                    <input type="email" id="guest-email" name="email">
                </div>
                <?php endif; ?>
                <div class="price-breakdown"></div>
                <div class="form-group">
                    <button type="submit" class="kaa-mall-btn">Purchase</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_afa_registration_form() {
        ob_start();
        ?>
        <div class="kaa-mall-dynamic-content-wrapper">
             <h3>AFA Registration</h3>
             <form id="kaa-mall-afa-registration-form" class="kaa-mall-form">
                <div class="form-group">
                    <label for="afa-full-name">Full Name</label>
                    <input type="text" id="afa-full-name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="afa-phone-number">Phone Number</label>
                    <input type="tel" id="afa-phone-number" name="phone_number" required>
                </div>
                 <div class="form-group">
                    <label for="afa-location">Location</label>
                    <input type="text" id="afa-location" name="location" required>
                </div>
                <div class="form-group">
                    <label for="afa-ghana-card">Ghana Card Number</label>
                    <input type="text" id="afa-ghana-card" name="ghana_card" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="kaa-mall-btn">Register</button>
                </div>
             </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_wallet_view() {
        ob_start();
        ?>
        <div class="kaa-mall-dynamic-content-wrapper">
            <h3>Wallet</h3>
            <div class="balance-card">
                 <div class="balance-info">
                    <p>My Balance</p>
                    <h2 class="balance-amount">GH₵ ...</h2>
                </div>
                <div class="balance-actions">
                    <button class="hide-balance-btn">Hide</button>
                    <button class="top-up-btn">Top Up</button>
                </div>
            </div>
             <div class="recent-transactions-card">
                <h3>Recent Wallet Transactions</h3>
                <ul class="transactions-list" id="wallet-transactions-list">
                    <!-- Transactions will be loaded here by JS -->
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_referral_link() {
        if ( ! is_user_logged_in() ) {
            return '';
        }
        $user_id = get_current_user_id();
        $shop_name = get_user_meta( $user_id, '_kaa_mall_shop_name', true );
        if ( $shop_name ) {
            return home_url( '/?ref_shop=' . $shop_name );
        }
        return home_url( '/?ref=' . $user_id );
    }

    public function render_referral_view() {
        ob_start();
        $referral_link = $this->get_referral_link();
        ?>
        <div class="kaa-mall-dynamic-content-wrapper">
            <h3>Refer a Friend</h3>
            <p>Share your unique link to earn rewards when your friends make a purchase.</p>
            <div class="form-group">
                <label for="referral-link">Your Referral Link</label>
                <input type="text" id="referral-link" value="<?php echo esc_url($referral_link); ?>" readonly>
            </div>
             <div class="form-group">
                <button class="kaa-mall-btn" onclick="navigator.clipboard.writeText('<?php echo esc_url($referral_link); ?>')">Copy Link</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }


    public function apply_coupon() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );

        $coupon_code = sanitize_text_field( $_POST['coupon_code'] );
        $coupon = new WC_Coupon( $coupon_code );

        if ( ! $coupon->get_code() ) {
            wp_send_json_error( array( 'message' => 'Coupon does not exist.' ) );
        }

        if ( ! $coupon->is_valid() ) {
            wp_send_json_error( array( 'message' => 'Coupon is not valid.' ) );
        }

        WC()->cart->add_discount( $coupon_code );

        wp_send_json_success( array(
            'message' => 'Coupon applied successfully.',
            'discount_amount' => $coupon->get_amount(),
        ) );
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
        if ( function_exists('WC') && class_exists('WC_Session_Handler') ) {
            if ( is_null( WC()->session ) ) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
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
        wp_enqueue_style( 'kaa-mall-portal-redesign', plugin_dir_url( __FILE__ ) . 'css/kaa-mall-portal-redesign.css', array(), $this->version );

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

        $reseller_instance = new Kaa_Mall_Reseller();
        $tier = $reseller_instance->get_reseller_tier( $reseller_id );

        if ( $tier ) {
            $discount_percentage = $tier['discount_percentage'];
            $discounted_prices = array();
            foreach ( $admin_prices as $bundle => $price ) {
                $discounted_prices[ $bundle ] = $price * ( 1 - ( $discount_percentage / 100 ) );
            }
            $admin_prices = $discounted_prices;
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

        $product = $this->get_product_by_name( 'Data Bundle' );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Data bundle product not found. Please contact support.' ) );
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

        $coupon_code = ! empty( $_POST['coupon_code'] ) ? sanitize_text_field( $_POST['coupon_code'] ) : null;
        $discount = 0;
        if ( $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            if ( $coupon->get_code() && $coupon->is_valid() ) {
                $discount = $coupon->get_amount();
                $final_price -= $discount;
            }
        }

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

        $order = wc_create_order();
        $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
            $order->set_total( $final_price );
            $order->set_status( 'processing' );
            $order->update_meta_data( 'Network', $network );
            $order->update_meta_data( 'Bundle', $bundle );
            $order->update_meta_data( 'Phone Number', $phone_number );

            if ( $coupon_code && $discount > 0 ) {
                $order->apply_coupon( $coupon_code );
            }

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

        $product = $this->get_product_by_name( 'Data Bundle' );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Data bundle product not found. Please contact support.' ) );
            return;
        }

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

        $coupon_code = ! empty( $_POST['coupon_code'] ) ? sanitize_text_field( $_POST['coupon_code'] ) : null;
        if ( $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            if ( $coupon->get_code() && $coupon->is_valid() ) {
                $discount = $coupon->get_amount();
                $final_price -= $discount;
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

                if ( $coupon_code ) {
                    $order->apply_coupon( $coupon_code );
                }

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

    public function download_history() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_die( 'You must be logged in to download history.' );
        }

        $user_id = get_current_user_id();

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="kaadatahub_history.csv"' );

        $output = fopen( 'php://output', 'w' );

        // Wallet History
        fputcsv( $output, array( 'Wallet History' ) );
        fputcsv( $output, array( 'Date', 'Type', 'Amount (GHS)', 'Details', 'Balance (GHS)' ) );

        global $wpdb;
        $table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';
        $wallet_transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT created_at, type, amount, details, balance_after FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        if ( ! empty( $wallet_transactions ) ) {
            foreach ( $wallet_transactions as $transaction ) {
                fputcsv( $output, array(
                    $transaction->created_at,
                    ucfirst( $transaction->type ),
                    number_format( $transaction->amount, 2 ),
                    $transaction->details,
                    number_format( $transaction->balance_after, 2 )
                ) );
            }
        } else {
            fputcsv( $output, array( 'No wallet transactions found.' ) );
        }

        fputcsv( $output, array() ); // Spacer row

        // Purchase History
        fputcsv( $output, array( 'Purchase History' ) );
        fputcsv( $output, array( 'Order ID', 'Date', 'Items', 'Total (GHS)', 'Status' ) );

        $orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        if ( ! empty( $orders ) ) {
            foreach ( $orders as $order ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $items[] = $item->get_name() . ' (x' . $item->get_quantity() . ')';
                }
                fputcsv( $output, array(
                    $order->get_id(),
                    $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ),
                    implode( ', ', $items ),
                    $order->get_total(),
                    wc_get_order_status_name( $order->get_status() )
                ) );
            }
        } else {
            fputcsv( $output, array( 'No purchase history found.' ) );
        }

        fclose( $output );
        wp_die();
    }

    public function render_history_portal() {
        if ( ! is_user_logged_in() ) {
            return 'Please log in to view your history.';
        }

        ob_start();
        $current_user = wp_get_current_user();
        $history_page_url = get_permalink( get_page_by_path( 'history' ) );
        ?>
        <div class="kaa-mall-portal-body">
             <!-- Sidebar -->
            <div class="kaa-mall-sidebar">
                <div class="sidebar-header">
                    <h2 class="brand-title">Kaadatahub</h2>
                    <button class="close-sidebar-btn">&times;</button>
                </div>
                <ul class="sidebar-nav">
                    <li class="nav-section-title">Services</li>
                    <li><a href="https://kaadatahub.shop/users-portal-new/"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-network="mtn"><i class="fas fa-mobile-alt"></i> MTN</a></li>
                    <li><a href="#" class="nav-link" data-network="airteltigo"><i class="fas fa-mobile-alt"></i> Airteltigo</a></li>
                    <li><a href="#" class="nav-link" data-network="vodafone"><i class="fas fa-mobile-alt"></i> Telecel</a></li>
                    <li><a href="#" class="nav-link" data-afa="true"><i class="fas fa-user-plus"></i> AFA Registration</a></li>

                    <li class="nav-section-title">Credits & Debits</li>
                    <li><a href="#" class="nav-link" data-wallet="true"><i class="fas fa-wallet"></i> Wallet</a></li>

                    <?php if ( in_array( 'reseller', (array) $current_user->roles ) ) : ?>
                    <li class="nav-section-title">Business</li>
                    <li><a href="https://kaadatahub.shop/resellers-portal/"><i class="fas fa-store"></i> Reseller</a></li>
                    <?php else: ?>
                    <li class="nav-section-title">Business</li>
                    <li><a href="https://kaadatahub.shop/apply-as-a-reseller/"><i class="fas fa-store"></i> Apply as a Reseller</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="sidebar-overlay"></div>

            <div class="kaa-mall-main-content">
                 <div class="main-header">
                    <button class="open-sidebar-btn"><i class="fas fa-bars"></i></button>
                    <div class="header-user-info">
                        <span>Hello, <?php echo esc_html( $current_user->display_name ); ?></span>
                    </div>
                    <div class="header-icons">
                        <div class="dark-mode-toggle">
                            <i class="fas fa-sun"></i>
                            <i class="fas fa-moon"></i>
                        </div>
                        <i class="fas fa-bell"></i>
                        <i class="fas fa-user" id="profile-icon"></i>
                    </div>
                    <div class="profile-popup" style="display: none;">
                        <div class="profile-header">
                            <span class="profile-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                            <button class="close-popup-btn">&times;</button>
                        </div>
                        <ul class="profile-menu">
                            <li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><i class="fas fa-user-circle"></i> My Profile</a></li>
                            <li><a href="#"><i class="fas fa-question-circle"></i> Support</a></li>
                            <li><a href="#"><i class="fas fa-cog"></i> Setting</a></li>
                            <li><a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
                        </ul>
                    </div>
                </div>
                <div class="history-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>History</h2>
                    <button id="download-history-btn" class="kaa-mall-btn" style="width: auto;">Download History</button>
                </div>
                 <div class="recent-transactions-card purchase-history">
                    <h3>Purchase History</h3>
                    <div style="overflow-x: auto;">
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
                </div>

                <div class="recent-transactions-card wallet-transactions">
                    <h3>Wallet History</h3>
                     <div style="overflow-x: auto;">
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
             <!-- Bottom Navigation -->
            <div class="bottom-nav">
                <a href="https://kaadatahub.shop/users-portal-new/" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="#" class="nav-item active">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
                <?php if ( in_array( 'reseller', (array) $current_user->roles ) ) : ?>
                <a href="https://kaadatahub.shop/resellers-portal/" class="nav-item">
                    <i class="fas fa-store"></i>
                    <span>Reseller</span>
                </a>
                <?php else: ?>
                <a href="https://kaadatahub.shop/apply-as-a-reseller/" class="nav-item">
                    <i class="fas fa-briefcase"></i>
                    <span>Apply as a Reseller</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
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

        $current_user = wp_get_current_user();
        $history_page_url = '';
        $history_page = get_page_by_path( 'history' );
        if ( $history_page ) {
            $history_page_url = get_permalink( $history_page->ID );
        }

        ?>
        <div class="kaa-mall-portal-body">
            <?php if ( is_user_logged_in() ): ?>
            <!-- Sidebar -->
            <div class="kaa-mall-sidebar">
                <div class="sidebar-header">
                    <h2 class="brand-title">Kaadatahub</h2>
                    <button class="close-sidebar-btn">&times;</button>
                </div>
                <ul class="sidebar-nav">
                    <li class="nav-section-title">Services</li>
                    <li><a href="https://kaadatahub.shop/users-portal-new/"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-network="mtn"><i class="fas fa-mobile-alt"></i> MTN</a></li>
                    <li><a href="#" class="nav-link" data-network="airteltigo"><i class="fas fa-mobile-alt"></i> Airteltigo</a></li>
                    <li><a href="#" class="nav-link" data-network="vodafone"><i class="fas fa-mobile-alt"></i> Telecel</a></li>
                    <li><a href="#" class="nav-link" data-afa="true"><i class="fas fa-user-plus"></i> AFA Registration</a></li>

                    <li class="nav-section-title">Credits & Debits</li>
                    <li><a href="#" class="nav-link" data-wallet="true"><i class="fas fa-wallet"></i> Wallet</a></li>

                    <?php if ( in_array( 'reseller', (array) $current_user->roles ) ) : ?>
                    <li class="nav-section-title">Business</li>
                    <li><a href="https://kaadatahub.shop/resellers-portal/"><i class="fas fa-store"></i> Reseller</a></li>
                    <?php else: ?>
                    <li class="nav-section-title">Business</li>
                    <li><a href="https://kaadatahub.shop/apply-as-a-reseller/"><i class="fas fa-store"></i> Apply as a Reseller</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="sidebar-overlay"></div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="kaa-mall-main-content">
                 <div class="main-header">
                    <?php if ( is_user_logged_in() ): ?>
                    <button class="open-sidebar-btn"><i class="fas fa-bars"></i></button>
                    <div class="header-user-info">
                        <span>Hello, <?php echo esc_html( $current_user->display_name ); ?></span>
                    </div>
                    <div class="header-icons">
                        <div class="dark-mode-toggle">
                            <i class="fas fa-sun"></i>
                            <i class="fas fa-moon"></i>
                        </div>
                        <i class="fas fa-bell"></i>
                        <i class="fas fa-user" id="profile-icon"></i>
                    </div>
                    <div class="profile-popup" style="display: none;">
                        <div class="profile-header">
                            <span class="profile-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                            <button class="close-popup-btn">&times;</button>
                        </div>
                        <ul class="profile-menu">
                            <li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><i class="fas fa-user-circle"></i> My Profile</a></li>
                            <li><a href="#"><i class="fas fa-question-circle"></i> Support</a></li>
                            <li><a href="#"><i class="fas fa-cog"></i> Setting</a></li>
                            <li><a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
                        </ul>
                    </div>
                    <?php else: ?>
                        <h2 class="brand-title">Kaadatahub</h2>
                    <?php endif; ?>
                </div>

                <div id="kaa-mall-main-view">
                <?php if ( is_user_logged_in() ): ?>
                    <div class="balance-card">
                        <div class="balance-info">
                            <p>My Balance</p>
                            <h2 class="balance-amount">GH₵ 0.00</h2>
                        </div>
                        <div class="balance-actions">
                            <button class="hide-balance-btn">Hide</button>
                            <button class="top-up-btn">Top Up</button>
                        </div>
                    </div>

                    <div class="action-icons">
                         <div class="action-icon" data-action="buy-data">
                            <div class="icon-wrapper" style="background-color: #e6f7ff;">
                                <i class="fas fa-shopping-cart" style="color: #1890ff;"></i>
                            </div>
                            <span>Buy Data</span>
                        </div>
                        <div class="action-icon" data-action="topup">
                            <div class="icon-wrapper" style="background-color: #f9f0ff;">
                                <i class="fas fa-wallet" style="color: #722ed1;"></i>
                            </div>
                            <span>Topup</span>
                        </div>
                        <div class="action-icon" data-action="history">
                             <div class="icon-wrapper" style="background-color: #fff1f0;">
                                <i class="fas fa-history" style="color: #cf1322;"></i>
                            </div>
                            <span>History</span>
                        </div>
                        <div class="action-icon" data-action="refer">
                            <div class="icon-wrapper" style="background-color: #fffbe6;">
                                <i class="fas fa-users" style="color: #d48806;"></i>
                            </div>
                            <span>Refer</span>
                        </div>
                    </div>

                    <div class="sales-performance-card">
                        <h3>Sales Performance</h3>
                        <canvas id="sales-chart"></canvas>
                    </div>

                    <div class="recent-transactions-card">
                        <h3>Recent Transactions</h3>
                        <ul class="transactions-list">
                            <!-- Transactions will be loaded here by JS -->
                        </ul>
                    </div>
                <?php else:
                        // Render a default view for guests, e.g., the MTN form
                        echo $this->render_data_bundle_form('mtn');
                endif; ?>
                </div>

            </div>

            <!-- Bottom Navigation -->
            <?php if ( is_user_logged_in() ): ?>
            <div class="bottom-nav">
                <a href="https://kaadatahub.shop/users-portal-new/" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="<?php echo esc_url($history_page_url); ?>" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
                <?php if ( in_array( 'reseller', (array) $current_user->roles ) ) : ?>
                <a href="https://kaadatahub.shop/resellers-portal/" class="nav-item">
                    <i class="fas fa-store"></i>
                    <span>Reseller</span>
                </a>
                <?php else: ?>
                <a href="https://kaadatahub.shop/apply-as-a-reseller/" class="nav-item">
                    <i class="fas fa-briefcase"></i>
                    <span>Apply as a Reseller</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <?php
        return ob_get_clean();
    }
}
