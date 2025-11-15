<?php

class Ocean_Service_Portal_Roles {

    public static function add_roles() {
        add_role(
            'kaa_agent',
            __( 'KAA Agent', 'ocean-service-portal' ),
            array(
                'read' => true,
            )
        );
    }

    public static function remove_roles() {
        remove_role( 'kaa_agent' );
    }
}
