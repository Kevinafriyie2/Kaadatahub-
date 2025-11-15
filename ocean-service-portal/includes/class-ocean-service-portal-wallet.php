<?php

class Ocean_Service_Portal_Wallet {

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'handle_wallet_top_up' ) );
    }

    public function get_balance( $agent_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kaa_wallet';
        $balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM $table_name WHERE agent_id = %d", $agent_id ) );
        return $balance ? $balance : 0;
    }

    public function top_up( $agent_id, $amount ) {
        global $wpdb;
        $wallet_table = $wpdb->prefix . 'kaa_wallet';
        $tx_table = $wpdb->prefix . 'kaa_wallet_tx';

        // Update balance
        $current_balance = $this->get_balance( $agent_id );
        $new_balance = $current_balance + $amount;
        $wpdb->replace(
            $wallet_table,
            array(
                'agent_id' => $agent_id,
                'balance'  => $new_balance,
            ),
            array( '%d', '%f' )
        );

        // Log transaction
        $wpdb->insert(
            $tx_table,
            array(
                'agent_id'   => $agent_id,
                'amount'     => $amount,
                'type'       => 'credit',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%f', '%s', '%s' )
        );
    }

    public function pay_with_wallet( $order_id ) {
        $order = wc_get_order( $order_id );
        $agent_id = $order->get_customer_id();
        $amount = $order->get_total();

        if ( $this->get_balance( $agent_id ) < $amount ) {
            return new WP_Error( 'insufficient_funds', __( 'Insufficient funds in your wallet.', 'ocean-service-portal' ) );
        }

        global $wpdb;
        $wallet_table = $wpdb->prefix . 'kaa_wallet';
        $tx_table = $wpdb->prefix . 'kaa_wallet_tx';

        // Update balance
        $current_balance = $this->get_balance( $agent_id );
        $new_balance = $current_balance - $amount;
        $wpdb->replace(
            $wallet_table,
            array(
                'agent_id' => $agent_id,
                'balance'  => $new_balance,
            ),
            array( '%d', '%f' )
        );

        // Log transaction
        $wpdb->insert(
            $tx_table,
            array(
                'agent_id'   => $agent_id,
                'amount'     => $amount,
                'type'       => 'debit',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%f', '%s', '%s' )
        );

        return true;
    }

    public function handle_wallet_top_up( $order_id ) {
        $order = wc_get_order( $order_id );
        $agent_id = $order->get_customer_id();

        if ( ! $agent_id || ! user_can( $agent_id, 'kaa_agent' ) ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product->get_sku() === 'KAA_WALLET_TOP_UP' ) {
                $this->top_up( $agent_id, $item->get_total() );
            }
        }
    }
}
