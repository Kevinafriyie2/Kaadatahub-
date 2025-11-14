<?php

class WASSCE_Checker_PRO_Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wassce_checker_codes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            serial varchar(255) NOT NULL,
            pin varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'unused',
            order_id bigint(20) UNSIGNED,
            assigned_at datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY serial (serial),
            UNIQUE KEY pin (pin)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
