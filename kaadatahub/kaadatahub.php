<?php
/**
 * Plugin Name: Kaadatahub
 * Plugin URI:  https://kaadatahub.com
 * Description: A complete mobile data bundles platform with a multi-level reseller system and WooCommerce integration.
 * Version:     1.0.0
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

// Start the session
add_action('init', 'kdh_start_session', 1);
function kdh_start_session() {
    if(!session_id()) {
        session_start();
    }
}

/**
 * Define constants
 */
define( 'KDH_VERSION', '1.0.0' );
define( 'KDH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KDH_MIN_PHP_VERSION', '7.4' );
define( 'KDH_MIN_WP_VERSION', '5.8' );
define( 'KDH_MIN_WC_VERSION', '5.0' );


// Activation hook
register_activation_hook(__FILE__, 'kdh_activate');

function kdh_activate() {
    // Check for plugin dependencies
    if ( ! function_exists('is_plugin_active') ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    // Check for WooCommerce
    if ( ! is_plugin_active('woocommerce/woocommerce.php') ) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Please install and activate WooCommerce before activating Kaadatahub.');
    }

    // Create custom tables
    kdh_create_custom_tables();
}


// Deactivation hook
register_deactivation_hook(__FILE__, 'kdh_deactivate');

function kdh_deactivate() {
    // Clean up on deactivation
}


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
add_action('wp_enqueue_scripts', 'kdh_enqueue_frontend_assets');
add_action('admin_enqueue_scripts', 'kdh_enqueue_admin_assets');

function kdh_enqueue_frontend_assets() {
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

function kdh_enqueue_admin_assets() {
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

function kdh_create_custom_tables() {
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
