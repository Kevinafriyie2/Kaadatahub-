<?php

class WASSCE_Checker_PRO_WooCommerce {

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'assign_code_to_order' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'display_code_on_thankyou_page' ), 10, 1 );
        add_action( 'woocommerce_email_after_order_details', array( $this, 'display_code_in_email' ), 10, 4 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_code_in_my_account' ), 10, 1 );
    }

    public function assign_code_to_order( $order_id ) {
        $order = wc_get_order( $order_id );
        $items = $order->get_items();
        foreach ( $items as $item ) {
            if ( $item->get_product_id() == 3190 ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'wassce_checker_codes';
                $code = $wpdb->get_row( "SELECT * FROM $table_name WHERE status = 'unused' ORDER BY id ASC LIMIT 1" );
                if ( $code ) {
                    $wpdb->update(
                        $table_name,
                        array(
                            'status'      => 'used',
                            'order_id'    => $order_id,
                            'assigned_at' => current_time( 'mysql' ),
                        ),
                        array( 'id' => $code->id )
                    );
                    $order->add_meta_data( '_wassce_serial', $code->serial, true );
                    $order->add_meta_data( '_wassce_pin', $code->pin, true );
                    $order->save();
                }
            }
        }
    }

    public function display_code_on_thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $serial = $order->get_meta( '_wassce_serial' );
        $pin = $order->get_meta( '_wassce_pin' );
        if ( $serial && $pin ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_popup_assets' ) );
            add_action( 'wp_footer', array( $this, 'popup_html' ) );
        }
    }

    public function enqueue_popup_assets() {
        wp_enqueue_style( 'wassce-checker-pro-popup', plugin_dir_url( __FILE__ ) . '../assets/css/wassce-checker-pro-popup.css' );
        wp_enqueue_script( 'wassce-checker-pro-popup', plugin_dir_url( __FILE__ ) . '../assets/js/wassce-checker-pro-popup.js', array( 'jquery' ), '1.0.0', true );
    }

    public function popup_html() {
        $order_id = absint( get_query_var('order-received') );
        $order = wc_get_order( $order_id );
        $serial = $order->get_meta( '_wassce_serial' );
        $pin = $order->get_meta( '_wassce_pin' );
        ?>
        <div class="wassce-checker-pro-popup-overlay">
            <div class="wassce-checker-pro-popup">
                <div class="wassce-checker-pro-popup-header">
                    <h2>Your WASSCE Results Checker Code</h2>
                    <a href="#" class="wassce-checker-pro-popup-close">&times;</a>
                </div>
                <div class="wassce-checker-pro-popup-content">
                    <p>Serial: <?php echo esc_html( $serial ); ?> <button class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $serial ); ?>')">Copy</button></p>
                    <p>PIN: <?php echo esc_html( $pin ); ?> <button class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $pin ); ?>')">Copy</button></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function display_code_in_email( $order, $sent_to_admin, $plain_text, $email ) {
        $serial = $order->get_meta( '_wassce_serial' );
        $pin = $order->get_meta( '_wassce_pin' );
        if ( $serial && $pin ) {
            echo '<h2>Your WASSCE Results Checker</h2>';
            echo '<p>Serial: ' . esc_html( $serial ) . '</p>';
            echo '<p>PIN: ' . esc_html( $pin ) . '</p>';
        }
    }

    public function display_code_in_my_account( $order ) {
        $serial = $order->get_meta( '_wassce_serial' );
        $pin = $order->get_meta( '_wassce_pin' );
        if ( $serial && $pin ) {
            echo '<h2>Your WASSCE Results Checker</h2>';
            echo '<p>Serial: ' . esc_html( $serial ) . '</p>';
            echo '<p>PIN: ' . esc_html( $pin ) . '</p>';
        }
    }
}

new WASSCE_Checker_PRO_WooCommerce();
