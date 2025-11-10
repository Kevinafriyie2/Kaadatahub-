<!-- KAA Mall Dashboard -->
<div id="kaa-mall-dashboard-wrapper">

    <!-- Sidebar Navigation -->
    <div id="kaa-mall-sidebar" class="kaa-mall-sidebar">
        <div class="sidebar-header">
            <h3 class="plugin-name">Hubnet</h3>
            <a href="javascript:void(0)" class="close-btn">&times;</a>
        </div>
        <ul class="sidebar-nav">
            <li><a href="#" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-section-title">Services</li>
            <li><a href="#" data-page="buy-data"><i class="fas fa-shopping-cart"></i> Buy Data</a></li>
            <li><a href="#"><i class="fas fa-briefcase"></i> AT iShare Business</a></li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-business-time"></i> MTNUP2U Business <i class="fas fa-chevron-down"></i></a>
                <ul class="submenu">
                    <li><a href="#">Inventory</a></li>
                    <li><a href="#">Bulk Upload</a></li>
                    <li><a href="#">Orders</a></li>
                </ul>
            </li>
            <li><a href="#"><i class="fas fa-briefcase"></i> AT Big Time Business</a></li>

            <li class="nav-section-title">Credits & Debits</li>
            <li><a href="#"><i class="fas fa-wallet"></i> Wallet</a></li>

            <?php
            $user = wp_get_current_user();
            if (in_array('reseller', (array) $user->roles)) :
            ?>
            <li class="nav-section-title">Reseller</li>
            <li><a href="#" data-page="my-shop"><i class="fas fa-store"></i> My Shop</a></li>
            <?php endif; ?>

            <li class="nav-section-title">Performance</li>
            <li><a href="#"><i class="fas fa-chart-line"></i> Sales Performance</a></li>

            <li class="nav-section-title">Developers</li>
            <li><a href="#"><i class="fas fa-code"></i> APIs & Webhooks</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div id="kaa-mall-main-content">
        <div class="top-bar">
            <span class="open-btn">&#9776;</span>
            <div class="user-actions">
                <i class="fas fa-bell"></i>
                <i class="fas fa-user-circle"></i>
            </div>
        </div>

        <div class="main-dashboard-content">
            <!-- Dynamic content will be loaded here -->
             <h1>Dashboard Content Area</h1>
             <p>Wallet balances and other features will be displayed here.</p>
        </div>
    </div>
</div>
