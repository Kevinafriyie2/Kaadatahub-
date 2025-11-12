<?php

class KDH_Wallet {

    public static function get_wallet_balance($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_wallets';
        $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM $table_name WHERE user_id = %d", $user_id));
        return $balance ? $balance : 0;
    }

    public static function add_to_wallet($user_id, $amount, $description = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_wallets';

        $current_balance = self::get_wallet_balance($user_id);
        $new_balance = $current_balance + $amount;

        $wpdb->replace(
            $table_name,
            ['user_id' => $user_id, 'balance' => $new_balance],
            ['%d', '%f']
        );

        self::log_transaction($user_id, $amount, 'credit', $description);
    }

    public static function purchase_with_wallet($user_id, $amount, $description = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_wallets';

        $current_balance = self::get_wallet_balance($user_id);

        if ($current_balance < $amount) {
            return new WP_Error('insufficient_funds', 'Insufficient funds in wallet.');
        }

        $new_balance = $current_balance - $amount;

        $wpdb->update(
            $table_name,
            ['balance' => $new_balance],
            ['user_id' => $user_id],
            ['%f'],
            ['%d']
        );

        self::log_transaction($user_id, $amount, 'debit', $description);

        return true;
    }

    public static function log_transaction($user_id, $amount, $type, $description) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_transactions';

        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'transaction_date' => current_time('mysql'),
            ],
            ['%d', '%f', '%s', '%s', '%s']
        );
    }

    public static function get_transaction_history($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_transactions';

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY transaction_date DESC", $user_id));
    }
}
