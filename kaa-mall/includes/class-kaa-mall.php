<?php

class Kaa_Mall {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        if ( ! defined( 'KAA_MALL_VERSION' ) ) {
            define( 'KAA_MALL_VERSION', '1.0.3' );
        }
        $this->plugin_name = 'kaa-mall';
        $this->version = KAA_MALL_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'init', array( $this, 'register_withdrawal_post_type' ) );
        add_action( 'plugins_loaded', array( $this, 'run_upgrade_routines' ) );
    }

    public function run_upgrade_routines() {
        $current_version = get_option( 'kaa_mall_version', '1.0.0' );
        if ( version_compare( $current_version, '1.0.3', '<' ) ) {
            // Since the activation hook now handles product creation/deletion,
            // we can just call the same robust functions here.
            require_once plugin_dir_path( __FILE__ ) . 'class-kaa-mall-activator.php';
            Kaa_Mall_Activator::delete_all_data_products();
            Kaa_Mall_Activator::create_data_bundle_products();
            update_option( 'kaa_mall_version', '1.0.3' );
        }
    }

    // The update_product_prices function is now obsolete as the activator logic is reused.
    // It is kept here to avoid breaking any potential future logic, but it is not called.
    private function update_product_prices() {
        $products_to_update = array(); // This is now empty.
        // Logic has been moved to the run_upgrade_routines function for consistency.
    }


    public function register_withdrawal_post_type() {
        register_post_type( 'kaa_withdrawal',
            array(
                'labels'      => array(
                    'name'          => __( 'Withdrawals', 'kaa-mall' ),
                    'singular_name' => __( 'Withdrawal', 'kaa-mall' ),
                ),
                'public'      => false,
                'show_ui'     => true,
                'show_in_menu'=> 'kaa_mall',
                'supports'    => array( 'title' ),
                'capabilities' => array(
                    'create_posts' => 'do_not_allow',
                ),
                'map_meta_cap' => true,
            )
        );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^shop/([^/]*)/?$', 'index.php?shop_name=$matches[1]', 'top' );
        add_filter( 'query_vars', function( $query_vars ) {
            $query_vars[] = 'shop_name';
            return $query_vars;
        } );
        add_action( 'template_redirect', function() {
            $shop_name = get_query_var( 'shop_name' );
            if ( $shop_name ) {
                $users = get_users( array(
                    'meta_key' => '_kaa_mall_shop_name',
                    'meta_value' => $shop_name,
                ) );
                if ( ! empty( $users ) ) {
                    $reseller_id = $users[0]->ID;
                    $redirect_url = add_query_arg( 'ref', $reseller_id, get_option( 'kaa_mall_user_portal_url' ) );
                    wp_redirect( $redirect_url );
                    exit;
                }
            }
        } );
    }

    private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-kaa-mall-admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-reseller.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-auth.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-portal-header.php';
    }

    private function define_admin_hooks() {
        $plugin_admin = new Kaa_Mall_Admin( $this->get_plugin_name(), $this->get_version() );
    }

    private function define_public_hooks() {
        $plugin_public = new Kaa_Mall_Public( $this->get_plugin_name(), $this->get_version() );
        $plugin_reseller = new Kaa_Mall_Reseller();
        $plugin_auth = new Kaa_Mall_Auth();
    }

    public function run() {
        // Not in use.
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
