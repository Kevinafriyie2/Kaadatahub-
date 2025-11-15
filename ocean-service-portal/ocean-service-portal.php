<?php
/**
 * Plugin Name: OCEAN SERVICE
 * Plugin URI: https://example.com/
 * Description: A telecom bundle selling platform for WordPress.
 * Version: 1.0.0
 * Author: Jules
 * Author URI: https://example.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ocean-service-portal
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ocean-service-portal.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ocean_service_portal() {
    $plugin = new Ocean_Service_Portal();
    $plugin->run();
}
run_ocean_service_portal();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ocean-service-portal-activator.php
 */
function activate_ocean_service_portal() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ocean-service-portal-activator.php';
    Ocean_Service_Portal_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ocean-service-portal-deactivator.php
 */
function deactivate_ocean_service_portal() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ocean-service-portal-deactivator.php';
    Ocean_Service_Portal_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ocean_service_portal' );
register_deactivation_hook( __FILE__, 'deactivate_ocean_service_portal' );
