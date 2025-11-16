<?php

class Ocean_Service_Activator {

    public static function activate() {
        // Get the 'customer' role to clone its capabilities.
        $customer = get_role( 'customer' );
        $capabilities = ( $customer instanceof WP_Role ) ? $customer->capabilities : array();

        // Add the 'agent' role with the same capabilities as a customer.
        add_role(
            'agent',
            __( 'Agent', 'ocean-service' ),
            $capabilities
        );

        self::create_wallet_transactions_table();
    }

    private static function create_wallet_transactions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ocean_service_wallet_transactions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount float NOT NULL,
            type varchar(20) NOT NULL,
            description text NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
