<div class="kaa-mall-wallet-content">

    <!-- Wallet Balance Card -->
    <div class="info-card purple-card">
        <div class="card-icon">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="card-content">
            <h3>My Wallet</h3>
            <p>Your current balance and top-up options.</p>
        </div>
    </div>

    <div class="wallet-balance-card">
        <p class="wallet-label">Current Balance</p>
        <h2 class="wallet-amount">GH₵ <?php echo number_format( Kaa_Mall_Wallet::get_wallet_balance(get_current_user_id()), 2 ); ?></h2>
    </div>

    <!-- Top Up Card -->
    <div class="top-up-card">
        <h4><i class="fas fa-credit-card"></i> Top Up Your Wallet</h4>
        <p>Enter the amount you want to add to your wallet. You will be redirected to Paystack to complete the payment.</p>
        <div class="form-group">
            <label for="top-up-amount">Amount (GH₵)</label>
            <input type="number" id="top-up-amount" placeholder="e.g., 50.00" step="0.01" min="1.00">
        </div>
        <button class="button top-up-btn"><i class="fas fa-arrow-up"></i> Top Up with Paystack</button>
    </div>

    <!-- Contact Admin Floating Button -->
    <a href="https://wa.me/<?php echo esc_attr( get_option('kaa_mall_whatsapp_number') ); ?>" class="contact-admin-fab" target="_blank">
        <div class="fab-icon">
            <i class="fas fa-comment-dots"></i>
        </div>
        <div class="fab-text">
            Contact the Admin
        </div>
    </a>
</div>
