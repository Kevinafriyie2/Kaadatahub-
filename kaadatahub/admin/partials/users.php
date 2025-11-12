<div class="wrap kdh-admin-wrap">
    <h1><?php esc_html_e( 'Users & Resellers', 'kaadatahub' ); ?></h1>

    <div class="kdh-admin-card">
        <h2><?php esc_html_e( 'All Users', 'kaadatahub' ); ?></h2>
        <?php
        $users = get_users();
        if ( ! empty( $users ) ) :
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Wallet Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users as $user ) : ?>
                        <tr>
                            <td><?php echo esc_html( $user->user_login ); ?></td>
                            <td><?php echo esc_html( $user->user_email ); ?></td>
                            <td><?php echo esc_html( wc_price( KDH_Wallet::get_wallet_balance( $user->ID ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['action' => 'impersonate', 'user_id' => $user->ID] ), 'kdh_impersonate' ) ); ?>" class="button">Impersonate</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No users found.', 'kaadatahub' ); ?></p>
        <?php endif; ?>
    </div>

    <div class="kdh-admin-card">
        <h2><?php esc_html_e( 'Pending Reseller Applications', 'kaadatahub' ); ?></h2>
        <?php
        $pending_resellers = KDH_Reseller::get_pending_reseller_applications();
        if ( ! empty( $pending_resellers ) ) :
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $pending_resellers as $reseller ) :
                        $user = get_userdata($reseller->user_id);
                    ?>
                        <tr>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['action' => 'approve_reseller', 'user_id' => $user->ID] ), 'kdh_approve_reseller' ) ); ?>" class="button button-primary">Approve</a>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( ['action' => 'deny_reseller', 'user_id' => $user->ID] ), 'kdh_deny_reseller' ) ); ?>" class="button button-secondary">Deny</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No pending applications.', 'kaadatahub' ); ?></p>
        <?php endif; ?>
    </div>

</div>
