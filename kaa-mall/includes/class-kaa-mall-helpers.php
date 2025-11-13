<?php

class Kaa_Mall_Helpers {

    public static function get_reseller_total_sales( $reseller_id ) {
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array( 'wc-completed', 'wc-processing' ),
            'numberposts' => -1,
            'meta_query'  => array(
                array(
                    'key'   => '_reseller_id',
                    'value' => $reseller_id,
                ),
            ),
        );
        $orders = get_posts( $args );
        $total_sales = 0;
        foreach ( $orders as $order_post ) {
            $order = wc_get_order( $order_post->ID );
            $total_sales += $order->get_total();
        }
        return $total_sales;
    }

    public static function get_reseller_tier_discounted_price( $original_price, $reseller_id, $network, $bundle_name ) {
        // Check for custom reseller prices first
        $reseller_prices = get_user_meta( $reseller_id, '_kaa_mall_reseller_prices_' . $network, true );
        if ( ! empty( $reseller_prices ) && isset( $reseller_prices[ $bundle_name ] ) ) {
            return $reseller_prices[ $bundle_name ];
        }

        // Apply tier discount if no custom price is set
        $tier_name = get_user_meta( $reseller_id, '_kaa_mall_reseller_tier', true );
        $tiers = get_option( 'kaa_mall_reseller_tiers', array() );
        foreach ( $tiers as $tier ) {
            if ( $tier['name'] === $tier_name ) {
                $discount = floatval( $tier['discount'] );
                return $original_price * ( 1 - ( $discount / 100 ) );
            }
        }

        return $original_price;
    }
}
