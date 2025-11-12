<?php

class Kaa_Mall_Activator {

    public static function activate() {
        self::create_virtual_product( 'Wallet Top-up' );
        self::create_virtual_product( 'Data Bundle' );
        self::create_virtual_product( 'AFA Registration' );
        self::add_reseller_role();
    }

    private static function add_reseller_role() {
        add_role(
            'reseller',
            __( 'Reseller', 'kaa-mall' ),
            array(
                'read' => true,
                'level_0' => true,
            )
        );
    }

    private static function create_virtual_product( $product_name ) {
        $option_name = 'kaa_mall_' . str_replace( '-', '_', sanitize_title( $product_name ) ) . '_product_id';

        // Check if we have a valid product ID stored.
        $product_id = get_option( $option_name );
        if ( $product_id && get_post_type( $product_id ) === 'product' ) {
            return;
        }

        // If no valid ID is stored, try to find the product by its title.
        $product = get_page_by_title( $product_name, OBJECT, 'product' );
        if ( $product ) {
            // Product exists, store its ID.
            update_option( $option_name, $product->ID );
        } else {
            // Product doesn't exist, create it.
            $new_product = new WC_Product_Simple();
            $new_product->set_name( $product_name );
            $new_product->set_slug( sanitize_title( $product_name ) );
            $new_product->set_virtual( true );
            $new_product->set_status( 'publish' );
            $new_product_id = $new_product->save();

            if ( $new_product_id ) {
                update_option( $option_name, $new_product_id );
            }
        }
    }
}
