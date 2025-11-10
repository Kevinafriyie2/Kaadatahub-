<div class="kaa-mall-my-shop-content">
    <h2>My Shop</h2>

    <div class="info-card green-card">
        <div class="card-content">
            <p class="wallet-label">Profit Wallet Balance</p>
            <h2 class="wallet-amount">GHS <?php echo number_format($profit_balance, 2); ?></h2>
        </div>
    </div>

    <div class="reseller-settings">
        <h3>Shop Settings</h3>
        <form id="reseller-shop-name-form">
            <div class="form-group">
                <label for="shop_name">Your Shop Name</label>
                <input type="text" name="shop_name" id="shop_name" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'kaa_mall_shop_name', true)); ?>">
                <p class="description">Use only letters, numbers, and dashes.</p>
            </div>
            <button type="submit" class="button">Save Shop Name</button>
        </form>

        <div class="reseller-link">
            <h3>Your Shop Link</h3>
            <p>Share this link with your customers:</p>
            <input type="text" value="<?php echo esc_url(home_url('/store/' . get_user_meta(get_current_user_id(), 'kaa_mall_shop_name', true))); ?>" readonly>
        </div>
    </div>

    <form id="reseller-prices-form">
        <h3>Set Your Prices</h3>
        <table class="reseller-prices-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Base Price</th>
                    <th>Your Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $network => $product_list) : ?>
                    <?php foreach ($product_list as $product) : ?>
                        <tr>
                            <td><?php echo esc_html($product->get_name()); ?></td>
                            <td><?php echo wc_price($product->get_price()); ?></td>
                            <td>
                                <input type="number" step="0.01" min="<?php echo esc_attr($product->get_price()); ?>" name="prices[<?php echo esc_attr($product->get_id()); ?>]" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), '_reseller_price_' . $product->get_id(), true)); ?>" placeholder="<?php echo esc_attr($product->get_price()); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="button">Save Prices</button>
    </form>
</div>
