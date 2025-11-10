<!-- KAA Mall Dashboard -->
<div id="kaa-mall-dashboard-wrapper">

    <!-- Sidebar Navigation -->
    <div id="kaa-mall-sidebar" class="kaa-mall-sidebar">
        <div class="sidebar-header">
            <h3 class="plugin-name">Kaadatahub</h3>
            <a href="javascript:void(0)" class="close-btn">&times;</a>
        </div>
        <ul class="sidebar-nav">
            <li><a href="#" data-page="dashboard-main"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-section-title">SERVICES</li>
            <li><a href="#" data-page="buy-data" data-network="mtn"><i class="fas fa-signal"></i> MTN</a></li>
            <li><a href="#" data-page="buy-data" data-network="airteltigo"><i class="fas fa-signal"></i> AirtelTigo</a></li>
            <li><a href="#" data-page="buy-data" data-network="vodafone"><i class="fas fa-signal"></i> Vodafone</a></li>
            <li><a href="#" data-page="wallet"><i class="fas fa-wallet"></i> Wallet</a></li>
            <li><a href="#" data-page="wallet-history"><i class="fas fa-history"></i> Wallet History</a></li>
            <?php
            $user = wp_get_current_user();
            if (in_array('reseller', (array) $user->roles)) :
            ?>
            <li><a href="#" data-page="my-shop"><i class="fas fa-store"></i> Reseller</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <a href="#" class="contact-admin-fab">
        <i class="fas fa-comment-dots"></i> Contact the Admin
    </a>

    <!-- Main Content -->
    <div id="kaa-mall-main-content">
        <div class="top-bar">
            <span class="open-btn">&#9776;</span>
            <div class="user-actions">
                <?php if (is_user_logged_in()) : ?>
                    <div class="user-profile">
                        <i class="fas fa-user-circle"></i>
                        <div class="profile-dropdown">
                            <a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a>
                        </div>
                    </div>
                <?php else : ?>
                    <a href="<?php echo wp_login_url(); ?>" class="button">Login</a>
                    <a href="<?php echo wp_registration_url(); ?>" class="button">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-header">
            <h2>Kaadatahub</h2>
            <p>Dashboard</p>
        </div>
        <div class="main-dashboard-content">
            <!-- Dynamic content will be loaded here -->
        </div>
    </div>
</div>
