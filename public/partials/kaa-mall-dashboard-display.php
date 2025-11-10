<!-- KAA Mall Dashboard -->
<div id="kaa-mall-dashboard-wrapper">

    <!-- Hamburger Navigation Menu -->
    <div id="kaa-mall-hamburger-nav">
        <div class="hamburger-header">
            <h3 class="plugin-name">Kaadatahub</h3>
            <a href="javascript:void(0)" class="close-hamburger">&times;</a>
        </div>
        <ul class="hamburger-nav-links">
            <li><a href="#" data-page="dashboard-main"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#" data-page="buy-data" data-network="mtn"><i class="fas fa-signal"></i> MTN</a></li>
            <li><a href="#" data-page="buy-data" data-network="airteltigo"><i class="fas fa-signal"></i> AirtelTigo</a></li>
            <li><a href="#" data-page="buy-data" data-network="vodafone"><i class="fas fa-signal"></i> Vodafone</a></li>
            <li><a href="#" data-page="wallet"><i class="fas fa-wallet"></i> Wallet</a></li>
            <li><a href="#" data-page="wallet-history"><i class="fas fa-history"></i> Wallet History</a></li>
            <?php
            $user = wp_get_current_user();
            if (in_array('reseller', (array) $user->roles)) :
            ?>
            <li><a href="#" data-page="my-shop"><i class="fas fa-store"></i> Resellers Dashboard</a></li>
            <?php endif; ?>
            <li><a href="<?php echo wp_logout_url(home_url()); ?>"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div id="kaa-mall-main-content">
        <div class="top-bar">
            <span class="open-hamburger">&#9776;</span>
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

        <div class="main-dashboard-content">
            <!-- All the new cards will go here -->
            <div class="dashboard-grid">

                <!-- Data Bundle Store Card -->
                <div class="card data-bundle-store-card">
                    <div class="card-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3>Data Bundle Store</h3>
                        <p>Instant Data Top-up for All Networks</p>
                    </div>
                </div>

                <!-- Wallet Balance Card -->
                <div class="card wallet-balance-card">
                    <?php include_once plugin_dir_path(__FILE__) . 'kaa-mall-wallet-display.php'; ?>
                </div>

                <!-- Data Purchase Card -->
                <div class="card data-purchase-card">
                     <?php include_once plugin_dir_path(__FILE__) . 'kaa-mall-buy-data-display.php'; ?>
                </div>

                <!-- AFA Bundle Registration Card -->
                <div class="card afa-registration-card">
                    <div class="card-header">
                        <i class="fas fa-bullseye"></i>
                        <h3>AFA Bundle Registration</h3>
                        <p>Register for AFA bundles and get amazing benefits!</p>
                    </div>
                    <form id="afa-registration-form">
                        <div class="form-group">
                            <label for="full-name"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" id="full-name" placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                            <input type="text" id="location" placeholder="Enter your location">
                        </div>
                        <div class="form-group">
                            <label for="ghana-card"><i class="fas fa-id-card"></i> ID Ghana Card Number</label>
                            <input type="text" id="ghana-card" placeholder="GHA-XXXXXXXX-X">
                        </div>
                        <div class="registration-fee">
                            <p>Registration Fee</p>
                            <p class="fee">GHC¢13.00</p>
                        </div>
                        <button type="submit" class="button"><i class="fas fa-money-bill-wave"></i> Register Now - GHC¢13</button>
                    </form>
                </div>

                <!-- Purchase History Card -->
                <div class="card purchase-history-card">
                    <div class="card-header">
                         <i class="fas fa-history"></i>
                        <h3>Purchase History</h3>
                    </div>
                    <div class="card-content">
                        <!-- Content will be loaded dynamically -->
                        <p>There has been a critical error on this website.</p>
                        <p><a href="#">Learn more about troubleshooting WordPress.</a></p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
