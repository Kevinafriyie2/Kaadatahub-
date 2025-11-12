<?php
/**
 * Plugin Name: Kaadatahub
 * Plugin URI:  https://kaadatahub.com
 * Description: A complete mobile data bundles platform with a multi-level reseller system and WooCommerce integration.
 * Version:     1.0.1
 * Author:      Kaadatahub
 * Author URI:  https://kaadatahub.com
 * License:     GPLv2 or later
 * Text Domain: kaadatahub
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Define constants
 */
define( 'KDH_VERSION', '1.0.1' );
define( 'KDH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 * The main plugin class
 */
final class Kaadatahub {

    /**
     * The single instance of the class.
     *
     * @var Kaadatahub
     */
    protected static $_instance = null;

    /**
     * Main Kaadatahub Instance.
     *
     * Ensures only one instance of Kaadatahub is loaded or can be loaded.
     *
     * @static
     * @return Kaadatahub - Main instance.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Kaadatahub Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), -1 );
    }

    /**
     * On plugins_loaded, load the real plugin.
     */
    public function on_plugins_loaded() {
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_not_active_notice' ) );
            return;
        }

        // Activation hook
        register_activation_hook(__FILE__, array( $this, 'activate' ));

        // Deactivation hook
        register_deactivation_hook(__FILE__, array( $this, 'deactivate' ));

        // Start the session
        add_action('init', array( $this, 'start_session' ), 1);

        // Include the dependencies
        require_once KDH_PLUGIN_DIR . 'includes/class-wallet.php';
        require_once KDH_PLUGIN_DIR . 'includes/class-reseller.php';
        require_once KDH_PLUGIN_DIR . 'includes/class-api.php';
        require_once KDH_PLUGIN_DIR . 'includes/class-woocommerce.php';
        require_once KDH_PLUGIN_DIR . 'includes/shortcodes.php';

        // Admin includes
        if ( is_admin() ) {
            require_once KDH_PLUGIN_DIR . 'admin/class-admin-pages.php';
            require_once KDH_PLUGIN_DIR . 'includes/class-admin.php';
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ));
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * WooCommerce not active notice.
     *
     * @return void
     */
    public function woocommerce_not_active_notice() {
        ?>
        <div class="error">
            <p><?php _e( 'Kaadatahub requires WooCommerce to be active. Please activate WooCommerce.', 'kaadatahub' ); ?></p>
        </div>
        <?php
    }

    /**
     * Activation hook.
     */
    public function activate() {
        // Create custom tables
        $this->create_custom_tables();
    }

    /**
     * Deactivation hook.
     */
    public function deactivate() {
        // Clean up on deactivation
    }

    /**
     * Start session.
     */
    public function start_session() {
        if(!session_id()) {
            session_start();
        }
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'kdh-frontend-css',
            KDH_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            KDH_VERSION
        );

        wp_enqueue_script(
            'kdh-frontend-js',
            KDH_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            KDH_VERSION,
            true
        );

        wp_localize_script(
            'kdh-frontend-js',
            'kdh_ajax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('kdh-ajax-nonce'),
            ]
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'kdh-admin-css',
            KDH_PLUGIN_URL . 'assets/css/admin.css',
            [],
            KDH_VERSION
        );

        wp_enqueue_script(
            'kdh-admin-js',
            KDH_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            KDH_VERSION,
            true
        );
    }

    /**
     * Create custom tables.
     */
    public function create_custom_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_name = $wpdb->prefix . 'kdh_wallets';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            balance decimal(10, 2) NOT NULL DEFAULT '0.00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table_name = $wpdb->prefix . 'kdh_transactions';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10, 2) NOT NULL,
            type varchar(50) NOT NULL,
            description text,
            transaction_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table_name = $wpdb->prefix . 'kdh_resellers';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            commission_rate decimal(5, 2) NOT NULL DEFAULT '0.00',
            referral_code varchar(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table_name = $wpdb->prefix . 'kdh_withdrawals';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10, 2) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            request_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql );
    }
}

/**
 * Main instance of Kaadatahub.
 *
 * Returns the main instance of Kaadatahub to prevent the need to use globals.
 *
 * @return Kaadatahub
 */
function kaadatahub() {
    return Kaadatahub::instance();
}

// Global for backwards compatibility.
$GLOBALS['kaadatahub'] = kaadatahub();
