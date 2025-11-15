<?php

class Ocean_Service_Portal_Bundle_Delivery {

    public function __construct() {
        add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_deliver_bundle_automatically' ) );
    }

    public function maybe_deliver_bundle_automatically( $order_id ) {
        // This will be implemented later.
    }

    public function deliver_bundle_manually( $order_id ) {
        // This will be implemented later.
    }
}
