<?php

class Ocean_Service_API_Handler {

    public static function send_bundle_request( $order_id, $product_id, $beneficiary_number ) {
        $api_url = get_option( 'ocean_service_api_url' );
        $api_key = get_option( 'ocean_service_api_key' );
        $api_code = get_post_meta( $product_id, '_api_code', true );

        if ( empty( $api_url ) || empty( $api_key ) || empty( $api_code ) ) {
            // Log an error: API settings are incomplete
            return false;
        }

        $body = array(
            'api_key' => $api_key,
            'api_code' => $api_code,
            'beneficiary' => $beneficiary_number,
        );

        $response = wp_remote_post( $api_url, array( 'body' => $body ) );

        if ( is_wp_error( $response ) ) {
            // Log an error
            return false;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ) );

        $order = wc_get_order( $order_id );
        if ( $response_body && isset( $response_body->status ) && $response_body->status === 'success' ) {
            if ( $order ) {
                $order->add_order_note( 'Bundle delivered successfully via API.' );
            }
            return true;
        } else {
            if ( $order ) {
                $order->add_order_note( 'API delivery failed. Response: ' . wp_remote_retrieve_body( $response ) );
            }
            return false;
        }
    }
}
