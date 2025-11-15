<?php

class Ocean_Service_Portal_WhatsApp {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_button' ) );
    }

    public function render_button() {
        $options = get_option( 'ocean_service_portal_options' );

        if ( isset( $options['enable_whatsapp_button'] ) && $options['enable_whatsapp_button'] && isset( $options['whatsapp_number'] ) ) {
            $number = $options['whatsapp_number'];
            $greeting = isset( $options['whatsapp_greeting'] ) ? rawurlencode( $options['whatsapp_greeting'] ) : '';
            ?>
            <a href="https://wa.me/<?php echo esc_attr( $number ); ?>?text=<?php echo $greeting; ?>" class="ocean-service-portal-whatsapp-button" target="_blank">
                <img src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/whatsapp.svg'; ?>" alt="WhatsApp">
            </a>
            <style>
                .ocean-service-portal-whatsapp-button {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 1000;
                }
            </style>
            <?php
        }
    }
}
