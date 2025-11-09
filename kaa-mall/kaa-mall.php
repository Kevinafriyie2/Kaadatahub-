<?php
/**
 * Plugin Name: KAA Mall
 * Plugin URI: https://yourwebsite.com
 * Description: A modern data bundle and wallet portal for WordPress.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kaa-mall
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin version.
define( 'KAA_MALL_VERSION', '1.0.0' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-kaa-mall.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_kaa_mall() {
    $plugin = new Kaa_Mall();
    $plugin->run();
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-kaa-mall-activator.php
 */
function activate_kaa_mall() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-kaa-mall-activator.php';
    Kaa_Mall_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-kaa-mall-deactivator.php
 */
function deactivate_kaa_mall() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-kaa-mall-deactivator.php';
    Kaa_Mall_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_kaa_mall' );
register_deactivation_hook( __FILE__, 'deactivate_kaa_mall' );

run_kaa_mall();
