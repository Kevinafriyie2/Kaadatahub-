<?php
/**
 * Plugin Name:       WASSCE Checker PRO
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       A plugin to check WASSCE results.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jules
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       wassce-checker-pro
 * Domain Path:       /languages
 */

function activate_wassce_checker_pro() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wassce-checker-pro-activator.php';
    WASSCE_Checker_PRO_Activator::activate();
}

register_activation_hook( __FILE__, 'activate_wassce_checker_pro' );

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/class-wassce-checker-pro-admin.php';
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wassce-checker-pro-woocommerce.php';
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wassce-checker-pro-stock-alert.php';

add_action( 'wassce_checker_pro_codes_uploaded', function() {
    delete_option( 'wassce_checker_pro_low_stock_alert_sent' );
} );
