<?php

class Ocean_Service_Public {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        // Enqueue public-facing stylesheets here.
    }

    public function enqueue_scripts() {
        // Enqueue public-facing scripts here.
    }
}
