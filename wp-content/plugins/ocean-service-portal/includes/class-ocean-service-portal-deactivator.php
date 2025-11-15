<?php

class Ocean_Service_Portal_Deactivator {

    public static function deactivate() {
        require_once plugin_dir_path( __FILE__ ) . 'class-ocean-service-portal-roles.php';
        Ocean_Service_Portal_Roles::remove_roles();
    }
}
