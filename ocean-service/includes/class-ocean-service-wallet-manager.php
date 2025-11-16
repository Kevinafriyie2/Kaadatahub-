<?php

class Ocean_Service_Wallet_Manager {

    public static function get_balance( $user_id ) {
        return (float) get_user_meta( $user_id, '_wallet_balance', true );
    }

    public static function add_funds( $user_id, $amount ) {
        $balance = self::get_balance( $user_id );
        $new_balance = $balance + (float) $amount;
        update_user_meta( $user_id, '_wallet_balance', $new_balance );
        return $new_balance;
    }

    public static function subtract_funds( $user_id, $amount ) {
        $balance = self::get_balance( $user_id );
        $new_balance = $balance - (float) $amount;

        if ( $new_balance < 0 ) {
            return false; // Insufficient funds
        }

        update_user_meta( $user_id, '_wallet_balance', $new_balance );
        self::log_transaction( $user_id, $amount, 'debit', 'Funds subtracted from wallet.' );
        return $new_balance;
    }

    public static function log_transaction( $user_id, $amount, $type, $description ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ocean_service_wallet_transactions';

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'created_at' => current_time( 'mysql' ),
            )
        );
    }
}
