<?php

class Ocean_Service_Emails {

    public static function send_email( $to, $subject, $body, $headers = '' ) {
        if ( empty( $headers ) ) {
            $headers = 'Content-Type: text/html; charset=UTF-8';
        }
        wp_mail( $to, $subject, $body, $headers );
    }

    public static function send_bundle_purchase_confirmation( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $to = $order->get_billing_email();
        $subject = 'Your Ocean Service Bundle Purchase';
        $body = '<p>Thank you for your purchase. Your bundle has been delivered.</p>';
        self::send_email( $to, $subject, $body );
    }

    public static function send_wallet_topup_confirmation( $user_id, $amount ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $to = $user->user_email;
        $subject = 'Your Wallet Top-Up Was Successful';
        $body = '<p>Your wallet has been credited with ' . wc_price( $amount ) . '.</p>';
        self::send_email( $to, $subject, $body );
    }

    public static function send_agent_welcome_email( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $to = $user->user_email;
        $subject = 'Welcome to the Ocean Service Agent Program';
        $body = '<p>Congratulations! You are now an Ocean Service agent and can enjoy special pricing on all our bundles.</p>';
        self::send_email( $to, $subject, $body );
    }
}
