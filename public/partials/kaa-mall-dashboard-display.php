<!-- KAA Mall Dashboard -->
<div id="kaa-mall-dashboard-wrapper">

    <!-- Sidebar Navigation -->
    <div id="kaa-mall-sidebar" class="kaa-mall-sidebar">
        <div class="sidebar-header">
            <h3 class="plugin-name">Kaadatahub</h3>
            <a href="javascript:void(0)" class="close-btn">&times;</a>
        </div>
        <ul class="sidebar-nav">
            <li class="nav-section-title">SERVICES</li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-signal"></i> MTN <i class="fas fa-chevron-down"></i></a>
                <ul class="submenu">
                    <li><a href="#" data-page="buy-data" data-network="mtn">Buy Data</a></li>
                    <li><a href="#">MTNUP2U Business</a></li>
                </ul>
            </li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-signal"></i> AirtelTigo <i class="fas fa-chevron-down"></i></a>
                <ul class="submenu">
                    <li><a href="#" data-page="buy-data" data-network="airteltigo">Buy Data</a></li>
                    <li><a href="#">AT iShare Business</a></li>
                </ul>
            </li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-signal"></i> Vodafone <i class="fas fa-chevron-down"></i></a>
                <ul class="submenu">
                    <li><a href="#" data-page="buy-data" data-network="vodafone">Buy Data</a></li>
                    <li><a href="#">AT Big Time Business</a></li>
                </ul>
            </li>
            <li><a href="#" data-page="wallet"><i class="fas fa-wallet"></i> Wallet</a></li>
            <?php
            $user = wp_get_current_user();
            if (in_array('reseller', (array) $user->roles)) :
            ?>
            <li><a href="#" data-page="my-shop"><i class="fas fa-store"></i> Reseller</a></li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer">
             <a href="#">Contact the Admin</a>
        </div>
    </div>

    <!-- Main Content -->
    <div id="kaa-mall-main-content">
        <div class="top-bar">
            <span class="open-btn">&#9776;</span>
            <div class="user-actions">
                <?php if (is_user_logged_in()) : ?>
                    <i class="fas fa-user-circle"></i>
                <?php else : ?>
                    <a href="<?php echo wp_login_url(); ?>" class="button">Login</a>
                    <a href="<?php echo wp_registration_url(); ?>" class="button">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-dashboard-content">
             <div class="dashboard-header">
                <h2>Kaadatahub</h2>
                <p>Dashboard</p>
            </div>
            <!-- Dynamic content will be loaded here -->
        </div>
    </div>
</div>
