<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 */

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 * @author     Your Name <you@yourwebsite.com>
 */
class Kaa_Mall {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Kaa_Mall_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'KAA_MALL_VERSION' ) ) {
			$this->version = KAA_MALL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'kaa-mall';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_auth_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Kaa_Mall_Loader. Orchestrates the hooks of the plugin.
	 * - Kaa_Mall_Public. Defines all hooks for the public side of the site.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-kaa-mall-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-kaa-mall-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-public.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-auth.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-kaa-mall-wallet.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-kaa-mall-profit-wallet.php';
		$this->loader = new Kaa_Mall_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Kaa_Mall_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Kaa_Mall_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// AJAX hooks
		$this->loader->add_action( 'wp_ajax_kaa_mall_load_page', $plugin_public, 'load_page_callback' );
		$this->loader->add_action( 'wp_ajax_nopriv_kaa_mall_load_page', $plugin_public, 'load_page_callback' );

		$this->loader->add_action( 'wp_ajax_kaa_mall_direct_purchase', $plugin_public, 'direct_purchase_callback' );
		$this->loader->add_action( 'wp_ajax_nopriv_kaa_mall_direct_purchase', $plugin_public, 'direct_purchase_callback' );

		$this->loader->add_action( 'wp_ajax_kaa_mall_verify_paystack_transaction', $plugin_public, 'verify_paystack_transaction_callback' );
		$this->loader->add_action( 'wp_ajax_nopriv_kaa_mall_verify_paystack_transaction', $plugin_public, 'verify_paystack_transaction_callback' );

		$this->loader->add_action( 'wp_ajax_kaa_mall_save_reseller_prices', $plugin_public, 'save_reseller_prices_callback' );
		$this->loader->add_action( 'wp_ajax_kaa_mall_save_shop_name', $plugin_public, 'save_shop_name_callback' );

		// Wallet Top Up
		$this->loader->add_action( 'wp_ajax_kaa_mall_top_up_wallet', $plugin_public, 'top_up_wallet_callback' );
		$this->loader->add_action( 'wp_ajax_kaa_mall_verify_top_up', $plugin_public, 'verify_top_up_callback' );

		// AFA Registration
		$this->loader->add_action( 'wp_ajax_kaa_mall_afa_registration', $plugin_public, 'afa_registration_callback' );
		$this->loader->add_action( 'wp_ajax_kaa_mall_verify_afa_registration', $plugin_public, 'verify_afa_registration_callback' );

		// Admin Wallet Adjustment
		$this->loader->add_action( 'wp_ajax_kaa_mall_adjust_wallet_balance', $plugin_admin, 'adjust_wallet_balance_callback' );

		$this->loader->add_filter( 'query_vars', $plugin_public, 'register_query_vars' );
		$this->loader->add_action( 'template_redirect', $plugin_public, 'handle_store_rewrite' );
	}

	private function define_auth_hooks() {
		$plugin_auth = new Kaa_Mall_Auth( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'init', $plugin_auth, 'register_shortcode' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_auth, 'enqueue_styles' );

		// AJAX hooks for login/registration
		$this->loader->add_action( 'wp_ajax_nopriv_kaa_mall_login', $plugin_auth, 'ajax_login' );
		$this->loader->add_action( 'wp_ajax_nopriv_kaa_mall_register', $plugin_auth, 'ajax_register' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
