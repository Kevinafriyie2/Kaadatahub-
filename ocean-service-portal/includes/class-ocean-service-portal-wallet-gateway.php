<?php

class Ocean_Service_Portal_Wallet_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'ocean_service_portal_wallet';
        $this->method_title       = __( 'Wallet', 'ocean-service-portal' );
        $this->method_description = __( 'Pay with your agent wallet.', 'ocean-service-portal' );
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'ocean-service-portal' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Wallet Payment', 'ocean-service-portal' ),
                'default' => 'yes',
            ),
            'title'   => array(
                'title'       => __( 'Title', 'ocean-service-portal' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'ocean-service-portal' ),
                'default'     => __( 'Wallet', 'ocean-service-portal' ),
                'desc_tip'    => true,
            ),
        );
    }

    public function is_available() {
        return current_user_can( 'kaa_agent' );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $wallet = new Ocean_Service_Portal_Wallet();
        $result = $wallet->pay_with_wallet( $order_id );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
            return;
        }

        $order->payment_complete();
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}
