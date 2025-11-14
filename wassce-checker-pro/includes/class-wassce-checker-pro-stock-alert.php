<?php

class WASSCE_Checker_PRO_Stock_Alert {

    public function __construct() {
        add_action( 'admin_init', array( $this, 'check_stock' ) );
    }

    public function check_stock() {
        if ( get_option( 'wassce_checker_pro_low_stock_alert_sent' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wassce_checker_codes';
        $unused_codes = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'unused'" );

        if ( $unused_codes < 10 ) {
            $this->send_alert();
            update_option( 'wassce_checker_pro_low_stock_alert_sent', true );
        }
    }

    public function send_alert() {
        $to = get_option( 'admin_email' );
        $subject = 'WASSCE Checker PRO Low Stock Alert';
        $message = 'The number of unused WASSCE Checker PRO codes is low. Please upload more codes.';
        wp_mail( $to, $subject, $message );
    }
}

new WASSCE_Checker_PRO_Stock_Alert();
