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
                padding: 15px 20px;
                background-color: #1e1e1e;
                color: #fff;
                border-bottom: 1px solid #333;
                position: relative;
            }
            .header-logo a {
                color: #ffc107;
                text-decoration: none;
                font-size: 1.5em;
                font-weight: bold;
            }
            .header-nav {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background-color: #1e1e1e;
                border-top: 1px solid #333;
            }
            .header-nav.active {
                display: flex;
            }
            .header-nav a {
                color: #e0e0e0;
                text-decoration: none;
                padding: 15px 20px;
                font-weight: 600;
                transition: color 0.2s ease, background-color 0.2s ease;
                border-bottom: 1px solid #333;
            }
            .header-nav a:last-child {
                border-bottom: none;
            }
            .header-nav a:hover {
                color: #ffc107;
                background-color: #2c2c2c;
            }
            .hamburger-menu {
                display: block;
                cursor: pointer;
            }
            .hamburger-menu .bar {
                display: block;
                width: 25px;
                height: 3px;
                margin: 5px auto;
                background-color: #fff;
                transition: all 0.3s ease-in-out;
            }

            @media (min-width: 768px) {
                .hamburger-menu {
                    display: none;
                }
                .header-nav {
                    display: flex;
                    flex-direction: row;
                    position: static;
                    border: none;
                }
                .header-nav a {
                    margin-left: 20px;
                    border: none;
                    padding: 0;
                }
                .header-nav a:hover {
                    background-color: transparent;
                }
            }
        </style>
        <header class="kaa-mall-portal-header">
            <div class="header-logo">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">KAA Mall</a>
            </div>
            <div class="hamburger-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
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
