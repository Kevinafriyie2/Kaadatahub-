<?php

class Kaa_Mall_Portal_Header {

    public static function render() {
        ob_start();
        ?>
        <style>
            .kaa-mall-portal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 30px;
                background-color: #1e1e1e;
                color: #fff;
                border-bottom: 1px solid #333;
            }
            .header-logo a {
                color: #ffc107;
                text-decoration: none;
                font-size: 1.5em;
                font-weight: bold;
            }
            .header-nav a {
                color: #e0e0e0;
                text-decoration: none;
                margin-left: 20px;
                font-weight: 600;
                transition: color 0.2s ease;
            }
            .header-nav a:hover {
                color: #ffc107;
            }
        </style>
        <header class="kaa-mall-portal-header">
            <div class="header-logo">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">KAA Mall</a>
            </div>
            <nav class="header-nav">
                <a href="<?php echo esc_url( get_option('kaa_mall_user_portal_url') ); ?>">Dashboard</a>
                <?php if ( current_user_can( 'reseller' ) ) : ?>
                    <a href="<?php echo esc_url( get_option('kaa_mall_reseller_portal_url') ); ?>">Reseller Dashboard</a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Logout</a>
            </nav>
        </header>
        <?php
        return ob_get_clean();
    }
}
