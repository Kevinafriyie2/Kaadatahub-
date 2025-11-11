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
            if ( has_shortcode( $post->post_content, 'kaa_reseller_portal' ) ) {
                $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-reseller.js';
                $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/kaa-mall-reseller.js' );
                wp_enqueue_script( 'kaa-mall-reseller', $js_file_url, array( 'jquery' ), $js_version, true );
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

    private function get_total_reseller_sales( $reseller_id ) {
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
            echo '<div class="kaa-mall-portal" style="max-width: 400px; margin: 40px auto; padding: 20px; background-color: #1e1e1e; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.5);">';
            echo '<h3 style="text-align: center; color: #ffffff; margin-bottom: 20px;">Please log in to access the Reseller Portal.</h3>';
            wp_login_form( array('redirect' => get_permalink()) );
            echo '</div>';
            return ob_get_clean();
        }

        if ( ! current_user_can( 'reseller' ) ) {
            return '<div class="kaa-mall-portal" style="text-align: center; padding: 40px;">You do not have the required permissions to view this page. Please contact the site administrator if you believe this is an error.</div>';
        }

        $reseller_id = get_current_user_id();
        $profit_balance = $this->get_profit_wallet_balance( $reseller_id );
        $total_sales = $this->get_total_reseller_sales( $reseller_id );
        $total_profit = $this->get_total_reseller_profit( $reseller_id );

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
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                background-attachment: fixed;
            }

            .kaa-mall-reseller-portal {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                color: var(--text-color);
                padding: 20px;
            }
            .reseller-header h2 {
                font-size: 2.5em;
                margin-bottom: 20px;
                color: var(--heading-color);
                text-align: center;
            }
            .reseller-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
            .reseller-card {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 16px;
                box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid var(--border-color);
                padding: 25px;
            }
            .reseller-card h3 {
                font-size: 1.4em;
                margin-top: 0;
                margin-bottom: 20px;
                color: var(--heading-color);
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 10px;
            }
            .profit-balance {
                font-size: 2.8em;
                font-weight: bold;
                color: var(--primary-color);
                text-align: center;
                margin: 10px 0;
            }
            .reseller-card label {
                display: block;
                margin-bottom: 8px;
                color: var(--text-color);
            }
            .reseller-card input[type="text"],
            .reseller-card input[type="number"],
            .reseller-card textarea {
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
            .reseller-card table {
                width: 100%;
                border-collapse: collapse;
            }
            .reseller-card th, .reseller-card td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid var(--border-color);
            }
            .reseller-card th {
                color: var(--heading-color);
            }
            .reseller-card button {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background-color: var(--primary-color);
                color: #121212;
                font-weight: bold;
                cursor: pointer;
                font-size: 1.1em;
            }
            .referral-link-wrapper {
                display: flex;
            }
            .referral-link-wrapper input {
                flex-grow: 1;
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
            }
            .referral-link-wrapper button {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
                width: auto;
                padding: 10px 15px;
            }
            .network-tabs {
                display: flex;
                justify-content: space-around;
                margin-bottom: 20px;
            }
            .network-tabs .tab-link {
                background: none;
                border: none;
                color: var(--text-color);
                cursor: pointer;
                padding: 10px;
                font-size: 1em;
            }
            .network-tabs .tab-link.active {
                border-bottom: 2px solid var(--primary-color);
                color: var(--primary-color);
            }
            .network-tab-content {
                display: none;
            }
            .network-tab-content.active {
                display: block;
            }
            #loginform label {
                color: var(--text-color);
            }
            #loginform input[type="text"],
            #loginform input[type="password"] {
                background-color: rgba(0, 0, 0, 0.2);
                border: 1px solid var(--border-color);
                color: var(--text-color);
                width: 100%;
                padding: 10px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            #loginform input[type="submit"] {
                background-color: var(--primary-color);
                color: #121212;
                border: none;
                font-weight: bold;
                width: 100%;
                padding: 12px;
                border-radius: 8px;
                cursor: pointer;
            }
            #loginform .forgetmenot label {
                color: var(--text-color);
            }
        </style>

        <div class="kaa-mall-reseller-portal">
            <div class="reseller-header">
                <h2>Reseller Dashboard</h2>
            </div>

            <div class="reseller-grid">
                <div class="reseller-card">
                    <h3>Your Reseller ID</h3>
                    <p class="profit-balance"><?php echo esc_html( $reseller_id ); ?></p>
                </div>

                <div class="reseller-card">
                    <h3>Your WhatsApp Group Link</h3>
                    <form id="kaa-mall-whatsapp-group-link-form">
                        <label for="whatsapp_group_link">Set your WhatsApp group link</label>
                        <input type="text" id="whatsapp_group_link" name="whatsapp_group_link" value="<?php echo esc_attr( get_user_meta( $reseller_id, '_kaa_mall_whatsapp_group_link', true ) ); ?>" placeholder="e.g., https://chat.whatsapp.com/your-group-id">
                        <button type="submit">Save WhatsApp Group Link</button>
                    </form>
                </div>

                <div class="reseller-card">
                    <h3>Your WhatsApp Number</h3>
                    <form id="kaa-mall-whatsapp-number-form">
                        <label for="whatsapp_number">Set your customer-facing WhatsApp number</label>
                        <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?php echo esc_attr( get_user_meta( $reseller_id, '_kaa_mall_whatsapp_number', true ) ); ?>" placeholder="e.g., 233201858375">
                        <button type="submit">Save WhatsApp Number</button>
                    </form>
                </div>

                <div class="reseller-card">
                    <h3>Your Profit Wallet</h3>
                    <p>Your current profit balance is:</p>
                    <p class="profit-balance"><?php echo wc_price( $profit_balance ); ?></p>
                </div>

                <div class="reseller-card">
                    <h3>Total Sales</h3>
                    <p class="profit-balance"><?php echo wc_price( $total_sales ); ?></p>
                </div>

                <div class="reseller-card">
                    <h3>Total Profit</h3>
                    <p class="profit-balance"><?php echo wc_price( $total_profit ); ?></p>
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
                    <form id="kaa-mall-shop-name-form">
                        <label for="shop_name">Set your unique shop name</label>
                        <input type="text" id="shop_name" name="shop_name" value="<?php echo esc_attr( get_user_meta( $reseller_id, '_kaa_mall_shop_name', true ) ); ?>" placeholder="e.g., my-data-shop">
                        <button type="submit">Save Shop Name</button>
                    </form>
                </div>

                <div class="reseller-card">
                    <h3>Your Referral Link</h3>
                    <p>Share this link with your customers.</p>
                    <div class="referral-link-wrapper">
                        <?php
                        $shop_name = get_user_meta( $reseller_id, '_kaa_mall_shop_name', true );
                        $portal_url = get_option( 'kaa_mall_user_portal_url' );
                        $referral_link = ! empty( $portal_url ) ? esc_url( add_query_arg( 'ref_shop', $shop_name, $portal_url ) ) : 'Please configure the User Portal URL in settings.';
                        ?>
                        <input type="text" id="kaa-mall-referral-link" value="<?php echo $referral_link; ?>" readonly>
                        <button id="kaa-mall-copy-btn">Copy</button>
                    </div>
                </div>

                <div class="reseller-card" style="grid-column: 1 / -1;">
                    <h3>Set Your Bundle Prices</h3>
                    <p>Set your own selling price for each bundle.</p>

                    <div class="network-tabs">
                        <button class="tab-link active" data-network="mtn">MTN</button>
                        <button class="tab-link" data-network="airteltigo">AirtelTigo</button>
                        <button class="tab-link" data-network="vodafone">Vodafone</button>
                        <button class="tab-link" data-network="afa">AFA Registration</button>
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

                    <div id="reseller-prices-afa" class="network-tab-content">
                        <form class="reseller-prices-form" data-network="afa">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Base Price</th>
                                        <th>Your Selling Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $admin_price = floatval( get_option( 'kaa_mall_afa_registration_fee', '13' ) );
                                    $reseller_prices = $this->get_reseller_prices( $reseller_id, 'afa' );
                                    $reseller_price = isset( $reseller_prices['registration'] ) ? $reseller_prices['registration'] : $admin_price;
                                    ?>
                                    <tr>
                                        <td>AFA Registration</td>
                                        <td><?php echo wc_price( $admin_price ); ?></td>
                                        <td><input type="number" name="prices[registration]" value="<?php echo esc_attr( $reseller_price ); ?>" step="0.01" min="<?php echo esc_attr( $admin_price ); ?>"></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="submit">Save AFA Price</button>
                        </form>
                    </div>
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
