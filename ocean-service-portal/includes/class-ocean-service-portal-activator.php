<?php

class Ocean_Service_Portal_Activator {

    public static function activate() {
        require_once plugin_dir_path( __FILE__ ) . 'class-ocean-service-portal-roles.php';
        Ocean_Service_Portal_Roles::add_roles();
        self::create_product_categories();
        self::create_wallet_tables();
        self::create_wallet_top_up_product();
    }

    public static function create_product_categories() {
        $categories = array( 'MTN', 'AirtelTigo', 'Telecel' );

        foreach ( $categories as $category ) {
            if ( ! term_exists( $category, 'product_cat' ) ) {
                wp_insert_term( $category, 'product_cat' );
            }
        }
    }

    public static function create_wallet_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_name = $wpdb->prefix . 'kaa_wallet';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            agent_id bigint(20) UNSIGNED NOT NULL,
            balance decimal(10, 2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            UNIQUE KEY agent_id (agent_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table_name = $wpdb->prefix . 'kaa_wallet_tx';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            agent_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(10, 2) NOT NULL,
            type varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql );
    }

    public static function create_wallet_top_up_product() {
        if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
            return;
        }

        if ( ! wc_get_product_id_by_sku( 'KAA_WALLET_TOP_UP' ) ) {
            $product = new WC_Product_Simple();
            $product->set_name( 'Wallet Top-up' );
            $product->set_sku( 'KAA_WALLET_TOP_UP' );
            $product->set_regular_price( 0 );
            $product->set_virtual( true );
            $product->set_status( 'publish' );
            $product->save();
        }
    }
}
