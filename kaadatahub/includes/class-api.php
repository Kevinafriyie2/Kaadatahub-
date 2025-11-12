<?php

class KDH_API {

    public static function init() {
        add_action('wp_ajax_kdh_purchase_data', [__CLASS__, 'purchase_data']);
        add_action('wp_ajax_nopriv_kdh_purchase_data', [__CLASS__, 'purchase_data_guest']);
        add_action('wp_ajax_kdh_top_up_wallet', [__CLASS__, 'top_up_wallet']);
        add_action('wp_ajax_kdh_get_wallet_balance', [__CLASS__, 'get_wallet_balance']);
    }

    public static function purchase_data() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'kdh-ajax-nonce' ) ) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $product_id = intval($_POST['product_id']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $payment_method = sanitize_text_field($_POST['payment_method']);

        $product = wc_get_product($product_id);
        $price = $product->get_price();

        if ($payment_method === 'wallet') {
            $result = KDH_Wallet::purchase_with_wallet($user_id, $price, "Data bundle purchase for {$phone_number}");
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            // Handle Paystack payment - this will be more complex and likely involve frontend JS
        }

        // Simulate instant delivery
        // ...

        wp_send_json_success('Purchase successful!');
    }

    public static function purchase_data_guest() {
        // Handle guest checkout - create a user or just process the order
        wp_send_json_success('Guest purchase successful!');
    }

    public static function top_up_wallet() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'kdh-ajax-nonce' ) ) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount']);

        // This would redirect to Paystack or use a Paystack popup
        // For now, we'll just simulate a successful top-up
        KDH_Wallet::add_to_wallet($user_id, $amount, 'Paystack top-up');

        wp_send_json_success('Top-up successful!');
    }

    public static function get_wallet_balance() {
        if ( ! wp_verify_nonce( $_GET['nonce'], 'kdh-ajax-nonce' ) ) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $balance = KDH_Wallet::get_wallet_balance($user_id);
        wp_send_json_success(['balance' => $balance]);
    }
}
