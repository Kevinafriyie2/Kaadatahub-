<?php

class Kaa_Mall_Auth {

    public function __construct() {
        add_shortcode( 'kaa_auth_portal', array( $this, 'render_auth_portal' ) );
        add_action( 'wp_ajax_nopriv_kaa_mall_register_user', array( $this, 'register_user' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        if ( is_page() || is_single() ) {
            global $post;
            if ( has_shortcode( $post->post_content, 'kaa_auth_portal' ) ) {
                $js_file_url = plugin_dir_url( __FILE__ ) . 'js/kaa-mall-auth.js';
                $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/kaa-mall-auth.js' );
                wp_enqueue_script( 'kaa-mall-auth', $js_file_url, array( 'jquery' ), $js_version, true );
                wp_localize_script( 'kaa-mall-auth', 'kaa_mall_auth_params', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'kaa_mall_auth_nonce' ),
                ) );
            }
        }
    }

    public function register_user() {
        check_ajax_referer( 'kaa_mall_auth_nonce', 'nonce' );

        $username = sanitize_user( $_POST['username'] );
        $email = sanitize_email( $_POST['email'] );
        $password = $_POST['password'];

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
        } else {
            // Automatically log the user in
            wp_set_current_user( $user_id, $username );
            wp_set_auth_cookie( $user_id );
            wp_send_json_success( array( 'message' => 'Registration successful! Redirecting...', 'redirect_url' => get_option('kaa_mall_user_portal_url') ) );
        }
    }

    public function render_auth_portal() {
        if ( is_user_logged_in() ) {
            wp_redirect( get_option('kaa_mall_user_portal_url') );
            exit;
        }

        ob_start();
        ?>
        <style>
            .kaa-auth-portal {
                max-width: 400px;
                margin: 40px auto;
                padding: 30px;
                background-color: #1e1e1e;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                color: #e0e0e0;
            }
            .auth-tabs {
                display: flex;
                margin-bottom: 25px;
                border-bottom: 1px solid #333;
            }
            .auth-tabs .tab-link {
                background: none;
                border: none;
                color: #a0a0a0;
                cursor: pointer;
                padding: 15px 20px;
                font-size: 1.1em;
                font-weight: 600;
                transition: color 0.2s ease, border-bottom 0.2s ease;
                border-bottom: 3px solid transparent;
            }
            .auth-tabs .tab-link.active {
                color: #ffc107;
                border-bottom-color: #ffc107;
            }
            .auth-tab-content {
                display: none;
            }
            .auth-tab-content.active {
                display: block;
            }
            .kaa-auth-portal h3 {
                text-align: center;
                color: #ffffff;
                margin-bottom: 25px;
                font-size: 1.8em;
            }
            .kaa-auth-portal form label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
            }
            .kaa-auth-portal form input[type="text"],
            .kaa-auth-portal form input[type="email"],
            .kaa-auth-portal form input[type="password"] {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: 1px solid #333;
                border-radius: 8px;
                background-color: #2c2c2c;
                color: #e0e0e0;
                font-size: 1em;
                box-sizing: border-box;
            }
            .kaa-auth-portal form button,
            .kaa-auth-portal form input[type="submit"] {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background-color: #ffc107;
                color: #121212;
                font-weight: bold;
                cursor: pointer;
                font-size: 1.1em;
                transition: background-color 0.2s ease;
            }
            .kaa-auth-portal form button:hover,
            .kaa-auth-portal form input[type="submit"]:hover {
                background-color: #ffdb6e;
            }
        </style>
        <div class="kaa-auth-portal">
            <div class="auth-tabs">
                <button class="tab-link active" data-tab="login">Login</button>
                <button class="tab-link" data-tab="register">Register</button>
            </div>

            <div id="login" class="auth-tab-content active">
                <h3>Login</h3>
                <?php wp_login_form( array('redirect' => get_option('kaa_mall_user_portal_url')) ); ?>
            </div>

            <div id="register" class="auth-tab-content">
                <h3>Register</h3>
                <form id="kaa-mall-register-form">
                    <p>
                        <label for="reg_username">Username</label>
                        <input type="text" name="username" id="reg_username" required>
                    </p>
                    <p>
                        <label for="reg_email">Email</label>
                        <input type="email" name="email" id="reg_email" required>
                    </p>
                    <p>
                        <label for="reg_password">Password</label>
                        <input type="password" name="password" id="reg_password" required>
                    </p>
                    <p>
                        <button type="submit">Register</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
