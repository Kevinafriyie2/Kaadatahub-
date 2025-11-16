<?php
/**
 * Ocean Service
 *
 * @package           Ocean_Service
 * @author            Jules
 * @wordpress-plugin
 * Plugin Name:       Ocean Service
 * Description:       A plugin to sell data bundles and manage agents.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jules
 * Text Domain:       ocean-service
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'OCEAN_SERVICE_VERSION', '1.0.0' );
define( 'OCEAN_SERVICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OCEAN_SERVICE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 * The code that runs during plugin activation.
 */
function activate_ocean_service() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ocean-service-activator.php';
    Ocean_Service_Activator::activate();
}

register_activation_hook( __FILE__, 'activate_ocean_service' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require OCEAN_SERVICE_PLUGIN_DIR . 'includes/class-ocean-service.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_ocean_service() {
    return Ocean_Service::get_instance();
}

run_ocean_service();
