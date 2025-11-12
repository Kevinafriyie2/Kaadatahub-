<div class="wrap kdh-admin-wrap">
    <h1><?php esc_html_e( 'Kaadatahub Dashboard', 'kaadatahub' ); ?></h1>

    <div class="kdh-admin-card">
        <h2><?php esc_html_e( 'Overview', 'kaadatahub' ); ?></h2>
        <?php
        // In a real implementation, these would be dynamic
        $total_users = count_users()['total_users'];
        $total_resellers = count(KDH_Reseller::get_all_resellers());
        ?>
        <ul>
            <li><strong>Total Users:</strong> <?php echo esc_html( $total_users ); ?></li>
            <li><strong>Total Resellers:</strong> <?php echo esc_html( $total_resellers ); ?></li>
            <!-- Add more stats here -->
        </ul>
    </div>

    <div class="kdh-admin-card">
        <h2><?php esc_html_e( 'Pending Reseller Applications', 'kaadatahub' ); ?></h2>
        <?php
        $pending_resellers = KDH_Reseller::get_pending_reseller_applications();
        if ( ! empty( $pending_resellers ) ) :
        ?>
            <ul>
                <?php foreach ( $pending_resellers as $reseller ) :
                    $user = get_userdata($reseller->user_id);
                ?>
                    <li><?php echo esc_html($user->user_login); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p><?php esc_html_e( 'No pending applications.', 'kaadatahub' ); ?></p>
        <?php endif; ?>
    </div>

</div>
