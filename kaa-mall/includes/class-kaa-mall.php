<?php

class Kaa_Mall {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'kaa-mall';
        $this->version = '1.0.0';
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-kaa-mall-admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-kaa-mall-reseller.php';
    }

    private function define_admin_hooks() {
        $plugin_admin = new Kaa_Mall_Admin( $this->get_plugin_name(), $this->get_version() );
    }

    private function define_public_hooks() {
        $plugin_public = new Kaa_Mall_Public( $this->get_plugin_name(), $this->get_version() );
        $plugin_reseller = new Kaa_Mall_Reseller();
    }

    public function run() {
        // This is where we would run the loader, but we're not using one yet.
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
