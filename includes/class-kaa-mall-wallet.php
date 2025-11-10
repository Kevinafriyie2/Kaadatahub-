<?php

/**
 * The wallet functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 */

/**
 * The wallet functionality of the plugin.
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 * @author     Your Name <you@yourwebsite.com>
 */
class Kaa_Mall_Wallet {

	/**
	 * Get the wallet balance for a user.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id    The ID of the user.
	 * @return   float              The balance of the user's wallet.
	 */
	public static function get_wallet_balance( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_wallets';
		$balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM $table_name WHERE user_id = %d", $user_id ) );
		return (float) $balance;
	}

	/**
	 * Update the wallet balance for a user.
	 *
	 * @since    1.0.0
	 * @param    int      $user_id    The ID of the user.
	 * @param    float    $amount     The amount to add (positive) or subtract (negative).
	 * @return   bool                 True on success, false on failure.
	 */
	public static function update_wallet_balance( $user_id, $amount ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_wallets';
		$current_balance = self::get_wallet_balance( $user_id );
		$new_balance = $current_balance + $amount;

		if ( $new_balance < 0 ) {
			return false; // Insufficient funds
		}

		$result = $wpdb->replace(
			$table_name,
			array(
				'user_id' => $user_id,
				'balance' => $new_balance,
			),
			array(
				'%d',
				'%f',
			)
		);

		return (bool) $result;
	}

	/**
	 * Create a new wallet for a user.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id    The ID of the user.
	 * @return   bool               True on success, false on failure.
	 */
	public static function create_wallet( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_wallets';

		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id' => $user_id,
				'balance' => 0,
			),
			array(
				'%d',
				'%f',
			)
		);

		return (bool) $result;
	}
}
