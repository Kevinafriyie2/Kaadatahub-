<?php

class Kaa_Mall_Activator {

    public static function activate() {
        self::create_virtual_product( 'Wallet Top-up' );
        self::create_virtual_product( 'Data Bundle' );
        self::create_virtual_product( 'AFA Registration' );
        self::add_reseller_role();
        self::create_history_page();
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

    private static function create_history_page() {
        $history_page_title = 'History';
        $history_page_content = '[kaa_mall_history_portal]';
        $history_page_slug = 'history';

        // Check if the page already exists
        if ( null === get_page_by_title( $history_page_title ) ) {
            // Create post object
            $page = array(
                'post_title'    => $history_page_title,
                'post_content'  => $history_page_content,
                'post_status'   => 'publish',
                'post_author'   => 1,
                'post_type'     => 'page',
                'post_name'     => $history_page_slug,
            );

            // Insert the post into the database
            $page_id = wp_insert_post( $page );

            if($page_id){
                // Store the URL in an option
                update_option( 'kaa_mall_history_portal_url', get_permalink( $page_id ) );
            }
        }
    }
}
