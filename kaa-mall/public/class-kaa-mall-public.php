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
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css' );

            $js_file_path = plugin_dir_path( __FILE__ ) . 'js/kaa-mall-public.js';
            $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js';
            $js_version = filemtime( $js_file_path );

            wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), $this->version, false );
            wp_enqueue_script( 'kaa-mall-public', $js_file_url, array( 'jquery', 'paystack-inline' ), $js_version, false );

            $user = wp_get_current_user();
            wp_localize_script( 'kaa-mall-public', 'kaa_mall_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'paystack_public_key' => get_option( 'kaa_mall_paystack_public_key' ),
                'user_email' => is_user_logged_in() ? $user->user_email : '',
                'currency' => get_woocommerce_currency(),
                'nonce' => wp_create_nonce( 'kaa_mall_nonce' ),
                'is_user_logged_in' => is_user_logged_in(),
            ) );
        }
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
            if ( get_user_by( 'id', $reseller_id ) ) {
                WC()->session->set( 'kaa_mall_reseller_id', $reseller_id );
            }
        }

        add_filter('body_class', function($classes) {
            $classes[] = 'kaa-mall-portal-page';
            return $classes;
        });

        ob_start();
        ?>
        <div class="kaa-mall-portal-container">
            <header class="portal-header">
                <div class="menu-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="header-icons">
                    <a href="#" class="icon-circle"><i class="fas fa-sun"></i></a>
                    <a href="#" class="icon-circle"><i class="fas fa-bell"></i></a>
                    <a href="#" class="icon-circle"><i class="fas fa-user"></i></a>
                </div>
            </header>

            <main>
                <div class="info-card">
                    <h1>Data Bundle Store</h1>
                    <p>Instant Data Top-up for All Networks</p>
                </div>

                <?php if ( is_user_logged_in() ) : ?>
                <div class="form-card wallet-card">
                    <div class="balance-display">
                        <span>Wallet Balance</span>
                        <span class="balance-amount">GH₵<span class="wallet-balance-display">0.00</span></span>
                    </div>
                    <button class="btn-secondary top-up-btn">Top Up</button>
                </div>
                <?php endif; ?>

                <div class="form-card">
                    <div class="network-tabs">
                        <button class="tab-link active" data-network="mtn">MTN</button>
                        <button class="tab-link" data-network="airteltigo">AirtelTigo</button>
                        <button class="tab-link" data-network="telecel">Telecel</button>
                    </div>

                    <div id="mtn" class="network-tab-content active">
                        <?php $this->render_bundle_form('mtn', 'MTN'); ?>
                    </div>
                    <div id="airteltigo" class="network-tab-content">
                        <?php $this->render_bundle_form('airteltigo', 'AirtelTigo'); ?>
                    </div>
                    <div id="telecel" class="network-tab-content">
                        <?php $this->render_bundle_form('telecel', 'Telecel'); ?>
                    </div>
                </div>

                <?php if ( is_user_logged_in() ) : ?>
                <div class="form-card afa-registration">
                     <h3>AFA Bundle Registration</h3>
                     <p>Register for AFA bundles and get amazing benefits!</p>
                     <form id="kaa-mall-afa-form">
                         <div class="form-group">
                             <label for="full_name">Full Name</label>
                             <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter your full name" required>
                         </div>
                         <div class="form-group">
                             <label for="location">Location</label>
                             <input type="text" id="location" name="location" class="form-control" placeholder="Enter your location" required>
                         </div>
                         <div class="form-group">
                             <label for="ghana_card">ID Ghana Card Number</label>
                             <input type="text" id="ghana_card" name="ghana_card" class="form-control" placeholder="GHA-XXXXXXXXX-X" required>
                         </div>
                         <div class="balance-info">
                            <span>Registration Fee</span>
                            <span>GH₵13.00</span>
                        </div>
                         <button type="submit" class="btn-primary">Register Now - GH₵13</button>
                     </form>
                </div>

                <div class="form-card purchase-history">
                    <h3>Purchase History</h3>
                    <div class="table-responsive">
                        <table class="history-table">
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
                <?php endif; ?>
                 <div class="form-card need-help">
                    <h3>Need Help?</h3>
                    <p>Our support team is here to help you 24/7</p>
                    <a href="#" class="btn-primary contact-admin-btn">Contact the Admin</a>
                </div>
            </main>

            <footer class="secured-footer">
                <p><i class="fas fa-lock"></i> Secured by hubnet</p>
            </footer>

            <!-- Success Modal -->
            <div id="success-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Thank You!</h2>
                    <p>Your order was successful. Please check your purchase history for the receipt.</p>
                    <button class="btn-primary" id="close-modal-btn">Go to Dashboard</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_bundle_form($network, $network_name) {
        ?>
        <form class="bundle-form" data-network="<?php echo esc_attr($network); ?>">
            <?php if ( ! is_user_logged_in() ) : ?>
                <div class="form-group">
                    <label for="email_<?php echo esc_attr($network); ?>">Your Email *</label>
                    <input type="email" id="email_<?php echo esc_attr($network); ?>" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="phone_number_<?php echo esc_attr($network); ?>"><?php echo esc_html($network_name); ?> Phone Number *</label>
                <input type="tel" id="phone_number_<?php echo esc_attr($network); ?>" name="phone_number" class="form-control" placeholder="0241234567" required>
            </div>
            <div class="form-group">
                <label for="bundle_package_<?php echo esc_attr($network); ?>">Choose a Menu *</label>
                <select id="bundle_package_<?php echo esc_attr($network); ?>" name="bundle" class="form-control" required>
                     <option value="">Select package</option>
                </select>
            </div>

            <div class="balance-info">
                 <span>Available balance : GH₵<span class="wallet-balance-display">0.00</span></span>
                 <span class="info-icon">i</span>
             </div>

            <div class="form-group payment-method">
                <label>Payment Method</label>
                <div class="payment-options">
                    <?php if ( is_user_logged_in() ) : ?>
                    <label><input type="radio" name="payment_method_<?php echo esc_attr($network); ?>" value="wallet" checked> Wallet Balance</label>
                    <?php endif; ?>
                    <label><input type="radio" name="payment_method_<?php echo esc_attr($network); ?>" value="paystack" <?php echo ! is_user_logged_in() ? 'checked' : ''; ?>> Paystack</label>
                </div>
            </div>

            <button type="submit" class="btn-primary">Continue</button>
        </form>
        <?php
    }
}
