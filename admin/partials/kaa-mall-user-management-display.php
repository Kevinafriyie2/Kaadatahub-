<div class="wrap">
    <h1>User Management</h1>

    <form method="get">
        <input type="hidden" name="page" value="kaa-mall-user-management">
        <?php
        $search = isset($_GET['s']) ? $_GET['s'] : '';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="user-search-input">Search Users:</label>';
        echo '<input type="search" id="user-search-input" name="s" value="' . esc_attr($search) . '">';
        echo '<input type="submit" id="search-submit" class="button" value="Search Users">';
        echo '</p>';
        ?>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Main Wallet Balance</th>
                <th>Profit Wallet Balance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $args = array();
            if ($search) {
                $args['search'] = '*' . $search . '*';
            }
            $users = get_users($args);
            foreach ($users as $user) {
                $user_id = $user->ID;
                $main_wallet_balance = Kaa_Mall_Wallet::get_wallet_balance($user_id);
                $profit_wallet_balance = Kaa_Mall_Profit_Wallet::get_profit_wallet_balance($user_id);
                echo '<tr>';
                echo '<td>' . esc_html($user->user_login) . '</td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . wc_price($main_wallet_balance) . '</td>';
                echo '<td>' . wc_price($profit_wallet_balance) . '</td>';
                echo '<td><button class="button adjust-wallet-btn" data-user-id="' . $user_id . '">Adjust Wallet</button></td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>

    <div id="adjust-wallet-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Adjust Wallet</h2>
            <form id="adjust-wallet-form">
                <input type="hidden" id="adjust-user-id" name="user_id">
                <p>
                    <label for="wallet-type">Wallet Type:</label>
                    <select id="wallet-type" name="wallet_type">
                        <option value="main">Main Wallet</option>
                        <option value="profit">Profit Wallet</option>
                    </select>
                </p>
                <p>
                    <label for="adjustment-amount">Amount:</label>
                    <input type="number" step="0.01" id="adjustment-amount" name="amount" required>
                </p>
                <p>
                    <button type="submit" class="button button-primary">Apply Adjustment</button>
                </p>
            </form>
        </div>
    </div>
</div>
