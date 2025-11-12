<?php

class Kaa_Mall {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'kaa-mall';
        $this->version = '1.0.0';
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        add_action( 'init', array( $this, 'register_withdrawal_post_type' ) );
        add_action( 'init', array( $this, 'register_reseller_application_post_type' ) );
        add_action( 'init', array( $this, 'register_broadcast_post_type' ) );
        add_action( 'init', array( $this, 'register_marketing_post_type' ) );
    }

    public function register_marketing_post_type() {
        register_post_type( 'kaa_mall_marketing',
            array(
                'labels'      => array(
                    'name'          => __( 'Marketing Toolkit', 'kaa-mall' ),
                    'singular_name' => __( 'Marketing Message', 'kaa-mall' ),
                ),
                'public'      => false,
                'show_ui'     => true,
                'show_in_menu'=> 'kaa_mall',
                'supports'    => array( 'title', 'editor' ),
            )
        );
    }

    public function register_broadcast_post_type() {
        register_post_type( 'kaa_mall_broadcast',
            array(
                'labels'      => array(
                    'name'          => __( 'Broadcasts', 'kaa-mall' ),
                    'singular_name' => __( 'Broadcast', 'kaa-mall' ),
                ),
                'public'      => false,
                'show_ui'     => false,
                'show_in_menu'=> false,
                'supports'    => array( 'title', 'editor' ),
            )
        );
    }

    public function register_reseller_application_post_type() {
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
                    'create_posts' => 'do_not_allow', // Disable creation from admin UI
                ),
                'map_meta_cap' => true,
            )
        );
    }

    private function load_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'class-kaa-mall-wallet.php';
        require_once plugin_dir_path( __FILE__ ) . '../admin/class-kaa-mall-admin.php';
        require_once plugin_dir_path( __FILE__ ) . '../public/class-kaa-mall-public.php';
        require_once plugin_dir_path( __FILE__ ) . '../public/class-kaa-mall-reseller.php';
        require_once plugin_dir_path( __FILE__ ) . '../public/class-kaa-mall-auth.php';
        require_once plugin_dir_path( __FILE__ ) . '../public/class-kaa-mall-portal-header.php';
    }

    private function define_admin_hooks() {
        $plugin_admin = new Kaa_Mall_Admin( $this->get_plugin_name(), $this->get_version() );
    }

    private function define_public_hooks() {
        $plugin_public = new Kaa_Mall_Public( $this->get_plugin_name(), $this->get_version() );
        add_action( 'init', array( $plugin_public, 'init_session' ) );
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
