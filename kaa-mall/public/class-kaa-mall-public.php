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

    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'kaa_user_portal' ) ) {
            $css_file_path = plugin_dir_path( __FILE__ ) . 'css/kaa-mall-portal-redesign.css';
            $css_file_url = plugin_dir_url( __FILE__ ) . 'css/kaa-mall-portal-redesign.css';
            $css_version = filemtime( $css_file_path );
            wp_enqueue_style( 'kaa-mall-portal-redesign', $css_file_url, array(), $css_version );
        }

        $js_file_path = plugin_dir_path( __FILE__ ) . 'js/kaa-mall-public.js';
        $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js';
        $js_version = filemtime( $js_file_path );

        wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), $this->version, false );
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', false );
        wp_enqueue_script( 'kaa-mall-public', $js_file_url, array( 'jquery', 'paystack-inline', 'chart-js' ), $js_version, false );

        $user = wp_get_current_user();
        wp_localize_script( 'kaa-mall-public', 'kaa_mall_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'paystack_public_key' => get_option( 'kaa_mall_paystack_public_key' ),
            'user_email' => $user->user_email,
            'currency' => get_woocommerce_currency(),
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

    private function get_product_by_stored_id( $product_key ) {
        $option_name = 'kaa_mall_' . str_replace( '-', '_', sanitize_title( $product_key ) ) . '_product_id';
        $product_id = get_option( $option_name );
        if ( ! $product_id ) {
            return null;
        }
        return wc_get_product( $product_id );
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

        // Trim the selected bundle to avoid validation issues
        $bundle = trim( $bundle );

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

            $product = $this->get_product_by_stored_id( 'Wallet Top-up' );
            if ( $product ) {
                $order = wc_create_order();
                $order->set_customer_id( $user_id );
                $order->add_product( $product, 1, array( 'subtotal' => $amount, 'total' => $amount ) );
                $order->set_total( $amount );
                $order->calculate_totals();
                $order->payment_complete();
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

        // Trim the selected bundle to avoid validation issues
        $bundle = trim( $bundle );

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

        $product = $this->get_product_by_stored_id( 'Data Bundle' );
        if ( $product ) {
            $order = wc_create_order();
            $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
            $order->set_total( $final_price );
            $order->calculate_totals();
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

        // Trim the selected bundle to avoid validation issues
        $bundle = trim( $bundle );

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

            $product = $this->get_product_by_stored_id( 'Data Bundle' );
            if ( $product ) {
                $order = wc_create_order();

                if ( $user_id ) {
                    $order->set_customer_id( $user_id );
                } else {
                    $order->set_billing_email( $email );
                }

                $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
                $order->set_total( $final_price );
                $order->calculate_totals();
                $order->payment_complete();
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

        $product = $this->get_product_by_stored_id( 'AFA Registration' );
        if ( $product ) {
            $order = wc_create_order();
            $order->set_customer_id( $user_id );
            $order->add_product( $product, 1, array( 'subtotal' => $final_price, 'total' => $final_price ) );
            $order->set_total( $final_price );
            $order->calculate_totals();
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
            if ( get_user_by( 'id', $reseller_id ) ) {
                WC()->session->set( 'kaa_mall_reseller_id', $reseller_id );
            }
        }

        if ( ! is_user_logged_in() && ! WC()->session->get( 'kaa_mall_reseller_id' ) ) {
            ob_start();
            ?>
            <div class="kaa-mall-portal" style="max-width: 400px; margin: 40px auto; padding: 20px; background-color: #1e1e1e; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.5);">
                <h3 style="text-align: center; color: #ffffff; margin-bottom: 20px;">Please log in to access the portal.</h3>
                <?php wp_login_form( array('redirect' => get_permalink()) ); ?>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        ?>
        <div class="kaa-mall-portal-body">
            <!-- Sidebar -->
            <div class="kaa-mall-sidebar">
                <div class="sidebar-header">
                    <h2>Kaadatahub</h2>
                    <button class="close-sidebar-btn">&times;</button>
                </div>
                <nav class="sidebar-nav">
                    <p class="nav-section-title">SERVICES</p>
                    <ul>
                        <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="#" class="nav-link" data-target="buy-data-mtn"><i class="fas fa-mobile-alt"></i> MTN</a></li>
                        <li><a href="#" class="nav-link" data-target="buy-data-vodafone"><i class="fas fa-mobile-alt"></i> Vodafone</a></li>
                        <li><a href="#" class="nav-link" data-target="buy-data-airteltigo"><i class="fas fa-mobile-alt"></i> Airteltigo</a></li>
                        <li><a href="#" class="nav-link" data-target="buy-data-telecel"><i class="fas fa-mobile-alt"></i> Telecel</a></li>
                        <li><a href="#" class="nav-link" data-target="afa-registration"><i class="fas fa-user-plus"></i> AFA Registration</a></li>
                    </ul>
                    <p class="nav-section-title">CREDITS & DEBITS</p>
                    <ul>
                        <li><a href="#" class="nav-link" data-target="wallet"><i class="fas fa-wallet"></i> Wallet</a></li>
                    </ul>
                    <?php
                    $user = wp_get_current_user();
                    if ( in_array( 'reseller', (array) $user->roles ) ) :
                    ?>
                    <p class="nav-section-title">RESELLER</p>
                    <ul>
                        <li><a href="/reseller-portal" class="nav-link"><i class="fas fa-user-tie"></i> Reseller Dashboard</a></li>
                    </ul>
                    <?php else : ?>
                    <p class="nav-section-title">RESELLER</p>
                    <ul>
                        <li><a href="https://kaadatahub.shop/apply-as-a-reseller/" class="nav-link"><i class="fas fa-user-tie"></i> Apply to be a Reseller</a></li>
                    </ul>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="kaa-mall-main-content">
                <!-- Header -->
                <header class="kaa-mall-portal-header">
                    <button class="hamburger-menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-icons">
                        <i class="fas fa-bell"></i>
                        <i class="fas fa-user-circle"></i>
                    </div>
                </header>

                <main class="portal-main">
                    <!-- Balance Card -->
                    <div class="kaa-mall-card balance-card">
                        <div class="balance-info">
                            <p>My Balance</p>
                            <h2 class="balance-amount">GH₵ 0.00</h2>
                        </div>
                        <div class="balance-actions">
                            <button class="hide-balance-btn">Hide</button>
                            <button class="top-up-btn">Top Up</button>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="kaa-mall-card quick-actions">
                        <a href="#" class="action-item" data-target="buy-data">
                            <div class="icon-wrapper" style="background-color: #E6F3FF;">
                                <i class="fas fa-shopping-cart" style="color: #007BFF;"></i>
                            </div>
                            <span>Buy Data</span>
                        </a>
                        <a href="#" class="action-item" data-target="topup">
                            <div class="icon-wrapper" style="background-color: #E4F8F0;">
                                <i class="fas fa-wallet" style="color: #28A745;"></i>
                            </div>
                            <span>Topup</span>
                        </a>
                        <a href="#" class="action-item" data-target="history">
                            <div class="icon-wrapper" style="background-color: #FFF2E6;">
                                <i class="fas fa-history" style="color: #FD7E14;"></i>
                            </div>
                            <span>History</span>
                        </a>
                        <a href="#" class="action-item" data-target="refer">
                             <div class="icon-wrapper" style="background-color: #F0E6FF;">
                                <i class="fas fa-users" style="color: #6F42C1;"></i>
                            </div>
                            <span>Refer</span>
                        </a>
                    </div>

                    <!-- Sales Performance -->
                    <div class="kaa-mall-card sales-performance">
                        <h3>Sales Performance</h3>
                        <canvas id="salesChart"></canvas>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="kaa-mall-card recent-transactions">
                        <h3>Recent Transactions</h3>
                        <div class="transactions-list">
                            <p>No recent transactions found.</p>
                        </div>
                    </div>

                    <!-- Hidden Data Purchase Forms -->
                    <div id="buy-data-mtn" class="kaa-mall-card data-bundles" style="display: none;">
                        <h3>MTN Bundles</h3>
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
                            <div class="payment-method">
                                <?php if ( is_user_logged_in() ) : ?>
                                <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                                <?php endif; ?>
                                <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                            </div>
                            <button type="submit">Buy MTN Bundle</button>
                        </form>
                    </div>

                    <div id="buy-data-airteltigo" class="kaa-mall-card data-bundles" style="display: none;">
                        <h3>AirtelTigo Bundles</h3>
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
                            <div class="payment-method">
                                <?php if ( is_user_logged_in() ) : ?>
                                <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                                <?php endif; ?>
                                <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                            </div>
                            <button type="submit">Buy AirtelTigo Bundle</button>
                        </form>
                    </div>

                    <div id="buy-data-vodafone" class="kaa-mall-card data-bundles" style="display: none;">
                        <h3>Vodafone Bundles</h3>
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
                            <div class="payment-method">
                                <?php if ( is_user_logged_in() ) : ?>
                                <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                                <?php endif; ?>
                                <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                            </div>
                            <button type="submit">Buy Vodafone Bundle</button>
                        </form>
                    </div>

                    <div id="buy-data-telecel" class="kaa-mall-card data-bundles" style="display: none;">
                        <h3>Telecel Bundles</h3>
                        <form class="bundle-form" data-network="telecel">
                            <?php if ( ! is_user_logged_in() ) : ?>
                                <label>Your Email</label>
                                <input type="email" name="email" placeholder="Enter your email" required>
                            <?php endif; ?>
                            <label>Telecel Phone Number</label>
                            <input type="tel" name="phone_number" placeholder="0241234567" required>
                            <label>Select Bundle</label>
                            <select name="bundle" required></select>
                            <label>Payment Method</label>
                            <div class="payment-method">
                                <?php if ( is_user_logged_in() ) : ?>
                                <label><input type="radio" name="payment_method" value="wallet" checked> Wallet Balance</label>
                                <?php endif; ?>
                                <label><input type="radio" name="payment_method" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack (Card/Mobile Money)</label>
                            </div>
                            <button type="submit">Buy Telecel Bundle</button>
                        </form>
                    </div>

                    <!-- Hidden AFA Form -->
                     <?php if ( is_user_logged_in() ) : ?>
                    <div id="afa-registration-form" class="kaa-mall-card afa-registration" style="display: none;">
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
                    <?php endif; ?>

                </main>
            </div>

            <!-- Bottom Navigation -->
            <nav class="kaa-mall-bottom-nav">
                <a href="#" class="nav-item active" data-target="home">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="#" class="nav-item" data-target="history">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
                 <a href="#" class="nav-item" data-target="services">
                    <i class="fas fa-briefcase"></i>
                    <span>Services</span>
                </a>
                <a href="#" class="nav-item" data-target="profile">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </nav>
        </div>
        <?php
        return ob_get_clean();
    }
}
