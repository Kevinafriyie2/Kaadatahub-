<?php

/**
 * The profit wallet functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 */

/**
 * The profit wallet functionality of the plugin.
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/includes
 * @author     Your Name <you@yourwebsite.com>
 */
class Kaa_Mall_Profit_Wallet {

	/**
	 * Get the profit wallet balance for a user.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id    The ID of the user.
	 * @return   float              The balance of the user's profit wallet.
	 */
	public static function get_profit_wallet_balance( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_profit_wallets';
		$balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM $table_name WHERE user_id = %d", $user_id ) );
		return (float) $balance;
	}

	/**
	 * Update the profit wallet balance for a user.
	 *
	 * @since    1.0.0
	 * @param    int      $user_id    The ID of the user.
	 * @param    float    $amount     The amount to add (positive) or subtract (negative).
	 * @return   bool                 True on success, false on failure.
	 */
	public static function update_profit_wallet_balance( $user_id, $amount ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_profit_wallets';
		$current_balance = self::get_profit_wallet_balance( $user_id );
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

}
