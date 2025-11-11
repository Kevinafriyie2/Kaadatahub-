<?php

class Kaa_Mall_Wallet {

    /**
     * Get the wallet balance for a user.
     *
     * @param int $user_id The ID of the user.
     * @return float The wallet balance.
     */
    public static function get_balance( $user_id ) {
        $balance = get_user_meta( $user_id, '_kaa_mall_wallet_balance', true );
        return empty( $balance ) ? 0.00 : floatval( $balance );
    }

    /**
     * Update a user's wallet balance and log the transaction.
     *
     * @param int    $user_id The ID of the user.
     * @param float  $amount  The amount to add (positive) or deduct (negative).
     * @param string $type    The type of transaction (e.g., 'top-up', 'purchase', 'fee', 'admin-top-up').
     * @param string $details A description of the transaction.
     */
    public static function update_balance_and_log( $user_id, $amount, $type, $details ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';

        $current_balance = self::get_balance( $user_id );
        $new_balance = $current_balance + $amount;
        update_user_meta( $user_id, '_kaa_mall_wallet_balance', $new_balance );

        $wpdb->insert(
            $table_name,
            array(
                'user_id'       => $user_id,
                'amount'        => $amount,
                'type'          => $type,
                'details'       => $details,
                'balance_after' => $new_balance,
            )
        );
    }
}
