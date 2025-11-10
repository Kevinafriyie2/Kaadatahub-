<?php

class Kaa_Mall_Activator {

    public static function activate() {
        self::create_virtual_product( 'Wallet Top-up' );
        self::create_virtual_product( 'Data Bundle' );
        self::create_virtual_product( 'AFA Registration' );
        self::add_reseller_role();
        self::create_page( 'Apply to be a Reseller', '[kaa_reseller_apply]' );
    }

    private static function create_page( $title, $content ) {
        if ( ! get_page_by_title( $title, 'OBJECT', 'page' ) ) {
            $page = array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_author'  => 1,
                'post_type'    => 'page',
            );
            wp_insert_post( $page );
        }
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
        if ( ! self::product_exists( $product_name ) ) {
            $product = new WC_Product_Simple();
            $product->set_name( $product_name );
            $product->set_slug( sanitize_title( $product_name ) );
            $product->set_virtual( true );
            $product->set_status( 'publish' );
            $product->save();
        }
    }

    private static function product_exists( $product_name ) {
        $product = get_page_by_title( $product_name, OBJECT, 'product' );
        return ( $product !== null );
    }
}
