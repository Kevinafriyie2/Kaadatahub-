<?php

class Ocean_Service_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        // Enqueue admin-specific stylesheets here.
    }

    public function enqueue_scripts() {
        // Enqueue admin-specific scripts here.
    }
}
