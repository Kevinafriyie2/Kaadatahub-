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
        add_action( 'admin_post_kaa_mall_bulk_update_order_status', array( $this, 'handle_bulk_update_order_status' ) );
        add_action( 'admin_post_kaa_mall_mark_withdrawal_paid', array( $this, 'handle_mark_withdrawal_paid' ) );
        add_action( 'admin_post_kaa_mall_approve_reseller', array( $this, 'handle_approve_reseller' ) );
        add_action( 'admin_post_kaa_mall_deny_reseller', array( $this, 'handle_deny_reseller' ) );
    }

    public function handle_approve_reseller() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $application_id = intval( $_GET['application_id'] );
        $user_id = get_post_field( 'post_author', $application_id );

        $user = new WP_User( $user_id );
        $user->add_role( 'reseller' );

        wp_update_post( array(
            'ID' => $application_id,
            'post_status' => 'publish',
        ) );

        wp_redirect( admin_url( 'admin.php?page=kaa_mall_reseller_applications' ) );
        exit;
    }

    public function handle_deny_reseller() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $application_id = intval( $_GET['application_id'] );
        wp_update_post( array(
            'ID' => $application_id,
            'post_status' => 'trash',
        ) );

        wp_redirect( admin_url( 'admin.php?page=kaa_mall_reseller_applications' ) );
        exit;
    }

    public function render_reseller_applications_page() {
        $args = array(
            'post_type' => 'reseller_application',
            'post_status' => 'pending',
            'posts_per_page' => -1,
        );
        $applications = get_posts( $args );
        ?>
        <div class="wrap">
            <h2>Reseller Applications</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $applications ) ) : ?>
                        <?php foreach ( $applications as $application ) : ?>
                            <tr>
                                <td><?php echo get_the_author_meta( 'display_name', $application->post_author ); ?></td>
                                <td><?php echo get_the_date( '', $application ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'kaa_mall_approve_reseller', 'application_id' => $application->ID ), admin_url( 'admin-post.php' ) ) ); ?>" class="button button-primary">Approve</a>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'kaa_mall_deny_reseller', 'application_id' => $application->ID ), admin_url( 'admin-post.php' ) ) ); ?>" class="button">Deny</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3">No pending applications.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
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
            'KAA Mall Settings',
            'KAA Mall',
            'manage_options',
            'kaa_mall',
            array( $this, 'render_settings_page' ),
            'dashicons-store'
        );
        add_submenu_page(
            'kaa_mall',
            'Reseller Applications',
            'Reseller Applications',
            'manage_options',
            'kaa_mall_reseller_applications',
            array( $this, 'render_reseller_applications_page' )
        );
    }

    public function register_settings() {
        $price_sanitize_args = array( 'sanitize_callback' => array( $this, 'sanitize_prices_callback' ) );
        register_setting( 'kaa_mall_options', 'kaa_mall_mtn_prices', $price_sanitize_args );
        register_setting( 'kaa_mall_options', 'kaa_mall_airteltigo_prices', $price_sanitize_args );
        register_setting( 'kaa_mall_options', 'kaa_mall_vodafone_prices', $price_sanitize_args );
        register_setting( 'kaa_mall_options', 'kaa_mall_telecel_prices', $price_sanitize_args );

        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_public_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_secret_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_business_email' );
        register_setting( 'kaa_mall_options', 'kaa_mall_user_portal_url' );
        register_setting( 'kaa_mall_options', 'kaa_mall_reseller_portal_url' );
        register_setting( 'kaa_mall_options', 'kaa_mall_reseller_application_fee' );
    }

    public function sanitize_prices_callback( $input ) {
        $prices_arr = json_decode( stripslashes( $input ), true );
        $sanitized_prices = array();

        if ( is_array( $prices_arr ) ) {
            foreach ( $prices_arr as $item ) {
                if ( ! empty( $item['name'] ) && isset( $item['price'] ) && is_numeric( $item['price'] ) ) {
                    $sanitized_prices[] = array(
                        'name'  => sanitize_text_field( trim($item['name']) ),
                        'price' => floatval( $item['price'] ),
                    );
                }
            }
        }
        return $sanitized_prices;
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
                        <th scope="row">Reseller Application Fee</th>
                        <td><input type="number" name="kaa_mall_reseller_application_fee" value="<?php echo esc_attr( get_option('kaa_mall_reseller_application_fee', '50') ); ?>" /></td>
                    </tr>
                </table>

                <h3>Network Prices</h3>
                <div id="kaa-mall-price-manager">
                    <?php
                    $networks = ['mtn', 'airteltigo', 'vodafone', 'telecel'];
                    foreach ($networks as $network) {
                        ?>
                        <div class="network-prices" id="prices-<?php echo $network; ?>" style="margin-bottom: 20px;">
                            <h4><?php echo ucfirst($network); ?> Bundles</h4>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Bundle Name</th>
                                        <th>Price (GH₵)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $prices_arr = get_option('kaa_mall_' . $network . '_prices');
                                    if (is_array($prices_arr)) {
                                        foreach ($prices_arr as $item) {
                                            $name = $item['name'];
                                            $price = $item['price'];
                                            ?>
                                            <tr>
                                                <td><input type="text" value="<?php echo esc_attr($name); ?>" class="bundle-name"></td>
                                                <td><input type="number" step="0.01" value="<?php echo esc_attr($price); ?>" class="bundle-price"></td>
                                                <td><button type="button" class="button remove-price-row">Remove</button></td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <button type="button" class="button add-price-row" data-network="<?php echo $network; ?>" style="margin-top: 10px;">Add Row</button>
                        </div>
                        <input type="hidden" name="kaa_mall_<?php echo $network; ?>_prices" id="hidden-prices-<?php echo $network; ?>">
                        <?php
                    }
                    ?>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#kaa-mall-price-manager').on('click', '.add-price-row', function() {
                    var network = $(this).data('network');
                    var table_body = $('#prices-' + network).find('tbody');
                    var new_row = '<tr>' +
                        '<td><input type="text" class="bundle-name" placeholder="e.g., 500MB"></td>' +
                        '<td><input type="number" step="0.01" class="bundle-price" placeholder="e.g., 5.00"></td>' +
                        '<td><button type="button" class="button remove-price-row">Remove</button></td>' +
                        '</tr>';
                    table_body.append(new_row);
                });

                $('#kaa-mall-price-manager').on('click', '.remove-price-row', function() {
                    $(this).closest('tr').remove();
                });

                $('form').on('submit', function() {
                    var networks = ['mtn', 'airteltigo', 'vodafone', 'telecel'];
                    networks.forEach(function(network) {
                        var prices_arr = [];
                        var table_rows = $('#prices-' + network).find('tbody tr');
                        table_rows.each(function() {
                            var name = $(this).find('.bundle-name').val().trim();
                            var price = $(this).find('.bundle-price').val().trim();
                            if (name && price) {
                                prices_arr.push({ name: name, price: price });
                            }
                        });
                        $('#hidden-prices-' + network).val(JSON.stringify(prices_arr));
                    });
                });
            });
        </script>
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
            $balances[ $user->display_name ] = floatval( $balance );
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

    private function get_withdrawal_requests() {
        $args = array(
            'post_type'   => 'kaa_withdrawal',
            'post_status' => 'pending',
            'numberposts' => -1,
        );
        return get_posts( $args );
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

        ob_start();
        ?>
        <style>
            :root {
                --primary-color: #4a90e2;
                --primary-hover-color: #357ABD;
                --background-color: #f7f8fc;
                --card-background-color: #ffffff;
                --text-color: #333;
                --heading-color: #1a1a1a;
                --border-color: #e6e6e6;
                --shadow-color: rgba(0, 0, 0, 0.08);
            }

            .kaa-mall-admin-portal {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                color: var(--text-color);
            }

            .kaa-mall-admin-portal h2 {
                font-size: 2em;
                font-weight: 600;
                color: var(--heading-color);
                margin-bottom: 30px;
            }

            .admin-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 25px;
            }

            .admin-section {
                background: var(--card-background-color);
                border-radius: 12px;
                box-shadow: 0 5px 15px var(--shadow-color);
                padding: 25px;
                margin-bottom: 25px;
            }

            .admin-section h3 {
                margin-top: 0;
                margin-bottom: 20px;
                color: var(--heading-color);
                font-size: 1.4em;
                font-weight: 600;
            }

            .admin-section table {
                width: 100%;
                border-collapse: collapse;
            }

            .admin-section th, .admin-section td {
                border-bottom: 1px solid var(--border-color);
                padding: 15px;
                text-align: left;
            }

            .admin-section th {
                background-color: #f9fafb;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.85em;
                letter-spacing: 0.5px;
            }

            .admin-section tr:last-child td {
                border-bottom: none;
            }

            .admin-section tr:hover {
                background-color: #f7f8fc;
            }

            .admin-section a {
                background-color: var(--primary-color);
                color: white;
                padding: 10px 15px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 600;
                transition: background-color 0.2s ease;
            }

            .admin-section a:hover {
                background-color: var(--primary-hover-color);
            }
        </style>
        <div class="kaa-mall-admin-portal">
            <h2>Admin Dashboard</h2>

            <div class="admin-grid">
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

            <div class="admin-section" style="grid-column: 1 / -1;">
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

            <div class="admin-section" style="grid-column: 1 / -1;">
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
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
