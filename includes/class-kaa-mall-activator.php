<?php

/**
 * Fired during plugin activation.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 * @author     Your Name <you@yourwebsite.com>
 */
class Kaa_Mall_Activator {

	/**
	 * Create the database tables for the wallet and profit wallet.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		// Table for user wallets
		$table_name = $wpdb->prefix . 'kaa_mall_wallets';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			balance decimal(10, 2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Table for profit wallets
		$table_name = $wpdb->prefix . 'kaa_mall_profit_wallets';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			balance decimal(10, 2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Table for wallet transactions
		$table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			amount decimal(10, 2) NOT NULL,
			type varchar(20) NOT NULL,
			description text NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );


		self::create_data_bundle_products();
		add_rewrite_rule('^store/([^/]*)/?', 'index.php?kaa_mall_store=$matches[1]', 'top');
		flush_rewrite_rules();
	}

	private static function create_data_bundle_products() {
		$products = array(
            // Airteltigo
            'Airteltigo 1GB' => 5, 'Airteltigo 2GB' => 10, 'Airteltigo 3GB' => 14, 'Airteltigo 4GB' => 18,
            'Airteltigo 5GB' => 24, 'Airteltigo 6GB' => 28, 'Airteltigo 8GB' => 34, 'Airteltigo 10GB' => 44,
            'Airteltigo 15GB' => 64, 'Airteltigo 20GB' => 84, 'Airteltigo 25GB' => 106, 'Airteltigo 30GB' => 127,
            'Airteltigo 40GB' => 166, 'Airteltigo 50GB' => 206,
            // Vodafone
            'Vodafone 5GB' => 24, 'Vodafone 10GB' => 42, 'Vodafone 20GB' => 82, 'Vodafone 25GB' => 106,
            'Vodafone 30GB' => 128, 'Vodafone 40GB' => 167, 'Vodafone 50GB' => 190, 'Vodafone 90GB' => 255,
            'Vodafone 190GB' => 356, 'Vodafone 280GB' => 537, 'Vodafone 380GB' => 658,
            // MTN
            'MTN 1GB' => 5.2, 'MTN 2GB' => 10.2, 'MTN 3GB' => 15, 'MTN 4GB' => 20, 'MTN 5GB' => 25,
            'MTN 6GB' => 30.5, 'MTN 8GB' => 35, 'MTN 10GB' => 45, 'MTN 15GB' => 65, 'MTN 20GB' => 86,
            'MTN 25GB' => 104.2, 'MTN 30GB' => 125, 'MTN 40GB' => 167, 'MTN 50GB' => 206,
        );

		foreach ( $products as $title => $price ) {
			// Check if product already exists
			$existing_product = get_page_by_title( $title, OBJECT, 'product' );

			if ( $existing_product ) {
				// Update existing product
				$product = wc_get_product( $existing_product->ID );
				if($product) {
					$product->set_regular_price( $price );
					$product->save();
				}
			} else {
				// Create new product
				$product = new WC_Product_Simple();
				$product->set_name( $title );
				$product->set_regular_price( $price );
				$product->set_virtual( true );
				$product->set_status( 'publish' );
				$product->save();
			}
		}
	}
}
