<?php

class Ocean_Service_Portal_Orders {

    public function __construct() {
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_beneficiary_number_to_order_item' ), 10, 4 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_beneficiary_number_to_order' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_beneficiary_number_in_admin' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'woocommerce_after_order_notes', array( $this, 'add_beneficiary_number_field' ) );
    }

    public function add_beneficiary_number_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['beneficiary_number'] ) ) {
            $item->add_meta_data( '_kaa_beneficiary_number', $values['beneficiary_number'], true );
        }
    }

    public function add_beneficiary_number_to_order( $order_id ) {
        if ( ! empty( $_POST['beneficiary_number'] ) ) {
            update_post_meta( $order_id, '_kaa_beneficiary_number', sanitize_text_field( $_POST['beneficiary_number'] ) );
        }
    }

    public function display_beneficiary_number_in_admin( $order ) {
        $beneficiary_number = get_post_meta( $order->get_id(), '_kaa_beneficiary_number', true );
        if ( ! empty( $beneficiary_number ) ) {
            echo '<p><strong>' . __( 'Beneficiary Number', 'ocean-service-portal' ) . ':</strong> ' . $beneficiary_number . '</p>';
        }
    }

    public function register_shortcodes() {
        add_shortcode( 'kaa_orders_history', array( $this, 'render_orders_history_shortcode' ) );
        add_shortcode( 'kaa_agent_dashboard', array( $this, 'render_agent_dashboard_shortcode' ) );
    }

    public function render_orders_history_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        ob_start();
        include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/orders-history.php';
        return ob_get_clean();
    }

    public function render_agent_dashboard_shortcode() {
        if ( ! current_user_can( 'kaa_agent' ) ) {
            return '<p>' . esc_html__( 'You must be logged in as an agent to view this content.', 'ocean-service-portal' ) . '</p>';
        }

        ob_start();
        include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/agent-dashboard.php';
        return ob_get_clean();
    }

    public function add_beneficiary_number_field( $checkout ) {
        woocommerce_form_field(
            'beneficiary_number',
            array(
                'type'        => 'text',
                'class'       => array( 'form-row-wide' ),
                'label'       => __( 'Beneficiary Number', 'ocean-service-portal' ),
                'placeholder' => __( 'Enter the beneficiary number', 'ocean-service-portal' ),
                'required'    => true,
            ),
            $checkout->get_value( 'beneficiary_number' )
        );
    }
}
