<?php

class Kaa_Mall {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        if ( defined( 'KAA_MALL_VERSION' ) ) {
            $this->version = KAA_MALL_VERSION;
        } else {
            $this->version = '1.1.0';
        }
        $this->plugin_name = 'kaa-mall';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'plugins_loaded', array( $this, 'check_for_updates' ) );
    }

    public function check_for_updates() {
        $db_version = get_option( 'kaa_mall_version' );
        if ( version_compare( $db_version, $this->version, '<' ) ) {
            // Run updates for this version
            if ( version_compare( $db_version, '1.1.0', '<' ) ) {
                $this->update_to_1_1_0();
            }
            update_option( 'kaa_mall_version', $this->version );
        }
    }

    private function update_to_1_1_0() {
        $airteltigo_prices = array(
            array('name' => '1GB', 'price' => 5.00),
            array('name' => '2GB', 'price' => 10.00),
            array('name' => '3GB', 'price' => 14.00),
            array('name' => '4GB', 'price' => 18.00),
            array('name' => '5GB', 'price' => 24.00),
            array('name' => '6GB', 'price' => 28.00),
            array('name' => '8GB', 'price' => 34.00),
            array('name' => '10GB', 'price' => 44.00),
            array('name' => '15GB', 'price' => 64.00),
            array('name' => '20GB', 'price' => 84.00),
            array('name' => '25GB', 'price' => 106.00),
            array('name' => '30GB', 'price' => 127.00),
            array('name' => '40GB', 'price' => 166.00),
            array('name' => '50GB', 'price' => 206.00),
        );

        $telecel_prices = array(
            array('name' => '5GB', 'price' => 24.00),
            array('name' => '10GB', 'price' => 42.00),
            array('name' => '20GB', 'price' => 82.00),
            array('name' => '25GB', 'price' => 106.00),
            array('name' => '30GB', 'price' => 128.00),
            array('name' => '40GB', 'price' => 167.00),
            array('name' => '50GB', 'price' => 190.00),
            array('name' => '90GB', 'price' => 255.00),
            array('name' => '190GB', 'price' => 356.00),
            array('name' => '280GB', 'price' => 537.00),
            array('name' => '380GB', 'price' => 658.00),
        );

        $mtn_prices = array(
            array('name' => '1GB', 'price' => 5.2),
            array('name' => '2GB', 'price' => 10.2),
            array('name' => '3GB', 'price' => 15),
            array('name' => '4GB', 'price' => 20),
            array('name' => '5GB', 'price' => 25),
            array('name' => '6GB', 'price' => 30.5),
            array('name' => '8GB', 'price' => 35),
            array('name' => '10GB', 'price' => 45),
            array('name' => '15GB', 'price' => 65),
            array('name' => '20GB', 'price' => 86),
            array('name' => '25GB', 'price' => 104.2),
            array('name' => '30GB', 'price' => 125),
            array('name' => '40GB', 'price' => 167),
            array('name' => '50GB', 'price' => 206),
        );

        update_option( 'kaa_mall_airteltigo_prices', $airteltigo_prices );
        update_option( 'kaa_mall_telecel_prices', $telecel_prices );
        update_option( 'kaa_mall_mtn_prices', $mtn_prices );
    }

    public function register_post_types() {
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
                    'create_posts' => 'do_not_allow', // Disable creation from admin UI
                ),
                'map_meta_cap' => true,
            )
        );

        register_post_type( 'reseller_application',
            array(
                'labels'      => array(
                    'name'          => __( 'Reseller Applications', 'kaa-mall' ),
                    'singular_name' => __( 'Reseller Application', 'kaa-mall' ),
                ),
                'public'      => false,
                'show_ui'     => true,
                'show_in_menu'=> 'kaa_mall',
                'supports'    => array( 'title', 'author' ),
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
        require_once plugin_dir_path( __FILE__ ) . 'class-kaa-mall-helpers.php';
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
        // This is where we would run the loader, but we're not using one yet.
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
