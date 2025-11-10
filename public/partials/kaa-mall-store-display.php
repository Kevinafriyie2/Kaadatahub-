<?php
/**
 * The store display for the Kaa Mall plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Kaa_Mall
 * @subpackage Kaa_Mall/public/partials
 */

// We need to manually query the products and reseller ID
$public = new Kaa_Mall_Public('kaa-mall', '1.0.0');
$products = $public->get_data_bundle_products();
$reseller_id = get_query_var('reseller_id');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . '../css/kaa-mall-store.css'; ?>">
</head>
<body <?php body_class(); ?>>

<div id="kaa-mall-store-wrapper">
    <div class="store-header">
        <h1>Welcome to <?php echo esc_html(get_user_by('id', $reseller_id)->display_name); ?>'s Shop</h1>
    </div>

    <div class="data-bundle-card">
        <div class="network-tabs">
            <?php $first = true; foreach ( $products as $network => $product_list ) : if ( empty( $product_list ) ) continue; ?>
                <button class="tab-link <?php if ($first) { echo 'active'; $first = false; } ?>" data-network="<?php echo esc_attr($network); ?>"><?php echo esc_html(ucfirst($network)); ?></button>
            <?php endforeach; ?>
        </div>

        <?php $first = true; foreach ( $products as $network => $product_list ) : if ( empty( $product_list ) ) continue; ?>
            <div class="data-bundle-form <?php if ($first) { echo 'active'; $first = false; } ?>" id="form-<?php echo esc_attr($network); ?>">
                <h4><i class="fas fa-signal"></i> <?php echo esc_html(ucfirst($network)); ?> Data Bundles</h4>
                <div class="form-group">
                    <label for="phone-number-<?php echo esc_attr($network); ?>"><?php echo esc_html(ucfirst($network)); ?> Phone Number</label>
                    <input type="text" id="phone-number-<?php echo esc_attr($network); ?>" placeholder="Enter phone number">
                </div>
                <div class="form-group">
                    <label for="bundle-select-<?php echo esc_attr($network); ?>">Select Bundle</label>
                    <select id="bundle-select-<?php echo esc_attr($network); ?>">
                        <option value="">— Choose Bundle —</option>
                        <?php foreach ( $product_list as $product ) :
                            $reseller_price = get_user_meta($reseller_id, '_reseller_price_' . $product->get_id(), true);
                            $price = $reseller_price ? $reseller_price : $product->get_price();
                        ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html($product->get_name() . ' - ' . wc_price($price)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="payment-options">
                        <label><input type="radio" name="payment-method-<?php echo esc_attr($network); ?>" value="paystack" checked> Paystack</label>
                    </div>
                </div>
                <button class="direct-purchase-btn" data-network="<?php echo esc_attr($network); ?>"><i class="fas fa-rocket"></i> Purchase</button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
