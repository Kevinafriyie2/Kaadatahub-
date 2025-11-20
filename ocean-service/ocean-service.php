/* Plugin Name: KAA Mall — Ocean Bundle & Wallet System Description: Full bundle purchase + single wallet + Paystack top-up + webhooks + admin dashboards + per-agent pricing + order history + agent registration. Version: 1.0.6 Author: Kevin Afriyie */
if (!defined('ABSPATH')) exit;
/* --------------------------- Basic helpers & constants --------------------------- */ if (!defined('KAAMALL_OSC')) define('KAAMALL_OSC', true);
function osc_log_admin($msg){ if(current_user_can('manage_options')) error_log('[KAA-OSC] '.$msg); }
function osc_json_exit($arr){ wp_send_json($arr); exit; }
/* --------------------------- Roles --------------------------- */ add_action('init', function(){ // Remove unwanted custom roles but keep WooCommerce defaults $unwanted_roles = array('subscriber', 'contributor', 'author', 'editor');
foreach($unwanted_roles as $role) { if(get_role($role)) { remove_role($role); } }
// Ensure our custom roles exist with the exact names you want if(!get_role('ocean_service_agent')) { add_role('ocean_service_agent', 'Ocean Service Agent', array('read'=>true)); }
if(!get_role('ocean_service_customer')) { add_role('ocean_service_customer', 'Ocean Service Customer', array('read'=>true)); }
// Remove any other custom ocean roles that might conflict $conflicting_roles = array('osc_agent', 'premium_agent', 'ocean_agent', 'ocean_customer', 'agent'); foreach($conflicting_roles as $role) { if(get_role($role)) { remove_role($role); } } });
/* --------------------------- Force WooCommerce Currency to GHS --------------------------- */ add_filter('woocommerce_currency', function($currency) { return 'GHS'; });
// Set default role to Ocean Service Customer add_filter('pre_option_default_role', function($default_role){ return 'ocean_service_customer'; });
/* --------------------------- User Role Management Helper --------------------------- */ function osc_get_available_roles() { $roles = array( 'ocean_service_customer' => 'Ocean Service Customer', 'ocean_service_agent' => 'Ocean Service Agent', 'customer' => 'Customer' // WooCommerce default );
// Include other WooCommerce roles if they exist if(get_role('subscriber')) $roles['subscriber'] = 'Subscriber'; if(get_role('administrator')) $roles['administrator'] = 'Administrator'; if(get_role('shop_manager')) $roles['shop_manager'] = 'Shop Manager';
return $roles; }
// Add role column to users list add_filter('manage_users_columns', function($columns) { $columns['osc_role'] = 'Ocean Role'; return $columns; });
add_filter('manage_users_custom_column', function($value, $column_name, $user_id) { if($column_name === 'osc_role') { $user = get_userdata($user_id); $roles = $user->roles;
if(in_array('ocean_service_agent', $roles)) { return 'Ocean Service Agent'; } elseif(in_array('ocean_service_customer', $roles)) { return 'Ocean Service Customer'; } elseif(in_array('customer', $roles)) { return 'Customer'; } else { return implode(', ', $roles); } } return $value; }, 10, 3);
/* --------------------------- Activation — set defaults --------------------------- */ register_activation_hook(FILE, function(){ if(!get_option('osc_network_products')) update_option('osc_network_products', array( 'MTN'=>[], 'Telecel'=>[], 'Airteltigo'=>[], 'Airteltigo iShare'=>[], 'Bigtime'=>[], 'AFA Registration'=>[], 'Call Minutes'=>[], 'Result Checkers'=>[] )); if(!get_option('osc_wallet_tx_logs')) update_option('osc_wallet_tx_logs', array()); if(!get_option('osc_purchase_logs')) update_option('osc_purchase_logs', array()); if(!get_option('osc_api_error_logs')) update_option('osc_api_error_logs', array()); if(!get_option('osc_role_pricing')) update_option('osc_role_pricing', array()); if(!get_option('osc_order_status_default')) update_option('osc_order_status_default', 'processing'); });
/* --------------------------- Admin Menu & Pages --------------------------- */ add_action('admin_menu', function(){ add_menu_page('Ocean Service','Ocean Service','manage_options','osc-settings','osc_admin_settings_page','dashicons-admin-generic',56); add_submenu_page('osc-settings','Network Products','Network Products','manage_options','osc-network-products','osc_network_products_page'); add_submenu_page('osc-settings','Wallet Management','Wallet','manage_options','osc-wallet','osc_wallet_admin_page'); add_submenu_page('osc-settings','Wallet Top-up','Top-up','manage_options','osc-wallet-topup','osc_wallet_topup_admin_page'); add_submenu_page('osc-settings','Order History','Order History','manage_options','osc-orders','osc_orders_admin_page'); add_submenu_page('osc-settings','Purchases Log','Purchases','manage_options','osc-purchases','osc_purchases_admin_page'); add_submenu_page('osc-settings','API Error Logs','API Errors','manage_options','osc-api-errors','osc_api_errors_page'); add_submenu_page('osc-settings','System Status','System Status','manage_options','osc_system_status_page'); add_submenu_page('osc-settings','Role Pricing','Role Pricing','manage_options','osc-role-pricing','osc_role_pricing_page'); add_submenu_page('osc-settings','Agent Registration','Agent Registration','manage_options','osc-agent-registration','osc_agent_registration_page'); });
add_action('admin_enqueue_scripts', function($hook) { global $pagenow; $page = $_GET['page'] ?? '';
if ($pagenow === 'admin.php' && ($page === 'osc-network-products' || $page === 'afa-registration' || $page === 'osc-role-pricing')) {
    wp_enqueue_style('woocommerce_admin_styles');
    wp_enqueue_script('wc-enhanced-select');
}

});
/* --------------------------- System Status --------------------------- */ function osc_system_status_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized'); $wc = class_exists('WooCommerce') ? 'Yes' : 'No'; $pay = get_option('osc_paystack_secret') ? 'Yes' : 'No'; $webhook = get_option('osc_notification_webhook',''); $default_status = get_option('osc_order_status_default', 'processing'); $networks = array_keys(get_option('osc_network_products', [])); $agents = count(get_users(array('role'=>'ocean_service_agent'))); $customers = count(get_users(array('role'=>'ocean_service_customer'))); $wc_customers = count(get_users(array('role'=>'customer'))); $wallet_logs = count(get_option('osc_wallet_tx_logs',[])); ?>
Ocean Service — System Status
SettingValueWordPress VersionWooCommerce ActivePaystack ConfiguredNotification WebhookDefault Order StatusOcean Service AgentsOcean Service CustomersWooCommerce CustomersWallet tx logs
/* --------------------------- Settings Page (Paystack, Webhook, Commission, Order Status) --------------------------- */ function osc_admin_settings_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized');

if($_POST && check_admin_referer('osc_settings_save','osc_settings_nonce')){ update_option('osc_company_name', sanitize_text_field($_POST['osc_company_name'] ?? 'Ocean Service')); update_option('osc_agent_fee', floatval($_POST['osc_agent_fee'] ?? 0)); update_option('osc_paystack_public', sanitize_text_field($_POST['osc_paystack_public'] ?? '')); update_option('osc_paystack_secret', sanitize_text_field($_POST['osc_paystack_secret'] ?? '')); update_option('osc_notification_webhook', esc_url_raw($_POST['osc_notification_webhook'] ?? '')); update_option('osc_commission_percent', floatval($_POST['osc_commission_percent'] ?? 5)); update_option('osc_order_status_default', sanitize_text_field($_POST['osc_order_status_default'] ?? 'processing')); echo '
Settings saved.
'; }

$company = get_option('osc_company_name','Ocean Service'); $agent_fee = get_option('osc_agent_fee', 10); $pub = get_option('osc_paystack_public',''); $sec = get_option('osc_paystack_secret',''); $webhook = get_option('osc_notification_webhook',''); $commission = get_option('osc_commission_percent',5); $default_status = get_option('osc_order_status_default', 'processing'); ?>
— Settings
/* Test webhook AJAX */ add_action('wp_ajax_osc_admin_test_webhook','osc_admin_test_webhook'); add_action('wp_ajax_osc_add_network', 'osc_ajax_add_network'); add_action('wp_ajax_osc_delete_network', 'osc_ajax_delete_network'); add_action('wp_ajax_osc_save_network_products', 'osc_ajax_save_network_products');

function osc_ajax_save_network_products() { if (!current_user_can('manage_options') || !check_ajax_referer('osc_network_nonce', 'nonce', false)) { wp_send_json_error('Unauthorized action.'); }
$network_products_data = isset($_POST['network_products']) ? $_POST['network_products'] : [];
$sanitized_data = [];

// Sanitize the incoming data
foreach ($network_products_data as $network => $product_ids) {
    $sanitized_network = sanitize_text_field(stripslashes($network));
    $sanitized_ids = is_array($product_ids) ? array_map('intval', $product_ids) : [];
    $sanitized_data[$sanitized_network] = $sanitized_ids;
}

update_option('osc_network_products', $sanitized_data);
wp_send_json_success('Product assignments saved.');

}
function osc_ajax_add_network() { if (!current_user_can('manage_options') || !check_ajax_referer('osc_network_nonce', 'nonce', false)) { wp_send_json_error('Unauthorized'); } $new_network_name = sanitize_text_field($_POST['network_name']); if (empty($new_network_name)) { wp_send_json_error('Network name cannot be empty.'); } $network_products = get_option('osc_network_products', []); if (isset($network_products[$new_network_name])) { wp_send_json_error('Network already exists.'); } $network_products[$new_network_name] = []; update_option('osc_network_products', $network_products); wp_send_json_success('Network added.'); }
function osc_ajax_delete_network() { if (!current_user_can('manage_options') || !check_ajax_referer('osc_network_nonce', 'nonce', false)) { wp_send_json_error('Unauthorized'); } $network_to_delete = sanitize_text_field($_POST['network_name']); $network_products = get_option('osc_network_products', []); if (isset($network_products[$network_to_delete])) { unset($network_products[$network_to_delete]); update_option('osc_network_products', $network_products); wp_send_json_success('Network deleted.'); } else { wp_send_json_error('Network not found.'); } }
function osc_admin_test_webhook(){ if(!current_user_can('manage_options')) osc_json_exit(['success'=>false,'message'=>'Unauthorized']); check_ajax_referer('osc_webhook_test','nonce');
$url = get_option('osc_notification_webhook',''); if(empty($url)) osc_json_exit(['success'=>false,'message'=>'Webhook not configured']);
$payload = array('event'=>'test','time'=>current_time('mysql'),'message'=>'Ocean Service test webhook'); $resp = wp_remote_post($url, array('body'=>wp_json_encode($payload),'headers'=>array('Content-Type'=>'application/json'),'timeout'=>15));
if(is_wp_error($resp)){ $err = $resp->get_error_message(); osc_api_log('webhook_test_error', $err, $payload); osc_json_exit(['success'=>false,'message'=>$err]); }
$code = wp_remote_retrieve_response_code($resp); $body = wp_remote_retrieve_body($resp); osc_api_log('webhook_test_response', 'HTTP '.$code.' Body: '.$body, $payload); osc_json_exit(['success'=>true,'message'=>'Sent, response code '.$code]); }
/* --------------------------- Network Products assignment --------------------------- */ function osc_network_products_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized'); $network_products = get_option('osc_network_products', []); ?>
Network Products Assignment
<div class="card">
    <h2 class="title">Add New Network</h2>
    <form id="osc-add-network-form">
        <input type="text" id="new-network-name" placeholder="Enter new network name" required>
        <button type="submit" class="button button-primary">Add Network</button>
    </form>
</div>

<hr>

<div id="osc-network-products-form">
    <table class="form-table" id="osc-network-table">
        <tbody>
        <?php foreach($network_products as $network => $assigned_ids): ?>
        <tr data-network="<?php echo esc_attr($network); ?>">
            <th scope="row" style="width: 200px;">
                <?php echo esc_html($network); ?>
                <br>
                <button class="button is-destructive delete-network" data-network="<?php echo esc_attr($network); ?>">Delete</button>
            </th>
            <td>
                <select name="osc_network_products[<?php echo esc_attr($network); ?>][]" multiple class="wc-product-search" style="width: 100%;" data-placeholder="Search for products…">
                    <?php foreach($assigned_ids as $pid): $p = wc_get_product($pid); if($p): ?>
                    <option value="<?php echo esc_attr($pid); ?>" selected="selected"><?php echo esc_html($p->get_formatted_name()); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button id="osc-save-products" class="button button-primary">Save Changes</button>
    <span class="spinner"></span>
</div>


intval($user_id), 'amount' => floatval($amount), 'type' => $type, 'note' => sanitize_text_field($note), 'time' => current_time('mysql') ); update_option('osc_wallet_tx_logs', $logs); } /* --------------------------- Purchase & API logs --------------------------- */ function osc_log_purchase($data){ $logs = get_option('osc_purchase_logs', []); $logs[] = array_merge($data, array('time'=>current_time('mysql'))); update_option('osc_purchase_logs', $logs); } function osc_api_log($type, $message, $payload=array()){ $logs = get_option('osc_api_error_logs', []); $logs[] = array('type'=>$type,'message'=>$message,'payload'=>$payload,'time'=>current_time('mysql')); update_option('osc_api_error_logs', $logs); } /* --------------------------- Admin Wallet page (manual credit/deduct) --------------------------- */ function osc_wallet_admin_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized'); if(isset($_POST['osc_admin_wallet_action']) && check_admin_referer('osc_admin_wallet','osc_admin_wallet_nonce')){ $identifier = sanitize_text_field($_POST['osc_admin_user']); $amount = floatval($_POST['osc_admin_amount']); $action = sanitize_text_field($_POST['osc_admin_action']); $user = null; if(is_email($identifier)) $user = get_user_by('email',$identifier); if(!$user){ $users = get_users(array('search'=>'*'.esc_sql($identifier).'*','search_columns'=>array('user_login','display_name','user_email'),'number'=>1)); if(!empty($users)) $user = $users[0]; } if(!$user){ echo '
User not found.
'; } else { if($action === 'credit'){ osc_credit_wallet($user->ID, $amount, 'Admin credit'); echo '
Credited GHS '.number_format($amount,2).' to '.esc_html($user->display_name).'
'; } else { $ok = osc_deduct_wallet($user->ID, $amount, 'Admin deduction'); if($ok) echo '
Deducted GHS '.number_format($amount,2).' from '.esc_html($user->display_name).'
'; else echo '
Insufficient balance.
'; } } } $users = get_users(array('orderby'=>'display_name','number'=>200)); ?>
Wallet Management
Quick Wallet Action
User Balances
ID); $roles = $u->roles; $role_display = ''; if(in_array('ocean_service_agent', $roles)) { $role_display = 'Ocean Service Agent'; } elseif(in_array('ocean_service_customer', $roles)) { $role_display = 'Ocean Service Customer'; } elseif(in_array('customer', $roles)) { $role_display = 'Customer'; } else { $role_display = implode(', ', $roles); } echo ''; } ?>
NameEmailBalanceRole'.esc_html($u->display_name).''.esc_html($u->user_email).''.number_format($bal,2).''.esc_html($role_display).'
/* --------------------------- New: Admin Wallet Top-up with User Selection --------------------------- */ function osc_wallet_topup_admin_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_admin_topup_action']) && check_admin_referer('osc_admin_topup','osc_admin_topup_nonce')){ $identifier = sanitize_text_field($_POST['osc_admin_user']); $amount = floatval($_POST['osc_admin_amount']); $note = sanitize_text_field($_POST['osc_admin_note'] ?? 'Admin top-up');
$user = null; if(is_email($identifier)) { $user = get_user_by('email',$identifier); } else { // Search by username or display name $users = get_users(array( 'search'=>''.esc_sql($identifier).'', 'search_columns'=>array('user_login','display_name','user_email'), 'number'=>1 )); if(!empty($users)) $user = $users[0]; }
if(!$user){ echo '
User not found.
'; } else { // Credit the wallet osc_credit_wallet($user->ID, $amount, $note); echo '
Successfully topped up GHS '.number_format($amount,2).' to '.esc_html($user->display_name).'
'; } }

// Get recent users for quick selection $recent_users = get_users(array('orderby'=>'registered','order'=>'DESC','number'=>10)); ?>
Wallet Top-up
Top-up User Wallet
Recent Users
ID); $roles = $u->roles; $role_display = ''; if(in_array('ocean_service_agent', $roles)) { $role_display = 'Ocean Service Agent'; } elseif(in_array('ocean_service_customer', $roles)) { $role_display = 'Ocean Service Customer'; } elseif(in_array('customer', $roles)) { $role_display = 'Customer'; } else { $role_display = implode(', ', $roles); } echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; } ?>
NameEmailBalanceRoleActions'.esc_html($u->display_name).''.esc_html($u->user_email).''.number_format($bal,2).''.esc_html($role_display).'

strtotime($to . ' 23:59:59')) continue; $results[] = $l; } if($export){ $filename = 'ocean_orders_'.date('YmdHis').'.csv'; header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename='.$filename); $out = fopen('php://output','w'); fputcsv($out, ['Time','User ID','User Name','Email','Network','Product ID','Product Name','Amount','Commission','Status','Phone']); foreach($results as $r){ fputcsv($out, [ $r['time'], $r['user_id'] ?? '', $r['user_name'] ?? '', $r['user_email'] ?? '', $r['network'] ?? '', $r['product_id'] ?? '', $r['product_name'] ?? '', $r['amount'] ?? '', $r['commission'] ?? '', $r['status'] ?? '', ($r['meta']['phone'] ?? '') ]); } exit; } $networks = array_keys(get_option('osc_network_products', array( 'MTN'=>[], 'Telecel'=>[], 'Airteltigo'=>[], 'Airteltigo iShare'=>[], 'Bigtime'=>[], 'AFA Registration'=>[], 'Call Minutes'=>[], 'Result Checkers'=>[] ))); $status_options = array('pending', 'processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed', 'topup'); ?>
Order History
'; } else { foreach(array_reverse($results) as $r){ echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; } } ?>
TimeUserEmailNetworkProductAmountCommissionStatusPhoneNo orders'.esc_html($r['time']).''.esc_html($r['user_name'] ?? '').''.esc_html($r['user_email'] ?? '').''.esc_html($r['network'] ?? '').''.esc_html(($r['product_name'] ?? '') . ' (ID:' . ($r['product_id'] ?? '') . ')').'GHS '.number_format($r['amount'] ?? 0,2).'GHS '.number_format($r['commission'] ?? 0,2).''.esc_html($r['status'] ?? '').''.esc_html($r['meta']['phone'] ?? '').'
/* --------------------------- New: Agent Registration Page --------------------------- */ function osc_agent_registration_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_agent_registration_submit']) && check_admin_referer('osc_agent_registration','osc_agent_registration_nonce')){ $first_name = sanitize_text_field($_POST['osc_first_name'] ?? ''); $last_name = sanitize_text_field($_POST['osc_last_name'] ?? ''); $email = sanitize_email($_POST['osc_email'] ?? ''); $username = sanitize_user($_POST['osc_username'] ?? ''); $password = $_POST['osc_password'] ?? ''; $confirm_password = $_POST['osc_confirm_password'] ?? '';
// Validate inputs if(empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) { echo '
All fields are required.
'; } elseif($password !== $confirm_password) { echo '
Passwords do not match.
'; } elseif(username_exists($username)) { echo '
Username already exists.
'; } elseif(email_exists($email)) { echo '
Email already registered.
'; } else { // Create new user with ocean_service_customer role first (this will be the default) $user_id = wp_insert_user(array( 'user_login' => $username, 'user_pass' => $password, 'user_email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'role' => 'ocean_service_customer' // Start as ocean service customer ));

if(is_wp_error($user_id)) { echo '
' . $user_id->get_error_message() . '
'; } else { // Now upgrade to ocean service agent role $user = get_userdata($user_id); $user->remove_role('ocean_service_customer'); $user->add_role('ocean_service_agent');

// Add welcome message to wallet osc_credit_wallet($user_id, get_option('osc_agent_fee', 10), 'Welcome bonus for agent registration');
echo '
Agent created successfully! Username: ' . esc_html($username) . '
'; } } } ?>

Agent Registration
Create a new agent account with automatic role assignment.
/* --------------------------- AJAX: Search Users for Top-up --------------------------- */ add_action('wp_ajax_osc_search_users', 'osc_ajax_search_users'); function osc_ajax_search_users(){ if(!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); exit; }

$query = sanitize_text_field($_GET['query'] ?? ''); if(empty($query)) { wp_send_json_success([]); exit; }
$users = get_users(array( 'search' => '' . esc_sql($query) . '', 'search_columns' => array('user_login', 'display_name', 'user_email'), 'number' => 10 ));
$results = array(); foreach($users as $user) { $results[] = array( 'id' => $user->ID, 'email' => $user->user_email, 'username' => $user->user_login, 'display_name' => $user->display_name ); }
wp_send_json_success($results); }
/* --------------------------- Purchases admin page (filters + CSV) --------------------------- */ function osc_purchases_admin_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized');
$filter_user = sanitize_text_field($_GET['filter_user'] ?? ''); $filter_net = sanitize_text_field($_GET['filter_network'] ?? ''); $from = sanitize_text_field($_GET['from'] ?? ''); $to = sanitize_text_field($_GET['to'] ?? ''); $export = isset($_GET['export']) ? true : false;
$logs = get_option('osc_purchase_logs', []); $results = [];
foreach($logs as $l){ if (!isset($l['time'])) { continue; } if($filter_user && stripos($l['user_email'] ?? '', $filter_user) === false && stripos($l['user_name'] ?? '', $filter_user) === false) continue; if($filter_net && ($l['network'] ?? '') !== $filter_net) continue; if($from && strtotime($l['time']) < strtotime($from)) continue; if($to && strtotime($l['time']) > strtotime($to . ' 23:59:59')) continue; $results[] = $l; }
if($export){ $filename = 'ocean_purchases_'.date('YmdHis').'.csv'; header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename='.$filename); $out = fopen('php://output','w'); fputcsv($out, ['Time','User','Email','Network','Product ID','Product Name','Amount','Commission','Status','Meta']); foreach($results as $r){ fputcsv($out, [$r['time'],$r['user_name'] ?? '',$r['user_email'] ?? '',$r['network'] ?? '',$r['product_id'] ?? '',$r['product_name'] ?? '',$r['amount'] ?? '',$r['commission'] ?? '',$r['status'] ?? '', json_encode($r['meta'] ?? [])]); } exit; }
$networks = array_keys(get_option('osc_network_products', array( 'MTN'=>[], 'Telecel'=>[], 'Airteltigo'=>[], 'Airteltigo iShare'=>[], 'Bigtime'=>[], 'AFA Registration'=>[], 'Call Minutes'=>[], 'Result Checkers'=>[] ))); ?>
Purchases Log
'; } else { foreach(array_reverse($results) as $r){ echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; echo ''; } } ?>
TimeUserEmailNetworkProductAmountCommissionStatusNo purchases'.esc_html($r['time']).''.esc_html($r['user_name'] ?? '').''.esc_html($r['user_email'] ?? '').''.esc_html($r['network'] ?? '').''.esc_html(($r['product_name'] ?? '') . ' (ID:' . ($r['product_id'] ?? '') . ')').'GHS '.number_format($r['amount'] ?? 0,2).'GHS '.number_format($r['commission'] ?? 0,2).''.esc_html($r['status'] ?? '').'
/* --------------------------- API Error logs admin page --------------------------- */ function osc_api_errors_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized'); $logs = array_reverse(get_option('osc_api_error_logs', [])); ?>

API / Webhook Error Logs
'; } else { foreach($logs as $l){ echo ''; } } ?>
TimeTypeMessagePayloadNo logs'.esc_html($l['time']).''.esc_html($l['type']).'
'.esc_html($l['message']).'
'.esc_html(json_encode($l['payload'])).'
/* --------------------------- Role Pricing Page --------------------------- */ function osc_role_pricing_page(){ if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_role_pricing_save']) && check_admin_referer('osc_role_pricing_save','osc_role_pricing_nonce')){ $role_pricing = array(); if(isset($_POST['osc_role_pricing']) && is_array($_POST['osc_role_pricing'])){ foreach($_POST['osc_role_pricing'] as $product_id => $prices){ // Only save if prices are different from original $product = wc_get_product($product_id); $original_price = $product ? floatval($product->get_price()) : 0;
$agent_price = !empty($prices['agent']) ? floatval($prices['agent']) : $original_price; $customer_price = !empty($prices['customer']) ? floatval($prices['customer']) : $original_price;
$role_pricing[$product_id] = array( 'agent' => $agent_price, 'customer' => $customer_price ); } } update_option('osc_role_pricing', $role_pricing); echo '
Role prices saved successfully.
'; }

$products = array(); if(class_exists('WC_Product')) $products = wc_get_products(array('limit'=>-1,'status'=>'publish')); $role_pricing = get_option('osc_role_pricing', array());
?>
Role-Based Pricing
Set different prices for agents and customers for each product. Leave blank to use original price.
/* --------------------------- Utility: get price for user with role-based pricing --------------------------- */ function osc_get_price_for_user($product_id, $user_id = 0){ $product = wc_get_product($product_id); if(!$product) return 0.00; $base_price = floatval($product->get_price());

// Get role pricing $role_pricing = get_option('osc_role_pricing', array());
// Determine user role $is_agent = false; $is_customer = false;
if($user_id && $user = get_userdata($user_id)){ $roles = (array)$user->roles; $is_agent = in_array('ocean_service_agent', $roles); $is_customer = in_array('ocean_service_customer', $roles) || in_array('customer', $roles) || (!in_array('ocean_service_agent', $roles) && count(array_intersect($roles, array('administrator', 'shop_manager'))) === 0); } else { // Not logged in - treat as customer $is_customer = true; }
// Calculate final price based on role if($is_agent && isset($role_pricing[$product_id]['agent'])) { return floatval($role_pricing[$product_id]['agent']); } elseif($is_customer && isset($role_pricing[$product_id]['customer'])) { return floatval($role_pricing[$product_id]['customer']); } else { // Fallback to base price return $base_price; } }
/* --------------------------- AJAX: get network products (respects per-user pricing) --------------------------- */ add_action('wp_ajax_osc_get_network_products','osc_ajax_get_network_products'); add_action('wp_ajax_nopriv_osc_get_network_products','osc_ajax_get_network_products'); function osc_ajax_get_network_products(){ $network = sanitize_text_field($_GET['network'] ?? ''); $mapping = get_option('osc_network_products', array( 'MTN'=>[], 'Telecel'=>[], 'Airteltigo'=>[], 'Airteltigo iShare'=>[], 'Bigtime'=>[], 'AFA Registration'=>[], 'Call Minutes'=>[], 'Result Checkers'=>[] )); $res = [];
if(isset($mapping[$network]) && is_array($mapping[$network])){ foreach($mapping[$network] as $pid){ $p = wc_get_product($pid); if(!$p) continue; $price = osc_get_price_for_user($pid, get_current_user_id()); $res[] = array('id'=>$pid,'name'=>$p->get_name(),'price'=>$price); } }
wp_send_json($res); }
/* --------------------------- AJAX: process purchase
Deduct wallet if method=walletIf method=paystack, create a pending purchase and return paystack checkout dataCreate Woo order (completed)Credit commission to agent (auto)Log purchaseSend external webhookReturn JSON status --------------------------- */ add_action('wp_ajax_osc_process_purchase','osc_process_purchase'); add_action('wp_ajax_nopriv_osc_process_purchase','osc_process_purchase'); function osc_process_purchase(){ $network = sanitize_text_field($_POST['network'] ?? ''); $product_id = intval($_POST['product'] ?? 0); $phone = sanitize_text_field($_POST['phone'] ?? ''); $method = sanitize_text_field($_POST['method'] ?? 'wallet'); $agent_phone = sanitize_text_field($_POST['agent_phone'] ?? '');
if(empty($network) || empty($product_id) || empty($phone)){ osc_json_exit(['status'=>'error','message'=>'Provide network, product and phone.']); }
$product = wc_get_product($product_id); if(!$product) osc_json_exit(['status'=>'error','message'=>'Product not found.']);
$user_id = is_user_logged_in() ? get_current_user_id() : 0; $price = osc_get_price_for_user($product_id, $user_id);
    } elseif($method === 'paystack'){
        $paystack_public = get_option('osc_paystack_public','');
        if(empty($paystack_public)) {
            osc_json_exit(['status'=>'error','message'=>'Paystack not configured.']);
        }

        $reference = 'osc_bundle_'.time().'_'.wp_rand(1000,9999);
        $user_data = get_userdata($user_id);
        $email = $user_id ? $user_data->user_email : 'guest@example.com';

        // Store purchase data in a transient for verification after payment
        $purchase_data = array(
            'user_id'      => $user_id,
            'network'      => $network,
            'product_id'   => $product_id,
            'phone'        => $phone,
            'agent_phone'  => $agent_phone,
            'price'        => $price,
        );
        set_transient('osc_bundle_' . $reference, $purchase_data, HOUR_IN_SECONDS);

        osc_json_exit([
            'status'          => 'paystack',
            'paystack_public' => $paystack_public,
            'amount'          => $price,
            'email'           => $email,
            'reference'       => $reference,
        ]);
        // Note: The script exits here for Paystack. Order creation happens in the verification step.
    }

    // This part below only runs for WALLET payments now.
    $order_id = 0;
    if(class_exists('WC_Order') && function_exists('wc_get_product')){
        try {
            $order = wc_create_order();
            $order->add_product(wc_get_product($product_id), 1);
if($user_id){ $user = wp_get_current_user(); $order->set_customer_id($user_id); // ✅ FIX: Set billing phone from user or provided phone if($user->billing_phone){ $order->set_billing_phone($user->billing_phone); } else { $order->set_billing_phone($phone); } $order->set_billing_email($user->user_email); $order->set_billing_first_name($user->first_name); $order->set_billing_last_name($user->last_name); } else { // ✅ FIX: For guest orders, set phone number $order->set_billing_phone($phone); $order->set_billing_email('guest@example.com'); $order->set_billing_first_name('Guest'); $order->set_billing_last_name('Customer'); }
$order->calculate_totals(); $default_status = get_option('osc_order_status_default', 'processing'); $order->update_status($default_status,'Order created by Ocean Service'); $order->save(); $order_id = $order->get_id();
// ✅ FIX: Save phone number to multiple meta fields for better compatibility $order->update_meta_data('_osc_beneficiary', $phone); $order->update_meta_data('_osc_network', $network); $order->update_meta_data('_bundle_phone_number', $phone); $order->update_meta_data('_customer_phone', $phone);
// ✅ FIX: Also save as regular order meta for easy access $order->update_meta_data('bundle_recipient_phone', $phone); $order->update_meta_data('bundle_network', $network); if (!empty($agent_phone)) { $order->update_meta_data('agent_phone', $agent_phone); }
$order->save();
} catch (Exception $e){ osc_api_log('order_create_error', $e->getMessage(), array('product'=>$product_id,'user'=>$user_id)); } } // Commission calculation & credit to agent (if purchaser is an agent) $commission_percent = floatval(get_option('osc_commission_percent', 5)); $commission_amount = round(($commission_percent/100) * $price, 2); if($user_id && in_array('ocean_service_agent', (array)wp_get_current_user()->roles)){ // credit commission to purchaser (agent) osc_credit_wallet($user_id, $commission_amount, 'Commission for sale'); }
// Log purchase $purchase = array( 'user_id' => $user_id, 'user_name' => $user_id ? wp_get_current_user()->display_name : '', 'user_email' => $user_id ? wp_get_current_user()->user_email : '', 'network' => $network, 'product_id' => $product_id, 'product_name' => $product->get_name(), 'amount' => $price, 'commission' => $commission_amount, 'order_id' => $order_id, 'status' => get_option('osc_order_status_default', 'processing'), 'meta' => array('phone'=>$phone) ); osc_log_purchase($purchase);
// External webhook $webhook = get_option('osc_notification_webhook',''); if($webhook){ $payload = array('event'=>'bundle_purchase','data'=>$purchase); $resp = wp_remote_post($webhook, array('body'=>wp_json_encode($payload),'headers'=>array('Content-Type'=>'application/json'),'timeout'=>20)); if(is_wp_error($resp)){ osc_api_log('webhook_error', $resp->get_error_message(), $payload); } else { $code = wp_remote_retrieve_response_code($resp); $body = wp_remote_retrieve_body($resp); osc_api_log('webhook_response', 'HTTP '.$code.' Body: '.$body, $payload); } }
osc_json_exit(['status'=>'success','message'=>'Bundle purchased successfully. Order ID: '.$order_id]); }

/* ---------------------------
AJAX: Verify Paystack Bundle Payment
- Verifies the payment with Paystack API
- Creates the WooCommerce order from transient data
--------------------------- */
add_action('wp_ajax_osc_verify_bundle_paystack_payment', 'osc_verify_bundle_paystack_payment');
add_action('wp_ajax_nopriv_osc_verify_bundle_paystack_payment', 'osc_verify_bundle_paystack_payment');
function osc_verify_bundle_paystack_payment() {
    // No nonce check here as the verification is the reference from Paystack
    $reference = sanitize_text_field($_POST['reference']);
    if (empty($reference)) {
        osc_json_exit(['status' => 'error', 'message' => 'Payment reference is missing.']);
    }

    // Retrieve purchase data from transient
    $purchase_data = get_transient('osc_bundle_' . $reference);
    if (false === $purchase_data) {
        osc_json_exit(['status' => 'error', 'message' => 'Purchase session expired or is invalid. Please try again.']);
    }

    // Verify transaction with Paystack
    $secret_key = get_option('osc_paystack_secret');
    $response = wp_remote_get("https://api.paystack.co/transaction/verify/{$reference}", [
        'headers' => ['Authorization' => 'Bearer ' . $secret_key]
    ]);

    if (is_wp_error($response)) {
        osc_api_log('paystack_verify_error', $response->get_error_message(), ['reference' => $reference]);
        osc_json_exit(['status' => 'error', 'message' => 'Could not verify payment. Please contact support.']);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!($body['status'] && $body['data']['status'] === 'success')) {
        osc_api_log('paystack_verify_failed', 'Payment verification failed.', ['reference' => $reference, 'response_body' => $body]);
        osc_json_exit(['status' => 'error', 'message' => 'Payment verification failed. Please contact support.']);
    }

    // Extract data from transient
    $user_id = $purchase_data['user_id'];
    $network = $purchase_data['network'];
    $product_id = $purchase_data['product_id'];
    $phone = $purchase_data['phone'];
    $agent_phone = $purchase_data['agent_phone'];
    $price = $purchase_data['price'];
    $product = wc_get_product($product_id);

    $order_id = 0;
    try {
        $order = wc_create_order();
        $order->add_product($product, 1);

        if ($user_id) {
            $user = get_userdata($user_id);
            $order->set_customer_id($user_id);
            $order->set_billing_phone($user->billing_phone ?: $phone);
            $order->set_billing_email($user->user_email);
            $order->set_billing_first_name($user->first_name);
            $order->set_billing_last_name($user->last_name);
        } else {
            $order->set_billing_phone($phone);
            $order->set_billing_email('guest@example.com'); // Paystack should provide an email, but have a fallback
            $order->set_billing_first_name('Guest');
            $order->set_billing_last_name('Customer');
        }

        $order->set_payment_method('paystack', 'Paystack');
        $order->calculate_totals();

        $order->update_meta_data('_osc_beneficiary', $phone);
        $order->update_meta_data('_osc_network', $network);
        $order->update_meta_data('_bundle_phone_number', $phone);
        $order->update_meta_data('bundle_recipient_phone', $phone);
        $order->update_meta_data('bundle_network', $network);
        if (!empty($agent_phone)) {
            $order->update_meta_data('agent_phone', $agent_phone);
        }

        $default_status = get_option('osc_order_status_default', 'processing');
        $order->update_status($default_status, 'Order paid via Paystack.', true);
        $order->save();
        $order_id = $order->get_id();

        // Commission calculation & credit to agent
        $commission_percent = floatval(get_option('osc_commission_percent', 5));
        $commission_amount = round(($commission_percent / 100) * $price, 2);
        if ($user_id && in_array('ocean_service_agent', (array)get_userdata($user_id)->roles)) {
            osc_credit_wallet($user_id, $commission_amount, 'Commission for sale');
        }

        // Log purchase
        $purchase_log_entry = array(
            'user_id' => $user_id,
            'user_name' => $user_id ? get_userdata($user_id)->display_name : 'Guest',
            'user_email' => $user_id ? get_userdata($user_id)->user_email : 'guest@example.com',
            'network' => $network,
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'amount' => $price,
            'commission' => $commission_amount,
            'order_id' => $order_id,
            'status' => $default_status,
            'meta' => array('phone' => $phone, 'paystack_ref' => $reference)
        );
        osc_log_purchase($purchase_log_entry);

        // External webhook
        $webhook = get_option('osc_notification_webhook','');
        if($webhook){
            $payload = array('event'=>'bundle_purchase','data'=>$purchase_log_entry);
            wp_remote_post($webhook, array('body'=>wp_json_encode($payload),'headers'=>array('Content-Type'=>'application/json'),'timeout'=>20));
        }

    } catch (Exception $e) {
        osc_api_log('bundle_paystack_order_error', $e->getMessage(), $purchase_data);
        osc_json_exit(['status' => 'error', 'message' => 'Could not create your order after payment. Please contact support with reference: ' . $reference]);
    }

    // Clean up the transient
    delete_transient('osc_bundle_' . $reference);

    osc_json_exit(['status' => 'success', 'message' => 'Bundle purchased successfully. Order ID: ' . $order_id]);
}

/* --------------------------- Paystack webhook endpoint (verify and credit wallet on topup)
listens for ?osc_paystack_webhook=1 --------------------------- */ add_action('init', function(){ if(isset($_GET['osc_paystack_webhook']) && $_GET['osc_paystack_webhook'] == '1'){ header('Content-Type: application/json'); $secret = get_option('osc_paystack_secret',''); $body = file_get_contents('php://input'); $sig = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : ''; // Optional verification - for now, we accept and decode $data = json_decode($body, true);
if(!$data){ echo json_encode(['status'=>false,'message'=>'Invalid payload']); exit; }
// Example structure check $event = $data['event'] ?? ''; if($event === 'charge.success' || ($data['event'] ?? '') === 'charge.success'){ // parse meta to get user_id and amount $meta = $data['data']['metadata'] ?? array(); $email = $data['data']['customer']['email'] ?? ''; $amount_ng = ($data['data']['amount'] ?? 0) / 100; // paystack uses kobo $ref = $data['data']['reference'] ?? ''; // find user by email $user = null; if($email) $user = get_user_by('email', $email); if($user){ // credit wallet osc_credit_wallet($user->ID, $amount_ng, 'Paystack topup ref '.$ref); // log purchase/topup osc_log_purchase(array( 'user_id'=>$user->ID,'user_name'=>$user->display_name,'user_email'=>$user->user_email, 'network'=>'topup','product_id'=>0,'product_name'=>'Top-up','amount'=>$amount_ng, 'commission'=>0,'order_id'=>0,'status'=>'topup','meta'=>array('reference'=>$ref) )); } else { osc_api_log('paystack_webhook_no_user','No user for email '.$email, $data); } }
echo json_encode(['status'=>true]); exit; } });
/* --------------------------- Modern Purchase Portal Shortcode (Updated Design) --------------------------- */
add_shortcode('ocean_service_portal','osc_portal_shortcode');
function osc_portal_shortcode(){
ob_start();
?>
<style>
    :root {
        --osc-primary-color: #0052cc;
        --osc-secondary-color: #f0f5ff;
        --osc-text-color: #333;
        --osc-border-color: #ddd;
        --osc-success-color: #008a00;
        --osc-font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    .osc-portal-container {
        font-family: var(--osc-font-family);
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 2.5rem;
        border-radius: 20px;
        max-width: 850px;
        margin: 2rem auto;
        box-shadow: 0 20px 50px -10px rgba(0, 82, 204, 0.2);
    }
    .osc-portal-header { text-align: center; margin-bottom: 2rem; }
    .osc-portal-title { font-size: 2rem; font-weight: 700; color: var(--osc-primary-color); }
    .osc-portal-subtitle { font-size: 1rem; color: #666; }

    .osc-portal-balance-card {
        background: var(--osc-primary-color);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .osc-balance-label { font-size: 0.9rem; opacity: 0.8; }
    .osc-balance-amount { font-size: 1.5rem; font-weight: 600; }

    .osc-form-step {
        background: #fff;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 1.5rem;
        border: 1px solid #e8e8e8;
    }
    .osc-form-title { font-size: 1.2rem; font-weight: 600; color: #333; margin-bottom: 1.5rem; }
    .osc-network-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 1rem; }
    .osc-network-btn {
        background: #fff;
        border: 2px solid var(--osc-border-color);
        border-radius: 10px;
        padding: 0.8rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .osc-network-btn:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .osc-network-btn.active {
        border-color: var(--osc-primary-color);
        color: var(--osc-primary-color);
        box-shadow: 0 5px 15px rgba(0, 82, 204, 0.15);
    }

    .osc-select, .osc-input {
        width: 100%;
        padding: 0.9rem 1rem;
        font-size: 1rem;
        border: 1px solid var(--osc-border-color);
        border-radius: 10px;
        background: #fafafa;
    }

    .osc-payment-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .osc-payment-option {
        padding: 1.5rem;
        border: 2px solid var(--osc-border-color);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }
    .osc-payment-option.active {
        border-color: var(--osc-primary-color);
        background: var(--osc-secondary-color);
    }
    .osc-payment-option.active::after {
        content: '✔';
        position: absolute;
        top: 10px;
        right: 10px;
        color: var(--osc-primary-color);
        font-weight: bold;
    }
    .osc-payment-name { font-weight: 600; }
    .osc-payment-desc { font-size: 0.9rem; color: #666; }

    .osc-summary { padding: 1.5rem; border-top: 1px solid #eee; margin-top: 1.5rem; }
    .osc-summary-item { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
    .osc-total { font-weight: 700; font-size: 1.2rem; color: var(--osc-primary-color); }

    .osc-btn-purchase {
        width: 100%;
        padding: 1.1rem;
        font-size: 1.1rem;
        font-weight: 700;
        background: linear-gradient(90deg, var(--osc-success-color), #00a000);
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 1rem;
    }
    .osc-btn-purchase:hover { box-shadow: 0 10px 20px -5px rgba(0, 138, 0, 0.4); transform: translateY(-3px); }

    /* Admin page styles */
    .wrap .card { background: #fff; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .wrap h1 { color: var(--osc-primary-color); }
</style>
<?php
$networks = array_keys(get_option('osc_network_products', array(
'MTN'=>[],
'Telecel'=>[],
'Airteltigo'=>[],
'Airteltigo iShare'=>[],
'Bigtime'=>[],
'AFA Registration'=>[],
'Call Minutes'=>[],
'Result Checkers'=>[]
)));
$user_id = get_current_user_id();
$balance = osc_get_wallet_balance($user_id);
$user_data = $user_id ? wp_get_current_user() : null;
?>
<div class="osc-portal-container">
    <div class="osc-portal-header">
        <h1 class="osc-portal-title">Ocean Services</h1>
        <p class="osc-portal-subtitle">Digital Bundle Purchase</p>
        <?php if($user_data): ?>
            <div class="osc-user-info">
                <span class="osc-user-avatar"><?php echo strtoupper(substr($user_data->display_name, 0, 1)); ?></span>
                <span class="osc-user-name"><?php echo esc_html($user_data->display_name); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="osc-portal-balance-card">
        <span class="osc-balance-label">Available Balance</span>
        <span class="osc-balance-amount">GHS <?php echo number_format($balance, 2); ?></span>
    </div>

    <div class="osc-portal-form">
        <div class="osc-form-step">
            <h2 class="osc-form-title">1. Choose Your Network</h2>
            <select id="osc-network-select" class="osc-select">
                <option value="">Select a network</option>
                <?php foreach($networks as $net): ?>
                    <option value="<?php echo esc_attr($net); ?>"><?php echo esc_html($net); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="osc-selected-network">
        </div>

        <div class="osc-form-step">
            <h2 class="osc-form-title">2. Select a Bundle</h2>
            <div id="osc-bundle-loader" style="display:none;">Loading...</div>
            <select id="osc-bundle-select" class="osc-select" disabled>
                <option>Select a network first</option>
            </select>
        </div>

        <div class="osc-form-step">
            <h2 class="osc-form-title">3. Recipient Information</h2>
            <input type="tel" id="osc-phone-input" class="osc-input" placeholder="Enter recipient's phone number">
        </div>

        <div class="osc-form-step">
            <h2 class="osc-form-title">4. Payment Method</h2>
            <div class="osc-payment-methods">
                <div class="osc-payment-option active" data-method="wallet">
                    <div class="osc-payment-details">
                        <span class="osc-payment-name">Wallet Balance</span>
                        <span class="osc-payment-desc">Pay with your available credit</span>
                    </div>
                </div>
                <div class="osc-payment-option" data-method="paystack">
                    <div class="osc-payment-details">
                        <span class="osc-payment-name">Paystack</span>
                        <span class="osc-payment-desc">Card or Mobile Money</span>
                    </div>
                </div>
            </div>
            <input type="hidden" id="osc-selected-payment-method" value="wallet">
        </div>

        <div class="osc-summary">
            <div class="osc-summary-item">
                <span>Bundle Cost</span>
                <span id="osc-bundle-cost">GHS 0.00</span>
            </div>
            <div class="osc-summary-item osc-total">
                <span>Total Amount</span>
                <span id="osc-total-amount">GHS 0.00</span>
            </div>
        </div>

        <button id="osc-purchase-btn" class="osc-btn-purchase">Purchase Bundle</button>
    </div>
    <footer class="osc-portal-footer">
        © <?php echo date('Y'); ?> Ocean Services. All rights reserved.
    </footer>
</div>

<!-- Agent Phone Modal -->
<style>
    .osc-modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.6);
        display: none;
        align-items: center;
        justify-content: center;
    }
    .osc-modal-content {
        background-color: #ffffff;
        margin: auto;
        padding: 25px;
        border: none;
        width: 90%;
        max-width: 450px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        animation: osc-modal-fade-in 0.3s ease-out;
    }
    @keyframes osc-modal-fade-in {
        from {transform: translateY(-50px); opacity: 0}
        to {transform: translateY(0); opacity: 1}
    }
    .osc-modal-title {
        font-size: 1.6rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 0.5rem;
    }
    .osc-modal p {
        color: #666;
        font-size: 1rem;
        margin-bottom: 1.5rem;
    }
    .osc-modal .osc-input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
    }
    .osc-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
    .osc-btn-primary, .osc-btn-secondary {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .osc-btn-primary {
        background-color: #0073e6;
        color: white;
    }
    .osc-btn-primary:hover {
        background-color: #005bb5;
    }
    .osc-btn-secondary {
        background-color: #f0f0f0;
        color: #333;
        border: 1px solid #ddd;
    }
    .osc-btn-secondary:hover {
        background-color: #e0e0e0;
    }
</style>
<div id="osc-agent-modal" class="osc-modal" style="display:none;">
<div class="osc-modal-content">
<h2 class="osc-modal-title">Confirm Purchase</h2>
<p>Please enter your agent phone number to complete the transaction.</p>
<div class="osc-form-group">
<label for="osc-agent-phone" class="osc-label">Agent Phone Number</label>
<input type="tel" id="osc-agent-phone" class="osc-input" placeholder="Enter your number">
</div>
<div class="osc-modal-actions">
<button id="osc-modal-cancel" class="osc-btn-secondary">Cancel</button>
<button id="osc-modal-confirm" class="osc-btn-primary">Confirm Purchase</button>
</div>
</div>
</div>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script type="text/javascript">
jQuery(document).ready(function($) {
    var purchaseData = {};

    function executePurchase() {
        var purchaseButton = $('#osc-purchase-btn');
        purchaseButton.text('Processing...').prop('disabled', true);

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'osc_process_purchase',
            network: purchaseData.network,
            product: purchaseData.productId,
            phone: purchaseData.recipientPhone,
            method: purchaseData.paymentMethod,
            agent_phone: purchaseData.agentPhone
        }, function(response) {
            if (response.status === 'success') {
                alert(response.message);
                location.reload();
            } else if (response.status === 'paystack') {
                var handler = PaystackPop.setup({
                    key: response.paystack_public,
                    email: response.email,
                    amount: response.amount * 100,
                    ref: response.reference,
                    currency: 'GHS',
                    callback: function(paystackResponse) {
                        purchaseButton.text('Verifying Payment...').prop('disabled', true);
                        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                            action: 'osc_verify_bundle_paystack_payment',
                            reference: paystackResponse.reference
                        }, function(verifyResponse) {
                            if (verifyResponse.status === 'success') {
                                alert(verifyResponse.message);
                                location.reload();
                            } else {
                                alert('Verification Error: ' + verifyResponse.message);
                                purchaseButton.text('Purchase Bundle').prop('disabled', false);
                            }
                        });
                    },
                    onClose: function() {
                        alert('Payment was not completed.');
                        purchaseButton.text('Purchase Bundle').prop('disabled', false);
                    }
                });
                handler.openIframe();
            } else {
                alert('Error: ' + response.message);
                purchaseButton.text('Purchase Bundle').prop('disabled', false);
            }
        }).fail(function() {
            alert('An unexpected error occurred. Please try again.');
            purchaseButton.text('Purchase Bundle').prop('disabled', false);
        });
    }

    $('#osc-purchase-btn').on('click', function(e) {
        e.preventDefault();
        purchaseData.network = $('#osc-selected-network').val();
        purchaseData.productId = $('#osc-bundle-select').val();
        purchaseData.recipientPhone = $('#osc-phone-input').val();
        purchaseData.paymentMethod = $('#osc-selected-payment-method').val();

        if (!purchaseData.network || !purchaseData.productId || !purchaseData.recipientPhone) {
            alert('Please fill out all the fields.');
            return;
        }

        $('#osc-agent-modal').css('display', 'flex');
        $('body').css('overflow', 'hidden');
    });

    $('#osc-modal-cancel').on('click', function() {
        $('#osc-agent-modal').hide();
        $('body').css('overflow', 'auto');
    });

    $('#osc-modal-confirm').on('click', function() {
        purchaseData.agentPhone = $('#osc-agent-phone').val();
        if (!purchaseData.agentPhone) {
            alert('Please enter your agent phone number.');
            return;
        }
        $('#osc-agent-modal').hide();
        $('body').css('overflow', 'auto');
        executePurchase();
    });

    $('.osc-payment-option').on('click', function() {
        $('.osc-payment-option').removeClass('active');
        $(this).addClass('active');
        $('#osc-selected-payment-method').val($(this).data('method'));
    });

    $('#osc-network-select').on('change', function() {
        var network = $(this).val();
        $('#osc-selected-network').val(network);

        if (!network) {
            $('#osc-bundle-select').empty().append('<option>Select a network first</option>').prop('disabled', true);
            $('#osc-bundle-cost').text('GHS 0.00');
            $('#osc-total-amount').text('GHS 0.00');
            return;
        }

        $('#osc-bundle-loader').show();
        $('#osc-bundle-select').hide();

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: { action: 'osc_get_network_products', network: network },
            success: function(response) {
                var bundleSelect = $('#osc-bundle-select');
                bundleSelect.empty().prop('disabled', false);
                if (response && response.length) {
                    bundleSelect.append('<option value="">Select a bundle</option>');
                    response.forEach(function(bundle) {
                        bundleSelect.append('<option value="' + bundle.id + '" data-price="' + bundle.price + '">' + bundle.name + ' - GHS ' + bundle.price + '</option>');
                    });
                } else {
                    bundleSelect.append('<option>No bundles available</option>').prop('disabled', true);
                }
            },
            error: function() {
                $('#osc-bundle-select').empty().append('<option>Failed to load bundles</option>').prop('disabled', true);
            },
            complete: function() {
                $('#osc-bundle-loader').hide();
                $('#osc-bundle-select').show();
            }
        });
    });

    $('#osc-bundle-select').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price') || 0;
        $('#osc-bundle-cost').text('GHS ' + parseFloat(price).toFixed(2));
        $('#osc-total-amount').text('GHS ' + parseFloat(price).toFixed(2));
    });
});
</script>
<?php
return ob_get_clean();
}

/* ---------------------------
New: Agent Registration Shortcode (Customers Only)
--------------------------- */
add_shortcode('ocean_service_agent_register', 'osc_agent_register_shortcode');
function osc_agent_register_shortcode(){
    ob_start();
    if(is_user_logged_in()){
        $user = wp_get_current_user();
        $roles = (array)$user->roles;

        if(in_array('ocean_service_agent', $roles)) {
            return '<div class="osc-agent-upgrade-form"><h2>Already an Agent</h2><p>You are already an Ocean Service Agent.</p></div>';
        }

        if(in_array('ocean_service_customer', $roles) || in_array('customer', $roles)) {
            $upgrade_fee = floatval(get_option('osc_agent_upgrade_fee', 50));
            $paystack_public_key = get_option('osc_paystack_public', '');
            ?>
            <div class="osc-agent-upgrade-form">
                <h2>Upgrade to Ocean Service Agent</h2>
                <p>To become an Ocean Service Agent and enjoy special benefits, you must pay a one-time upgrade fee.</p>

                <div class="osc-afa-summary" style="margin-bottom: 1.5rem;">
                    <span>Upgrade Fee</span>
                    <strong>GHS <?php echo number_format($upgrade_fee, 2); ?></strong>
                </div>

                <form id="osc-agent-upgrade-form">
                    <div class="osc-payment-methods">
                        <div class="osc-payment-option" data-method="wallet">
                            <span class="osc-payment-name">Wallet</span>
                            <span class="osc-payment-desc">Available: GHS <?php echo number_format(osc_get_wallet_balance($user->ID), 2); ?></span>
                        </div>
                        <div class="osc-payment-option active" data-method="paystack">
                            <span class="osc-payment-name">Paystack</span>
                            <span class="osc-payment-desc">Card or Mobile Money</span>
                        </div>
                    </div>
                    <input type="hidden" id="osc-selected-payment-method" value="paystack">
                    <button type="submit" class="osc-afa-submit-btn">Upgrade to Agent</button>
                </form>
                <div id="osc-upgrade-feedback" style="margin-top: 1rem; text-align: center;"></div>
            </div>
            <script src="https://js.paystack.co/v1/inline.js"></script>
            <script>
            jQuery(document).ready(function($) {
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

                $('.osc-payment-option').on('click', function() {
                    $('.osc-payment-option').removeClass('active');
                    $(this).addClass('active');
                    $('#osc-selected-payment-method').val($(this).data('method'));
                });

                $('#osc-agent-upgrade-form').on('submit', function(e) {
                    e.preventDefault();
                    const feedback = $('#osc-upgrade-feedback');
                    const submitButton = $(this).find('.osc-afa-submit-btn');
                    const paymentMethod = $('#osc-selected-payment-method').val();
                    const nonce = '<?php echo wp_create_nonce('osc_agent_upgrade_nonce'); ?>';

                    feedback.text('Processing your upgrade...').show();
                    submitButton.prop('disabled', true);

        $.post(ajaxurl, {
                        action: 'osc_process_agent_upgrade',
                        nonce: nonce,
                        method: paymentMethod
        })
        .done(function(response) {
                        if (response.status === 'success') {
                            feedback.css('color', 'green').text(response.message);
                            setTimeout(() => window.location.reload(), 2000);
                        } else if (response.status === 'paystack') {
                            var handler = PaystackPop.setup({
                                key: response.paystack_public,
                                email: response.email,
                                amount: response.amount * 100,
                                ref: response.reference,
                                currency: 'GHS',
                                callback: function(paystackResponse) {
                                    feedback.text('Payment successful! Verifying...').css('color', 'blue');
                        $.post(ajaxurl, {
                                        action: 'osc_verify_agent_upgrade_payment',
                                        nonce: nonce,
                                        reference: paystackResponse.reference
                        })
                        .done(function(verifyResponse) {
                                        if (verifyResponse.status === 'success') {
                                            feedback.css('color', 'green').text(verifyResponse.message);
                                            setTimeout(() => window.location.reload(), 2000);
                                        } else {
                                            feedback.css('color', 'red').text(verifyResponse.message);
                                            submitButton.prop('disabled', false);
                                        }
                        })
                        .fail(function() {
                            feedback.css('color', 'red').text('An error occurred during verification. Please contact support.');
                            submitButton.prop('disabled', false);
                        });
                    },
                    onClose: function() {
                        feedback.text('Payment cancelled.').css('color', 'orange');
                        submitButton.prop('disabled', false);
                    }
                });
                handler.openIframe();
            } else {
                feedback.css('color', 'red').text(response.message);
                submitButton.prop('disabled', false);
            }
        })
        .fail(function() {
            feedback.css('color', 'red').text('A critical error occurred. Please refresh the page and try again.');
            submitButton.prop('disabled', false);
        });
    });
});
</script>
            <?php
            return ob_get_clean();
        }
        return '<div class="notice notice-warning"><p>You cannot register as an agent. Only customers can upgrade.</p></div>';
    } else {
// Not logged in - show registration form for new customers
        if($_POST && isset($_POST['osc_customer_register_submit']) && check_admin_referer('osc_customer_register','osc_customer_register_nonce')){
            $first_name = sanitize_text_field($_POST['osc_first_name'] ?? '');
            $last_name = sanitize_text_field($_POST['osc_last_name'] ?? '');
            $email = sanitize_email($_POST['osc_email'] ?? '');
            $username = sanitize_user($_POST['osc_username'] ?? '');
            $password = $_POST['osc_password'] ?? '';
            $confirm_password = $_POST['osc_confirm_password'] ?? '';

            // Form validation
            if(empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
                echo '<div class="notice notice-error"><p>All fields are required.</p></div>';
            } elseif($password !== $confirm_password) {
                echo '<div class="notice notice-error"><p>Passwords do not match.</p></div>';
            } elseif(username_exists($username)) {
                echo '<div class="notice notice-error"><p>Username already exists.</p></div>';
            } elseif(email_exists($email)) {
                echo '<div class="notice notice-error"><p>Email already registered.</p></div>';
            } else {
                // Create new user with the correct default role
                $user_id = wp_insert_user(array(
                    'user_login' => $username,
                    'user_pass'  => $password,
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'role'       => 'ocean_service_customer'
                ));

                if(is_wp_error($user_id)) {
                    echo '<div class="notice notice-error"><p>' . $user_id->get_error_message() . '</p></div>';
                } else {
                    // Automatically log in the new user and refresh to show the upgrade form
                    wp_set_auth_cookie($user_id);
                    echo '<div class="notice notice-success is-dismissible"><p>Registration successful! You can now upgrade to an agent.</p></div>';
                    echo '<script>window.location.reload();</script>';
                }
            }
        }
        ?>
        <div class="osc-customer-register-form">
            <h2>Customer Registration</h2>
            <p>Register as a customer to start using our services. You can upgrade to an Ocean Service Agent at any time.</p>
            <form method="post">
                <?php wp_nonce_field('osc_customer_register','osc_customer_register_nonce'); ?>
                <p><label for="osc_first_name">First Name</label><br><input type="text" name="osc_first_name" id="osc_first_name" required></p>
                <p><label for="osc_last_name">Last Name</label><br><input type="text" name="osc_last_name" id="osc_last_name" required></p>
                <p><label for="osc_email">Email</label><br><input type="email" name="osc_email" id="osc_email" required></p>
                <p><label for="osc_username">Username</label><br><input type="text" name="osc_username" id="osc_username" required></p>
                <p><label for="osc_password">Password</label><br><input type="password" name="osc_password" id="osc_password" required></p>
                <p><label for="osc_confirm_password">Confirm Password</label><br><input type="password" name="osc_confirm_password" id="osc_confirm_password" required></p>
                <?php submit_button('Register','primary','osc_customer_register_submit'); ?>
            </form>
        </div>
        <?php
    }
    return ob_get_clean();
}
<h2>Upgrade to Ocean Service Agent</h2>
<p>You are currently a customer. To become an Ocean Service Agent and enjoy special benefits, you must pay a one-time upgrade fee.</p>

<div class="osc-afa-summary" style="margin-bottom: 1.5rem;">
    <span>Upgrade Fee</span>
    <strong>GHS <?php echo number_format($upgrade_fee, 2); ?></strong>
</div>

<form id="osc-agent-upgrade-form">
    <div class="osc-payment-methods">
        <div class="osc-payment-option" data-method="wallet">
            <span class="osc-payment-name">Wallet</span>
            <span class="osc-payment-desc">Available: GHS <?php echo number_format(osc_get_wallet_balance($user->ID), 2); ?></span>
        </div>
        <div class="osc-payment-option active" data-method="paystack">
            <span class="osc-payment-name">Paystack</span>
            <span class="osc-payment-desc">Card or Mobile Money</span>
        </div>
    </div>
    <input type="hidden" id="osc-selected-payment-method" value="paystack">

    <button type="submit" class="osc-afa-submit-btn">Upgrade to Agent</button>
</form>
<div id="osc-upgrade-feedback" style="margin-top: 1rem; text-align: center;"></div>
    </div>

    <script>

jQuery(document).ready(function($) { $('.osc-payment-option').on('click', function() { $('.osc-payment-option').removeClass('active'); $(this).addClass('active'); $('#osc-selected-payment-method').val($(this).data('method')); });
$('#osc-agent-upgrade-form').on('submit', function(e) {
    e.preventDefault();
    const feedback = $('#osc-upgrade-feedback');
    const submitButton = $(this).find('.osc-afa-submit-btn');
    const paymentMethod = $('#osc-selected-payment-method').val();
    const nonce = '<?php echo wp_create_nonce('osc_agent_upgrade_nonce'); ?>';

    feedback.text('Processing your upgrade...').show();
    submitButton.prop('disabled', true);

    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
        action: 'osc_process_agent_upgrade',
        nonce: nonce,
        method: paymentMethod
    }, function(response) {
        if (response.status === 'success') {
            feedback.css('color', 'green').text(response.message);
            setTimeout(() => window.location.reload(), 2000);
        } else if (response.status === 'paystack') {
            var handler = PaystackPop.setup({
                key: response.paystack_public,
                email: response.email,
                amount: response.amount * 100,
                ref: response.reference,
                currency: 'GHS',
                callback: function(paystackResponse) {
                    feedback.text('Payment successful! Verifying...').css('color', 'blue');
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'osc_verify_agent_upgrade_payment',
                        nonce: nonce,
                        reference: paystackResponse.reference
                    }, function(verifyResponse) {
                        if (verifyResponse.status === 'success') {
                            feedback.css('color', 'green').text(verifyResponse.message);
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            feedback.css('color', 'red').text(verifyResponse.message);
                            submitButton.prop('disabled', false);
                        }
                    });
                },
                onClose: function() {
                    feedback.text('Payment cancelled.').css('color', 'orange');
                    submitButton.prop('disabled', false);
                }
            });
            handler.openIframe();
        } else {
            feedback.css('color', 'red').text(response.message);
            submitButton.prop('disabled', false);
        }
    });
});

}); <?php return ob_get_clean(); }
// If user has other roles, don't allow
return '<div class="notice notice-warning"><p>You cannot register as an agent. Only customers can upgrade to Ocean Service Agents.</p></div>';

} else { // Not logged in - show registration form for new customers if($_POST && isset($_POST['osc_customer_register_submit']) && check_admin_referer('osc_customer_register','osc_customer_register_nonce')){ $first_name = sanitize_text_field($_POST['osc_first_name'] ?? ''); $last_name = sanitize_text_field($_POST['osc_last_name'] ?? ''); $email = sanitize_email($_POST['osc_email'] ?? ''); $username = sanitize_user($_POST['osc_username'] ?? ''); $password = $_POST['osc_password'] ?? ''; $confirm_password = $_POST['osc_confirm_password'] ?? '';
    // Validate inputs
    if(empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        echo '<div class="notice notice-error"><p>All fields are required.</p></div>';
    } elseif($password !== $confirm_password) {
        echo '<div class="notice notice-error"><p>Passwords do not match.</p></div>';
    } elseif(username_exists($username)) {
        echo '<div class="notice notice-error"><p>Username already exists.</p></div>';
    } elseif(email_exists($email)) {
        echo '<div class="notice notice-error"><p>Email already registered.</p></div>';
    } else {
        // Create new user with ocean_service_customer role
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'ocean_service_customer'
        ));

        if(is_wp_error($user_id)) {
            echo '<div class="notice notice-error"><p>' . $user_id->get_error_message() . '</p></div>';
        } else {
            // Log in the new user
            wp_set_auth_cookie($user_id);

            echo '<div class="notice notice-success is-dismissible"><p>Customer registration successful! You can now upgrade to an Ocean Service Agent if desired.</p></div>';
        }
    }
}
?>
<div class="osc-customer-register-form">
    <h2>Customer Registration</h2>
    <p>Register as a customer to start using our services. You can later upgrade to an Ocean Service Agent.</p>
    <form method="post">
        <?php wp_nonce_field('osc_customer_register','osc_customer_register_nonce'); ?>
        <p><label for="osc_first_name">First Name</label><br><input type="text" name="osc_first_name" id="osc_first_name" required></p>
        <p><label for="osc_last_name">Last Name</label><br><input type="text" name="osc_last_name" id="osc_last_name" required></p>
        <p><label for="osc_email">Email</label><br><input type="email" name="osc_email" id="osc_email" required></p>
        <p><label for="osc_username">Username</label><br><input type="text" name="osc_username" id="osc_username" required></p>
        <p><label for="osc_password">Password</label><br><input type="password" name="osc_password" id="osc_password" required></p>
        <p><label for="osc_confirm_password">Confirm Password</label><br><input type="password" name="osc_confirm_password" id="osc_confirm_password" required></p>
        <?php submit_button('Register','primary','osc_customer_register_submit'); ?>
    </form>
</div>
<?php
return ob_get_clean();

} }
/* --------------------------- Shortcode: Topup button (Paystack) Usage: [ocean_service_topup amount="50"] --------------------------- */ add_shortcode('ocean_service_topup', 'osc_shortcode_topup'); function osc_shortcode_topup($atts){ $atts = shortcode_atts(array('amount'=>50), $atts, 'ocean_service_topup'); $amount = floatval($atts['amount']); if(!is_user_logged_in()) return '
Please login to top up
';

$pub = get_option('osc_paystack_public',''); if(empty($pub)) return '
Paystack not configured
';

// Return a simple checkout button which expects client-side Paystack integration $email = wp_get_current_user()->user_email; $ref = 'topup_'.time().'_'.wp_rand(1000,9999); ob_start(); ?>
Please log in to view your order history.
'; } if (!class_exists('WooCommerce')) { return '
WooCommerce is not active.
'; } ob_start(); $user_id = get_current_user_id(); $orders = wc_get_orders([ 'customer_id' => $user_id, 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC', 'status' => array('completed', 'processing', 'pending', 'on-hold', 'cancelled', 'failed') ]); ?>
Order History
Your recent purchases and transactions.
<?php if (empty($orders)): ?>
    <div class="osc-no-orders">
        <h3>No orders yet</h3>
        <p>Your purchase history will appear here.</p>
    </div>
<?php else: ?>
    <?php foreach ($orders as $order):
        $order_items = $order->get_items();
        $first_item = !empty($order_items) ? reset($order_items)->get_name() : 'Order';
        $status = $order->get_status();

        // Meta data
        $network = $order->get_meta('bundle_network') ?: 'N/A';
        $agent_phone = $order->get_meta('agent_phone') ?: 'N/A';
        $beneficiary_phone = $order->get_billing_phone() ?: $order->get_meta('bundle_recipient_phone') ?: $order->get_meta('_osc_beneficiary') ?: $order->get_meta('_bundle_phone_number') ?: 'N/A';
    ?>
    <div class="osc-order-card">
        <div class="osc-card-summary">
            <div class="osc-summary-icon">📦</div>
            <div class="osc-summary-info">
                <div class="product"><?php echo esc_html($first_item); ?></div>
                <div class="date">Order #<?php echo $order->get_order_number(); ?> &bull; <?php echo $order->get_date_created()->date('M d, Y'); ?></div>
            </div>
            <div class="osc-summary-total"><?php echo $order->get_formatted_order_total(); ?></div>
            <div class="osc-summary-status status-<?php echo esc_attr($status); ?>">
                <?php echo esc_html(wc_get_order_status_name($status)); ?>
            </div>
        </div>
        <div class="osc-card-details">
            <div class="osc-details-grid">
                <div class="osc-detail-item"><h4>Network</h4><p><?php echo esc_html($network); ?></p></div>
                <div class="osc-detail-item"><h4>Beneficiary Phone</h4><p><?php echo esc_html($beneficiary_phone); ?></p></div>
                <div class="osc-detail-item"><h4>Agent Phone</h4><p><?php echo esc_html($agent_phone); ?></p></div>
                <div class="osc-detail-item"><h4>Payment Method</h4><p><?php echo esc_html($order->get_payment_method_title()); ?></p></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="osc-history-summary">
        <div class="osc-summary-card">
            <h4>Total Orders</h4>
            <p><?php echo count($orders); ?></p>
        </div>
        <div class="osc-summary-card">
            <h4>Total Spent</h4>
            <p>
                <?php
                $total_spent = 0;
                foreach ($orders as $order) {
                    if ($order->get_status() === 'completed') {
                        $total_spent += $order->get_total();
                    }
                }
                echo wc_price($total_spent);
                ?>
            </p>
        </div>
    </div>
<?php endif; ?>

WooCommerce is not active. AFA registration requires WooCommerce.
'; } ob_start(); $product_id = get_option('osc_afa_product_id'); $product = $product_id ? wc_get_product($product_id) : null; // New: Check if AFA product exists and has a price if (!$product || $product->get_price() === '') { return '
AFA registration product is not configured or does not have a price. Please contact an administrator.
'; } $afa_price = $product->get_price(); ?>
🏢 AFA Registration
Register for the Agricultural Farmers Association.
'error', 'message' => 'Security check failed.']); } if (!is_user_logged_in()) { osc_json_exit(['status' => 'error', 'message' => 'You must be logged in to upgrade.']); } $user_id = get_current_user_id(); $upgrade_fee = floatval(get_option('osc_agent_fee', 10)); $method = sanitize_text_field($_POST['method']); if ($method === 'wallet') { if (!osc_deduct_wallet($user_id, $upgrade_fee, 'Agent Upgrade Fee')) { osc_json_exit(['status' => 'error', 'message' => 'Insufficient wallet balance.']); } try { $user = new WP_User($user_id); $user->remove_role('ocean_service_customer'); $user->remove_role('customer'); $user->add_role('ocean_service_agent'); } catch (Exception $e) { osc_credit_wallet($user_id, $upgrade_fee, 'Refund for failed agent upgrade.'); osc_api_log('agent_upgrade_error', $e->getMessage(), ['user_id' => $user_id]); osc_json_exit(['status' => 'error', 'message' => 'Could not upgrade your account. The fee has been refunded.']); } osc_json_exit(['status' => 'success', 'message' => 'Congratulations! You are now an Ocean Service Agent.']); } elseif ($method === 'paystack') { $paystack_public = get_option('osc_paystack_public', ''); if (empty($paystack_public)) { osc_json_exit(['status' => 'error', 'message' => 'Paystack is not configured.']); } $email = wp_get_current_user()->user_email; $reference = 'agent_upgrade_' . $user_id . '_' . time(); // Store user ID in a transient for verification after payment set_transient('agent_upgrade_' . $reference, $user_id, HOUR_IN_SECONDS); osc_json_exit([ 'status' => 'paystack', 'paystack_public' => $paystack_public, 'amount' => $upgrade_fee, 'email' => $email, 'reference' => $reference ]); } } function osc_verify_agent_upgrade_payment() { if (!check_ajax_referer('osc_agent_upgrade_nonce', 'nonce', false)) { osc_json_exit(['status' => 'error', 'message' => 'Security check failed.']); } $reference = sanitize_text_field($_POST['reference']); $user_id = get_transient('agent_upgrade_' . $reference); if (false === $user_id || !is_user_logged_in() || $user_id != get_current_user_id()) { osc_json_exit(['status' => 'error', 'message' => 'Upgrade session is invalid or has expired.']); } $secret_key = get_option('osc_paystack_secret'); $response = wp_remote_get("https://api.paystack.co/transaction/verify/{$reference}", [ 'headers' => ['Authorization' => 'Bearer ' . $secret_key] ]); if (is_wp_error($response) || json_decode(wp_remote_retrieve_body($response), true)['data']['status'] !== 'success') { osc_json_exit(['status' => 'error', 'message' => 'Payment verification failed. Please contact support.']); } $user = new WP_User($user_id); $user->remove_role('ocean_service_customer'); $user->remove_role('customer'); $user->add_role('ocean_service_agent'); delete_transient('agent_upgrade_' . $reference); osc_json_exit(['status' => 'success', 'message' => 'Congratulations! Your account has been upgraded to an Ocean Service Agent.']); } function osc_verify_afa_paystack_payment() { if (!wp_verify_nonce($_POST['afa_nonce'], 'afa_registration_nonce')) { osc_json_exit(['status'=>'error','message'=>'Security verification failed.']); } $reference = sanitize_text_field($_POST['reference']); if (empty($reference)) { osc_json_exit(['status' => 'error', 'message' => 'Payment reference is missing.']); } // Retrieve registration data from transient $registration_data = get_transient('afa_reg_' . $reference); if (false === $registration_data) { osc_json_exit(['status' => 'error', 'message' => 'Registration session expired or is invalid. Please try again.']); } // Verify transaction with Paystack $secret_key = get_option('osc_paystack_secret'); $response = wp_remote_get("https://api.paystack.co/transaction/verify/{$reference}", [ 'headers' => ['Authorization' => 'Bearer ' . $secret_key] ]); if (is_wp_error($response)) { osc_json_exit(['status' => 'error', 'message' => 'Could not verify payment. Please contact support.']); } $body = json_decode(wp_remote_retrieve_body($response), true); if (!($body['status'] && $body['data']['status'] === 'success')) { osc_json_exit(['status' => 'error', 'message' => 'Payment verification failed. Please contact support.']); } // All checks passed, create the order try { $product_id = get_option('osc_afa_product_id'); $product = wc_get_product($product_id); $user_id = $registration_data['user_id']; $order = wc_create_order(['customer_id' => $user_id]); $order->add_product($product, 1); $user = $user_id ? get_userdata($user_id) : null; $order->set_address([ 'first_name' => $user ? $user->first_name : $registration_data['full_name'], 'phone' => $registration_data['phone_number'], 'address_1' => $registration_data['location'], 'email' => $user ? $user->user_email : 'guest@example.com', ], 'billing'); $order->calculate_totals(); foreach ($registration_data as $key => $value) { $order->update_meta_data('afa_' . $key, $value); } $default_status = get_option('osc_afa_order_status_default', 'completed'); $order->update_status($default_status, 'AFA registration paid via Paystack.', true); $order_id = $order->get_id(); delete_transient('afa_reg_' . $reference); // Clean up } catch (Exception $e) { osc_api_log('afa_paystack_order_error', $e->getMessage(), $registration_data); osc_json_exit(['status' => 'error', 'message' => 'Could not create your registration order after payment. Please contact support with reference: ' . $reference]); } osc_json_exit(['status' => 'success', 'message' => 'Registration and payment successful! Order ID: ' . $order_id]); } function osc_process_afa_registration(){ // Nonce is checked in the JS that calls this if (!wp_verify_nonce($_POST['afa_nonce'], 'afa_registration_nonce')) { osc_json_exit(['status'=>'error','message'=>'Security verification failed.']); } $full_name = sanitize_text_field($_POST['full_name'] ?? ''); $phone_number = sanitize_text_field($_POST['phone_number'] ?? ''); $ghana_card = sanitize_text_field($_POST['ghana_card'] ?? ''); $location = sanitize_text_field($_POST['location'] ?? ''); $notes = sanitize_textarea_field($_POST['notes'] ?? ''); $method = sanitize_text_field($_POST['method'] ?? 'wallet'); if (empty($full_name) || empty($phone_number) || empty($ghana_card) || empty($location)) { osc_json_exit(['status' => 'error', 'message' => 'Please fill in all required fields.']); } $product_id = get_option('osc_afa_product_id'); $product = $product_id ? wc_get_product($product_id) : null; if (!$product) { osc_json_exit(['status' => 'error', 'message' => 'AFA registration product not configured.']); } $price = $product->get_price(); $user_id = get_current_user_id(); if ($method === 'wallet') { if (!$user_id) { osc_json_exit(['status' => 'error', 'message' => 'You must be logged in to pay with your wallet.']); } if (!osc_deduct_wallet($user_id, $price, 'AFA Registration Fee')) { osc_json_exit(['status' => 'error', 'message' => 'Insufficient wallet balance.']); } } elseif ($method === 'paystack') { $paystack_public = get_option('osc_paystack_public', ''); if (empty($paystack_public)) { osc_json_exit(['status' => 'error', 'message' => 'Paystack is not configured.']); } $email = $user_id ? wp_get_current_user()->user_email : 'guest@example.com'; $reference = 'afa_' . time() . '_' . wp_rand(1000, 9999); // Store registration data in a transient $registration_data = [ 'user_id' => $user_id, 'full_name' => $full_name, 'phone_number' => $phone_number, 'ghana_card' => $ghana_card, 'location' => $location, 'notes' => $notes, 'price' => $price ]; set_transient('afa_reg_' . $reference, $registration_data, HOUR_IN_SECONDS); osc_json_exit([ 'status' => 'paystack', 'paystack_public' => $paystack_public, 'amount' => $price, 'email' => $email, 'reference' => $reference ]); return; } try { $user = $user_id ? get_userdata($user_id) : null; $order = wc_create_order(['customer_id' => $user_id]); $order->add_product($product, 1); $address = [ 'first_name' => $user ? $user->first_name : $full_name, 'last_name' => $user ? $user->last_name : '', 'phone' => $phone_number, 'address_1' => $location, 'email' => $user ? $user->user_email : 'guest@example.com', ]; $order->set_address($address, 'billing'); $order->calculate_totals(); $order->update_meta_data('afa_full_name', $full_name); $order->update_meta_data('afa_phone_number', $phone_number); $order->update_meta_data('afa_ghana_card', $ghana_card); $order->update_meta_data('afa_location', $location); $order->update_meta_data('afa_notes', $notes); $default_status = get_option('osc_afa_order_status_default', 'completed'); $order->update_status($default_status, 'AFA registration paid via Wallet.', true); $order_id = $order->get_id(); } catch (Exception $e) { if ($method === 'wallet' && $user_id) { osc_credit_wallet($user_id, $price, 'Refund for failed AFA registration order.'); } osc_api_log('afa_order_create_error', $e->getMessage(), $_POST); osc_json_exit(['status' => 'error', 'message' => 'Could not create your registration order. Please contact support.']); } osc_json_exit(['status' => 'success', 'message' => 'Registration successful! Order ID: ' . $order_id]); } function osc_add_afa_to_cart() { // Verify nonce if (!wp_verify_nonce($_POST['afa_nonce'], 'afa_registration_nonce')) { wp_send_json_error('Security verification failed.'); } if (!class_exists('WooCommerce')) { wp_send_json_error('WooCommerce is not active.'); } $product_id = get_option('osc_afa_product_id'); if (!$product_id || !wc_get_product($product_id)) { wp_send_json_error('AFA registration product is not configured.'); } // Clear any existing AFA products in cart $cart = WC()->cart; if ($cart) { foreach ($cart->get_cart() as $cart_item_key => $cart_item) { if ($cart_item['product_id'] == $product_id) { $cart->remove_cart_item($cart_item_key); } } } // Add AFA product to cart with custom data $cart_item_data = array( 'afa_registration_data' => array( 'full_name' => sanitize_text_field($_POST['full_name']), 'phone_number' => sanitize_text_field($_POST['phone_number']), 'ghana_card' => sanitize_text_field($_POST['ghana_card']), 'location' => sanitize_text_field($_POST['location']), 'notes' => sanitize_textarea_field($_POST['notes']), 'registration_date' => current_time('mysql') ) ); $added = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data); if ($added) { wp_send_json_success('AFA registration added to cart. Redirecting to checkout...'); } else { wp_send_json_error('Failed to add registration to cart.'); } } /* --------------------------- Display AFA registration data in cart and checkout --------------------------- */ add_filter('woocommerce_get_item_data', 'osc_display_afa_data_in_cart', 10, 2); function osc_display_afa_data_in_cart($item_data, $cart_item) { if (isset($cart_item['afa_registration_data'])) { $afa_data = $cart_item['afa_registration_data']; $item_data[] = array( 'name' => 'Full Name', 'value' => $afa_data['full_name'] ); $item_data[] = array( 'name' => 'Phone Number', 'value' => $afa_data['phone_number'] ); $item_data[] = array( 'name' => 'Ghana Card', 'value' => $afa_data['ghana_card'] ); $item_data[] = array( 'name' => 'Location', 'value' => $afa_data['location'] ); if (!empty($afa_data['notes'])) { $item_data[] = array( 'name' => 'Notes', 'value' => $afa_data['notes'] ); } } return $item_data; } /* --------------------------- Save AFA data to order meta --------------------------- */ add_action('woocommerce_checkout_create_order_line_item', 'osc_save_afa_data_to_order', 10, 4); function osc_save_afa_data_to_order($item, $cart_item_key, $values, $order) { if (isset($values['afa_registration_data'])) { $item->add_meta_data('_afa_registration_data', $values['afa_registration_data']); } } /* --------------------------- Admin configuration for AFA Registration --------------------------- */ add_action('admin_menu', 'osc_afa_admin_menu'); function osc_afa_admin_menu() { add_options_page( 'AFA Registration Settings', 'AFA Registration', 'manage_options', 'afa-registration', 'osc_afa_settings_page' ); } function osc_afa_settings_page() { if (isset($_POST['submit_afa_settings'])) { if (!empty($_POST['afa_product_id'])) { update_option('osc_afa_product_id', intval($_POST['afa_product_id'])); } echo '
Settings saved successfully!
'; } $current_product_id = get_option('osc_afa_product_id'); $current_product = $current_product_id ? wc_get_product($current_product_id) : null; ?>
AFA Registration Settings
Setup Instructions
Create a product in WooCommerce called "AFA Registration"Set the product price to match your registration feeSelect that product in the dropdown aboveSave settingsUse the shortcode [afa_registration] on any page
/* --------------------------- Utility: log helper --------------------------- */ function osc_api_log_once($type, $message, $payload=array()){ osc_api_log($type, $message, $payload); }

/* --------------------------- End of plugin --------------------------- */