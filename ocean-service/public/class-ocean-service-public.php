<?php

class Ocean_Service_Public {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_filter( 'woocommerce_get_price_html', array( $this, 'custom_price_html' ), 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_agent_price_to_cart' ), 10, 1 );

        add_shortcode( 'ocean_agent_dashboard', array( $this, 'render_agent_dashboard' ) );
        add_shortcode( 'ocean_wallet_topup', array( $this, 'render_wallet_topup_form' ) );
        add_shortcode( 'ocean_order_history', array( $this, 'render_order_history' ) );
        add_shortcode( 'ocean_bundles', array( $this, 'render_bundle_purchase_ui' ) );
        add_shortcode( 'ocean_afa_registration', array( $this, 'render_afa_registration_form' ) );

        add_action( 'template_redirect', array( $this, 'handle_wallet_topup_submission' ) );
        add_action( 'template_redirect', array( $this, 'handle_bundle_purchase' ) );
        add_action( 'template_redirect', array( $this, 'handle_afa_submission' ) );
        add_action( 'init', array( $this, 'handle_paystack_callback' ) );
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_wallet_gateway' ) );

        // Display beneficiary number in cart and checkout
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_beneficiary_in_cart' ), 10, 2 );
        // Save beneficiary number to order item meta
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_beneficiary_to_order_item' ), 10, 4 );

        add_action( 'wp_footer', array( $this, 'add_floating_whatsapp_button' ) );
    }

    public function add_floating_whatsapp_button() {
        $whatsapp_number = get_option( 'ocean_service_whatsapp_number' );
        if ( empty( $whatsapp_number ) ) {
            return;
        }
        ?>
        <a href="https://wa.me/<?php echo esc_attr( $whatsapp_number ); ?>" class="ocean-whatsapp-button" target="_blank">
            <!-- You can use an SVG or an image here for the icon -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M.323 12.022c0 2.15.58 4.14 1.636 5.924L.435 23.565l5.823-1.528a11.93 11.93 0 005.742 1.485h.005c6.602 0 11.95-5.348 11.95-11.95S18.527.127 11.925.127.323 5.42 .323 12.022zm7.042 9.002a9.922 9.922 0 01-5.01-1.38l-.357-.212-3.734.98 1.002-3.648-.232-.372a9.922 9.922 0 01-1.51-5.37C.145 6.627 5.42 1.352 12.023 1.352c6.601 0 11.875 5.275 11.875 11.874 0 6.6-5.274 11.875-11.875 11.875h-.005a9.922 9.922 0 01-4.636-1.18zM8.32 6.915c-.24-.538-.485-.55-1.02-.55h-.32c-.443 0-.75.112-.75.55s-1.02 2.37-1.02 4.098c0 1.728 1.02 3.03 1.155 3.248.134.218.788 1.275 2.68 2.27.42.22.75.28.98.28.37 0 .93-.11.93-.8s-1.02-1.18-1.02-1.28c0-.1 0-.19.09-.28.09-.09.21-.13.3-.13.1 0 .22.04.3.09.09.04.4.2.8.35.4.15.65.2.75.25.1.05.15.1.15.2s0 .4-.04.8c-.05.4-.2.75-.3.85-.1.1-.25.15-.35.15s-.4-.05-.8-.2c-.4-.15-1.2-.55-2.05-1.25-.85-.7-1.4-1.4-1.55-1.65-.15-.25-.3-.4-.3-.55s0-.25.04-.35c.05-.1.1-.15.15-.2s.15-.04.2-.04c.05 0 .1 0 .15.04s.25-.3.35-.4c.1-.1.15-.2.1-.3s-.1-.2-.2-.25z"></path></svg>
        </a>
        <style>
            .ocean-whatsapp-button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background-color: #25D366;
                color: white;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 1000;
            }
        </style>
        <?php
    }

    public function handle_paystack_callback() {
        if ( isset( $_GET['paystack_ref'] ) ) {
            $reference = sanitize_text_field( $_GET['paystack_ref'] );
            $secret_key = get_option( 'ocean_service_paystack_secret_key' );

            $response = wp_remote_get( 'https://api.paystack.co/transaction/verify/' . $reference, array(
                'headers' => array( 'Authorization' => 'Bearer ' . $secret_key )
            ) );

            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ) );
                if ( $body->status && $body->data->status === 'success' ) {
                    $amount = $body->data->amount / 100; // Amount is in kobo
                    $user = get_user_by( 'email', $body->data->customer->email );

                    if ( $user ) {
                        Ocean_Service_Wallet_Manager::add_funds( $user->ID, $amount );
                        Ocean_Service_Wallet_Manager::log_transaction( $user->ID, $amount, 'credit', 'Paystack top-up: ' . $reference );
                        Ocean_Service_Emails::send_wallet_topup_confirmation( $user->ID, $amount );

                        // Redirect to a success page
                        wp_redirect( add_query_arg( 'topup_success', 'true', get_permalink( get_page_by_path('wallet-top-up') ) ) );
                        exit;
                    }
                }
            }
        }
    }

    public function render_afa_registration_form() {
        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data">
            <h2>AFA Registration</h2>
            <p><label>Full Name <input type="text" name="full_name" required></label></p>
            <p><label>Email <input type="email" name="email" required></label></p>
            <p><label>ID Front <input type="file" name="id_front" accept="image/*" required></label></p>
            <p><label>ID Back <input type="file" name="id_back" accept="image/*" required></label></p>
            <?php wp_nonce_field( 'afa_submission_nonce', 'afa_submission_nonce_field' ); ?>
            <p><input type="submit" name="submit_afa" value="Submit"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_afa_submission() {
        if ( isset( $_POST['submit_afa'] ) && wp_verify_nonce( $_POST['afa_submission_nonce_field'], 'afa_submission_nonce' ) ) {
            $full_name = sanitize_text_field( $_POST['full_name'] );
            $email = sanitize_email( $_POST['email'] );

            $post_id = wp_insert_post( array(
                'post_title'   => 'AFA Submission from ' . $full_name,
                'post_status'  => 'pending',
                'post_type'    => 'afa_submission',
            ) );

            if ( $post_id ) {
                add_post_meta( $post_id, 'full_name', $full_name );
                add_post_meta( $post_id, 'email', $email );

                if ( ! empty( $_FILES['id_front']['name'] ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    $id_front = wp_handle_upload( $_FILES['id_front'], array( 'test_form' => false ) );
                    if ( $id_front && ! isset( $id_front['error'] ) ) {
                        add_post_meta( $post_id, 'id_front_url', $id_front['url'] );
                    }
                }
                if ( ! empty( $_FILES['id_back']['name'] ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    $id_back = wp_handle_upload( $_FILES['id_back'], array( 'test_form' => false ) );
                    if ( $id_back && ! isset( $id_back['error'] ) ) {
                        add_post_meta( $post_id, 'id_back_url', $id_back['url'] );
                    }
                }
                wp_redirect( add_query_arg( 'afa_submitted', 'true', $_SERVER['REQUEST_URI'] ) );
                exit;
            }
        }
    }

    public function display_beneficiary_in_cart( $item_data, $cart_item ) {
        if ( isset( $cart_item['beneficiary_number'] ) ) {
            $item_data[] = array(
                'key'     => 'Beneficiary',
                'value'   => wc_clean( $cart_item['beneficiary_number'] ),
                'display' => '',
            );
        }
        return $item_data;
    }

    public function save_beneficiary_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['beneficiary_number'] ) ) {
            $item->add_meta_data( 'Beneficiary Number', $values['beneficiary_number'] );
        }
    }

    public function handle_bundle_purchase() {
        if ( isset( $_POST['ocean_buy_now'] ) && wp_verify_nonce( $_POST['ocean_bundle_nonce'], 'ocean_bundle_purchase' ) ) {
            $product_id = absint( $_POST['product_id'] );
            $beneficiary_number = sanitize_text_field( $_POST['beneficiary_number'] );

            // You might want to add more validation for the phone number here
            if ( ! empty( $product_id ) && ! empty( $beneficiary_number ) ) {
                WC()->cart->empty_cart();
                WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'beneficiary_number' => $beneficiary_number ) );
                wp_redirect( wc_get_checkout_url() );
                exit;
            }
        }
    }

    public function render_bundle_purchase_ui() {
        ob_start();

        // Define the categories to display
        $category_slugs = array( 'mtn', 'airteltigo', 'telecel' );
        $product_cats = get_terms( 'product_cat', array( 'slug' => $category_slugs, 'hide_empty' => false ) );

        if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) {
            echo '<div class="ocean-bundles-container">';

            // Basic tab navigation
            echo '<ul class="ocean-tabs">';
            foreach( $product_cats as $key => $category ) {
                echo '<li class="tab-link ' . ( $key == 0 ? 'current' : '' ) . '" data-tab="tab-' . esc_attr( $category->slug ) . '">' . esc_html( $category->name ) . '</li>';
            }
            echo '</ul>';

            foreach( $product_cats as $key => $category ) {
                echo '<div id="tab-' . esc_attr( $category->slug ) . '" class="tab-content ' . ( $key == 0 ? 'current' : '' ) . '">';

                $products = wc_get_products( array( 'category' => array( $category->slug ) ) );

                if ( ! empty( $products ) ) {
                    echo '<div class="bundle-list">';
                    foreach ( $products as $product ) {
                        echo '<div class="bundle-item">';
                        echo '<h3>' . esc_html( $product->get_name() ) . '</h3>';
                        echo '<p class="price">' . $product->get_price_html() . '</p>';
                        echo '<form method="post">';
                        echo '<input type="hidden" name="product_id" value="' . esc_attr( $product->get_id() ) . '">';
                        echo '<input type="tel" name="beneficiary_number" placeholder="Enter phone number" required>';
                        wp_nonce_field( 'ocean_bundle_purchase', 'ocean_bundle_nonce' );
                        echo '<button type="submit" name="ocean_buy_now">Buy Now</button>';
                        echo '</form>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p>No bundles available in this category.</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>Bundle categories not found.</p>';
        }

        // Basic JS for tabs
        wc_enqueue_js("
            jQuery('.ocean-tabs .tab-link').on('click', function(){
                var tab_id = jQuery(this).attr('data-tab');

                jQuery('.ocean-tabs .tab-link').removeClass('current');
                jQuery('.tab-content').removeClass('current');

                jQuery(this).addClass('current');
                jQuery('#'+tab_id).addClass('current');
            });
        ");

        return ob_get_clean();
    }

    public function render_order_history() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to view your order history.</p>';
        }

        ob_start();

        $customer_orders = wc_get_orders( array(
            'customer' => get_current_user_id(),
            'status'   => array_keys( wc_get_order_statuses() ),
        ) );

        if ( $customer_orders ) {
            ?>
            <table class="shop_table shop_table_responsive my_account_orders">
                <thead>
                    <tr>
                        <th class="order-number"><span class="nobr">Order</span></th>
                        <th class="order-date"><span class="nobr">Date</span></th>
                        <th class="order-status"><span class="nobr">Status</span></th>
                        <th class="order-total"><span class="nobr">Total</span></th>
                        <th class="order-actions"><span class="nobr">&nbsp;</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $customer_orders as $order ) : ?>
                        <tr class="order">
                            <td class="order-number" data-title="Order">
                                <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
                                    <?php echo _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number(); ?>
                                </a>
                            </td>
                            <td class="order-date" data-title="Date">
                                <time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>
                            </td>
                            <td class="order-status" data-title="Status" style="white-space:nowrap;">
                                <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                            </td>
                            <td class="order-total" data-title="Total">
                                <?php echo wp_kses_post( sprintf( _n( '%1$s for %2$s item', '%1$s for %2$s items', $order->get_item_count(), 'woocommerce' ), $order->get_formatted_order_total(), $order->get_item_count() ) ); ?>
                            </td>
                            <td class="order-actions">
                                <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button view">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p>No orders found.</p>';
        }

        return ob_get_clean();
    }

    public function filter_wallet_gateway( $gateways ) {
        if ( ! $this->is_user_agent() ) {
            unset( $gateways['ocean_wallet'] );
        }
        return $gateways;
    }

    public function handle_wallet_topup_submission() {
        if ( isset( $_POST['ocean_submit_topup'] ) && wp_verify_nonce( $_POST['ocean_wallet_topup_nonce_field'], 'ocean_wallet_topup_nonce' ) ) {
            $amount = floatval( sanitize_text_field( $_POST['ocean_topup_amount'] ) );
            $proof = sanitize_textarea_field( $_POST['ocean_topup_proof'] );
            $user_id = get_current_user_id();

            // Create a custom post type for top-up requests
            $post_id = wp_insert_post( array(
                'post_title'   => 'Wallet Top-Up Request - User ' . $user_id,
                'post_content' => 'Proof: ' . $proof,
                'post_status'  => 'pending',
                'post_type'    => 'wallet_topup_request',
                'post_author'  => $user_id,
            ) );

            if ( $post_id ) {
                update_post_meta( $post_id, '_topup_amount', $amount );
                // Redirect to a confirmation page or show a message
                wp_redirect( add_query_arg( 'topup_submitted', 'true', $_SERVER['REQUEST_URI'] ) );
                exit;
            }
        }
    }

    public function render_wallet_topup_form() {
        if ( ! is_user_logged_in() || ! $this->is_user_agent() ) {
            return '<p>You must be logged in as an agent to top up your wallet.</p>';
        }

        ob_start();
        ?>
        <div class="ocean-wallet-topup">
            <h2><?php _e( 'Wallet Top-Up', 'ocean-service' ); ?></h2>

            <div class="topup-options">
                <!-- Paystack Form -->
                <div class="paystack-form">
                    <h3>Pay with Paystack</h3>
                    <form class="ocean-paystack-form">
                        <p>
                            <label for="ocean_topup_amount"><?php _e( 'Amount', 'ocean-service' ); ?></label>
                            <input type="number" id="ocean_topup_amount" name="ocean_topup_amount" min="1" step="0.01" required />
                        </p>
                        <p>
                            <label for="ocean_topup_email"><?php _e( 'Your Email', 'ocean-service' ); ?></label>
                            <input type="email" id="ocean_topup_email" name="ocean_topup_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required />
                        </p>
                        <p>
                            <button type="submit"><?php _e( 'Pay Now', 'ocean-service' ); ?></button>
                        </p>
                    </form>
                </div>

                <!-- Manual Transfer Form -->
                <div class="manual-transfer-form">
                    <h3>Manual Bank Transfer</h3>
                    <form method="post">
                         <p>
                            <label for="ocean_manual_amount"><?php _e( 'Amount', 'ocean-service' ); ?></label>
                            <input type="number" id="ocean_manual_amount" name="ocean_topup_amount" min="1" step="0.01" required />
                        </p>
                        <p>
                            <label for="ocean_topup_proof"><?php _e( 'Proof of Payment (Transaction ID, etc.)', 'ocean-service' ); ?></label>
                            <textarea id="ocean_topup_proof" name="ocean_topup_proof" rows="4" required></textarea>
                        </p>
                        <p>
                            <?php wp_nonce_field( 'ocean_wallet_topup_nonce', 'ocean_wallet_topup_nonce_field' ); ?>
                            <input type="submit" name="ocean_submit_topup" value="<?php _e( 'Submit for Review', 'ocean-service' ); ?>" />
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_agent_dashboard() {
        if ( ! $this->is_user_agent() ) {
            return '<p>You are not authorized to view this page.</p>';
        }

        ob_start();
        $user_id = get_current_user_id();
        $balance = Ocean_Service_Wallet_Manager::get_balance( $user_id );

        global $wpdb;
        $table_name = $wpdb->prefix . 'ocean_service_wallet_transactions';
        $transactions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 10", $user_id ) );
        ?>
        <div class="ocean-agent-dashboard">
            <h1><?php _e( 'Agent Dashboard', 'ocean-service' ); ?></h1>

            <div class="wallet-balance">
                <h3><?php _e( 'Your Wallet Balance', 'ocean-service' ); ?></h3>
                <p><?php echo wc_price( $balance ); ?></p>
            </div>

            <div class="wallet-transactions">
                <h3><?php _e( 'Recent Transactions', 'ocean-service' ); ?></h3>
                <table class="shop_table shop_table_responsive">
                    <thead>
                        <tr>
                            <th><?php _e( 'Date', 'ocean-service' ); ?></th>
                            <th><?php _e( 'Amount', 'ocean-service' ); ?></th>
                            <th><?php _e( 'Type', 'ocean-service' ); ?></th>
                            <th><?php _e( 'Description', 'ocean-service' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $transactions ) ) : ?>
                            <?php foreach ( $transactions as $transaction ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_format( date_create( $transaction->created_at ), 'Y-m-d H:i:s' ) ); ?></td>
                                    <td><?php echo wc_price( $transaction->amount ); ?></td>
                                    <td><?php echo esc_html( ucfirst( $transaction->type ) ); ?></td>
                                    <td><?php echo esc_html( $transaction->description ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4"><?php _e( 'No transactions found.', 'ocean-service' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function custom_price_html( $price, $product ) {
        if ( $this->is_user_agent() && $product->get_meta('_agent_price') ) {
            $agent_price = $product->get_meta('_agent_price');
            return wc_price( $agent_price );
        }
        return $price;
    }

    public function apply_agent_price_to_cart( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! $this->is_user_agent() ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ( $product->get_meta('_agent_price') ) {
                $agent_price = $product->get_meta('_agent_price');
                $cart_item['data']->set_price( $agent_price );
            }
        }
    }

    private function is_user_agent() {
        $user = wp_get_current_user();
        return in_array( 'agent', (array) $user->roles );
    }

    public function enqueue_styles() {
        wp_enqueue_style( 'ocean-service-public', OCEAN_SERVICE_PLUGIN_URL . 'assets/css/public.css', array(), OCEAN_SERVICE_VERSION, 'all' );
    }

    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ocean_wallet_topup' ) ) {
            wp_enqueue_script( 'paystack-js', 'https://js.paystack.co/v1/inline.js', array( 'jquery' ), null, true );

            $script = "
                jQuery(document).on('submit', 'form.ocean-paystack-form', function(e) {
                    e.preventDefault();

                    var amount = jQuery('#ocean_topup_amount').val() * 100; // Amount in kobo
                    var email = jQuery('#ocean_topup_email').val();
                    var publicKey = '" . esc_js( get_option('ocean_service_paystack_public_key') ) . "';

                    var handler = PaystackPop.setup({
                        key: publicKey,
                        email: email,
                        amount: amount,
                        callback: function(response){
                            // Handle the response
                            var redirect_url = new URL('" . esc_url( $_SERVER['REQUEST_URI'] ) . "');
                            redirect_url.searchParams.set('paystack_ref', response.reference);
                            window.location.href = redirect_url.href;
                        },
                        onClose: function(){
                            alert('Transaction was not completed, window closed.');
                        }
                    });
                    handler.openIframe();
                });
            ";
            wp_add_inline_script( 'paystack-js', $script );
        }
    }
}
