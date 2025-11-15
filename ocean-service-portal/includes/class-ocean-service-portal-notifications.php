<?php

class Ocean_Service_Portal_Notifications {

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'send_notifications' ) );
    }

    public function send_notifications( $order_id ) {
        $options = get_option( 'ocean_service_portal_options' );
        $order = wc_get_order( $order_id );
        $beneficiary = $order->get_meta( '_kaa_beneficiary_number' );

        if ( isset( $options['enable_sms_notifications'] ) && $options['enable_sms_notifications'] && isset( $options['sms_api_key'] ) ) {
            // Placeholder for SMS API call
            $order->add_order_note( sprintf( __( 'Sent SMS notification to %s.', 'ocean-service-portal' ), $beneficiary ) );
        }

        if ( isset( $options['enable_whatsapp_notifications'] ) && $options['enable_whatsapp_notifications'] && isset( $options['whatsapp_api_key'] ) ) {
            // Placeholder for WhatsApp API call
            $order->add_order_note( sprintf( __( 'Sent WhatsApp notification to %s.', 'ocean-service-portal' ), $beneficiary ) );
        }
    }
}
