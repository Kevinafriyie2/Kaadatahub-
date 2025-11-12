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
            if ( is_a($post, 'WP_Post') && has_shortcode( $post->post_content, 'kaa_auth_portal' ) ) {
                wp_enqueue_style( 'kaa-mall-portal-redesign', plugin_dir_url( __FILE__ ) . 'css/kaa-mall-portal-redesign.css', array(), '1.0.0' );
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
         <div class="kaa-mall-portal-body" style="height: auto; display: flex; justify-content: center; align-items: center; padding: 40px 0; background-color: #f7f8fc;">
            <div class="kaa-mall-main-content" style="width: 100%; max-width: 450px;">
                <div class="kaa-mall-dynamic-content-wrapper">
                    <div class="auth-header" style="text-align: center; margin-bottom: 20px;">
                         <h2 class="brand-title" style="text-align: center; margin-bottom: 10px;">Kaadatahub</h2>
                        <p>Welcome! Please sign in or register to continue.</p>
                    </div>

                    <div class="auth-tabs" style="display: flex; background-color: #f0f2f5; border-radius: 8px; padding: 5px; margin-bottom: 20px;">
                        <button class="tab-link active" data-tab="login" style="flex: 1; padding: 10px; border: none; background-color: #fff; border-radius: 6px; cursor: pointer; font-weight: 600; color: #8a2be2; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Sign In</button>
                        <button class="tab-link" data-tab="register" style="flex: 1; padding: 10px; border: none; background: none; border-radius: 6px; cursor: pointer; font-weight: 500; color: #555;">Sign Up</button>
                    </div>

                    <div id="login" class="auth-tab-content active">
                        <form id="kaa-mall-login-form" class="kaa-mall-form" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
                            <div class="form-group">
                                <label for="user_login">Email or Username</label>
                                <input type="text" name="log" id="user_login" required>
                            </div>
                            <div class="form-group">
                                <label for="user_pass">Password</label>
                                <input type="password" name="pwd" id="user_pass" required>
                            </div>
                             <div class="form-options" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 14px;">
                                <div class="remember-me">
                                    <input type="checkbox" name="rememberme" id="rememberme" value="forever">
                                    <label for="rememberme" style="font-weight: normal; margin-left: 5px;">Remember me</label>
                                </div>
                                <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="color: #8a2be2; text-decoration: none;">Forgot password?</a>
                            </div>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url( get_option('kaa_mall_user_portal_url') ); ?>">
                            <button type="submit" class="kaa-mall-btn">Sign In</button>
                        </form>
                    </div>

                    <div id="register" class="auth-tab-content" style="display: none;">
                        <form id="kaa-mall-register-form" class="kaa-mall-form">
                            <div class="form-group">
                                 <label for="reg_username">Username</label>
                                <input type="text" name="username" id="reg_username" required>
                            </div>
                            <div class="form-group">
                                <label for="reg_email">Email</label>
                                <input type="email" name="email" id="reg_email" required>
                            </div>
                            <div class="form-group">
                                <label for="reg_password">Password</label>
                                <input type="password" name="password" id="reg_password" required>
                            </div>
                            <button type="submit" class="kaa-mall-btn">Sign Up</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
