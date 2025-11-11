<?php

class Kaa_Mall_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_post_kaa_mall_top_up_wallet', array( $this, 'handle_top_up_wallet' ) );
        add_action( 'admin_post_kaa_mall_bulk_update_order_status', array( $this, 'handle_bulk_update_order_status' ) );
        add_action( 'admin_post_kaa_mall_mark_withdrawal_paid', array( $this, 'handle_mark_withdrawal_paid' ) );
        add_action( 'wp_ajax_kaa_mall_search_users', array( $this, 'search_users' ) );
        add_action( 'admin_post_kaa_mall_login_as_user', array( $this, 'handle_login_as_user' ) );
        add_action( 'admin_post_kaa_mall_approve_reseller', array( $this, 'handle_approve_reseller' ) );
        add_action( 'admin_post_kaa_mall_deny_reseller', array( $this, 'handle_deny_reseller' ) );
        add_action( 'admin_post_kaa_mall_send_broadcast', array( $this, 'handle_send_broadcast' ) );
        add_action( 'admin_post_kaa_mall_edit_broadcast', array( $this, 'handle_edit_broadcast' ) );
        add_action( 'admin_post_kaa_mall_delete_broadcast', array( $this, 'handle_delete_broadcast' ) );
    }

    public function handle_edit_broadcast() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        check_admin_referer( 'kaa_mall_edit_broadcast_nonce', 'kaa_mall_edit_broadcast_nonce' );

        $broadcast_id = intval( $_POST['broadcast_id'] );
        $subject = sanitize_text_field( $_POST['broadcast_subject'] );
        $message = wp_kses_post( $_POST['broadcast_message'] );

        wp_update_post( array(
            'ID' => $broadcast_id,
            'post_title' => $subject,
            'post_content' => $message,
        ) );

        $redirect_url = add_query_arg( array(
            'page' => 'kaa-mall-broadcasts',
            'message' => 'Broadcast updated successfully.'
        ), admin_url( 'admin.php' ) );
        wp_redirect( $redirect_url );
        exit;
    }

    public function handle_delete_broadcast() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $broadcast_id = intval( $_GET['broadcast_id'] );
        wp_delete_post( $broadcast_id, true );

        $redirect_url = add_query_arg( 'message', 'Broadcast deleted successfully.', wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }

    public function handle_send_broadcast() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        check_admin_referer( 'kaa_mall_send_broadcast_nonce', 'kaa_mall_send_broadcast_nonce' );

        $subject = sanitize_text_field( $_POST['broadcast_subject'] );
        $message = wp_kses_post( $_POST['broadcast_message'] );
        $recipient = sanitize_text_field( $_POST['broadcast_recipient'] );

        // Save the broadcast
        wp_insert_post( array(
            'post_type' => 'kaa_mall_broadcast',
            'post_title' => $subject,
            'post_content' => $message,
            'post_status' => 'publish',
        ) );

        // Get recipients
        if ( $recipient === 'resellers' ) {
            $users = get_users( array( 'role' => 'reseller' ) );
        } else {
            $users = get_users();
        }

        $emails = array();
        foreach ( $users as $user ) {
            $emails[] = $user->user_email;
        }

        // Send the email
        wp_mail( $emails, $subject, $message );

        $redirect_url = add_query_arg( 'message', 'Broadcast sent successfully.', wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }

    public function handle_approve_reseller() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $application_id = intval( $_GET['application_id'] );
        $user_id = get_post_field( 'post_author', $application_id );

        $user = get_user_by( 'id', $user_id );
        $user->add_role( 'reseller' );

        wp_update_post( array(
            'ID' => $application_id,
            'post_status' => 'publish',
        ) );

        // Send email to user
        $user_email = $user->user_email;
        $subject = 'Your Reseller Application has been Approved';
        $message = 'Congratulations! Your reseller application has been approved. You can now access the reseller dashboard.';
        wp_mail( $user_email, $subject, $message );

        $redirect_url = add_query_arg( 'message', 'Reseller approved.', wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }

    public function handle_deny_reseller() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $application_id = intval( $_GET['application_id'] );
        $user_id = get_post_field( 'post_author', $application_id );

        wp_update_post( array(
            'ID' => $application_id,
            'post_status' => 'trash',
        ) );

        // Send email to user
        $user = get_user_by( 'id', $user_id );
        $user_email = $user->user_email;
        $subject = 'Your Reseller Application has been Denied';
        $message = 'We regret to inform you that your reseller application has been denied at this time.';
        wp_mail( $user_email, $subject, $message );

        $redirect_url = add_query_arg( 'message', 'Reseller denied.', wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }

    public function handle_login_as_user() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $user_id = intval( $_GET['user_id'] );
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        $portal_url = get_option( 'kaa_mall_user_portal_url' );
        if( empty( $portal_url ) ) {
            $portal_url = home_url();
        }

        wp_redirect( $portal_url );
        exit;
    }

    public function search_users() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(), 403 );
        }

        $search = sanitize_text_field( $_POST['search'] );

        $user_query = new WP_User_Query( array(
            'search'         => '*' . esc_attr( $search ) . '*',
            'search_columns' => array( 'user_login', 'user_email', 'user_nicename' ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => 'first_name',
                    'value'   => $search,
                    'compare' => 'LIKE'
                ),
                array(
                    'key'     => 'last_name',
                    'value'   => $search,
                    'compare' => 'LIKE'
                ),
            ),
            'number'         => 10,
        ) );
        $users = $user_query->get_results();

        $results = array();
        foreach ( $users as $user ) {
            $results[] = array(
                'id' => $user->ID,
                'text' => $user->display_name . ' (' . $user->user_email . ')',
            );
        }

        wp_send_json_success( $results );
    }

    public function handle_mark_withdrawal_paid() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $request_id = intval( $_GET['request_id'] );
        wp_update_post( array(
            'ID' => $request_id,
            'post_status' => 'publish',
        ) );

        $redirect_url = add_query_arg( 'message', 'Withdrawal marked as paid.', wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }

    public function enqueue_styles() {
        // Styles are now inlined in the shortcode output.
    }

    private function get_wallet_balance( $user_id ) {
        $balance = get_user_meta( $user_id, '_kaa_mall_wallet_balance', true );
        return empty( $balance ) ? 0.00 : floatval( $balance );
    }

    public function handle_top_up_wallet() {
        if ( ! isset( $_POST['kaa_mall_top_up_wallet_nonce'] ) || ! wp_verify_nonce( $_POST['kaa_mall_top_up_wallet_nonce'], 'kaa_mall_top_up_wallet_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $user_id = intval( $_POST['user_id'] );
        $amount = floatval( $_POST['amount'] );

        if ( $user_id > 0 && $amount > 0 ) {
            $current_balance = $this->get_wallet_balance( $user_id );
            $new_balance = $current_balance + $amount;
            update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

            $redirect_url = add_query_arg( 'message', 'Wallet topped up successfully.', wp_get_referer() );
        } else {
            $redirect_url = add_query_arg( 'message', 'Invalid user or amount.', wp_get_referer() );
        }

        wp_redirect( $redirect_url );
        exit;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Kaadatahub',
            'Kaadatahub',
            'manage_options',
            'kaa_mall',
            array( $this, 'render_admin_portal' ),
            'dashicons-store'
        );

        add_submenu_page(
            'kaa_mall',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'kaa_mall',
            array( $this, 'render_admin_portal' )
        );

        add_submenu_page(
            'kaa_mall',
            'Settings',
            'Settings',
            'manage_options',
            'kaa-mall-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'kaa_mall',
            'Reseller Applications',
            'Reseller Applications',
            'manage_options',
            'kaa-mall-reseller-applications',
            array($this, 'display_reseller_applications_page')
        );

        add_submenu_page(
            'kaa_mall',
            'Broadcast Messages',
            'Broadcast Messages',
            'manage_options',
            'kaa-mall-broadcasts',
            array($this, 'display_broadcasts_page')
        );
    }

    public function register_settings() {
        register_setting( 'kaa_mall_options', 'kaa_mall_mtn_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_airteltigo_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_vodafone_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_mtn_out_of_stock' );
        register_setting( 'kaa_mall_options', 'kaa_mall_airteltigo_out_of_stock' );
        register_setting( 'kaa_mall_options', 'kaa_mall_vodafone_out_of_stock' );
        register_setting( 'kaa_mall_options', 'kaa_mall_afa_out_of_stock' );
        register_setting( 'kaa_mall_options', 'kaa_mall_reseller_application_fee' );
        register_setting( 'kaa_mall_options', 'kaa_mall_afa_registration_fee' );
        register_setting( 'kaa_mall_options', 'kaa_mall_low_balance_threshold' );
        register_setting( 'kaa_mall_options', 'kaa_mall_enable_low_balance_alerts' );
        register_setting( 'kaa_mall_options', 'kaa_mall_bundle_service_fee' );
        register_setting( 'kaa_mall_options', 'kaa_mall_topup_service_fee' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_public_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_secret_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_business_email' );
        register_setting( 'kaa_mall_options', 'kaa_mall_user_portal_url' );
        register_setting( 'kaa_mall_options', 'kaa_mall_reseller_portal_url' );
        register_setting( 'kaa_mall_options', 'kaa_mall_whatsapp_number' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h2>KAA Mall Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'kaa_mall_options' );
                do_settings_sections( 'kaa_mall_options' );
                ?>
                <h3>Paystack Settings</h3>
                <table class="form-table">
                    <tr valign="top">
                    <th scope="row">Public Key</th>
                    <td><input type="text" name="kaa_mall_paystack_public_key" value="<?php echo esc_attr( get_option('kaa_mall_paystack_public_key') ); ?>" size="50" /></td>
                    </tr>

                    <tr valign="top">
                    <th scope="row">Secret Key</th>
                    <td><input type="text" name="kaa_mall_paystack_secret_key" value="<?php echo esc_attr( get_option('kaa_mall_paystack_secret_key') ); ?>" size="50" /></td>
                    </tr>

                    <tr valign="top">
                    <th scope="row">Business Email</th>
                    <td><input type="email" name="kaa_mall_business_email" value="<?php echo esc_attr( get_option('kaa_mall_business_email') ); ?>" size="50" /></td>
                    </tr>
                </table>

                <h3>Portal Page Settings</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">User Portal URL</th>
                        <td><input type="text" name="kaa_mall_user_portal_url" value="<?php echo esc_attr( get_option('kaa_mall_user_portal_url') ); ?>" size="50" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Reseller Portal URL</th>
                        <td><input type="text" name="kaa_mall_reseller_portal_url" value="<?php echo esc_attr( get_option('kaa_mall_reseller_portal_url') ); ?>" size="50" /></td>
                    </tr>
                </table>

                <h3>Reseller Settings</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Application Fee</th>
                        <td><input type="number" name="kaa_mall_reseller_application_fee" value="<?php echo esc_attr( get_option('kaa_mall_reseller_application_fee', '10') ); ?>" step="0.01" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">AFA Registration Base Price</th>
                        <td><input type="number" name="kaa_mall_afa_registration_fee" value="<?php echo esc_attr( get_option('kaa_mall_afa_registration_fee', '13') ); ?>" step="0.01" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">AFA Registration Out of Stock</th>
                        <td><label><input type="checkbox" name="kaa_mall_afa_out_of_stock" value="1" <?php checked( get_option( 'kaa_mall_afa_out_of_stock' ), 1 ); ?>> Mark AFA registration as out of stock</label></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Default WhatsApp Number</th>
                        <td><input type="text" name="kaa_mall_whatsapp_number" value="<?php echo esc_attr( get_option('kaa_mall_whatsapp_number') ); ?>" placeholder="e.g., 233201858375" />
                        <p class="description">Enter the default WhatsApp number for the 'Contact Admin' button. Resellers can override this.</p></td>
                    </tr>
                </table>

                <h3>Email Notification Settings</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Low Balance Alerts</th>
                        <td><label><input type="checkbox" name="kaa_mall_enable_low_balance_alerts" value="1" <?php checked( get_option( 'kaa_mall_enable_low_balance_alerts' ), 1 ); ?>> Enable low balance email alerts</label></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Low Balance Threshold</th>
                        <td><input type="number" name="kaa_mall_low_balance_threshold" value="<?php echo esc_attr( get_option('kaa_mall_low_balance_threshold', '5') ); ?>" step="0.01" /></td>
                    </tr>
                </table>

                <h3>MTN Prices</h3>
                <p><label><input type="checkbox" name="kaa_mall_mtn_out_of_stock" value="1" <?php checked( get_option( 'kaa_mall_mtn_out_of_stock' ), 1 ); ?>> Mark all MTN bundles as out of stock</label></p>
                <textarea name="kaa_mall_mtn_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_mtn_prices') ); ?></textarea>
                <h3>AirtelTigo Prices</h3>
                <p><label><input type="checkbox" name="kaa_mall_airteltigo_out_of_stock" value="1" <?php checked( get_option( 'kaa_mall_airteltigo_out_of_stock' ), 1 ); ?>> Mark all AirtelTigo bundles as out of stock</label></p>
                <textarea name="kaa_mall_airteltigo_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_airteltigo_prices') ); ?></textarea>
                <h3>Vodafone Prices</h3>
                <p><label><input type="checkbox" name="kaa_mall_vodafone_out_of_stock" value="1" <?php checked( get_option( 'kaa_mall_vodafone_out_of_stock' ), 1 ); ?>> Mark all Vodafone bundles as out of stock</label></p>
                <textarea name="kaa_mall_vodafone_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_vodafone_prices') ); ?></textarea>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_all_orders() {
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => 'any',
            'numberposts' => -1,
        );

        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
            $search_term = sanitize_text_field( $_REQUEST['s'] );

            // Check if the search term is a reseller's name
            $resellers = get_users( array(
                'search'         => '*' . esc_attr( $search_term ) . '*',
                'search_columns' => array( 'display_name' ),
            ) );

            if ( ! empty( $resellers ) ) {
                $reseller_ids = wp_list_pluck( $resellers, 'ID' );
                $args['meta_query'] = array(
                    array(
                        'key'     => '_reseller_id',
                        'value'   => $reseller_ids,
                        'compare' => 'IN',
                    ),
                );
            } else {
                $args['s'] = $search_term;
            }
        }

        return get_posts( $args );
    }

    public function handle_bulk_update_order_status() {
        if ( ! isset( $_POST['kaa_mall_bulk_update_order_status_nonce'] ) || ! wp_verify_nonce( $_POST['kaa_mall_bulk_update_order_status_nonce'], 'kaa_mall_bulk_update_order_status_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        if ( isset( $_POST['order_ids'] ) && isset( $_POST['bulk_action'] ) && $_POST['bulk_action'] != '-1' ) {
            $order_ids = array_map( 'intval', $_POST['order_ids'] );
            $new_status = sanitize_text_field( $_POST['bulk_action'] );

            foreach ( $order_ids as $order_id ) {
                $order = wc_get_order( $order_id );
                $order->update_status( $new_status );
            }

            $redirect_url = add_query_arg( 'message', 'Orders updated successfully.', wp_get_referer() );
        } else {
            $redirect_url = add_query_arg( 'message', 'No orders or action selected.', wp_get_referer() );
        }

        wp_redirect( $redirect_url );
        exit;
    }

    private function get_all_user_wallet_balances() {
        $users = get_users( array(
            'meta_key' => '_kaa_mall_wallet_balance',
            'meta_compare' => 'EXISTS'
        ) );
        $balances = array();
        foreach ( $users as $user ) {
            $balance = get_user_meta( $user->ID, '_kaa_mall_wallet_balance', true );
            $balances[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'balance' => floatval( $balance )
            );
        }
        return $balances;
    }

    private function get_all_users() {
        return get_users();
    }

    private function get_all_resellers() {
        $args = array(
            'role' => 'reseller',
        );
        return get_users( $args );
    }

    private function get_total_reseller_profit() {
        global $wpdb;
        $total_profit = $wpdb->get_var( "SELECT SUM(meta_value) FROM $wpdb->usermeta WHERE meta_key = '_kaa_mall_reseller_profit_balance'" );
        return floatval( $total_profit );
    }

    private function get_withdrawal_requests() {
        $args = array(
            'post_type'   => 'kaa_withdrawal',
            'post_status' => 'pending',
            'numberposts' => -1,
        );
        return get_posts( $args );
    }

    private function get_total_sales_today() {
        $today = date('Y-m-d');
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-completed', 'wc-processing'),
            'date_query' => array(
                array(
                    'after'     => $today . ' 00:00:00',
                    'before'    => $today . ' 23:59:59',
                    'inclusive' => true,
                ),
            ),
            'posts_per_page' => -1,
        );
        $orders = new WP_Query($args);
        $total_sales = 0;
        while ($orders->have_posts()) {
            $orders->the_post();
            $order = wc_get_order(get_the_ID());
            $total_sales += $order->get_total();
        }
        wp_reset_postdata();
        return $total_sales;
    }

    private function get_new_users_this_month() {
        $first_day_of_month = date('Y-m-01');
        $args = array(
            'date_query' => array(
                array(
                    'after'     => $first_day_of_month,
                    'inclusive' => true,
                ),
            ),
        );
        $user_query = new WP_User_Query($args);
        return $user_query->get_total();
    }

    private function get_popular_data_bundles() {
        global $wpdb;

        $results = $wpdb->get_results( "
            SELECT p.ID, p.post_title, COUNT(oim.meta_value) as purchase_count
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            JOIN {$wpdb->posts} p ON oim.meta_value = p.ID
            WHERE oim.meta_key = '_product_id'
            GROUP BY p.ID
            ORDER BY purchase_count DESC
            LIMIT 5
        " );

        return $results;
    }

    private function get_reseller_leaderboard() {
        $resellers = get_users(array('role' => 'reseller'));
        $leaderboard = array();

        foreach ($resellers as $reseller) {
            $args = array(
                'post_type' => 'shop_order',
                'post_status' => array('wc-completed', 'wc-processing'),
                'meta_key' => '_reseller_id',
                'meta_value' => $reseller->ID,
                'posts_per_page' => -1,
            );
            $orders = new WP_Query($args);
            $total_sales = 0;
            while ($orders->have_posts()) {
                $orders->the_post();
                $order = wc_get_order(get_the_ID());
                $total_sales += $order->get_total();
            }
            wp_reset_postdata();

            if ($total_sales > 0) {
                $leaderboard[] = array(
                    'name' => $reseller->display_name,
                    'sales' => $total_sales,
                );
            }
        }

        usort($leaderboard, function($a, $b) {
            return $b['sales'] - $a['sales'];
        });

        return $leaderboard;
    }

    public function render_admin_portal() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return 'You do not have permission to view this page.';
        }

        if ( isset( $_GET['message'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $_GET['message'] ) . '</p></div>';
        }

        $all_orders = $this->get_all_orders();
        $wallet_balances = $this->get_all_user_wallet_balances();
        $all_users = $this->get_all_users();
        $all_resellers = $this->get_all_resellers();
        $withdrawal_requests = $this->get_withdrawal_requests();
        $total_reseller_profit = $this->get_total_reseller_profit();
        $total_sales_today = $this->get_total_sales_today();
        $new_users_this_month = $this->get_new_users_this_month();
        $popular_bundles = $this->get_popular_data_bundles();
        $reseller_leaderboard = $this->get_reseller_leaderboard();

        ob_start();
        ?>
        <style>
            :root {
                --primary-color: #0073aa;
                --background-color: #f0f0f1;
                --card-background-color: #ffffff;
                --text-color: #3c434a;
                --heading-color: #1d2327;
                --border-color: #dcdcde;
                --shadow-color: rgba(0, 0, 0, 0.05);
            }

            .kaa-mall-admin-portal {
                background-color: var(--background-color);
                padding: 20px;
                margin-left: -20px; /* Counteract default WP admin margin */
            }

            .kaa-mall-admin-portal h2 {
                font-size: 2em;
                font-weight: 600;
                color: var(--heading-color);
                margin-bottom: 20px;
            }

            .admin-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 20px;
            }

            .admin-section {
                background: var(--card-background-color);
                border: 1px solid var(--border-color);
                border-radius: 4px;
                box-shadow: 0 1px 1px var(--shadow-color);
                padding: 20px;
            }

            .admin-section.full-width {
                grid-column: 1 / -1;
            }

            .admin-section h3 {
                margin-top: 0;
                margin-bottom: 20px;
                color: var(--heading-color);
                font-size: 1.2em;
                font-weight: 600;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 15px;
            }

            .admin-section .form-table th, .admin-section .form-table td {
                padding: 10px 0;
            }

            .admin-section .form-table input[type="text"],
            .admin-section .form-table input[type="number"] {
                width: 100%;
            }

            .admin-section table:not(.form-table) {
                width: 100%;
                border-collapse: collapse;
            }

            .admin-section th, .admin-section td {
                border-bottom: 1px solid var(--border-color);
                padding: 12px;
                text-align: left;
            }

            .admin-section th {
                font-weight: 600;
            }

            .admin-section .button {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
                color: white;
            }
        </style>
        <div class="kaa-mall-admin-portal">
            <h2>Admin Dashboard</h2>

            <div class="admin-grid">
                <div class="admin-section">
                    <h3>Total Sales Today</h3>
                    <p style="font-size: 2em; font-weight: bold; text-align: center; margin: 20px 0;"><?php echo wc_price($total_sales_today); ?></p>
                </div>
                <div class="admin-section">
                    <h3>New Users This Month</h3>
                    <p style="font-size: 2em; font-weight: bold; text-align: center; margin: 20px 0;"><?php echo $new_users_this_month; ?></p>
                </div>
                <div class="admin-section">
                    <h3>Total Reseller Profit</h3>
                    <p style="font-size: 2em; font-weight: bold; text-align: center; margin: 20px 0;"><?php echo wc_price( $total_reseller_profit ); ?></p>
                </div>
                <div class="admin-section">
                    <h3>Popular Data Bundles</h3>
                    <ul>
                        <?php foreach ( $popular_bundles as $bundle ) : ?>
                            <li><?php echo esc_html( $bundle->post_title ); ?> (<?php echo $bundle->purchase_count; ?> sales)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="admin-section">
                    <h3>Reseller Leaderboard</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Reseller</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ( $reseller_leaderboard as $reseller_data ) :
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo esc_html( $reseller_data['name'] ); ?></td>
                                    <td><?php echo wc_price( $reseller_data['sales'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-section">
                    <h3>Top Up User Wallet</h3>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="kaa_mall_top_up_wallet">
                        <?php wp_nonce_field( 'kaa_mall_top_up_wallet_nonce', 'kaa_mall_top_up_wallet_nonce' ); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Search User</th>
                                <td>
                                    <input type="text" id="kaa-mall-user-search" placeholder="Search by username or email...">
                                    <input type="hidden" name="user_id" id="kaa-mall-user-id">
                                    <div id="kaa-mall-user-search-results"></div>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Amount</th>
                                <td><input type="number" name="amount" step="0.01" min="0.01" required /></td>
                            </tr>
                        </table>
                        <?php submit_button( 'Top Up Wallet' ); ?>
                    </form>
                </div>

                <div class="admin-section">
                    <h3>User Wallet Balances</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $wallet_balances as $item ) : ?>
                                <tr>
                                    <td><?php echo esc_html($item['name']); ?></td>
                                    <td><?php echo wc_price( $item['balance'] ); ?></td>
                                    <td><a href="<?php echo esc_url( add_query_arg( array('action' => 'kaa_mall_login_as_user', 'user_id' => $item['id']), admin_url('admin-post.php') ) ); ?>" class="button">Login As</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-section">
                    <h3>All Resellers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $all_resellers as $reseller ) : ?>
                                <tr>
                                    <td><?php echo $reseller->ID; ?></td>
                                    <td><?php echo esc_html( $reseller->display_name ); ?></td>
                                    <td><?php echo esc_html( $reseller->user_email ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-section">
                    <h3>Network Prices</h3>
                    <a href="<?php echo admin_url( 'admin.php?page=kaa_mall' ); ?>">Manage Prices</a>
                </div>
            </div>

            <div class="admin-section full-width">
                <h3>Withdrawal Requests</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reseller</th>
                            <th>Amount</th>
                            <th>Payment Details</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $withdrawal_requests as $request ) :
                            $reseller = get_user_by( 'id', $request->post_author );
                            ?>
                            <tr>
                                <td><?php echo get_the_date( 'Y-m-d H:i:s', $request ); ?></td>
                                <td><?php echo esc_html( $reseller->display_name ); ?></td>
                                <td><?php echo wc_price( get_post_meta( $request->ID, '_withdrawal_amount', true ) ); ?></td>
                                <td><?php echo esc_html( get_post_meta( $request->ID, '_payment_details', true ) ); ?></td>
                                <td><a href="<?php echo esc_url( add_query_arg( array('action' => 'kaa_mall_mark_withdrawal_paid', 'request_id' => $request->ID), admin_url('admin-post.php') ) ); ?>" class="button">Mark as Paid</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-section full-width">
                <h3>All Orders</h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="kaa_mall_bulk_update_order_status">
                    <?php wp_nonce_field( 'kaa_mall_bulk_update_order_status_nonce', 'kaa_mall_bulk_update_order_status_nonce' ); ?>

                    <div style="margin-bottom: 20px; display: flex; justify-content: space-between;">
                        <div>
                            <select name="bulk_action">
                                <option value="-1">Bulk Actions</option>
                                <option value="processing">Mark Processing</option>
                                <option value="completed">Mark Completed</option>
                                <option value="on-hold">Mark On-Hold</option>
                                <option value="cancelled">Mark Cancelled</option>
                            </select>
                            <button type="submit" class="button">Apply</button>
                        </div>
                        <div>
                            <input type="text" name="s" placeholder="Search orders..." value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>">
                            <button type="submit" class="button">Search</button>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-orders"></th>
                                <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Reseller</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_orders as $order_post ) :
                            $order = wc_get_order( $order_post->ID );
                            if ( ! $order ) {
                                continue;
                            }
                            $date_created = $order->get_date_created();
                            $reseller_id = $order->get_meta( '_reseller_id' );
                            ?>
                            <tr>
                                <td><input type="checkbox" name="order_ids[]" value="<?php echo $order->get_id(); ?>"></td>
                                <td><a href="<?php echo get_edit_post_link( $order->get_id() ); ?>"><?php echo $order->get_id(); ?></a></td>
                                <td><?php echo $date_created ? $date_created->date_i18n( 'Y-m-d H:i:s' ) : 'N/A'; ?></td>
                                <td><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></td>
                                <td><?php echo $order->get_formatted_order_total(); ?></td>
                                <td><?php echo wc_get_order_status_name( $order->get_status() ); ?></td>
                                <td>
                                    <?php
                                    if ( $reseller_id ) {
                                        $reseller = get_user_by( 'id', $reseller_id );
                                        echo esc_html( $reseller->display_name );
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#select-all-orders').on('click', function() {
                    var checkboxes = $(this).closest('table').find('tbody input[type="checkbox"]');
                    checkboxes.prop('checked', $(this).is(':checked'));
                });

                var searchTimer;
                $('#kaa-mall-user-search').on('keyup', function() {
                    clearTimeout(searchTimer);
                    var searchTerm = $(this).val();
                    if (searchTerm.length < 2) {
                        $('#kaa-mall-user-search-results').empty();
                        return;
                    }

                    searchTimer = setTimeout(function() {
                        $.post(ajaxurl, {
                            action: 'kaa_mall_search_users',
                            search: searchTerm
                        }, function(response) {
                            var resultsContainer = $('#kaa-mall-user-search-results');
                            resultsContainer.empty();
                            if (response.success && response.data.length) {
                                var list = $('<ul>');
                                $.each(response.data, function(i, user) {
                                    list.append($('<li>').data('userid', user.id).text(user.text));
                                });
                                resultsContainer.append(list);
                            } else {
                                resultsContainer.text('No users found.');
                            }
                        });
                    }, 500); // Debounce for 500ms
                });

                $(document).on('click', '#kaa-mall-user-search-results li', function() {
                    var userId = $(this).data('userid');
                    var userName = $(this).text();
                    $('#kaa-mall-user-id').val(userId);
                    $('#kaa-mall-user-search').val(userName);
                    $('#kaa-mall-user-search-results').empty();
                });
            });
        </script>
        <style>
            #kaa-mall-user-search-results {
                position: relative;
            }
            #kaa-mall-user-search-results ul {
                position: absolute;
                background: white;
                border: 1px solid #ddd;
                list-style: none;
                margin: 0;
                padding: 0;
                width: 100%;
                z-index: 100;
            }
            #kaa-mall-user-search-results li {
                padding: 8px 12px;
                cursor: pointer;
            }
            #kaa-mall-user-search-results li:hover {
                background: #f0f0f0;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    public function display_reseller_applications_page() {
        ?>
        <div class="wrap">
            <h2>Reseller Applications</h2>
            <?php
            $applications = get_posts( array(
                'post_type' => 'reseller_application',
                'post_status' => 'pending',
                'numberposts' => -1,
            ) );
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $applications ) ) : ?>
                        <?php foreach ( $applications as $application ) :
                            $user = get_user_by( 'id', $application->post_author );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo get_the_date( '', $application ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'kaa_mall_approve_reseller', 'application_id' => $application->ID ), admin_url( 'admin-post.php' ) ) ); ?>" class="button button-primary">Approve</a>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'kaa_mall_deny_reseller', 'application_id' => $application->ID ), admin_url( 'admin-post.php' ) ) ); ?>" class="button">Deny</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4">No pending applications.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function display_broadcasts_page() {
        if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
            $this->display_edit_broadcast_page();
        } else {
            $this->display_broadcast_list_page();
        }
    }

    private function display_edit_broadcast_page() {
        $broadcast_id = intval( $_GET['broadcast_id'] );
        $broadcast = get_post( $broadcast_id );
        ?>
        <div class="wrap">
            <h2>Edit Broadcast Message</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="kaa_mall_edit_broadcast">
                <input type="hidden" name="broadcast_id" value="<?php echo esc_attr( $broadcast_id ); ?>">
                <?php wp_nonce_field( 'kaa_mall_edit_broadcast_nonce', 'kaa_mall_edit_broadcast_nonce' ); ?>
                <p>
                    <label for="broadcast_subject">Subject</label>
                    <input type="text" name="broadcast_subject" id="broadcast_subject" class="widefat" value="<?php echo esc_attr( $broadcast->post_title ); ?>" required>
                </p>
                <p>
                    <label for="broadcast_message">Message</label>
                    <textarea name="broadcast_message" id="broadcast_message" class="widefat" rows="5" required><?php echo esc_textarea( $broadcast->post_content ); ?></textarea>
                </p>
                <?php submit_button( 'Update Broadcast' ); ?>
            </form>
        </div>
        <?php
    }

    private function display_broadcast_list_page() {
        ?>
        <div class="wrap">
            <h2>Broadcast Messages</h2>

            <div id="col-container">
                <div id="col-right">
                    <div class="col-wrap">
                        <h3>Previous Broadcasts</h3>
                        <?php
                        $broadcasts = get_posts( array(
                            'post_type' => 'kaa_mall_broadcast',
                            'numberposts' => -1,
                        ) );
                        ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $broadcasts ) ) : ?>
                                    <?php foreach ( $broadcasts as $broadcast ) : ?>
                                        <tr>
                                            <td><?php echo get_the_date( '', $broadcast ); ?></td>
                                            <td><?php echo esc_html( $broadcast->post_title ); ?></td>
                                            <td><?php echo esc_html( $broadcast->post_content ); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'kaa-mall-broadcasts', 'action' => 'edit', 'broadcast_id' => $broadcast->ID ), admin_url( 'admin.php' ) ) ); ?>" class="button">Edit</a>
                                                <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'kaa_mall_delete_broadcast', 'broadcast_id' => $broadcast->ID ), admin_url( 'admin-post.php' ) ) ); ?>" class="button">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4">No broadcasts sent yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="col-left">
                    <div class="col-wrap">
                        <h3>Send New Broadcast</h3>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="kaa_mall_send_broadcast">
                            <?php wp_nonce_field( 'kaa_mall_send_broadcast_nonce', 'kaa_mall_send_broadcast_nonce' ); ?>
                            <p>
                                <label for="broadcast_subject">Subject</label>
                                <input type="text" name="broadcast_subject" id="broadcast_subject" class="widefat" required>
                            </p>
                            <p>
                                <label for="broadcast_message">Message</label>
                                <textarea name="broadcast_message" id="broadcast_message" class="widefat" rows="5" required></textarea>
                            </p>
                            <p>
                                <label>Send to:</label><br>
                                <input type="radio" name="broadcast_recipient" value="all" checked> All Users<br>
                                <input type="radio" name="broadcast_recipient" value="resellers"> Resellers Only<br>
                            </p>
                            <?php submit_button( 'Send Broadcast' ); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
