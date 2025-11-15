<?php

class Ocean_Service_Portal_Notifications {

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'send_notifications' ) );
    }

    public function send_notifications( $order_id ) {
        // This will be implemented later.
    }
}
