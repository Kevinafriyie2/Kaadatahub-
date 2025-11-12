<?php

class KDH_Admin_Pages {

    public static function init() {
        add_action('admin_init', [__CLASS__, 'handle_actions']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function handle_actions() {
        // Handle reseller approval
        if (isset($_GET['action']) && $_GET['action'] === 'approve_reseller' && isset($_GET['user_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'kdh_approve_reseller')) {
                wp_die('Invalid nonce');
            }
            $user_id = intval($_GET['user_id']);
            KDH_Reseller::approve_reseller($user_id);
            wp_redirect(admin_url('admin.php?page=kdh-users'));
            exit;
        }

        // Handle reseller denial
        if (isset($_GET['action']) && $_GET['action'] === 'deny_reseller' && isset($_GET['user_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'kdh_deny_reseller')) {
                wp_die('Invalid nonce');
            }
            $user_id = intval($_GET['user_id']);
            KDH_Reseller::deny_reseller($user_id);
            wp_redirect(admin_url('admin.php?page=kdh-users'));
            exit;
        }

        // Handle impersonation
        if (isset($_GET['action']) && $_GET['action'] === 'impersonate' && isset($_GET['user_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'kdh_impersonate')) {
                wp_die('Invalid nonce');
            }
            if (!current_user_can('manage_options')) {
                wp_die('You do not have permission to do this.');
            }
            $user_id = intval($_GET['user_id']);
            $_SESSION['kdh_impersonating'] = get_current_user_id();
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            wp_redirect(home_url());
            exit;
        }

        // Handle stop impersonating
        if (isset($_GET['action']) && $_GET['action'] === 'stop_impersonating') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'kdh_stop_impersonating')) {
                wp_die('Invalid nonce');
            }
            if (isset($_SESSION['kdh_impersonating'])) {
                $admin_id = intval($_SESSION['kdh_impersonating']);
                unset($_SESSION['kdh_impersonating']);
                wp_set_current_user($admin_id);
                wp_set_auth_cookie($admin_id);
            }
            wp_redirect(admin_url('admin.php?page=kdh-users'));
            exit;
        }

        // Handle approve withdrawal
        if (isset($_GET['action']) && $_GET['action'] === 'approve_withdrawal' && isset($_GET['withdrawal_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'kdh_approve_withdrawal')) {
                wp_die('Invalid nonce');
            }
            $withdrawal_id = intval($_GET['withdrawal_id']);
            KDH_Reseller::approve_withdrawal($withdrawal_id);
            wp_redirect(admin_url('admin.php?page=kdh-withdrawals'));
            exit;
        }

        // Handle deny withdrawal
        if (isset($_GET['action']) && $_GET['action'] === 'deny_withdrawal' && isset($_GET['withdrawal_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'kdh_deny_withdrawal')) {
                wp_die('Invalid nonce');
            }
            $withdrawal_id = intval($_GET['withdrawal_id']);
            KDH_Reseller::deny_withdrawal($withdrawal_id);
            wp_redirect(admin_url('admin.php?page=kdh-withdrawals'));
            exit;
        }
    }

    public static function register_settings() {
        register_setting('kdh_settings_group', 'kdh_paystack_secret_key');
        register_setting('kdh_settings_group', 'kdh_paystack_public_key');

        add_settings_section('kdh_paystack_section', 'Paystack Settings', null, 'kaadatahub-settings');

        add_settings_field('kdh_paystack_secret_key', 'Paystack Secret Key', [__CLASS__, 'render_secret_key_field'], 'kaadatahub-settings', 'kdh_paystack_section');
        add_settings_field('kdh_paystack_public_key', 'Paystack Public Key', [__CLASS__, 'render_public_key_field'], 'kaadatahub-settings', 'kdh_paystack_section');
    }

    public static function render_secret_key_field() {
        $value = get_option('kdh_paystack_secret_key');
        echo '<input type="text" name="kdh_paystack_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public static function render_public_key_field() {
        $value = get_option('kdh_paystack_public_key');
        echo '<input type="text" name="kdh_paystack_public_key" value="' . esc_attr($value) . '" class="regular-text">';
    }
}
