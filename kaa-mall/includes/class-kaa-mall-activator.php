<?php

class Kaa_Mall_Activator {

    public static function activate() {
        self::create_virtual_product( 'Wallet Top-up' );
        self::create_virtual_product( 'Data Bundle' );
        self::create_virtual_product( 'AFA Registration' );
    }

    private static function create_virtual_product( $product_name ) {
        if ( ! post_exists( $product_name ) ) {
            $product = new WC_Product_Simple();
            $product->set_name( $product_name );
            $product->set_slug( sanitize_title( $product_name ) );
            $product->set_virtual( true );
            $product->set_status( 'publish' );
            $product->save();
        }
    }
}
