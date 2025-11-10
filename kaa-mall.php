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
 * The code that runs during plugin activation.
 * This action is documented in includes/class-kaa-mall-activator.php
 */
function activate_kaa_mall() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-kaa-mall-activator.php';
	Kaa_Mall_Activator::activate();
}

register_activation_hook( __FILE__, 'activate_kaa_mall' );

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
 * Initialize the plugin.
 */
function init_kaa_mall() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'kaa_mall_woocommerce_not_active_notice' );
		return;
	}

	require plugin_dir_path( __FILE__ ) . 'includes/class-kaa-mall.php';
	run_kaa_mall();
}

add_action( 'plugins_loaded', 'init_kaa_mall' );

function kaa_mall_woocommerce_not_active_notice() {
    ?>
    <div class="error">
        <p><?php _e( 'KAA Mall requires WooCommerce to be installed and active. Please install and activate WooCommerce.', 'kaa-mall' ); ?></p>
    </div>
    <?php
}
