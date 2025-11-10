<?php

/**
 * The authentication functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/public
 */
class Kaa_Mall_Auth {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function register_shortcode() {
        add_shortcode('kaa_auth_portal', array($this, 'render_auth_portal_shortcode'));
    }

    public function render_auth_portal_shortcode() {
        if (is_user_logged_in()) {
            return '<p>You are already logged in.</p>';
        }

        ob_start();
        include_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/kaa-mall-auth-display.php';
        return ob_get_clean();
    }

    public function enqueue_styles() {
        if (is_a_page_with_shortcode('kaa_auth_portal')) {
            wp_enqueue_style(
                $this->plugin_name . '-auth',
                plugin_dir_url(__FILE__) . 'css/kaa-mall-auth.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    public function ajax_login() {
        check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

        $info = array();
        $info['user_login'] = $_POST['username'];
        $info['user_password'] = $_POST['password'];
        $info['remember'] = true;

        $user_signon = wp_signon($info, false);
        if (is_wp_error($user_signon)) {
            wp_send_json_error('Wrong username or password.');
        } else {
            wp_send_json_success(array('redirect' => home_url('/dashboard')));
        }
    }

    public function ajax_register() {
        check_ajax_referer('kaa_mall_ajax_nonce', 'nonce');

        $email = $_POST['email'];
        $password = $_POST['password'];

        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        } else {
            // Create a wallet for the new user
            Kaa_Mall_Wallet::create_wallet($user_id);
            wp_send_json_success('Registration successful. You can now log in.');
        }
    }
}

if (!function_exists('is_a_page_with_shortcode')) {
    function is_a_page_with_shortcode($shortcode) {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, $shortcode);
    }
}
