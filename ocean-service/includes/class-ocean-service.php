<?php

class Ocean_Service {

    protected static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'includes/class-ocean-service-activator.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'includes/class-ocean-service-wallet-manager.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'includes/class-ocean-service-wallet-gateway.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'includes/class-ocean-service-api-handler.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'includes/class-ocean-service-emails.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'admin/class-ocean-service-admin.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'public/class-ocean-service-public.php';
    }

    private function init_hooks() {
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_wallet_gateway' ) );
        if ( is_admin() ) {
            $admin = new Ocean_Service_Admin( 'ocean-service', OCEAN_SERVICE_VERSION );
        } else {
            $public = new Ocean_Service_Public( 'ocean-service', OCEAN_SERVICE_VERSION );
            add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_styles' ) );
            add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_scripts' ) );
        }

        add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_upgrade_to_agent' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'trigger_bundle_delivery' ) );
        add_action( 'init', array( $this, 'register_wallet_topup_post_type' ) );
        add_action( 'init', array( $this, 'register_afa_submission_post_type' ) );
    }

    public function register_afa_submission_post_type() {
        $args = array(
            'public'       => false,
            'show_ui'      => true,
            'label'        => 'AFA Submissions',
            'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap' => true,
        );
        register_post_type( 'afa_submission', $args );
    }

    public function trigger_bundle_delivery( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            $beneficiary_number = $item->get_meta( 'Beneficiary Number' );

            if ( $beneficiary_number ) {
                if ( Ocean_Service_API_Handler::send_bundle_request( $order_id, $product_id, $beneficiary_number ) ) {
                    Ocean_Service_Emails::send_bundle_purchase_confirmation( $order_id );
                }
            }
        }
    }

    public function add_wallet_gateway( $gateways ) {
        $gateways[] = 'Ocean_Service_Wallet_Gateway';
        return $gateways;
    }

    public function register_wallet_topup_post_type() {
        $args = array(
            'public'       => false,
            'show_ui'      => true,
            'label'        => 'Wallet Top-Up Requests',
            'supports'     => array( 'title', 'editor', 'author' ),
            'capabilities' => array(
                'create_posts' => 'do_not_allow', // Disable creation from admin
            ),
            'map_meta_cap' => true,
        );
        register_post_type( 'wallet_topup_request', $args );
    }

    public function maybe_upgrade_to_agent( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return;
        }

        $agent_fee_product_id = get_option( 'ocean_service_agent_fee_product_id' );
        if ( ! $agent_fee_product_id ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            if ( $item->get_product_id() == $agent_fee_product_id ) {
                $user = new WP_User( $user_id );
                $user->set_role( 'agent' );
                Ocean_Service_Emails::send_agent_welcome_email( $user_id );
                break;
            }
        }
    }
}
