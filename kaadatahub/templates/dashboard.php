<?php
// templates/dashboard.php

if ( ! is_user_logged_in() ) {
    return;
}

$user_id = get_current_user_id();
$wallet_balance = KDH_Wallet::get_wallet_balance( $user_id );
$transaction_history = KDH_Wallet::get_transaction_history( $user_id );
?>

<div class="kdh-dashboard-container">

    <?php
    // Add a "Stop Impersonating" button if the admin is impersonating
    if ( isset( $_SESSION['kdh_impersonating'] ) ) {
        echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( ['action' => 'stop_impersonating'] ), 'kdh_stop_impersonating' ) ) . '" class="kdh-btn">Stop Impersonating</a>';
    }
    ?>

    <!-- Wallet Balance -->
    <div class="kdh-card">
        <h2>My Wallet</h2>
        <p>Balance: <span class="wallet-balance"><?php echo esc_html( $wallet_balance ); ?></span></p>
        <form id="kdh-topup-form">
            <input type="number" name="amount" placeholder="Amount to top up" required>
            <button type="submit" class="kdh-btn">Top Up with Paystack</button>
        </form>
    </div>

    <!-- Purchase Data -->
    <div class="kdh-card">
        <h2>Purchase Data</h2>
        <form id="kdh-purchase-form">
            <?php
            // A simple dropdown for products. A real implementation would be more complex.
            $products = wc_get_products(['limit' => -1, 'type' => 'data_bundle']);
            if ( ! empty( $products ) ) {
                echo '<select name="product_id">';
                foreach ( $products as $product ) {
                    echo '<option value="' . esc_attr( $product->get_id() ) . '">' . esc_html( $product->get_name() ) . ' - ' . esc_html( wc_price( $product->get_price() ) ) . '</option>';
                }
                echo '</select>';
            }
            ?>
            <input type="text" name="phone_number" placeholder="Phone Number" required>
            <select name="payment_method">
                <option value="wallet">Pay with Wallet</option>
                <option value="paystack">Pay with Paystack</option>
            </select>
            <button type="submit" class="kdh-btn">Purchase</button>
        </form>
    </div>

    <!-- Transaction History -->
    <div class="kdh-card">
        <h2>Transaction History</h2>
        <?php if ( ! empty( $transaction_history ) ) : ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $transaction_history as $transaction ) : ?>
                        <tr>
                            <td><?php echo esc_html( $transaction->transaction_date ); ?></td>
                            <td><?php echo esc_html( $transaction->type ); ?></td>
                            <td><?php echo esc_html( wc_price( $transaction->amount ) ); ?></td>
                            <td><?php echo esc_html( $transaction->description ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No transactions yet.</p>
        <?php endif; ?>
    </div>

</div>
