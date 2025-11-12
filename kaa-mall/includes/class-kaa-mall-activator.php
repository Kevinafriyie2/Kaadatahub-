<?php

class Kaa_Mall_Activator {

    public static function activate() {
        self::create_wallet_transactions_table();
        self::create_virtual_product( 'Wallet Top-up' );
        self::create_virtual_product( 'Data Bundle' );
        self::create_virtual_product( 'AFA Registration' );
        self::add_reseller_role();
        self::create_history_page();
    }

    private static function create_wallet_transactions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kaa_mall_wallet_transactions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10, 2) NOT NULL,
            type varchar(255) NOT NULL,
            details text NOT NULL,
            balance_after decimal(10, 2) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
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
