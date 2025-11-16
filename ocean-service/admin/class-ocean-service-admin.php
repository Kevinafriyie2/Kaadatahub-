<?php

class Ocean_Service_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Add custom fields to product page
        add_action( 'woocommerce_product_options_pricing', array( $this, 'add_custom_product_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_product_fields' ) );

        // Add meta box for top-up requests
        add_action( 'add_meta_boxes', array( $this, 'add_topup_request_meta_box' ) );
        add_action( 'save_post_wallet_topup_request', array( $this, 'handle_topup_request_actions' ) );

        // Add custom columns to top-up request list
        add_filter( 'manage_wallet_topup_request_posts_columns', array( $this, 'add_topup_request_columns' ) );
        add_action( 'manage_wallet_topup_request_posts_custom_column', array( $this, 'render_topup_request_columns' ), 10, 2 );

        // Add meta box for AFA submissions
        add_action( 'add_meta_boxes', array( $this, 'add_afa_submission_meta_box' ) );

        // Add manual wallet adjustment to user profile
        add_action( 'show_user_profile', array( $this, 'add_manual_wallet_adjustment_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'add_manual_wallet_adjustment_fields' ) );
        add_action( 'personal_options_update', array( $this, 'handle_manual_wallet_adjustment' ) );
        add_action( 'edit_user_profile_update', array( $this, 'handle_manual_wallet_adjustment' ) );
    }

    public function add_manual_wallet_adjustment_fields( $user ) {
        if ( ! current_user_can( 'manage_options' ) || ! in_array( 'agent', (array) $user->roles ) ) {
            return;
        }
        ?>
        <h3>Wallet Management</h3>
        <table class="form-table">
            <tr>
                <th><label for="wallet_adjustment_amount">Adjustment Amount</label></th>
                <td>
                    <input type="number" step="0.01" name="wallet_adjustment_amount" id="wallet_adjustment_amount" class="regular-text" />
                    <p class="description">Enter a positive value to add funds, or a negative value to subtract funds.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wallet_adjustment_reason">Reason</label></th>
                <td>
                    <input type="text" name="wallet_adjustment_reason" id="wallet_adjustment_reason" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
        wp_nonce_field( 'manual_wallet_adjustment', 'manual_wallet_adjustment_nonce' );
    }

    public function handle_manual_wallet_adjustment( $user_id ) {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['manual_wallet_adjustment_nonce'] ) || ! wp_verify_nonce( $_POST['manual_wallet_adjustment_nonce'], 'manual_wallet_adjustment' ) ) {
            return;
        }

        $amount = floatval( $_POST['wallet_adjustment_amount'] );
        $reason = sanitize_text_field( $_POST['wallet_adjustment_reason'] );

        if ( $amount > 0 ) {
            Ocean_Service_Wallet_Manager::add_funds( $user_id, $amount );
            Ocean_Service_Wallet_Manager::log_transaction( $user_id, $amount, 'credit', 'Manual adjustment: ' . $reason );
        } elseif ( $amount < 0 ) {
            Ocean_Service_Wallet_Manager::subtract_funds( $user_id, abs( $amount ) );
            Ocean_Service_Wallet_Manager::log_transaction( $user_id, abs( $amount ), 'debit', 'Manual adjustment: ' . $reason );
        }
    }

    public function add_afa_submission_meta_box() {
        add_meta_box(
            'ocean_service_afa_submission',
            'AFA Submission Details',
            array( $this, 'render_afa_submission_meta_box' ),
            'afa_submission',
            'normal',
            'high'
        );
    }

    public function render_afa_submission_meta_box( $post ) {
        $full_name = get_post_meta( $post->ID, 'full_name', true );
        $email = get_post_meta( $post->ID, 'email', true );
        $id_front_url = get_post_meta( $post->ID, 'id_front_url', true );
        $id_back_url = get_post_meta( $post->ID, 'id_back_url', true );

        echo '<p><strong>Full Name:</strong> ' . esc_html( $full_name ) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html( $email ) . '</p>';
        if ( $id_front_url ) {
            echo '<p><strong>ID Front:</strong><br><img src="' . esc_url( $id_front_url ) . '" style="max-width: 300px;"></p>';
        }
        if ( $id_back_url ) {
            echo '<p><strong>ID Back:</strong><br><img src="' . esc_url( $id_back_url ) . '" style="max-width: 300px;"></p>';
        }
    }

    public function add_topup_request_columns( $columns ) {
        $columns['amount'] = 'Amount';
        $columns['status'] = 'Status';
        return $columns;
    }

    public function render_topup_request_columns( $column, $post_id ) {
        if ( $column === 'amount' ) {
            $amount = get_post_meta( $post_id, '_topup_amount', true );
            echo wc_price( $amount );
        }

        if ( $column === 'status' ) {
            echo get_post_status( $post_id );
        }
    }

    public function add_topup_request_meta_box() {
        add_meta_box(
            'ocean_service_topup_request',
            'Top-Up Request Actions',
            array( $this, 'render_topup_request_meta_box' ),
            'wallet_topup_request',
            'side',
            'high'
        );
    }

    public function render_topup_request_meta_box( $post ) {
        wp_nonce_field( 'ocean_service_topup_action', 'ocean_service_topup_nonce' );
        ?>
        <p>
            <button type="submit" name="ocean_service_topup_action" value="approve" class="button button-primary">Approve</button>
            <button type="submit" name="ocean_service_topup_action" value="reject" class="button">Reject</button>
        </p>
        <?php
    }

    public function handle_topup_request_actions( $post_id ) {
        if ( ! isset( $_POST['ocean_service_topup_nonce'] ) || ! wp_verify_nonce( $_POST['ocean_service_topup_nonce'], 'ocean_service_topup_action' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['ocean_service_topup_action'] ) ) {
            $action = sanitize_text_field( $_POST['ocean_service_topup_action'] );
            $post = get_post( $post_id );
            $user_id = $post->post_author;

            $amount = get_post_meta( $post_id, '_topup_amount', true );

            if ( $action === 'approve' && $amount > 0 ) {
                Ocean_Service_Wallet_Manager::add_funds( $user_id, $amount );
                Ocean_Service_Wallet_Manager::log_transaction( $user_id, $amount, 'credit', 'Admin approved top-up request #' . $post_id );
                Ocean_Service_Emails::send_wallet_topup_confirmation( $user_id, $amount );
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
            } elseif ( $action === 'reject' ) {
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
            }

            // Prevent re-processing on subsequent saves
            remove_action( 'save_post_wallet_topup_request', array( $this, 'handle_topup_request_actions' ) );
        }
    }

    public function add_custom_product_fields() {
        global $post;

        woocommerce_wp_text_input(
            array(
                'id'          => '_agent_price',
                'label'       => __( 'Agent Price', 'ocean-service' ),
                'placeholder' => '',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the price for agents.', 'ocean-service' ),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' 	=> 'any',
                    'min'	=> '0'
                )
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'          => '_api_code',
                'label'       => __( 'API Code', 'ocean-service' ),
                'placeholder' => '',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the API code for this bundle.', 'ocean-service' ),
            )
        );
    }

    public function save_custom_product_fields( $post_id ) {
        $agent_price = isset( $_POST['_agent_price'] ) ? wc_clean( $_POST['_agent_price'] ) : '';
        update_post_meta( $post_id, '_agent_price', $agent_price );

        $api_code = isset( $_POST['_api_code'] ) ? sanitize_text_field( $_POST['_api_code'] ) : '';
        update_post_meta( $post_id, '_api_code', $api_code );
    }

    public function add_options_page() {
        add_options_page(
            'Ocean Service Settings',
            'Ocean Service',
            'manage_options',
            'ocean-service',
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Ocean Service Settings</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'ocean_service_option_group' );
                    do_settings_sections( 'ocean-service-admin' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting(
            'ocean_service_option_group',
            'ocean_service_agent_fee_product_id',
            array( 'sanitize_callback' => 'absint' )
        );

        add_settings_section(
            'ocean_service_setting_section',
            'Agent Settings',
            null,
            'ocean-service-admin'
        );

        add_settings_field(
            'agent_fee_product_id',
            'Agent Registration Fee Product ID',
            array( $this, 'agent_fee_product_id_callback' ),
            'ocean-service-admin',
            'ocean_service_setting_section'
        );

        // API Settings
        add_settings_section(
            'ocean_service_api_section',
            'API Settings',
            null,
            'ocean-service-admin'
        );
        add_settings_field( 'api_url', 'Telco API URL', array( $this, 'api_url_callback' ), 'ocean-service-admin', 'ocean_service_api_section' );
        add_settings_field( 'api_key', 'Telco API Key', array( $this, 'api_key_callback' ), 'ocean-service-admin', 'ocean_service_api_section' );

        // Paystack Settings
        add_settings_section(
            'ocean_service_paystack_section',
            'Paystack Settings',
            null,
            'ocean-service-admin'
        );
        add_settings_field( 'paystack_public_key', 'Paystack Public Key', array( $this, 'paystack_public_key_callback' ), 'ocean-service-admin', 'ocean_service_paystack_section' );
        add_settings_field( 'paystack_secret_key', 'Paystack Secret Key', array( $this, 'paystack_secret_key_callback' ), 'ocean-service-admin', 'ocean_service_paystack_section' );

        // Support Settings
        add_settings_section(
            'ocean_service_support_section',
            'Support Settings',
            null,
            'ocean-service-admin'
        );
        add_settings_field( 'whatsapp_number', 'WhatsApp Support Number', array( $this, 'whatsapp_number_callback' ), 'ocean-service-admin', 'ocean_service_support_section' );

        register_setting( 'ocean_service_option_group', 'ocean_service_api_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'ocean_service_option_group', 'ocean_service_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'ocean_service_option_group', 'ocean_service_paystack_public_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'ocean_service_option_group', 'ocean_service_paystack_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'ocean_service_option_group', 'ocean_service_whatsapp_number', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    }

    public function whatsapp_number_callback() {
        printf( '<input type="text" id="whatsapp_number" name="ocean_service_whatsapp_number" value="%s" />', esc_attr( get_option('ocean_service_whatsapp_number') ) );
    }

    public function api_url_callback() {
        printf( '<input type="text" id="api_url" name="ocean_service_api_url" value="%s" />', esc_attr( get_option('ocean_service_api_url') ) );
    }

    public function api_key_callback() {
        printf( '<input type="text" id="api_key" name="ocean_service_api_key" value="%s" />', esc_attr( get_option('ocean_service_api_key') ) );
    }

    public function paystack_public_key_callback() {
        printf( '<input type="text" id="paystack_public_key" name="ocean_service_paystack_public_key" value="%s" />', esc_attr( get_option('ocean_service_paystack_public_key') ) );
    }

    public function paystack_secret_key_callback() {
        printf( '<input type="text" id="paystack_secret_key" name="ocean_service_paystack_secret_key" value="%s" />', esc_attr( get_option('ocean_service_paystack_secret_key') ) );
    }

    public function agent_fee_product_id_callback() {
        printf(
            '<input type="number" id="agent_fee_product_id" name="ocean_service_agent_fee_product_id" value="%s" />',
            esc_attr( get_option( 'ocean_service_agent_fee_product_id' ) )
        );
    }

    public function enqueue_styles() {
        // Enqueue admin-specific stylesheets here.
    }

    public function enqueue_scripts() {
        // Enqueue admin-specific scripts here.
    }
}
