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
        add_action( 'kaa_mall_update_reseller_tiers_cron', array( $this, 'update_reseller_tiers' ) );

        if ( ! wp_next_scheduled( 'kaa_mall_update_reseller_tiers_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'kaa_mall_update_reseller_tiers_cron' );
        }
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

    public function update_reseller_tiers() {
        $tiers = get_option( 'kaa_mall_reseller_tiers', array() );
        if ( empty( $tiers ) ) {
            return;
        }

        // Sort tiers by sales goal descending
        usort( $tiers, function( $a, $b ) {
            return $b['goal'] - $a['goal'];
        } );

        $resellers = get_users( array( 'role' => 'reseller' ) );
        foreach ( $resellers as $reseller ) {
            $total_sales = Kaa_Mall_Helpers::get_reseller_total_sales( $reseller->ID );
            $assigned_tier = 'None';
            foreach ( $tiers as $tier ) {
                if ( $total_sales >= $tier['goal'] ) {
                    $assigned_tier = $tier['name'];
                    break;
                }
            }
            update_user_meta( $reseller->ID, '_kaa_mall_reseller_tier', $assigned_tier );
        }
    }

    private function get_reseller_total_sales( $reseller_id ) {
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array( 'wc-completed', 'wc-processing' ),
            'numberposts' => -1,
            'meta_query'  => array(
                array(
                    'key'   => '_reseller_id',
                    'value' => $reseller_id,
                ),
            ),
        );
        $orders = get_posts( $args );
        $total_sales = 0;
        foreach ( $orders as $order_post ) {
            $order = wc_get_order( $order_post->ID );
            $total_sales += $order->get_total();
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
            'KAA Mall',
            'KAA Mall',
            'manage_options',
            'kaa_mall_dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-store',
            2
        );
        add_submenu_page(
            'kaa_mall_dashboard',
            'User Management',
            'User Management',
            'manage_options',
            'kaa_mall_user_management',
            array( $this, 'render_user_management_page' )
        );
        add_submenu_page(
            'kaa_mall_dashboard',
            'Reports',
            'Reports',
            'manage_options',
            'kaa_mall_reports',
            array( $this, 'render_reports_page' )
        );
        add_submenu_page(
            'kaa_mall_dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'kaa_mall_settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'kaa_mall_options', 'kaa_mall_mtn_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_airteltigo_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_vodafone_prices' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_public_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_paystack_secret_key' );
        register_setting( 'kaa_mall_options', 'kaa_mall_business_email' );
        register_setting( 'kaa_mall_options', 'kaa_mall_user_portal_url' );
        register_setting( 'kaa_mall_options', 'kaa_mall_reseller_portal_url' );
        register_setting( 'kaa_mall_options', 'kaa_mall_reseller_tiers' );
    }

    private function get_total_reseller_profit() {
        $resellers = get_users( array( 'role' => 'reseller' ) );
        $total_profit = 0;
        foreach ( $resellers as $reseller ) {
            $total_profit += get_user_meta( $reseller->ID, '_kaa_mall_reseller_profit_balance', true );
        }
        return $total_profit;
    }

    private function get_total_sales_today() {
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array( 'wc-completed', 'wc-processing' ),
            'date_query'  => array(
                array(
                    'after' => 'today',
                ),
            ),
            'numberposts' => -1,
        );
        $orders = get_posts( $args );
        $total_sales = 0;
        foreach ( $orders as $order_post ) {
            $order = wc_get_order( $order_post->ID );
            $total_sales += $order->get_total();
        }
        return $total_sales;
    }

    private function get_new_users_this_month() {
        $args = array(
            'date_query' => array(
                array(
                    'after' => 'first day of this month',
                ),
            ),
        );
        $users = get_users( $args );
        return count( $users );
    }

    private function get_monthly_revenue_data() {
        $revenue_data = array();
        for ( $i = 11; $i >= 0; $i-- ) {
            $month = date( 'Y-m', strtotime( "-$i months" ) );
            $revenue_data[ $month ] = 0;
        }

        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => 'wc-completed',
            'numberposts' => -1,
            'date_query'  => array(
                array(
                    'after' => '12 months ago',
                ),
            ),
        );
        $orders = get_posts( $args );
        foreach ( $orders as $order_post ) {
            $order = wc_get_order( $order_post->ID );
            $month = $order->get_date_created()->date( 'Y-m' );
            if ( isset( $revenue_data[ $month ] ) ) {
                $revenue_data[ $month ] += $order->get_total();
            }
        }
        return $revenue_data;
    }

    private function get_popular_bundles() {
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array( 'wc-completed', 'wc-processing' ),
            'numberposts' => -1,
        );
        $orders = get_posts( $args );
        $bundles = array();
        foreach ( $orders as $order_post ) {
            $order = wc_get_order( $order_post->ID );
            $bundle = $order->get_meta( 'Bundle' );
            if ( ! empty( $bundle ) ) {
                if ( ! isset( $bundles[ $bundle ] ) ) {
                    $bundles[ $bundle ] = 0;
                }
                $bundles[ $bundle ]++;
            }
        }
        arsort( $bundles );
        return array_slice( $bundles, 0, 5, true );
    }

    public function render_dashboard_page() {
        ?>
        <style>
            .kaa-admin-card { background: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
            .kaa-admin-card h3 { margin-top: 0; }
            .kaa-admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
            .kaa-admin-stat-card { text-align: center; }
            .kaa-admin-stat-card .stat-number { font-size: 2.5em; font-weight: bold; }
            .kaa-admin-stat-card .stat-label { color: #555; }
        </style>
        <div class="wrap">
            <h1>Kaadatahub Dashboard</h1>

            <div class="kaa-admin-grid">
                <div class="kaa-admin-card kaa-admin-stat-card">
                    <div class="stat-number"><?php echo wc_price( $this->get_total_reseller_profit() ); ?></div>
                    <div class="stat-label">Total Reseller Profit</div>
                </div>
                <div class="kaa-admin-card kaa-admin-stat-card">
                    <div class="stat-number"><?php echo wc_price( $this->get_total_sales_today() ); ?></div>
                    <div class="stat-label">Total Sales Today</div>
                </div>
                <div class="kaa-admin-card kaa-admin-stat-card">
                     <div class="stat-number"><?php echo $this->get_new_users_this_month(); ?></div>
                    <div class="stat-label">New Users This Month</div>
                </div>
            </div>

            <div class="kaa-admin-card">
                <h3>Popular Bundles</h3>
                <ul>
                    <?php
                    $popular_bundles = $this->get_popular_bundles();
                    foreach ( $popular_bundles as $bundle => $count ) {
                        echo '<li>' . esc_html( $bundle ) . ' (' . $count . ' sales)</li>';
                    }
                    ?>
                </ul>
            </div>

             <div class="kaa-admin-card">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=kaa_mall_user_management'); ?>">Manage Users</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=kaa_mall_reports'); ?>">View Reports</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=kaa_mall_settings'); ?>">Plugin Settings</a></li>
                    <li><a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>">View All Orders</a></li>
                </ul>
            </div>
        </div>
        <?php
    }

    public function render_user_management_page() {
        $users = get_users();
        ?>
        <div class="wrap">
            <h1>User Management</h1>
            <div class="kaa-admin-grid">
                <div class="kaa-admin-card" style="grid-column: 1 / -1;">
                    <h3>All Users</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Wallet Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $users as $user ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $user->user_login ); ?></td>
                                    <td><?php echo esc_html( $user->user_email ); ?></td>
                                    <td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
                                    <td>₵<?php echo number_format( $this->get_wallet_balance( $user->ID ), 2 ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="kaa-admin-card">
                    <h3>Top Up User Wallet</h3>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="kaa_mall_top_up_wallet">
                        <?php wp_nonce_field( 'kaa_mall_top_up_wallet_nonce', 'kaa_mall_top_up_wallet_nonce' ); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Select User</th>
                                <td>
                                    <select name="user_id" style="width: 100%;">
                                        <?php foreach ( $users as $user ) : ?>
                                            <option value="<?php echo $user->ID; ?>"><?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Amount (GH₵)</th>
                                <td><input type="number" name="amount" step="0.01" min="0.01" required /></td>
                            </tr>
                        </table>
                        <?php submit_button( 'Top Up Wallet' ); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_reports_page() {
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true );
        $monthly_revenue = $this->get_monthly_revenue_data();
        $popular_bundles = $this->get_popular_bundles();
        ?>
        <div class="wrap">
            <h1>Sales Reports</h1>
            <div class="kaa-admin-card">
                <h3>Monthly Revenue</h3>
                <canvas id="monthlyRevenueChart" width="400" height="200"></canvas>
            </div>
            <div class="kaa-admin-card">
                <h3>Top Selling Bundles</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Bundle</th>
                            <th>Sales Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $popular_bundles as $bundle => $count ) : ?>
                            <tr>
                                <td><?php echo esc_html( $bundle ); ?></td>
                                <td><?php echo $count; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('monthlyRevenueChart').getContext('2d');
                var myChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode( array_keys( $monthly_revenue ) ); ?>,
                        datasets: [{
                            label: 'Monthly Revenue',
                            data: <?php echo json_encode( array_values( $monthly_revenue ) ); ?>,
                            backgroundColor: 'rgba(74, 144, 226, 0.5)',
                            borderColor: 'rgba(74, 144, 226, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        </script>
        <?php
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

                <h3>MTN Prices</h3>
                <textarea name="kaa_mall_mtn_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_mtn_prices') ); ?></textarea>
                <h3>AirtelTigo Prices</h3>
                <textarea name="kaa_mall_airteltigo_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_airteltigo_prices') ); ?></textarea>
                <h3>Telecel Prices</h3>
                <textarea name="kaa_mall_telecel_prices" rows="10" cols="50"><?php echo esc_attr( get_option('kaa_mall_telecel_prices') ); ?></textarea>

                <h3>Reseller Tiers</h3>
                <div id="kaa-mall-tier-manager">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Tier Name</th>
                                <th>Sales Goal (GH₵)</th>
                                <th>Discount (%)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="tier-rows-container">
                        </tbody>
                    </table>
                    <button type="button" class="button" id="add-tier-row" style="margin-top: 10px;">Add Tier</button>
                    <input type="hidden" name="kaa_mall_reseller_tiers" id="hidden-tiers-input">
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Tiers
                var tiers = <?php echo json_encode( get_option('kaa_mall_reseller_tiers', array() ) ); ?>;

                function render_tier_rows() {
                    var container = $('#tier-rows-container');
                    container.empty();
                    tiers.forEach(function(tier, index) {
                        var row = '<tr>' +
                            '<td><input type="text" class="tier-name" value="' + tier.name + '"></td>' +
                            '<td><input type="number" class="tier-goal" value="' + tier.goal + '"></td>' +
                            '<td><input type="number" class="tier-discount" value="' + tier.discount + '"></td>' +
                            '<td><button type="button" class="button remove-tier-row" data-index="' + index + '">Remove</button></td>' +
                            '</tr>';
                        container.append(row);
                    });
                }

                $('#add-tier-row').on('click', function() {
                    tiers.push({ name: '', goal: 0, discount: 0 });
                    render_tier_rows();
                });

                $('#tier-rows-container').on('click', '.remove-tier-row', function() {
                    var index = $(this).data('index');
                    tiers.splice(index, 1);
                    render_tier_rows();
                });

                $('form').on('submit', function() {
                    var updated_tiers = [];
                    $('#tier-rows-container tr').each(function() {
                        var name = $(this).find('.tier-name').val();
                        var goal = $(this).find('.tier-goal').val();
                        var discount = $(this).find('.tier-discount').val();
                        updated_tiers.push({ name: name, goal: goal, discount: discount });
                    });
                    $('#hidden-tiers-input').val(JSON.stringify(updated_tiers));
                });

                render_tier_rows();
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
