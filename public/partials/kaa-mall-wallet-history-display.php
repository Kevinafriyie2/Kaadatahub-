<div class="kaa-mall-wallet-history-content">
    <div class="info-card">
        <div class="card-icon">
            <i class="fas fa-history"></i>
        </div>
        <div class="card-content">
            <h3>Wallet Transaction History</h3>
            <p>View your recent wallet activity.</p>
        </div>
    </div>

    <div class="transaction-history-card">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount (GH₵)</th>
                    <th>Description</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $user_id = get_current_user_id();
                $transactions = Kaa_Mall_Wallet::get_wallet_transactions($user_id);

                if (empty($transactions)) : ?>
                    <tr>
                        <td colspan="5">You have no transactions yet.</td>
                    </tr>
                <?php else :
                    foreach ($transactions as $transaction) : ?>
                        <tr class="transaction-<?php echo esc_attr($transaction->type); ?>">
                            <td><?php echo esc_html(date('F j, Y, g:i a', strtotime($transaction->transaction_date))); ?></td>
                            <td><?php echo esc_html(ucfirst($transaction->type)); ?></td>
                            <td><?php echo esc_html(number_format($transaction->amount, 2)); ?></td>
                            <td><?php echo esc_html($transaction->description); ?></td>
                            <td><?php echo esc_html($transaction->reference_id ? 'Order #' . $transaction->reference_id : 'N/A'); ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>
