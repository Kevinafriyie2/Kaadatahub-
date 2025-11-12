<?php

class KDH_Shortcodes {

    public static function init() {
        add_shortcode('kdh_dashboard', [__CLASS__, 'render_dashboard']);
        add_shortcode('kdh_reseller_portal', [__CLASS__, 'render_reseller_portal']);
        add_shortcode('kdh_data_products', [__CLASS__, 'render_data_products']);
    }

    public static function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return 'Please log in to view your dashboard.';
        }

        ob_start();
        include KDH_PLUGIN_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }

    public static function render_reseller_portal($atts) {
        if (!is_user_logged_in()) {
            return 'Please log in to view the reseller portal.';
        }

        if (!KDH_Reseller::is_reseller(get_current_user_id())) {
            return 'You are not an approved reseller.';
        }

        ob_start();
        include KDH_PLUGIN_DIR . 'templates/reseller-portal.php';
        return ob_get_clean();
    }

    public static function render_data_products($atts) {
        $atts = shortcode_atts([
            'network' => 'all',
        ], $atts, 'kdh_data_products');

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => [],
        ];

        if ($atts['network'] !== 'all') {
            $args['meta_query'][] = [
                'key' => '_network',
                'value' => $atts['network'],
                'compare' => '=',
            ];
        }

        $products = new WP_Query($args);

        ob_start();
        include KDH_PLUGIN_DIR . 'templates/data-products.php';
        wp_reset_postdata();
        return ob_get_clean();
    }
}

KDH_Shortcodes::init();
