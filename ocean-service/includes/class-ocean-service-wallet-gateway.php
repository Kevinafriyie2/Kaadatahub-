<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ocean_Service_Wallet_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'ocean_wallet';
        $this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields         = false;
        $this->method_title       = 'Wallet';
        $this->method_description = 'Pay with your agent wallet.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Wallet Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'Wallet Payment',
                'desc_tip'    => true,
            ),
        );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        $total = $order->get_total();

        $balance = Ocean_Service_Wallet_Manager::get_balance( $user_id );

        if ( $balance < $total ) {
            wc_add_notice( 'Insufficient wallet balance.', 'error' );
            return;
        }

        $new_balance = Ocean_Service_Wallet_Manager::subtract_funds( $user_id, $total );

        if ( $new_balance !== false ) {
            $order->payment_complete();
            $order->add_order_note( 'Paid using wallet. New balance: ' . wc_price( $new_balance ) );
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        } else {
            wc_add_notice( 'Payment error.', 'error' );
            return;
        }
    }
}
