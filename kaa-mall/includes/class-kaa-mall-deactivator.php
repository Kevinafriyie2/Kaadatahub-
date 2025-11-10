<?php

class Kaa_Mall_Deactivator {

    public static function deactivate() {
        self::remove_reseller_role();
        flush_rewrite_rules();
    }

    private static function remove_reseller_role() {
        if ( get_role( 'reseller' ) ) {
            remove_role( 'reseller' );
        }
    }
}
