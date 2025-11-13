<?php

class Kaa_Mall_Activator {

    public static function activate() {
        // Core functionalities
        self::create_virtual_product( 'Wallet Top-up' );
        self::create_virtual_product( 'AFA Registration' );

        // Wipe all old data products and create the new ones.
        self::delete_all_data_products();
        self::create_data_bundle_products();

        // Ensure the reseller role exists.
        self::add_reseller_role();
    }

    private static function add_reseller_role() {
        add_role(
            'reseller',
            __( 'Reseller', 'kaa-mall' ),
            array( 'read' => true, 'level_0' => true )
        );
    }

    private static function delete_all_data_products() {
        // This function now deletes ALL previous data bundle products to ensure a clean slate.
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        );

        $products = get_posts( $args );

        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( $product ) {
                $product_name = $product->get_name();
                // Target any product starting with MTN, AT, Voda, or Telecel.
                if ( preg_match('/^(MTN|AT|Voda|Telecel)\s/', $product_name) || $product_name === 'Data Bundle' ) {
                    wp_delete_post( $product->get_id(), true ); // true = force delete
                }
            }
        }
    }

    private static function create_data_bundle_products() {
        // Defines the new, definitive list of data bundles and their prices.
        $bundles = array(
            // Airteltigo Bundles
            'AT 1GB' => 5.00, 'AT 2GB' => 10.00, 'AT 3GB' => 14.00, 'AT 4GB' => 18.00, 'AT 5GB' => 24.00, 'AT 6GB' => 28.00, 'AT 8GB' => 34.00, 'AT 10GB' => 44.00, 'AT 15GB' => 64.00, 'AT 20GB' => 84.00, 'AT 25GB' => 106.00, 'AT 30GB' => 127.00, 'AT 40GB' => 166.00, 'AT 50GB' => 206.00,
            // Telecel Bundles
            'Telecel 5GB' => 24.00, 'Telecel 10GB' => 42.00, 'Telecel 20GB' => 82.00, 'Telecel 25GB' => 106.00, 'Telecel 30GB' => 128.00, 'Telecel 40GB' => 167.00, 'Telecel 50GB' => 190.00, 'Telecel 90GB' => 255.00, 'Telecel 190GB' => 356.00, 'Telecel 280GB' => 537.00, 'Telecel 380GB' => 658.00,
            // MTN Bundles
            'MTN 1GB' => 5.2, 'MTN 2GB' => 10.2, 'MTN 3GB' => 15.0, 'MTN 4GB' => 20.0, 'MTN 5GB' => 25.0, 'MTN 6GB' => 30.5, 'MTN 8GB' => 35.0, 'MTN 10GB' => 45.0, 'MTN 15GB' => 65.0, 'MTN 20GB' => 86.0, 'MTN 25GB' => 104.2, 'MTN 30GB' => 125.0, 'MTN 40GB' => 167.0, 'MTN 50GB' => 206.0,
        );

        foreach ( $bundles as $name => $price ) {
            self::create_or_update_product_by_name( $name, $price );
        }
    }

    private static function create_or_update_product_by_name( $product_name, $price ) {
        $product = get_page_by_title( $product_name, OBJECT, 'product' );

        if ( $product ) {
            $product_obj = wc_get_product( $product->ID );
            if ( $product_obj ) {
                $product_obj->set_regular_price( $price );
                $product_obj->save();
            }
        } else {
            $new_product = new WC_Product_Simple();
            $new_product->set_name( $product_name );
            $new_product->set_slug( sanitize_title( $product_name ) );
            $new_product->set_virtual( true );
            $new_product->set_regular_price( $price );
            $new_product->set_status( 'publish' );
            $new_product->save();
        }
    }

    private static function create_virtual_product( $product_name ) {
        // Kept for core products like Wallet Top-up.
        $option_name = 'kaa_mall_' . str_replace( '-', '_', sanitize_title( $product_name ) ) . '_product_id';

        $product_id = get_option( $option_name );
        if ( $product_id && get_post_type( $product_id ) === 'product' ) {
            return;
        }

        $product = get_page_by_title( $product_name, OBJECT, 'product' );
        if ( $product ) {
            update_option( $option_name, $product->ID );
        } else {
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
