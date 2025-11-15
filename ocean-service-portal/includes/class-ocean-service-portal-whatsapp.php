<?php

class Ocean_Service_Portal_WhatsApp {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_button' ) );
    }

    public function render_button() {
        // This will be implemented later.
    }
}
