<?php

class Ocean_Service_Portal_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function enqueue_styles() {
        // Enqueue admin-specific stylesheets here.
    }

    public function enqueue_scripts() {
        // Enqueue admin-specific scripts here.
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'OCEAN SERVICE', 'ocean-service-portal' ),
            __( 'OCEAN SERVICE', 'ocean-service-portal' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'render_settings_page' ),
            'dashicons-admin-generic',
            6
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Settings', 'ocean-service-portal' ),
            __( 'Settings', 'ocean-service-portal' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'OCEAN SERVICE Settings', 'ocean-service-portal' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ocean_service_portal' );
                do_settings_sections( 'ocean_service_portal' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'ocean_service_portal', 'ocean_service_portal_options' );

        add_settings_section(
            'ocean_service_portal_telco_api',
            __( 'Telco API Settings', 'ocean-service-portal' ),
            null,
            'ocean_service_portal'
        );

        add_settings_field(
            'mtn_api_key',
            __( 'MTN API Key', 'ocean-service-portal' ),
            array( $this, 'render_text_field' ),
            'ocean_service_portal',
            'ocean_service_portal_telco_api',
            array( 'id' => 'mtn_api_key' )
        );

        add_settings_section(
            'ocean_service_portal_notifications',
            __( 'Notification Settings', 'ocean-service-portal' ),
            null,
            'ocean_service_portal'
        );

        add_settings_field(
            'sms_api_key',
            __( 'SMS API Key', 'ocean-service-portal' ),
            array( $this, 'render_text_field' ),
            'ocean_service_portal',
            'ocean_service_portal_notifications',
            array( 'id' => 'sms_api_key' )
        );

        add_settings_section(
            'ocean_service_portal_whatsapp',
            __( 'WhatsApp Settings', 'ocean-service-portal' ),
            null,
            'ocean_service_portal'
        );

        add_settings_field(
            'whatsapp_number',
            __( 'WhatsApp Number', 'ocean-service-portal' ),
            array( $this, 'render_text_field' ),
            'ocean_service_portal',
            'ocean_service_portal_whatsapp',
            array( 'id' => 'whatsapp_number' )
        );
    }

    public function render_text_field( $args ) {
        $options = get_option( 'ocean_service_portal_options' );
        $value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
        echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="ocean_service_portal_options[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '">';
    }
}
