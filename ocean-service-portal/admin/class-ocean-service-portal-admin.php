<?php

class Ocean_Service_Portal_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
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
        // This will be implemented later.
        echo '<h1>OCEAN SERVICE Settings</h1>';
    }
}
