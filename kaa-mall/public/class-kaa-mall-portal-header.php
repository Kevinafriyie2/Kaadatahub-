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
                color: #fff;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1001;
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            }
            .header-logo a {
                color: #fff;
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
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                border-top: 1px solid rgba(255, 255, 255, 0.2);
                z-index: 1000;
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
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }
            .header-nav a:last-child {
                border-bottom: none;
            }
            .header-nav a:hover {
                color: #ffc107;
                background-color: rgba(0, 0, 0, 0.3);
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
                    background: none;
                    backdrop-filter: none;
                    -webkit-backdrop-filter: none;
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
                <a href="https://kaadatahub.shop/users-portal-new/">Dashboard</a>
                <?php
                $history_url = get_option('kaa_mall_history_portal_url');
                if ( empty( $history_url ) ) {
                    $history_page = get_page_by_path( 'history' );
                    if ( $history_page ) {
                        $history_url = get_permalink( $history_page->ID );
                    }
                }
                ?>
                <a href="<?php echo esc_url( $history_url ); ?>">History</a>
                <?php if ( current_user_can( 'reseller' ) ) : ?>
                    <a href="https://kaadatahub.shop/resellers-portal/">Reseller Dashboard</a>
                <?php else : ?>
                    <a href="https://kaadatahub.shop/apply-as-a-reseller/">Apply to be a Reseller</a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Logout</a>
            </nav>
        </header>
        <?php
        return ob_get_clean();
    }
}
