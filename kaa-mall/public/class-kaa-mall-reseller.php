<?php

class Kaa_Mall_Reseller {

    public function __construct() {
        add_shortcode( 'kaa_reseller_portal', array( $this, 'render_reseller_portal' ) );
        add_shortcode( 'kaa_reseller_apply', array( $this, 'render_apply_form' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_kaa_mall_save_reseller_prices', array( $this, 'save_reseller_prices' ) );
        add_action( 'wp_ajax_kaa_mall_save_shop_name', array( $this, 'save_shop_name' ) );
        add_action( 'wp_ajax_kaa_mall_save_whatsapp_number', array( $this, 'save_whatsapp_number' ) );
        add_action( 'wp_ajax_kaa_mall_save_whatsapp_group_link', array( $this, 'save_whatsapp_group_link' ) );
        add_action( 'wp_ajax_kaa_mall_request_withdrawal', array( $this, 'request_withdrawal' ) );
        add_action( 'wp_ajax_kaa_mall_submit_reseller_application', array( $this, 'submit_reseller_application' ) );
        add_action( 'wp_ajax_kaa_mall_get_reseller_analytics', array( $this, 'get_reseller_analytics' ) );
    }

    public function get_reseller_analytics() {
        check_ajax_referer( 'kaa_mall_reseller_nonce', 'nonce' );

        if ( ! current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $reseller_id = get_current_user_id();

        // Daily Sales Data
        $daily_sales = array();
        for ( $i = 6; $i >= 0; $i-- ) {
            $date = date( 'Y-m-d', strtotime( "-$i days" ) );
            $daily_sales[ date( 'D', strtotime( $date ) ) ] = 0;
        }

        $daily_orders = wc_get_orders( array(
            'limit' => -1,
            'status' => array( 'wc-completed', 'wc-processing' ),
            'date_created' => '>=' . date( 'Y-m-d', strtotime( '-6 days' ) ),
            'meta_key' => '_reseller_id',
            'meta_value' => $reseller_id,
        ) );

        foreach ( $daily_orders as $order ) {
            $date_key = date( 'D', strtotime( $order->get_date_created() ) );
            if ( isset( $daily_sales[ $date_key ] ) ) {
                $daily_sales[ $date_key ] += $order->get_total();
            }
        }

        // Weekly Sales Data
        $weekly_sales = array();
        for ( $i = 3; $i >= 0; $i-- ) {
            $date = strtotime( "-$i week" );
            $weekly_sales[ 'Week ' . date( 'W', $date ) ] = 0;
        }

        $weekly_orders = wc_get_orders( array(
            'limit' => -1,
            'status' => array( 'wc-completed', 'wc-processing' ),
            'date_created' => '>=' . date( 'Y-m-d', strtotime( '-3 weeks' ) ),
            'meta_key' => '_reseller_id',
            'meta_value' => $reseller_id,
        ) );

        foreach ( $weekly_orders as $order ) {
            $week_key = 'Week ' . date( 'W', strtotime( $order->get_date_created() ) );
            if ( isset( $weekly_sales[ $week_key ] ) ) {
                $weekly_sales[ $week_key ] += $order->get_total();
            }
        }

        wp_send_json_success( array(
            'daily_sales' => $daily_sales,
            'weekly_sales' => $weekly_sales,
        ) );
    }

    public function submit_reseller_application() {
        check_ajax_referer( 'kaa_mall_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to apply.' ) );
        }

        $user_id = get_current_user_id();

        if ( current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You are already a reseller.' ) );
        }

        $existing_application = get_posts( array(
            'post_type' => 'reseller_application',
            'author' => $user_id,
            'post_status' => 'pending',
        ) );

        if ( ! empty( $existing_application ) ) {
            wp_send_json_error( array( 'message' => 'You already have a pending application.' ) );
        }

        $reference = sanitize_text_field( $_POST['reference'] );
        $secret_key = get_option( 'kaa_mall_paystack_secret_key' );
        $application_fee = floatval( get_option( 'kaa_mall_reseller_application_fee', '10' ) );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: Bearer $secret_key",
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
            $amount_paid = $result->data->amount / 100;

            if ( $amount_paid < $application_fee ) {
                wp_send_json_error( array( 'message' => 'The amount paid is incorrect.' ) );
            }

            $post_id = wp_insert_post( array(
                'post_title' => 'Reseller Application from ' . wp_get_current_user()->display_name,
                'post_type' => 'reseller_application',
                'post_status' => 'pending',
                'post_author' => $user_id,
            ) );

            if ( $post_id ) {
                update_post_meta( $post_id, '_paystack_reference', $reference );
                $admin_email = get_option( 'admin_email' );
                $subject = 'New Reseller Application';
                $message = 'A new reseller application has been submitted by ' . wp_get_current_user()->display_name . '. Please review it in the admin dashboard.';
                wp_mail( $admin_email, $subject, $message );
                wp_send_json_success( array( 'message' => 'Your application has been submitted. You will be notified once it has been reviewed.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'There was an error submitting your application. Please try again.' ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Transaction verification failed.' ) );
        }
    }

    public function enqueue_scripts() {
        if ( is_page() || is_single() ) { // Basic check to see if we are on a page that might contain the shortcode
            global $post;
            if ( ! is_a( $post, 'WP_Post' ) ) {
                return;
            }
            if ( has_shortcode( $post->post_content, 'kaa_reseller_portal' ) ) {
                 // Enqueue new theme styles and public JS for sidebar
                wp_enqueue_style( 'kaa-mall-portal-redesign', plugin_dir_url( __FILE__ ) . 'css/kaa-mall-portal-redesign.css', array(), '1.0.0' );
                wp_enqueue_script( 'kaa-mall-public', plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js', array( 'jquery' ), '1.0.0', true );

                // Enqueue reseller-specific scripts
                wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true );
                $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-reseller.js';
                $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/kaa-mall-reseller.js' );
                wp_enqueue_script( 'kaa-mall-reseller', $js_file_url, array( 'jquery', 'chart-js' ), $js_version, true );
                wp_localize_script( 'kaa-mall-reseller', 'kaa_mall_reseller_params', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'kaa_mall_reseller_nonce' ),
                ) );
            }

            if ( has_shortcode( $post->post_content, 'kaa_reseller_apply' ) ) {
                wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), null, true );
                $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-reseller-apply.js';
                $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/kaa-mall-reseller-apply.js' );
                wp_enqueue_script( 'kaa-mall-reseller-apply', $js_file_url, array( 'jquery', 'paystack-inline' ), $js_version, true );

                $user = wp_get_current_user();
                wp_localize_script( 'kaa-mall-reseller-apply', 'kaa_mall_params', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'paystack_public_key' => get_option( 'kaa_mall_paystack_public_key' ),
                    'nonce' => wp_create_nonce( 'kaa_mall_nonce' ),
                    'user_email' => $user->user_email,
                    'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'GHS',
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

        $portal_url = get_option( 'kaa_mall_user_portal_url' );
        if ( empty( $portal_url ) ) {
            wp_send_json_error( array( 'message' => 'The user portal URL is not configured. Please contact an administrator.' ) );
        }

        $referral_link = esc_url( add_query_arg( 'ref_shop', $shop_name, $portal_url ) );

        wp_send_json_success( array(
            'message' => 'Shop name updated successfully.',
            'shop_name' => $shop_name,
            'referral_link' => $referral_link,
        ) );
    }

    public function save_whatsapp_number() {
        check_ajax_referer( 'kaa_mall_reseller_nonce', 'nonce' );

        if ( ! current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $whatsapp_number = sanitize_text_field( $_POST['whatsapp_number'] );
        $reseller_id = get_current_user_id();

        update_user_meta( $reseller_id, '_kaa_mall_whatsapp_number', $whatsapp_number );

        wp_send_json_success( array( 'message' => 'WhatsApp number updated successfully.' ) );
    }

    public function save_whatsapp_group_link() {
        check_ajax_referer( 'kaa_mall_reseller_nonce', 'nonce' );

        if ( ! current_user_can( 'reseller' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $whatsapp_group_link = sanitize_text_field( $_POST['whatsapp_group_link'] );
        $reseller_id = get_current_user_id();

        update_user_meta( $reseller_id, '_kaa_mall_whatsapp_group_link', $whatsapp_group_link );

        wp_send_json_success( array( 'message' => 'WhatsApp group link updated successfully.' ) );
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

            // Send email notification to admin
            $admin_email = get_option( 'admin_email' );
            $reseller = get_userdata( $reseller_id );
            $subject = 'New Withdrawal Request from ' . $reseller->display_name;
            $message = "A new withdrawal request has been submitted:\n\n" .
                       "Reseller: " . $reseller->display_name . " (" . $reseller->user_email . ")\n" .
                       "Amount: " . wc_price( $amount ) . "\n" .
                       "Payment Details: " . $payment_details . "\n\n" .
                       "You can view and process this request in your WordPress admin dashboard.";
            wp_mail( $admin_email, $subject, $message );

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

    public function get_total_reseller_sales( $reseller_id ) {
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => array_keys( wc_get_order_statuses() ),
            'meta_key' => '_reseller_id',
            'meta_value' => $reseller_id,
            'numberposts' => -1,
            'fields' => 'ids',
        );
        $orders = get_posts( $args );
        $total_sales = 0;
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $total_sales += $order->get_total();
            }
        }
        return $total_sales;
    }

    private function get_total_reseller_profit( $reseller_id ) {
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => array_keys( wc_get_order_statuses() ),
            'meta_key' => '_reseller_id',
            'meta_value' => $reseller_id,
            'numberposts' => -1,
            'fields' => 'ids',
        );
        $orders = get_posts( $args );
        $total_profit = 0;
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $profit = $order->get_meta( '_reseller_profit' );
                if ( ! empty( $profit ) ) {
                    $total_profit += floatval( $profit );
                }
            }
        }
        return $total_profit;
    }

    public function get_reseller_tier( $reseller_id ) {
        $tiers = get_option( 'kaa_mall_reseller_tiers', array() );
        if ( empty( $tiers ) ) {
            return null;
        }

        // Sort tiers by sales required, descending
        usort( $tiers, function( $a, $b ) {
            return $b['sales_required'] - $a['sales_required'];
        } );

        $total_sales = $this->get_total_reseller_sales( $reseller_id );

        foreach ( $tiers as $tier ) {
            if ( $total_sales >= $tier['sales_required'] ) {
                return $tier;
            }
        }

        return null;
    }

    public function render_apply_form() {
        ob_start();

        if ( ! is_user_logged_in() ) {
            echo '<p>You must be logged in to apply to be a reseller.</p>';
            return ob_get_clean();
        }

        if ( current_user_can( 'reseller' ) ) {
            echo '<p>You are already a reseller.</p>';
            return ob_get_clean();
        }

        $user_id = get_current_user_id();
        $existing_application = get_posts( array(
            'post_type' => 'reseller_application',
            'author' => $user_id,
            'post_status' => 'pending',
        ) );

        if ( ! empty( $existing_application ) ) {
            echo '<p>You have a pending reseller application. Please wait for an administrator to review it.</p>';
            return ob_get_clean();
        }

        $application_fee = get_option( 'kaa_mall_reseller_application_fee', '10' );
        ?>
        <style>
            .kaa-mall-apply-portal {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                background-color: #121212;
                color: #e0e0e0;
                padding: 20px;
                max-width: 500px;
                margin: 40px auto;
                border-radius: 12px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            }
            .kaa-mall-apply-portal h3 {
                font-size: 1.8em;
                color: #ffffff;
                text-align: center;
                margin-bottom: 20px;
            }
            .kaa-mall-apply-portal p {
                text-align: center;
                margin-bottom: 15px;
            }
            .kaa-mall-apply-portal .fee {
                font-size: 2.2em;
                font-weight: bold;
                color: #ffc107;
                text-align: center;
                margin: 20px 0;
            }
            .kaa-mall-apply-portal button {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background-color: #ffc107;
                color: #121212;
                font-weight: bold;
                cursor: pointer;
                font-size: 1.1em;
            }
        </style>
        <div class="kaa-mall-apply-portal" id="kaa-mall-reseller-apply-form">
            <h3>Apply to be a Reseller</h3>
            <p>To become a reseller, you need to pay a one-time application fee.</p>
            <p class="fee"><?php echo wc_price( $application_fee ); ?></p>
            <button id="kaa-mall-apply-now-btn" data-fee="<?php echo esc_attr( $application_fee ); ?>">Apply Now</button>
        </div>
        <?php

        return ob_get_clean();
    }

    public function render_reseller_portal() {
        if ( ! is_user_logged_in() ) {
            ob_start();
            // Using a simplified version of the new theme for the login prompt
            ?>
            <div class="kaa-mall-portal-body" style="height: auto; justify-content: center; align-items: center; background-color: #f7f8fc;">
                 <div class="kaa-mall-main-content" style="width: 100%; max-width: 400px; padding: 20px;">
                    <div class="kaa-mall-dynamic-content-wrapper">
                        <h2 class="brand-title" style="text-align: center; margin-bottom: 20px;">Kaadatahub</h2>
                        <h3 style="text-align: center; margin-bottom: 20px;">Please log in to access the Reseller Portal.</h3>
                        <?php wp_login_form( array('redirect' => get_permalink()) ); ?>
                    </div>
                 </div>
            </div>
            <?php
            return ob_get_clean();
        }

        if ( ! current_user_can( 'reseller' ) ) {
            // Using a simplified version of the new theme for the permission error
            ob_start();
            $current_user = wp_get_current_user();
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
                         <li><a href="<?php echo esc_url( get_permalink( get_page_by_path( 'user-portal' ) ) ); ?>"><i class="fas fa-home"></i> Dashboard</a></li>
                    </ul>
                </div>
                <div class="sidebar-overlay"></div>

                <!-- Main Content -->
                <div class="kaa-mall-main-content">
                     <div class="main-header">
                        <button class="open-sidebar-btn"><i class="fas fa-bars"></i></button>
                        <div class="header-user-info">
                            <span>Hello, <?php echo esc_html( $current_user->display_name ); ?></span>
                        </div>
                        <div class="header-icons">
                            <i class="fas fa-bell"></i>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="kaa-mall-dynamic-content-wrapper">
                        <p>You do not have the required permissions to view this page. Please contact the site administrator if you believe this is an error.</p>
                         <p>If you are not a reseller, you can <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'apply-to-be-a-reseller' ) ) ); ?>">apply here</a>.</p>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }


        $reseller_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $profit_balance = $this->get_profit_wallet_balance( $reseller_id );
        $total_sales = $this->get_total_reseller_sales( $reseller_id );
        $total_profit = $this->get_total_reseller_profit( $reseller_id );

        ob_start();
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
                    <li><a href="<?php echo esc_url( get_permalink( get_page_by_path( 'user-portal' ) ) ); ?>"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-network="mtn"><i class="fas fa-mobile-alt"></i> MTN</a></li>
                    <li><a href="#" class="nav-link" data-network="airteltigo"><i class="fas fa-mobile-alt"></i> Airteltigo</a></li>
                    <li><a href="#" class="nav-link" data-network="vodafone"><i class="fas fa-mobile-alt"></i> Telecel</a></li>
                    <li><a href="#" class="nav-link" data-afa="true"><i class="fas fa-user-plus"></i> AFA Registration</a></li>

                    <li class="nav-section-title">Credits & Debits</li>
                    <li><a href="#" class="nav-link" data-wallet="true"><i class="fas fa-wallet"></i> Wallet</a></li>

                    <?php if ( in_array( 'reseller', (array) $current_user->roles ) ) : ?>
                    <li class="nav-section-title">Business</li>
                    <li><a href="<?php echo esc_url( get_permalink( get_page_by_path( 'reseller-portal' ) ) ); ?>" class="active"><i class="fas fa-store"></i> Reseller</a></li>
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
                     <div class="profile-popup">
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

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Reseller Dashboard</h2>
                </div>

                <div class="reseller-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                     <div class="sales-performance-card" style="grid-column: 1 / -1;">
                        <h3>Analytics</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                            <div>
                                <h4>Daily Sales</h4>
                                <canvas id="daily-sales-chart"></canvas>
                            </div>
                            <div>
                                <h4>Weekly Sales</h4>
                                <canvas id="weekly-sales-chart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="recent-transactions-card">
                         <h3>Your Profit Wallet</h3>
                        <p>Your current profit balance is:</p>
                        <h2 class="balance-amount" style="color: #8a2be2;"><?php echo wc_price( $profit_balance ); ?></h2>
                    </div>

                     <div class="recent-transactions-card">
                        <h3>Total Sales</h3>
                        <h2 class="balance-amount"><?php echo wc_price( $total_sales ); ?></h2>
                    </div>

                     <div class="recent-transactions-card">
                        <h3>Total Profit</h3>
                        <h2 class="balance-amount"><?php echo wc_price( $total_profit ); ?></h2>
                    </div>

                     <div class="recent-transactions-card">
                        <h3>Request Withdrawal</h3>
                        <form id="kaa-mall-withdrawal-form" class="kaa-mall-form">
                            <div class="form-group">
                                <label for="withdrawal_amount">Amount</label>
                                <input type="number" name="amount" id="withdrawal_amount" step="0.01" min="1" max="<?php echo esc_attr($profit_balance); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="payment_details">Payment Details (e.g., Mobile Money Number)</label>
                                <textarea name="payment_details" id="payment_details" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="kaa-mall-btn">Request Withdrawal</button>
                        </form>
                    </div>

                     <div class="recent-transactions-card">
                        <h3>Your Shop Name</h3>
                        <form id="kaa-mall-shop-name-form" class="kaa-mall-form">
                            <div class="form-group">
                                <label for="shop_name">Set your unique shop name</label>
                                <input type="text" id="shop_name" name="shop_name" value="<?php echo esc_attr( get_user_meta( $reseller_id, '_kaa_mall_shop_name', true ) ); ?>" placeholder="e.g., my-data-shop">
                            </div>
                            <button type="submit" class="kaa-mall-btn">Save Shop Name</button>
                        </form>
                    </div>

                     <div class="recent-transactions-card">
                        <h3>Your Referral Link</h3>
                        <p>Share this link with your customers.</p>
                        <div class="referral-link-wrapper" style="display: flex;">
                             <?php
                            $shop_name = get_user_meta( $reseller_id, '_kaa_mall_shop_name', true );
                            $portal_url = get_permalink( get_page_by_path( 'user-portal' ) );
                            $referral_link = ! empty( $shop_name ) ? esc_url( add_query_arg( 'ref_shop', $shop_name, $portal_url ) ) : 'Please set a shop name first.';
                            ?>
                            <input type="text" id="kaa-mall-referral-link" value="<?php echo $referral_link; ?>" readonly style="flex-grow: 1;">
                            <button id="kaa-mall-copy-btn" class="kaa-mall-btn" style="width: auto; margin-left: 10px;">Copy</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
