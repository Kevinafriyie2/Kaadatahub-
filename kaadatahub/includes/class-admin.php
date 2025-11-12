<?php

class KDH_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
    }

    public static function add_admin_menu() {
        add_menu_page(
            __('Kaadatahub', 'kaadatahub'),
            __('Kaadatahub', 'kaadatahub'),
            'manage_options',
            'kaadatahub',
            [__CLASS__, 'render_dashboard_page'],
            'dashicons-cloud',
            2
        );

        add_submenu_page(
            'kaadatahub',
            __('Analytics', 'kaadatahub'),
            __('Analytics', 'kaadatahub'),
            'manage_options',
            'kdh-analytics',
            [__CLASS__, 'render_analytics_page']
        );

        add_submenu_page(
            'kaadatahub',
            __('Dashboard', 'kaadatahub'),
            __('Dashboard', 'kaadatahub'),
            'manage_options',
            'kaadatahub',
            [__CLASS__, 'render_dashboard_page']
        );

        add_submenu_page(
            'kaadatahub',
            __('Users & Resellers', 'kaadatahub'),
            __('Users & Resellers', 'kaadatahub'),
            'manage_options',
            'kdh-users',
            [__CLASS__, 'render_users_page']
        );

        add_submenu_page(
            'kaadatahub',
            __('Data Products', 'kaadatahub'),
            __('Data Products', 'kaadatahub'),
            'manage_options',
            'edit.php?post_type=product'
        );

        add_submenu_page(
            'kaadatahub',
            __('Withdrawals', 'kaadatahub'),
            __('Withdrawals', 'kaadatahub'),
            'manage_options',
            'kdh-withdrawals',
            [__CLASS__, 'render_withdrawals_page']
        );

        add_submenu_page(
            'kaadatahub',
            __('Settings', 'kaadatahub'),
            __('Settings', 'kaadatahub'),
            'manage_options',
            'kdh-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_dashboard_page() {
        include KDH_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    public static function render_users_page() {
        include KDH_PLUGIN_DIR . 'admin/partials/users.php';
    }

    public static function render_withdrawals_page() {
        include KDH_PLUGIN_DIR . 'admin/partials/withdrawals.php';
    }

    public static function render_analytics_page() {
        include KDH_PLUGIN_DIR . 'admin/partials/analytics.php';
    }

    public static function render_settings_page() {
        include KDH_PLUGIN_DIR . 'admin/partials/settings.php';
    }
}
