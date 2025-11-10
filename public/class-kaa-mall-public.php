<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/public
 * @author     Your Name <you@yourwebsite.com>
 */
class Kaa_Mall_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/kaa-mall-public.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4', 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/kaa-mall-public.js', array( 'jquery' ), $this->version, false );
		wp_localize_script(
			$this->plugin_name,
			'kaa_mall_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'kaa_mall_ajax_nonce' ),
			)
		);

		// Only load the Paystack script on the dashboard or store page
		if ( is_a_page_with_shortcode('kaa_mall_dashboard') || get_query_var('kaa_mall_store') ) {
			wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js', array(), $this->version, true );
		}
	}

	/**
	 * Renders the dashboard shortcode.
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes.
	 * @return   string            The shortcode output.
	 */
	public function get_user_wallet_balance( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return 0.00;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_wallets';

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);

		return is_null( $balance ) ? 0.00 : (float) $balance;
	}

	public function render_dashboard_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to view your dashboard.</p>';
		}

		$balance = $this->get_user_wallet_balance();
		$products = $this->get_data_bundle_products();

		ob_start();
		require 'partials/kaa-mall-dashboard-display.php';
		return ob_get_clean();
	}

	public function get_data_bundle_products() {
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_virtual',
					'value' => 'yes',
				),
			),
		);
		$products_query = new WP_Query($args);
		$products = array(
			'mtn' => array(),
			'airteltigo' => array(),
			'vodafone' => array(),
		);

		if ($products_query->have_posts()) {
			while ($products_query->have_posts()) {
				$products_query->the_post();
				$product = wc_get_product(get_the_ID());
				$name = $product->get_name();

				if (stripos($name, 'MTN') !== false) {
					$products['mtn'][] = $product;
				} elseif (stripos($name, 'AirtelTigo') !== false) {
					$products['airteltigo'][] = $product;
				} elseif (stripos($name, 'Vodafone') !== false) {
					$products['vodafone'][] = $product;
				}
			}
			wp_reset_postdata();
		}
		return $products;
	}

	/**
	 * Register all shortcodes.
	 *
	 * @since 1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'kaa_mall_dashboard', array( $this, 'render_dashboard_shortcode' ) );
	}

	/**
	 * AJAX handler for loading page content.
	 *
	 * @since 1.0.0
	 */
	public function load_page_callback() {
		check_ajax_referer( 'kaa_mall_ajax_nonce', 'nonce' );

		$page = sanitize_text_field( $_POST['page'] );
		$network = isset( $_POST['network'] ) ? sanitize_text_field( $_POST['network'] ) : '';
		$partial_path = plugin_dir_path( __FILE__ ) . 'partials/kaa-mall-' . $page . '-display.php';

		$balance = $this->get_user_wallet_balance();
		$products = $this->get_data_bundle_products();
		$profit_balance = 0;
		if (in_array('reseller', (array) wp_get_current_user()->roles)) {
			$profit_balance = Kaa_Mall_Profit_Wallet::get_profit_wallet_balance(get_current_user_id());
		}

		if ( file_exists( $partial_path ) ) {
			ob_start();
			require $partial_path;
			wp_send_json_success( ob_get_clean() );
		} else {
			wp_send_json_error( 'The requested content could not be found.' );
		}

		wp_die();
	}

	/**
	 * AJAX handler for direct data bundle purchase.
	 *
	 * @since 1.0.0
	 */
	public function direct_purchase_callback() {
		check_ajax_referer( 'kaa_mall_ajax_nonce', 'nonce' );

		$reseller_id = isset($_POST['reseller_id']) ? absint($_POST['reseller_id']) : 0;

		if ( ! is_user_logged_in() && ! $reseller_id ) {
			wp_send_json_error( 'You must be logged in to make a purchase.' );
			wp_die();
		}

		$product_id = absint( $_POST['product_id'] );
		$payment_method = sanitize_text_field( $_POST['payment_method'] );
		$reseller_id = isset($_POST['reseller_id']) ? absint($_POST['reseller_id']) : 0;

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( 'Invalid product.' );
			wp_die();
		}

		if ( 'wallet' === $payment_method ) {
			$this->handle_wallet_payment( $product, $reseller_id );
		} elseif ( 'paystack' === $payment_method ) {
			$this->handle_paystack_payment( $product, $reseller_id );
		} else {
			wp_send_json_error( 'Invalid payment method.' );
			wp_die();
		}
	}

	private function handle_wallet_payment( $product, $reseller_id = 0 ) {
		$user_id = get_current_user_id();
		$current_balance = $this->get_user_wallet_balance( $user_id );

		$product_price = $product->get_price();
		if ($reseller_id) {
			$reseller_price = get_user_meta($reseller_id, '_reseller_price_' . $product->get_id(), true);
			if ($reseller_price) {
				$product_price = $reseller_price;
			}
		}

		if ( $current_balance < $product_price ) {
			wp_send_json_error( 'Insufficient wallet balance.' );
			wp_die();
		}

		// Deduct from wallet and create order
		$order = $this->create_woocommerce_order( $product, $user_id, 'wallet' );
		$description = 'Purchase of ' . $product->get_name();
		Kaa_Mall_Wallet::update_wallet_balance( $user_id, -$product_price, 'debit', $description, $order->get_id() );

		$guest_email = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';

		if ($reseller_id) {
			$base_price = $product->get_price();
			$profit = $product_price - $base_price;
			if ($profit > 0) {
				Kaa_Mall_Profit_Wallet::update_profit_wallet_balance($reseller_id, $profit);
			}
		}

		wp_send_json_success( 'Purchase successful!' );
		wp_die();
	}

	private function handle_paystack_payment( $product ) {
		$user = wp_get_current_user();
		$paystack_pk = get_option( 'kaa_mall_paystack_public_key' );

		if ( ! $paystack_pk ) {
			wp_send_json_error( 'Paystack payment is not configured.' );
			wp_die();
		}

		wp_send_json_success( array(
			'publicKey' => $paystack_pk,
			'email'     => $user->user_email,
			'amount'    => $product->get_price() * 100, // Paystack amount is in kobo
			'currency'  => 'GHS',
			'productId' => $product->get_id(),
		) );
		wp_die();
	}

	public function verify_paystack_transaction_callback() {
		check_ajax_referer( 'kaa_mall_ajax_nonce', 'nonce' );

		$reference = sanitize_text_field( $_POST['reference'] );
		$product_id = absint( $_POST['productId'] );

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( 'Invalid product.' );
			wp_die();
		}

		$paystack_sk = get_option( 'kaa_mall_paystack_secret_key' );
		$response = wp_remote_get( "https://api.paystack.co/transaction/verify/$reference", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $paystack_sk,
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Could not verify transaction.' );
			wp_die();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 'success' === $body->data->status ) {
			$guest_email = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';
			$reseller_id = isset($_POST['reseller_id']) ? absint($_POST['reseller_id']) : 0;
			$this->create_woocommerce_order( $product, get_current_user_id(), 'paystack', $guest_email );

			if ($reseller_id) {
				$base_price = $product->get_price();
				$reseller_price = get_user_meta($reseller_id, '_reseller_price_' . $product->get_id(), true);
				if ($reseller_price) {
					$profit = $reseller_price - $base_price;
					if ($profit > 0) {
						Kaa_Mall_Profit_Wallet::update_profit_wallet_balance($reseller_id, $profit);
					}
				}
			}

			wp_send_json_success( 'Payment successful!' );
		} else {
			wp_send_json_error( 'Payment verification failed.' );
		}

		wp_die();
	}

	private function create_woocommerce_order( $product, $user_id, $payment_method, $guest_email = '' ) {
		$order_data = array();
		if ($user_id) {
			$order_data['customer_id'] = $user_id;
		}
		$order = wc_create_order($order_data);

		$order->add_product( $product, 1 );

		$billing_address = array();
		if ($user_id) {
			$billing_address['email'] = get_userdata($user_id)->user_email;
		} else {
			$billing_address['email'] = $guest_email;
		}
		$order->set_address($billing_address, 'billing');

		$order->set_payment_method_title( 'KAA Mall ' . ucfirst( $payment_method ) );
		$order->calculate_totals();
		$order->update_status( 'processing', 'Purchase from KAA Mall dashboard.' );
	}

	public function save_reseller_prices_callback() {
		check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

		if (!is_user_logged_in() || !in_array('reseller', (array) wp_get_current_user()->roles)) {
			wp_send_json_error('You are not authorized to perform this action.');
			wp_die();
		}

		$prices = $_POST['prices'];
		$user_id = get_current_user_id();

		foreach ($prices as $product_id => $price) {
			update_user_meta($user_id, '_reseller_price_' . $product_id, sanitize_text_field($price));
		}

		wp_send_json_success('Prices saved successfully.');
		wp_die();
	}

	public function save_shop_name_callback() {
		check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

		if (!is_user_logged_in() || !in_array('reseller', (array) wp_get_current_user()->roles)) {
			wp_send_json_error('You are not authorized to perform this action.');
			wp_die();
		}

		$shop_name = sanitize_title($_POST['shop_name']);
		$user_id = get_current_user_id();

		// Check if the shop name is unique
		$existing_user = get_users(array(
			'meta_key' => 'kaa_mall_shop_name',
			'meta_value' => $shop_name,
			'exclude' => array($user_id),
		));

		if (!empty($existing_user)) {
			wp_send_json_error('This shop name is already taken. Please choose another one.');
			wp_die();
		}

		update_user_meta($user_id, 'kaa_mall_shop_name', $shop_name);

		wp_send_json_success('Shop name saved successfully.');
		wp_die();
	}

	public function top_up_wallet_callback() {
		check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error('You must be logged in to top up your wallet.');
			wp_die();
		}

		$amount = floatval($_POST['amount']);
		$user = wp_get_current_user();
		$paystack_pk = get_option('kaa_mall_paystack_public_key');

		if (!$paystack_pk) {
			wp_send_json_error('Paystack payment is not configured.');
			wp_die();
		}

		wp_send_json_success(array(
			'publicKey' => $paystack_pk,
			'email'     => $user->user_email,
			'amount'    => $amount * 100, // Paystack amount is in kobo
			'currency'  => 'GHS',
		));
		wp_die();
	}

	public function verify_top_up_callback() {
		check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

		$reference = sanitize_text_field($_POST['reference']);
		$amount = floatval($_POST['amount']);

		$paystack_sk = get_option('kaa_mall_paystack_secret_key');
		$response = wp_remote_get("https://api.paystack.co/transaction/verify/$reference", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $paystack_sk,
			),
		));

		if (is_wp_error($response)) {
			wp_send_json_error('Could not verify transaction.');
			wp_die();
		}

		$body = json_decode(wp_remote_retrieve_body($response));

		if ('success' === $body->data->status) {
			$description = 'Wallet top-up via Paystack';
			Kaa_Mall_Wallet::update_wallet_balance(get_current_user_id(), $amount, 'credit', $description, $reference);
			wp_send_json_success('Wallet topped up successfully.');
		} else {
			wp_send_json_error('Payment verification failed.');
		}

		wp_die();
	}

	public function afa_registration_callback() {
		check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error('You must be logged in to register for AFA bundles.');
			wp_die();
		}

		$user = wp_get_current_user();
		$paystack_pk = get_option('kaa_mall_paystack_public_key');

		if (!$paystack_pk) {
			wp_send_json_error('Paystack payment is not configured.');
			wp_die();
		}

		wp_send_json_success(array(
			'publicKey' => $paystack_pk,
			'email'     => $user->user_email,
			'amount'    => 13 * 100, // AFA registration fee
			'currency'  => 'GHS',
		));
		wp_die();
	}

	public function verify_afa_registration_callback() {
		check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

		$reference = sanitize_text_field($_POST['reference']);

		$paystack_sk = get_option('kaa_mall_paystack_secret_key');
		$response = wp_remote_get("https://api.paystack.co/transaction/verify/$reference", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $paystack_sk,
			),
		));

		if (is_wp_error($response)) {
			wp_send_json_error('Could not verify transaction.');
			wp_die();
		}

		$body = json_decode(wp_remote_retrieve_body($response));

		if ('success' === $body->data->status) {
			// Here you would typically save the registration details to the database
			wp_send_json_success('AFA registration successful!');
		} else {
			wp_send_json_error('Payment verification failed.');
		}

		wp_die();
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'kaa_mall_store';
		return $vars;
	}

	public function handle_store_rewrite() {
		if ( get_query_var( 'kaa_mall_store' ) ) {
			$shop_name = get_query_var( 'kaa_mall_store' );
			$user = get_users(array(
				'meta_key' => 'kaa_mall_shop_name',
				'meta_value' => $shop_name,
			));

			if ( !empty( $user ) ) {
				$reseller_id = $user[0]->ID;
				// Now you can display the store for this reseller
				// You might want to create a new template for this
				// For now, let's just enqueue a script and display a partial
				wp_enqueue_script( $this->plugin_name . '-store', plugin_dir_url( __FILE__ ) . 'js/kaa-mall-store.js', array( 'jquery' ), $this->version, false );
				wp_localize_script(
					$this->plugin_name . '-store',
					'kaa_mall_store_ajax',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'kaa_mall_ajax_nonce' ),
						'reseller_id' => $reseller_id
					)
				);

				// Include a template file
				include_once( plugin_dir_path( __FILE__ ) . 'partials/kaa-mall-store-display.php' );
				exit;
			}
		}
	}
}

if (!function_exists('is_a_page_with_shortcode')) {
    function is_a_page_with_shortcode($shortcode) {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, $shortcode);
    }
}
