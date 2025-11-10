<div class="kaa-mall-buy-data-content">

    <h2 class="network-title"><?php echo esc_html(ucfirst($network)); ?> Bundles</h2>

    <!-- Data Bundle Store Card -->
    <div class="info-card orange-card">
        <div class="card-icon">
            <i class="fas fa-mobile-alt"></i>
        </div>
        <div class="card-content">
            <h3>Data Bundle Store</h3>
            <p>Instant Data Top-up for All Networks</p>
        </div>
    </div>

    <!-- Wallet Balance Card -->
    <div class="info-card purple-card">
        <div class="card-content">
            <p class="wallet-label">Wallet Balance</p>
            <h2 class="wallet-amount">GHS <?php echo number_format( $balance, 2 ); ?></h2>
            <button class="top-up-btn"><i class="fas fa-credit-card"></i> Top Up Wallet</button>
        </div>
    </div>

    <!-- Data Bundles Card -->
    <div class="data-bundle-card">
        <?php
        $product_list = $products[$network];
        ?>
        <div class="data-bundle-form active" id="form-<?php echo esc_attr($network); ?>">
            <h4><i class="fas fa-signal"></i> <?php echo esc_html(ucfirst($network)); ?> Data Bundles</h4>
            <div class="form-group">
                    <label for="phone-number-<?php echo esc_attr($network); ?>"><?php echo esc_html(ucfirst($network)); ?> Phone Number</label>
                    <input type="text" id="phone-number-<?php echo esc_attr($network); ?>" placeholder="Enter phone number">
                </div>
                <?php if (!is_user_logged_in()) : ?>
                <div class="form-group">
                    <label for="guest-email-<?php echo esc_attr($network); ?>">Email Address</label>
                    <input type="email" id="guest-email-<?php echo esc_attr($network); ?>" placeholder="Enter your email address">
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="bundle-select-<?php echo esc_attr($network); ?>">Select Bundle</label>
                    <select id="bundle-select-<?php echo esc_attr($network); ?>">
                        <option value="">— Choose Bundle —</option>
                        <?php foreach ( $product_list as $product ) :
                            $name = $product->get_name();
                            $price = $product->get_price();
                            // Extract size like "1GB" or "500MB" from the name
                            preg_match('/(\d+(\.\d+)?(GB|MB))/i', $name, $matches);
                            $size = isset($matches[0]) ? $matches[0] : $name;
                        ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html( $size . ' - GH₵' . number_format($price, 2) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="payment-options">
                        <label><input type="radio" name="payment-method-<?php echo esc_attr($network); ?>" value="wallet" checked> Wallet Balance</label>
                        <label><input type="radio" name="payment-method-<?php echo esc_attr($network); ?>" value="paystack"> Paystack</label>
                    </div>
                </div>
                <button class="direct-purchase-btn" data-network="<?php echo esc_attr($network); ?>"><i class="fas fa-rocket"></i> Purchase</button>
            </div>
    </div>

    <!-- AFA Bundle Registration Card -->
    <div class="info-card afa-card">
        <div class="card-content">
            <h4><i class="fas fa-bullseye"></i> AFA Bundle Registration</h4>
            <p>Register for AFA bundles and get amazing benefits!</p>
            <form class="afa-form">
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
                    <p class="fee-amount">GHS 13.00</p>
                </div>
                <button class="register-btn"><i class="fas fa-hand-holding-usd"></i> Register Now - GHS 13</button>
            </form>
        </div>
    </div>

    <!-- Purchase History Card -->
    <div class="purchase-history-card">
         <h4><i class="fas fa-history"></i> Purchase History</h4>
         <p>No recent purchases.</p>
    </div>

</div>
