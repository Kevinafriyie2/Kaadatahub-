<?php
// templates/reseller-portal.php

if ( ! is_user_logged_in() || ! KDH_Reseller::is_reseller( get_current_user_id() ) ) {
    return;
}

$user_id = get_current_user_id();
$referral_link = KDH_Reseller::get_referral_link( $user_id );
// In a real implementation, you'd have functions to get sales data
$total_sales = 0; // Placeholder
$total_commission = 0; // Placeholder
?>

<div class="kdh-dashboard-container">

    <!-- Reseller Info -->
    <div class="kdh-card">
        <h2>Reseller Dashboard</h2>
        <p>Welcome, Reseller!</p>
        <p>Your referral link: <code><?php echo esc_url( $referral_link ); ?></code></p>
    </div>

    <!-- Sales Performance -->
    <div class="kdh-card">
        <h2>Sales Performance</h2>
        <p>Total Sales: <?php echo esc_html( wc_price( $total_sales ) ); ?></p>
        <p>Total Commission: <?php echo esc_html( wc_price( $total_commission ) ); ?></p>
    </div>

    <!-- Withdrawal Request -->
    <div class="kdh-card">
        <h2>Request Withdrawal</h2>
        <form id="kdh-withdrawal-form">
            <input type="number" name="amount" placeholder="Amount to withdraw" required>
            <button type="submit" class="kdh-btn">Request Withdrawal</button>
        </form>
    </div>

</div>
