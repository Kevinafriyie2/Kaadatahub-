<?php

class Ocean_Service {

    protected static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'admin/class-ocean-service-admin.php';
        require_once OCEAN_SERVICE_PLUGIN_DIR . 'public/class-ocean-service-public.php';
    }

    private function init_hooks() {
        // Actions and filters will go here.
    }
}
