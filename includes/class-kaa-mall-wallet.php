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
	public static function update_wallet_balance( $user_id, $amount, $type, $description, $reference_id = null ) {
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

		if ($result) {
			self::log_transaction($user_id, $type, $amount, $description, $reference_id);
		}

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

	/**
	 * Log a new wallet transaction.
	 *
	 * @since    1.0.0
	 * @param    int      $user_id        The ID of the user.
	 * @param    string   $type           The type of transaction (e.g., 'credit', 'debit').
	 * @param    float    $amount         The amount of the transaction.
	 * @param    string   $description    A description of the transaction.
	 * @param    int|null $reference_id   Optional reference ID (e.g., order ID).
	 * @return   bool                     True on success, false on failure.
	 */
	public static function log_transaction( $user_id, $type, $amount, $description, $reference_id = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';

		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'      => $user_id,
				'type'         => $type,
				'amount'       => $amount,
				'description'  => $description,
				'reference_id' => $reference_id,
			),
			array(
				'%d',
				'%s',
				'%f',
				'%s',
				'%d',
			)
		);

		return (bool) $result;
	}

	/**
	 * Get wallet transactions for a user.
	 *
	 * @since    1.0.0
	 * @param    int      $user_id    The ID of the user.
	 * @param    int      $limit      The number of transactions to retrieve.
	 * @param    int      $offset     The offset for pagination.
	 * @return   array                An array of transaction objects.
	 */
	public static function get_wallet_transactions( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d ORDER BY transaction_date DESC LIMIT %d OFFSET %d",
			$user_id,
			$limit,
			$offset
		) );

		return $results;
	}
}
