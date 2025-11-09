<?php

class Kaa_Mall_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_shortcode( 'kaa_admin_portal', array( $this, 'render_admin_portal' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_post_kaa_mall_top_up_wallet', array( $this, 'handle_top_up_wallet' ) );
    }

    public function enqueue_styles() {
        wp_enqueue_style( 'kaa-mall-admin', plugin_dir_url( __FILE__ ) . 'css/kaa-mall-admin.css', array(), $this->version, 'all' );
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
            'KAA Mall Settings',
            'KAA Mall',
            'manage_options',
            'kaa_mall',
            array( $this, 'render_settings_page' ),
            'dashicons-store'
        );
    }

    public function register_settings() {
        register_setting( 'kaa_mall_options', 'kaa_mall_mtn_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_airteltigo_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_vodafone_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_public_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_secret_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_business_email' );
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

                <h3>MTN Prices</h3>
                <textarea name="kaa_mall_mtn_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_mtn_prices') ); ?></textarea>
                <h3>AirtelTigo Prices</h3>
                <textarea name="kaa_mall_airteltigo_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_airteltigo_prices') ); ?></textarea>
                <h3>Vodafone Prices</h3>
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
        return get_posts( $args );
    }

    private function get_all_user_wallet_balances() {
        $users = get_users( array(
            'meta_key' => '_kaa_mall_wallet_balance',
            'meta_compare' => 'EXISTS'
        ) );
        $balances = array();
        foreach ( $users as $user ) {
            $balance = get_user_meta( $user->ID, '_kaa_mall_wallet_balance', true );
            $balances[ $user->display_name ] = floatval( $balance );
        }
        return $balances;
    }

    private function get_all_users() {
        return get_users();
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

        ob_start();
        ?>
        <div class="kaa-mall-admin-portal">
            <h2>Admin Dashboard</h2>
            <div class="admin-section">
                <h3>Top Up User Wallet</h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="kaa_mall_top_up_wallet">
                    <?php wp_nonce_field( 'kaa_mall_top_up_wallet_nonce', 'kaa_mall_top_up_wallet_nonce' ); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Select User</th>
                            <td>
                                <select name="user_id">
                                    <?php foreach ( $all_users as $user ) : ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html( $user->display_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                <h3>All Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_orders as $order_post ) :
                            $order = wc_get_order( $order_post->ID );
                            $date_created = $order->get_date_created();
                            ?>
                            <tr>
                                <td><?php echo $order->get_id(); ?></td>
                                <td><?php echo $date_created ? $date_created->date_i18n( 'Y-m-d H:i:s' ) : 'N/A'; ?></td>
                                <td><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></td>
                                <td><?php echo $order->get_formatted_order_total(); ?></td>
                                <td><?php echo wc_get_order_status_name( $order->get_status() ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="admin-section">
                <h3>User Wallet Balances</h3>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $wallet_balances as $user => $balance ) : ?>
                            <tr>
                                <td><?php echo $user; ?></td>
                                <td>₵<?php echo number_format( $balance, 2 ); ?></td>
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
        <?php
        return ob_get_clean();
    }
}
