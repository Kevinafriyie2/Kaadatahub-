<?php

class Ocean_Service_Portal_Bundle_Delivery {

    public function __construct() {
        add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_deliver_bundle_automatically' ) );
    }

    public function maybe_deliver_bundle_automatically( $order_id ) {
        $options = get_option( 'ocean_service_portal_options' );
        $order = wc_get_order( $order_id );

        if ( isset( $options['automatic_delivery'] ) && $options['automatic_delivery'] ) {
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                $network = $product->get_meta( '_bundle_network' );
                $api_key = isset( $options[ strtolower( $network ) . '_api_key' ] ) ? $options[ strtolower( $network ) . '_api_key' ] : '';
                $beneficiary = $order->get_meta( '_kaa_beneficiary_number' );

                if ( $api_key && $beneficiary ) {
                    // Placeholder for telco API call
                    $order->add_order_note( sprintf( __( 'Attempted automatic bundle delivery to %s via %s API.', 'ocean-service-portal' ), $beneficiary, $network ) );
                }
            }
        }
    }

    public function deliver_bundle_manually( $order_id ) {
        // This will be implemented later.
    }
}
