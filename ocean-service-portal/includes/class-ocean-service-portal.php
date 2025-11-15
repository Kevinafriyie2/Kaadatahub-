<?php

class Ocean_Service_Portal {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'ocean-service-portal';
        $this->version = '1.0.0';

        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_not_active_notice' ) );
            return;
        }

        $this->load_dependencies();
        $this->wallet = new Ocean_Service_Portal_Wallet();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    public function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    public function woocommerce_not_active_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e( 'OCEAN SERVICE requires WooCommerce to be installed and active.', 'ocean-service-portal' ); ?></p>
        </div>
        <?php
    }

    private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-loader.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ocean-service-portal-admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-ocean-service-portal-public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-products.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-orders.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-wallet.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-bundle-delivery.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-notifications.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-afa.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-whatsapp.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ocean-service-portal-wallet-gateway.php';

        $this->loader = new Ocean_Service_Portal_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new Ocean_Service_Portal_Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

        $plugin_products = new Ocean_Service_Portal_Products();
        $this->loader->add_action( 'woocommerce_product_options_general_product_data', $plugin_products, 'add_custom_fields' );
        $this->loader->add_action( 'woocommerce_process_product_meta', $plugin_products, 'save_custom_fields' );

        $plugin_orders = new Ocean_Service_Portal_Orders();
        $this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $plugin_orders, 'display_beneficiary_number_in_admin' );
    }

    private function define_public_hooks() {
        $plugin_public = new Ocean_Service_Portal_Public( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        $plugin_products = new Ocean_Service_Portal_Products();
        $this->loader->add_filter( 'woocommerce_get_price_html', $plugin_products, 'display_agent_price', 10, 2 );

        $plugin_orders = new Ocean_Service_Portal_Orders();
        $this->loader->add_action( 'woocommerce_checkout_create_order_line_item', $plugin_orders, 'add_beneficiary_number_to_order_item', 10, 4 );
        $this->loader->add_action( 'woocommerce_checkout_update_order_meta', $plugin_orders, 'add_beneficiary_number_to_order' );
        $this->loader->add_action( 'init', $plugin_orders, 'register_shortcodes' );

        $bundle_delivery = new Ocean_Service_Portal_Bundle_Delivery();
        $this->loader->add_action( 'woocommerce_order_status_processing', $bundle_delivery, 'maybe_deliver_bundle_automatically' );

        $notifications = new Ocean_Service_Portal_Notifications();
        $this->loader->add_action( 'woocommerce_order_status_completed', $notifications, 'send_notifications' );

        $afa = new Ocean_Service_Portal_AFA();
        $this->loader->add_action( 'init', $afa, 'register_cpt' );

        $whatsapp = new Ocean_Service_Portal_WhatsApp();
        $this->loader->add_action( 'wp_footer', $whatsapp, 'render_button' );

        $this->loader->add_filter( 'woocommerce_payment_gateways', $this, 'add_wallet_gateway' );
    }

    public function add_wallet_gateway( $gateways ) {
        $gateways[] = 'Ocean_Service_Portal_Wallet_Gateway';
        return $gateways;
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}
