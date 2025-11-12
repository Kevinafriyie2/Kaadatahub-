<?php

class KDH_WooCommerce {

    public static function init() {
        // Register custom product type
        add_action( 'init', [__CLASS__, 'register_data_bundle_product_type'] );
        add_filter( 'product_type_selector', [__CLASS__, 'add_data_bundle_product_type'] );

        // Add custom fields to product editor
        add_action( 'woocommerce_product_options_general_product_data', [__CLASS__, 'add_custom_fields'] );
        add_action( 'woocommerce_process_product_meta', [__CLASS__, 'save_custom_fields'] );

        // Modify checkout fields
        add_filter( 'woocommerce_checkout_fields', [__CLASS__, 'custom_checkout_fields'] );
        add_action( 'woocommerce_checkout_update_order_meta', [__CLASS__, 'save_custom_checkout_fields'] );
    }

    public static function register_data_bundle_product_type() {
        class WC_Product_Data_Bundle extends WC_Product {
            public function get_type() {
                return 'data_bundle';
            }
        }
    }

    public static function add_data_bundle_product_type( $types ) {
        $types['data_bundle'] = __( 'Data Bundle', 'kaadatahub' );
        return $types;
    }

    public static function add_custom_fields() {
        global $post;

        echo '<div class="options_group">';

        woocommerce_wp_select([
            'id' => '_network',
            'label' => __('Network', 'kaadatahub'),
            'options' => [
                'mtn' => __('MTN', 'kaadatahub'),
                'vodafone' => __('Vodafone', 'kaadatahub'),
                'airteltigo' => __('AirtelTigo', 'kaadatahub'),
            ],
        ]);

        woocommerce_wp_text_input([
            'id' => '_data_size',
            'label' => __('Data Size (e.g., 1GB)', 'kaadatahub'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_validity',
            'label' => __('Validity (e.g., 30 Days)', 'kaadatahub'),
        ]);

        echo '</div>';
    }

    public static function save_custom_fields( $post_id ) {
        $network = isset( $_POST['_network'] ) ? sanitize_text_field( $_POST['_network'] ) : '';
        update_post_meta( $post_id, '_network', $network );

        $data_size = isset( $_POST['_data_size'] ) ? sanitize_text_field( $_POST['_data_size'] ) : '';
        update_post_meta( $post_id, '_data_size', $data_size );

        $validity = isset( $_POST['_validity'] ) ? sanitize_text_field( $_POST['_validity'] ) : '';
        update_post_meta( $post_id, '_validity', $validity );
    }

    public static function custom_checkout_fields( $fields ) {
        $fields['billing']['billing_phone_number'] = array(
            'label'     => __('Phone Number for Data', 'kaadatahub'),
            'placeholder'   => _x('024 aky aky aky', 'placeholder', 'kaadatahub'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
         );

        return $fields;
    }

    public static function save_custom_checkout_fields( $order_id ) {
        if ( ! empty( $_POST['billing_phone_number'] ) ) {
            update_post_meta( $order_id, 'Phone Number for Data', sanitize_text_field( $_POST['billing_phone_number'] ) );
        }
    }
}

KDH_WooCommerce::init();
