<?php

class Ocean_Service_Portal_Products {

    public function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_fields' ) );
        add_filter( 'woocommerce_get_price_html', array( $this, 'display_agent_price' ), 10, 2 );
    }

    public function add_custom_fields() {
        global $product_object;

        echo '<div class="options_group">';

        woocommerce_wp_text_input(
            array(
                'id'          => '_agent_price',
                'label'       => __( 'Agent Price', 'ocean-service-portal' ),
                'placeholder' => '',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the price for agents.', 'ocean-service-portal' ),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '0',
                ),
            )
        );

        woocommerce_wp_select(
            array(
                'id'      => '_bundle_network',
                'label'   => __( 'Bundle Network', 'ocean-service-portal' ),
                'options' => array(
                    ''           => __( 'Select a network', 'ocean-service-portal' ),
                    'MTN'        => __( 'MTN', 'ocean-service-portal' ),
                    'AirtelTigo' => __( 'AirtelTigo', 'ocean-service-portal' ),
                    'Telecel'    => __( 'Telecel', 'ocean-service-portal' ),
                ),
            )
        );

        echo '</div>';
    }

    public function save_custom_fields( $post_id ) {
        $agent_price = isset( $_POST['_agent_price'] ) ? wc_clean( $_POST['_agent_price'] ) : '';
        update_post_meta( $post_id, '_agent_price', $agent_price );

        $bundle_network = isset( $_POST['_bundle_network'] ) ? wc_clean( $_POST['_bundle_network'] ) : '';
        update_post_meta( $post_id, '_bundle_network', $bundle_network );
    }

    public function display_agent_price( $price, $product ) {
        if ( current_user_can( 'kaa_agent' ) && $product->get_meta( '_agent_price' ) ) {
            $agent_price = wc_price( $product->get_meta( '_agent_price' ) );
            return $agent_price;
        }
        return $price;
    }
}
