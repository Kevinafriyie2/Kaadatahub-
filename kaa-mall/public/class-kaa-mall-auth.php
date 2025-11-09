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
            $redirect_url = get_option('kaa_mall_user_portal_url', home_url());
            wp_redirect( $redirect_url );
            exit;
        }

        ob_start();
        ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

            .kaa-auth-portal {
                font-family: 'Poppins', sans-serif;
                max-width: 420px;
                margin: 50px auto;
                padding: 40px;
                background-color: #ffffff;
                border-radius: 24px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
                color: #555;
            }

            .auth-header {
                text-align: center;
                margin-bottom: 30px;
            }

            .auth-header .icon {
                font-size: 48px;
                color: #6a5af9;
                margin-bottom: 15px;
            }

            .auth-header h1 {
                font-size: 28px;
                font-weight: 700;
                color: #333;
                margin: 0;
            }

            .auth-header p {
                color: #888;
                margin-top: 5px;
            }

            .auth-info-box {
                background-color: #eef2ff;
                border-left: 4px solid #6a5af9;
                padding: 15px;
                margin-bottom: 30px;
                border-radius: 8px;
                font-size: 14px;
            }
             .auth-info-box .new-badge {
                background-color: #6a5af9;
                color: white;
                font-size: 10px;
                padding: 2px 6px;
                border-radius: 4px;
                margin-left: 5px;
                font-weight: 600;
            }

            .auth-tabs {
                display: flex;
                background-color: #f4f4f7;
                border-radius: 12px;
                padding: 5px;
                margin-bottom: 30px;
            }

            .auth-tabs .tab-link {
                flex: 1;
                background: none;
                border: none;
                color: #555;
                cursor: pointer;
                padding: 12px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 8px;
                transition: all 0.3s ease;
            }

            .auth-tabs .tab-link.active {
                background-color: #ffffff;
                color: #6a5af9;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .auth-tab-content {
                display: none;
            }

            .auth-tab-content.active {
                display: block;
            }

            .form-group {
                position: relative;
                margin-bottom: 20px;
            }

            .form-group .form-icon {
                position: absolute;
                top: 50%;
                left: 15px;
                transform: translateY(-50%);
                color: #aaa;
            }

            .form-group input[type="text"],
            .form-group input[type="email"],
            .form-group input[type="password"] {
                width: 100%;
                padding: 14px 14px 14px 45px;
                border: 1px solid #ddd;
                border-radius: 12px;
                background-color: #f9f9f9;
                color: #333;
                font-size: 15px;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
                box-sizing: border-box;
            }

            .form-group input:focus {
                outline: none;
                border-color: #6a5af9;
                box-shadow: 0 0 0 3px rgba(106, 90, 249, 0.2);
            }

            .form-group .password-toggle {
                position: absolute;
                top: 50%;
                right: 15px;
                transform: translateY(-50%);
                color: #aaa;
                cursor: pointer;
            }

            .form-options {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                font-size: 14px;
            }

            .form-options .remember-me {
                display: flex;
                align-items: center;
            }

            .form-options .remember-me input {
                margin-right: 8px;
            }

            .form-options a {
                color: #6a5af9;
                text-decoration: none;
                font-weight: 500;
            }

            .form-submit-button {
                width: 100%;
                padding: 15px;
                border: none;
                border-radius: 12px;
                background: linear-gradient(to right, #6a5af9, #8a7dfc);
                color: #ffffff;
                font-weight: 600;
                cursor: pointer;
                font-size: 16px;
                transition: all 0.3s ease;
                box-shadow: 0 5px 15px rgba(106, 90, 249, 0.3);
            }
            .form-submit-button:hover {
                 transform: translateY(-2px);
                 box-shadow: 0 8px 20px rgba(106, 90, 249, 0.4);
            }
        </style>
        <div class="kaa-auth-portal">
            <div class="auth-header">
                <div class="icon"><i class="fas fa-user-circle"></i></div>
                <h1>WELCOME TO KAA MALL</h1>
                <p>Your shopping destination in Ghana</p>
            </div>

            <div class="auth-info-box">
                <span><i class="fas fa-info-circle"></i> <strong>Sign in</strong> if you have an account.</span><br>
                <span><strong>Sign up</strong> if you're new. <span class="new-badge">NEW</span></span>
            </div>

            <div class="auth-tabs">
                <button class="tab-link active" data-tab="login">Sign In</button>
                <button class="tab-link" data-tab="register">Sign Up</button>
            </div>

            <div id="login" class="auth-tab-content active">
                <form id="kaa-mall-login-form" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
                    <div class="form-group">
                        <i class="fas fa-envelope form-icon"></i>
                        <input type="text" name="log" id="user_login" placeholder="Email or Username" required>
                    </div>
                    <div class="form-group">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" name="pwd" id="user_pass" placeholder="Password" required>
                        <i class="fas fa-eye password-toggle"></i>
                    </div>
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" name="rememberme" id="rememberme" value="forever">
                            <label for="rememberme">Remember me</label>
                        </div>
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot password?</a>
                    </div>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( get_option('kaa_mall_user_portal_url') ); ?>">
                    <button type="submit" class="form-submit-button">Sign In &rarr;</button>
                </form>
            </div>

            <div id="register" class="auth-tab-content">
                <form id="kaa-mall-register-form">
                    <div class="form-group">
                        <i class="fas fa-user form-icon"></i>
                        <input type="text" name="username" id="reg_username" placeholder="Username" required>
                    </div>
                    <div class="form-group">
                        <i class="fas fa-envelope form-icon"></i>
                        <input type="email" name="email" id="reg_email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" name="password" id="reg_password" placeholder="Password" required>
                         <i class="fas fa-eye password-toggle"></i>
                    </div>
                    <button type="submit" class="form-submit-button">Sign Up</button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
