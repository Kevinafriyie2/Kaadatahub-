<div class="wrap kdh-admin-wrap">
    <h1><?php esc_html_e( 'Withdrawal Requests', 'kaadatahub' ); ?></h1>

    <div class="kdh-admin-card">
        <h2><?php esc_html_e( 'Pending Requests', 'kaadatahub' ); ?></h2>
        <?php
        $withdrawals = KDH_Reseller::get_pending_withdrawals();
        if ( ! empty( $withdrawals ) ) :
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Amount</th>
                        <th>Request Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $withdrawals as $withdrawal ) :
                        $user = get_userdata($withdrawal->user_id);
                    ?>
                        <tr>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html(wc_price($withdrawal->amount)); ?></td>
                            <td><?php echo esc_html($withdrawal->request_date); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['action' => 'approve_withdrawal', 'withdrawal_id' => $withdrawal->id] ), 'kdh_approve_withdrawal' ) ); ?>" class="button button-primary">Approve</a>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['action' => 'deny_withdrawal', 'withdrawal_id' => $withdrawal->id] ), 'kdh_deny_withdrawal' ) ); ?>" class="button button-secondary">Deny</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No pending withdrawal requests.', 'kaadatahub' ); ?></p>
        <?php endif; ?>
    </div>
</div>
