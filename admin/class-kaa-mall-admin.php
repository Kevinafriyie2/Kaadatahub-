<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/admin
 */
class Kaa_Mall_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/kaa-mall-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/kaa-mall-admin.js', array('jquery'), $this->version, false);
    }

    public function add_menu_page() {
        add_menu_page(
            'KAA Mall Settings',
            'KAA Mall',
            'manage_options',
            $this->plugin_name,
            array($this, 'render_settings_page'),
            'dashicons-cart',
            58
        );

        add_submenu_page(
            $this->plugin_name,
            'User Management',
            'User Management',
            'manage_options',
            $this->plugin_name . '-user-management',
            array($this, 'render_user_management_page')
        );
    }

    public function render_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/kaa-mall-admin-display.php';
    }

    public function render_user_management_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/kaa-mall-user-management-display.php';
    }

    public function register_settings() {
        register_setting('kaa_mall_options_group', 'kaa_mall_paystack_public_key');
        register_setting('kaa_mall_options_group', 'kaa_mall_paystack_secret_key');

        add_settings_section(
            'kaa_mall_paystack_section',
            'Paystack Settings',
            null,
            $this->plugin_name
        );

        add_settings_field(
            'kaa_mall_paystack_public_key',
            'Paystack Public Key',
            array($this, 'render_paystack_public_key_field'),
            $this->plugin_name,
            'kaa_mall_paystack_section'
        );

        add_settings_field(
            'kaa_mall_paystack_secret_key',
            'Paystack Secret Key',
            array($this, 'render_paystack_secret_key_field'),
            $this->plugin_name,
            'kaa_mall_paystack_section'
        );
    }

    public function render_paystack_public_key_field() {
        $value = get_option('kaa_mall_paystack_public_key');
        echo '<input type="text" name="kaa_mall_paystack_public_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function render_paystack_secret_key_field() {
        $value = get_option('kaa_mall_paystack_secret_key');
        echo '<input type="password" name="kaa_mall_paystack_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function adjust_wallet_balance_callback() {
        check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('You are not authorized to perform this action.');
            wp_die();
        }

        $user_id = absint($_POST['user_id']);
        $wallet_type = sanitize_text_field($_POST['wallet_type']);
        $amount = floatval($_POST['amount']);

        if ($wallet_type === 'main') {
            Kaa_Mall_Wallet::update_wallet_balance($user_id, $amount);
        } elseif ($wallet_type === 'profit') {
            Kaa_Mall_Profit_Wallet::update_profit_wallet_balance($user_id, $amount);
        } else {
            wp_send_json_error('Invalid wallet type.');
            wp_die();
        }

        wp_send_json_success('Wallet balance updated successfully.');
        wp_die();
    }
}
