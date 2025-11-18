<?php
/*
Plugin Name: KAA Mall — Ocean Bundle & Wallet System
Description: Full bundle purchase + single wallet + Paystack top-up + webhooks + admin dashboards + per-agent pricing + order history + agent registration.
Version: 1.0.6
Author: Kevin Afriyie
*/

if (!defined('ABSPATH')) exit;

/* ---------------------------
Basic helpers & constants
--------------------------- */
if (!defined('KAAMALL_OSC')) define('KAAMALL_OSC', true);

function osc_log_admin($msg){
if(current_user_can('manage_options')) error_log('[KAA-OSC] '.$msg);
}

function osc_json_exit($arr){
wp_send_json($arr);
exit;
}

/* ---------------------------
Roles
--------------------------- */
add_action('init', function(){
// Remove unwanted custom roles but keep WooCommerce defaults
$unwanted_roles = array('subscriber', 'contributor', 'author', 'editor');

foreach($unwanted_roles as $role) {
if(get_role($role)) {
remove_role($role);
}
}

// Ensure our custom roles exist with the exact names you want
if(!get_role('ocean_service_agent')) {
add_role('ocean_service_agent', 'Ocean Service Agent', array('read'=>true));
}

if(!get_role('ocean_service_customer')) {
add_role('ocean_service_customer', 'Ocean Service Customer', array('read'=>true));
}

// Remove any other custom ocean roles that might conflict
$conflicting_roles = array('osc_agent', 'premium_agent', 'ocean_agent', 'ocean_customer', 'agent');
foreach($conflicting_roles as $role) {
if(get_role($role)) {
remove_role($role);
}
}
});

// Set default role to Ocean Service Customer
add_filter('pre_option_default_role', function($default_role){
return 'ocean_service_customer';
});

/* ---------------------------
User Role Management Helper
--------------------------- */
function osc_get_available_roles() {
$roles = array(
'ocean_service_customer' => 'Ocean Service Customer',
'ocean_service_agent' => 'Ocean Service Agent',
'customer' => 'Customer' // WooCommerce default
);

// Include other WooCommerce roles if they exist
if(get_role('subscriber')) $roles['subscriber'] = 'Subscriber';
if(get_role('administrator')) $roles['administrator'] = 'Administrator';
if(get_role('shop_manager')) $roles['shop_manager'] = 'Shop Manager';

return $roles;
}

// Add role column to users list
add_filter('manage_users_columns', function($columns) {
$columns['osc_role'] = 'Ocean Role';
return $columns;
});

add_filter('manage_users_custom_column', function($value, $column_name, $user_id) {
if($column_name === 'osc_role') {
$user = get_userdata($user_id);
$roles = $user->roles;

if(in_array('ocean_service_agent', $roles)) {
return 'Ocean Service Agent';
} elseif(in_array('ocean_service_customer', $roles)) {
return 'Ocean Service Customer';
} elseif(in_array('customer', $roles)) {
return 'Customer';
} else {
return implode(', ', $roles);
}
}
return $value;
}, 10, 3);

/* ---------------------------
Activation — set defaults
--------------------------- */
register_activation_hook(__FILE__, function(){
if(!get_option('osc_network_products')) update_option('osc_network_products', array(
'MTN'=>[],
'Telecel'=>[],
'Airteltigo'=>[],
'Airteltigo iShare'=>[],
'Bigtime'=>[],
'AFA Registration'=>[],
'Call Minutes'=>[],
'Result Checkers'=>[]
));
if(!get_option('osc_wallet_tx_logs')) update_option('osc_wallet_tx_logs', array());
if(!get_option('osc_purchase_logs')) update_option('osc_purchase_logs', array());
if(!get_option('osc_api_error_logs')) update_option('osc_api_error_logs', array());
if(!get_option('osc_role_pricing')) update_option('osc_role_pricing', array());
if(!get_option('osc_order_status_default')) update_option('osc_order_status_default', 'processing');
if(!get_option('osc_available_networks')) update_option('osc_available_networks', array(
'MTN',
'Telecel',
'Airteltigo',
'Airteltigo iShare',
'Bigtime',
'AFA Registration',
'Call Minutes',
'Result Checkers'
));
});

/* ---------------------------
Admin Menu & Pages
--------------------------- */
add_action('admin_menu', function(){
add_menu_page('Ocean Service','Ocean Service','manage_options','osc-settings','osc_admin_settings_page','dashicons-admin-generic',56);
add_submenu_page('osc-settings','Network Products','Network Products','manage_options','osc-network-products','osc_network_products_page');
add_submenu_page('osc-settings','Wallet Management','Wallet','manage_options','osc-wallet','osc_wallet_admin_page');
add_submenu_page('osc-settings','Wallet Top-up','Top-up','manage_options','osc-wallet-topup','osc_wallet_topup_admin_page');
add_submenu_page('osc-settings','Order History','Order History','manage_options','osc-orders','osc_orders_admin_page');
add_submenu_page('osc-settings','Purchases Log','Purchases','manage_options','osc-purchases','osc_purchases_admin_page');
add_submenu_page('osc-settings','API Error Logs','API Errors','manage_options','osc-api-errors','osc_api_errors_page');
add_submenu_page('osc-settings','System Status','System Status','manage_options','osc_system_status_page');
add_submenu_page('osc-settings','Role Pricing','Role Pricing','manage_options','osc-role-pricing','osc_role_pricing_page');
add_submenu_page('osc-settings','Agent Registration','Agent Registration','manage_options','osc-agent-registration','osc_agent_registration_page');
});

/* ---------------------------
System Status
--------------------------- */
function osc_system_status_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');
$wc = class_exists('WooCommerce') ? 'Yes' : 'No';
$pay = get_option('osc_paystack_secret') ? 'Yes' : 'No';
$webhook = get_option('osc_notification_webhook','');
$default_status = get_option('osc_order_status_default', 'processing');
$networks = get_option('osc_available_networks', array());
$agents = count(get_users(array('role'=>'ocean_service_agent')));
$customers = count(get_users(array('role'=>'ocean_service_customer')));
$wc_customers = count(get_users(array('role'=>'customer')));
$wallet_logs = count(get_option('osc_wallet_tx_logs',[]));
?>
<div class="wrap">
<h1>Ocean Service — System Status</h1>
<table class="widefat fixed" cellspacing="0">
<thead>
<tr>
<th id="columnname" class="manage-column column-columnname" scope="col" style="width: 30%;">Setting</th>
<th id="columnname" class="manage-column column-columnname" scope="col">Value</th>
</tr>
</thead>
<tbody>
<tr>
<td>WordPress Version</td>
<td><?php echo get_bloginfo('version'); ?></td>
</tr>
<tr>
<td>WooCommerce Active</td>
<td><?php echo $wc; ?></td>
</tr>
<tr>
<td>Paystack Configured</td>
<td><?php echo $pay; ?></td>
</tr>
<tr>
<td>Notification Webhook</td>
<td><?php echo esc_html($webhook); ?></td>
</tr>
<tr>
<td>Default Order Status</td>
<td><?php echo esc_html($default_status); ?></td>
</tr>
<tr>
<td>Ocean Service Agents</td>
<td><?php echo $agents; ?></td>
</tr>
<tr>
<td>Ocean Service Customers</td>
<td><?php echo $customers; ?></td>
</tr>
<tr>
<td>WooCommerce Customers</td>
<td><?php echo $wc_customers; ?></td>
</tr>
<tr>
<td>Wallet tx logs</td>
<td><?php echo $wallet_logs; ?></td>
</tr>
</tbody>
</table>
</div>
<?php
}

/* ---------------------------
Settings Page (Paystack, Webhook, Commission, Order Status)
--------------------------- */
function osc_admin_settings_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

if($_POST && check_admin_referer('osc_settings_save','osc_settings_nonce')){
update_option('osc_company_name', sanitize_text_field($_POST['osc_company_name'] ?? 'Ocean Service'));
update_option('osc_agent_fee', floatval($_POST['osc_agent_fee'] ?? 0));
update_option('osc_paystack_public', sanitize_text_field($_POST['osc_paystack_public'] ?? ''));
update_option('osc_paystack_secret', sanitize_text_field($_POST['osc_paystack_secret'] ?? ''));
update_option('osc_notification_webhook', esc_url_raw($_POST['osc_notification_webhook'] ?? ''));
update_option('osc_commission_percent', floatval($_POST['osc_commission_percent'] ?? 5));
update_option('osc_order_status_default', sanitize_text_field($_POST['osc_order_status_default'] ?? 'processing'));
echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
}

$company = get_option('osc_company_name','Ocean Service');
$agent_fee = get_option('osc_agent_fee', 10);
$pub = get_option('osc_paystack_public','');
$sec = get_option('osc_paystack_secret','');
$webhook = get_option('osc_notification_webhook','');
$commission = get_option('osc_commission_percent',5);
$default_status = get_option('osc_order_status_default', 'processing');
?>
<div class="wrap">
<h1><?php echo esc_html($company); ?> — Settings</h1>
<form method="post">
<?php wp_nonce_field('osc_settings_save','osc_settings_nonce'); ?>
<table class="form-table">
<tbody>
<tr>
<th scope="row"><label for="osc_company_name">Company Name</label></th>
<td><input name="osc_company_name" type="text" id="osc_company_name" value="<?php echo esc_attr($company); ?>" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="osc_agent_fee">Agent Registration Fee</label></th>
<td><input name="osc_agent_fee" type="number" step="0.01" id="osc_agent_fee" value="<?php echo esc_attr($agent_fee); ?>" class="regular-text">
<p class="description">Amount to credit agent wallet on registration.</p></td>
</tr>
<tr>
<th scope="row"><label for="osc_paystack_public">Paystack Public Key</label></th>
<td><input name="osc_paystack_public" type="text" id="osc_paystack_public" value="<?php echo esc_attr($pub); ?>" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="osc_paystack_secret">Paystack Secret Key</label></th>
<td><input name="osc_paystack_secret" type="text" id="osc_paystack_secret" value="<?php echo esc_attr($sec); ?>" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="osc_notification_webhook">Notification Webhook URL</label></th>
<td><input name="osc_notification_webhook" type="url" id="osc_notification_webhook" value="<?php echo esc_attr($webhook); ?>" class="regular-text">
<button type="button" id="osc-test-webhook" class="button">Test</button> <span id="osc-test-webhook-status"></span></td>
</tr>
<tr>
<th scope="row"><label for="osc_commission_percent">Agent Commission (%)</label></th>
<td><input name="osc_commission_percent" type="number" step="0.1" id="osc_commission_percent" value="<?php echo esc_attr($commission); ?>" class="small-text"></td>
</tr>
<tr>
<th scope="row"><label for="osc_order_status_default">Default Order Status</label></th>
<td>
<select name="osc_order_status_default" id="osc_order_status_default">
<?php foreach(wc_get_order_statuses() as $key => $label): ?>
<option value="<?php echo esc_attr(str_replace('wc-','',$key)); ?>" <?php selected($default_status, str_replace('wc-','',$key)); ?>><?php echo esc_html($label); ?></option>
<?php endforeach; ?>
</select>
</td>
</tr>
</tbody>
</table>
<?php submit_button(); ?>
</form>
</div>
<script>
jQuery(document).ready(function($){
$('#osc-test-webhook').click(function(){
var status = $('#osc-test-webhook-status');
status.text('Sending...');
$.post(ajaxurl, {action:'osc_admin_test_webhook', nonce:'<?php echo wp_create_nonce('osc_webhook_test'); ?>'}, function(r){
if(r.success) status.text('Success: '+r.message);
else status.text('Error: '+r.message);
});
});
});
</script>
<?php
}

/* Test webhook AJAX */
add_action('wp_ajax_osc_admin_test_webhook','osc_admin_test_webhook');
function osc_admin_test_webhook(){
if(!current_user_can('manage_options')) osc_json_exit(['success'=>false,'message'=>'Unauthorized']);
check_ajax_referer('osc_webhook_test','nonce');

$url = get_option('osc_notification_webhook','');
if(empty($url)) osc_json_exit(['success'=>false,'message'=>'Webhook not configured']);

$payload = array('event'=>'test','time'=>current_time('mysql'),'message'=>'Ocean Service test webhook');
$resp = wp_remote_post($url, array('body'=>wp_json_encode($payload),'headers'=>array('Content-Type'=>'application/json'),'timeout'=>15));

if(is_wp_error($resp)){
$err = $resp->get_error_message();
osc_api_log('webhook_test_error', $err, $payload);
osc_json_exit(['success'=>false,'message'=>$err]);
}

$code = wp_remote_retrieve_response_code($resp);
$body = wp_remote_retrieve_body($resp);
osc_api_log('webhook_test_response', 'HTTP '.$code.' Body: '.$body, $payload);
osc_json_exit(['success'=>true,'message'=>'Sent, response code '.$code]);
}

/* ---------------------------
Network Products assignment
--------------------------- */
function osc_network_products_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_network_products_save']) && check_admin_referer('osc_network_products_save','osc_network_products_nonce')){
$network_products = array();
if(isset($_POST['osc_network_products']) && is_array($_POST['osc_network_products'])){
foreach($_POST['osc_network_products'] as $network => $product_ids){
$network_products[$network] = array_map('intval', $product_ids);
}
}
update_option('osc_network_products',$network_products);
echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
}

$products = array();
if(class_exists('WC_Product')) $products = wc_get_products(array('limit'=>-1,'status'=>'publish'));
$network_products = get_option('osc_network_products', array(
'MTN'=>[],
'Telecel'=>[],
'Airteltigo'=>[],
'Airteltigo iShare'=>[],
'Bigtime'=>[],
'AFA Registration'=>[],
'Call Minutes'=>[],
'Result Checkers'=>[]
));
?>
<div class="wrap">
<h1>Network Products Assignment</h1>
<form method="post">
<?php wp_nonce_field('osc_network_products_save','osc_network_products_nonce'); ?>
<table class="form-table">
<?php foreach($network_products as $network => $assigned_ids): ?>
<tr>
<th scope="row"><?php echo esc_html($network); ?></th>
<td>
<select name="osc_network_products[<?php echo esc_attr($network); ?>][]" multiple class="wc-product-search" style="width: 50%;">
<?php foreach($assigned_ids as $pid): $p = wc_get_product($pid); if($p): ?>
<option value="<?php echo esc_attr($pid); ?>" selected="selected"><?php echo esc_html($p->get_formatted_name()); ?></option>
<?php endif; endforeach; ?>
</select>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php submit_button('Save Changes','primary','osc_network_products_save'); ?>
</form>
</div>
<?php
}

/* ---------------------------
Wallet functions (single wallet)
--------------------------- */
function osc_wallet_key(){ return 'osc_wallet_balance'; }

function osc_get_wallet_balance($user_id){
if(!$user_id) return 0.00;
return floatval(get_user_meta($user_id, osc_wallet_key(), true) ?: 0.00);
}

function osc_set_wallet_balance($user_id, $amount){
if(!$user_id) return false;
update_user_meta($user_id, osc_wallet_key(), floatval($amount));
return true;
}

function osc_credit_wallet($user_id, $amount, $note='Admin credit'){
if(!$user_id) return false;
$cur = osc_get_wallet_balance($user_id);
$new = $cur + floatval($amount);
osc_set_wallet_balance($user_id, $new);
osc_log_wallet_tx($user_id, floatval($amount), 'credit', $note);
return true;
}

function osc_deduct_wallet($user_id, $amount, $note='Order payment'){
if(!$user_id) return false;
$cur = osc_get_wallet_balance($user_id);
if($cur < floatval($amount)) return false;
$new = $cur - floatval($amount);
osc_set_wallet_balance($user_id, $new);
osc_log_wallet_tx($user_id, -1 * floatval($amount), 'debit', $note);
return true;
}

function osc_log_wallet_tx($user_id, $amount, $type='credit', $note=''){
$logs = get_option('osc_wallet_tx_logs', []);
$logs[] = array(
'user_id' => intval($user_id),
'amount' => floatval($amount),
'type' => $type,
'note' => sanitize_text_field($note),
'time' => current_time('mysql')
);
update_option('osc_wallet_tx_logs', $logs);
}

/* ---------------------------
Purchase & API logs
--------------------------- */
function osc_log_purchase($data){
$logs = get_option('osc_purchase_logs', []);
$logs[] = array_merge($data, array('time'=>current_time('mysql')));
update_option('osc_purchase_logs', $logs);
}

function osc_api_log($type, $message, $payload=array()){
$logs = get_option('osc_api_error_logs', []);
$logs[] = array('type'=>$type,'message'=>$message,'payload'=>$payload,'time'=>current_time('mysql'));
update_option('osc_api_error_logs', $logs);
}

/* ---------------------------
Admin Wallet page (manual credit/deduct)
--------------------------- */
function osc_wallet_admin_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_admin_wallet_action']) && check_admin_referer('osc_admin_wallet','osc_admin_wallet_nonce')){
$identifier = sanitize_text_field($_POST['osc_admin_user']);
$amount = floatval($_POST['osc_admin_amount']);
$action = sanitize_text_field($_POST['osc_admin_action']);

$user = null;
if(is_email($identifier)) $user = get_user_by('email',$identifier);

if(!$user){
$users = get_users(array('search'=>'*'.esc_sql($identifier).'*','search_columns'=>array('user_login','display_name','user_email'),'number'=>1));
if(!empty($users)) $user = $users[0];
}

if(!$user){
echo '<div class="notice notice-error"><p>User not found.</p></div>';
} else {
if($action === 'credit'){
osc_credit_wallet($user->ID, $amount, 'Admin credit');
echo '<div class="notice notice-success is-dismissible"><p>Credited GHS '.number_format($amount,2).' to '.esc_html($user->display_name).'</p></div>';
} else {
$ok = osc_deduct_wallet($user->ID, $amount, 'Admin deduction');
if($ok) echo '<div class="notice notice-success is-dismissible"><p>Deducted GHS '.number_format($amount,2).' from '.esc_html($user->display_name).'</p></div>';
else echo '<div class="notice notice-error"><p>Insufficient balance.</p></div>';
}
}
}

$users = get_users(array('orderby'=>'display_name','number'=>200));
?>
<div class="wrap">
<h1>Wallet Management</h1>

<div class="card">
<h2 class="title">Quick Wallet Action</h2>
<form method="post">
<?php wp_nonce_field('osc_admin_wallet','osc_admin_wallet_nonce'); ?>
<table class="form-table">
<tr>
<th scope="row"><label for="osc_admin_user">User</label></th>
<td><input name="osc_admin_user" type="text" id="osc_admin_user" placeholder="Email, username, or name" class="regular-text"></td>
</tr>
<tr>
<th scope="row"><label for="osc_admin_amount">Amount</label></th>
<td><input name="osc_admin_amount" type="number" step="0.01" id="osc_admin_amount" class="regular-text"></td>
</tr>
<tr>
<th scope="row">Action</th>
<td>
<fieldset>
<label><input type="radio" name="osc_admin_action" value="credit" checked> Credit</label>
<label><input type="radio" name="osc_admin_action" value="deduct"> Deduct</label>
</fieldset>
</td>
</tr>
</table>
<?php submit_button('Apply Action','primary','osc_admin_wallet_action'); ?>
</form>
</div>

<h2 class="title" style="margin-top:2rem;">User Balances</h2>
<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th scope="col" class="manage-column">Name</th>
<th scope="col" class="manage-column">Email</th>
<th scope="col" class="manage-column">Balance</th>
<th scope="col" class="manage-column">Role</th>
</tr>
</thead>
<tbody>
<?php foreach($users as $u){
$bal = osc_get_wallet_balance($u->ID);
$roles = $u->roles;
$role_display = '';
if(in_array('ocean_service_agent', $roles)) {
$role_display = 'Ocean Service Agent';
} elseif(in_array('ocean_service_customer', $roles)) {
$role_display = 'Ocean Service Customer';
} elseif(in_array('customer', $roles)) {
$role_display = 'Customer';
} else {
$role_display = implode(', ', $roles);
}
echo '<tr>
<td>'.esc_html($u->display_name).'</td>
<td>'.esc_html($u->user_email).'</td>
<td>'.number_format($bal,2).'</td>
<td>'.esc_html($role_display).'</td>
</tr>';
} ?>
</tbody>
</table>
</div>
<?php
}

/* ---------------------------
New: Admin Wallet Top-up with User Selection
--------------------------- */
function osc_wallet_topup_admin_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_admin_topup_action']) && check_admin_referer('osc_admin_topup','osc_admin_topup_nonce')){
$identifier = sanitize_text_field($_POST['osc_admin_user']);
$amount = floatval($_POST['osc_admin_amount']);
$note = sanitize_text_field($_POST['osc_admin_note'] ?? 'Admin top-up');

$user = null;
if(is_email($identifier)) {
$user = get_user_by('email',$identifier);
} else {
// Search by username or display name
$users = get_users(array(
'search'=>'*'.esc_sql($identifier).'*',
'search_columns'=>array('user_login','display_name','user_email'),
'number'=>1
));
if(!empty($users)) $user = $users[0];
}

if(!$user){
echo '<div class="notice notice-error"><p>User not found.</p></div>';
} else {
// Credit the wallet
osc_credit_wallet($user->ID, $amount, $note);
echo '<div class="notice notice-success is-dismissible"><p>Successfully topped up GHS '.number_format($amount,2).' to '.esc_html($user->display_name).'</p></div>';
}
}

// Get recent users for quick selection
$recent_users = get_users(array('orderby'=>'registered','order'=>'DESC','number'=>10));
?>
<div class="wrap">
<h1>Wallet Top-up</h1>
<div class="card">
<h2 class="title">Top-up User Wallet</h2>
<form method="post" id="osc-topup-form">
<?php wp_nonce_field('osc_admin_topup','osc_admin_topup_nonce'); ?>
<table class="form-table">
<tr>
<th scope="row"><label for="osc_admin_user_search">User Search</label></th>
<td>
<input type="text" id="osc_admin_user_search" placeholder="Type to search..." class="regular-text">
<input type="hidden" name="osc_admin_user" id="osc_admin_user_selected">
<div id="osc-user-search-results"></div>
</td>
</tr>
<tr>
<th scope="row"><label for="osc_admin_amount">Amount</label></th>
<td><input name="osc_admin_amount" type="number" step="0.01" id="osc_admin_amount" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="osc_admin_note">Note (Optional)</label></th>
<td><input name="osc_admin_note" type="text" id="osc_admin_note" class="regular-text" placeholder="Admin top-up"></td>
</tr>
</table>
<?php submit_button('Top-up Wallet','primary','osc_admin_topup_action'); ?>
</form>
</div>

<h2 class="title" style="margin-top: 2rem;">Recent Users</h2>
<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th scope="col">Name</th>
<th scope="col">Email</th>
<th scope="col">Balance</th>
<th scope="col">Role</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($recent_users as $u){
$bal = osc_get_wallet_balance($u->ID);
$roles = $u->roles;
$role_display = '';
if(in_array('ocean_service_agent', $roles)) {
$role_display = 'Ocean Service Agent';
} elseif(in_array('ocean_service_customer', $roles)) {
$role_display = 'Ocean Service Customer';
} elseif(in_array('customer', $roles)) {
$role_display = 'Customer';
} else {
$role_display = implode(', ', $roles);
}
echo '<tr>';
echo '<td>'.esc_html($u->display_name).'</td>';
echo '<td>'.esc_html($u->user_email).'</td>';
echo '<td>'.number_format($bal,2).'</td>';
echo '<td>'.esc_html($role_display).'</td>';
echo '<td><button class="button select-user" data-email="'.esc_attr($u->user_email).'">Select</button></td>';
echo '</tr>';
} ?>
</tbody>
</table>
</div>
<script>
jQuery(document).ready(function($){
var searchTimeout;
$('#osc_admin_user_search').on('keyup', function(){
clearTimeout(searchTimeout);
var query = $(this).val();
if(query.length < 3) {
$('#osc-user-search-results').empty();
return;
}
searchTimeout = setTimeout(function(){
$.get(ajaxurl, {
action: 'osc_search_users',
query: query
}, function(response) {
var resultsDiv = $('#osc-user-search-results');
resultsDiv.empty();
if(response.success && response.data.length > 0) {
var list = $('<ul>');
response.data.forEach(function(user){
list.append('<li data-email="'+user.email+'">'+user.display_name+' ('+user.email+')</li>');
});
resultsDiv.html(list);
} else {
resultsDiv.text('No users found.');
}
});
}, 500);
});

$(document).on('click', '#osc-user-search-results li', function(){
var email = $(this).data('email');
$('#osc_admin_user_search').val($(this).text());
$('#osc_admin_user_selected').val(email);
$('#osc-user-search-results').empty();
});

$('.select-user').on('click', function(){
var email = $(this).data('email');
var name = $(this).closest('tr').find('td:first').text();
$('#osc_admin_user_search').val(name + ' (' + email + ')');
$('#osc_admin_user_selected').val(email);
$('html, body').animate({ scrollTop: 0 }, 'slow');
});
});
</script>
<style>
#osc-user-search-results { border: 1px solid #ddd; max-height: 200px; overflow-y: auto; background: #fff; }
#osc-user-search-results ul { margin: 0; padding: 0; list-style: none; }
#osc-user-search-results li { padding: 8px; cursor: pointer; }
#osc-user-search-results li:hover { background: #f0f0f0; }
</style>
<?php
}

/* ---------------------------
New: Order History Page
--------------------------- */
function osc_orders_admin_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

$user_id = sanitize_text_field($_GET['user_id'] ?? '');
$status = sanitize_text_field($_GET['status'] ?? '');
$network = sanitize_text_field($_GET['network'] ?? '');
$from = sanitize_text_field($_GET['from'] ?? '');
$to = sanitize_text_field($_GET['to'] ?? '');
$export = isset($_GET['export']) ? true : false;

$logs = get_option('osc_purchase_logs', []);
$results = [];

foreach($logs as $l){
// Filter by user if specified
if($user_id && ($l['user_id'] ?? 0) != $user_id) continue;
// Filter by status if specified
if($status && ($l['status'] ?? '') !== $status) continue;
// Filter by network if specified
if($network && ($l['network'] ?? '') !== $network) continue;
// Filter by date range
if($from && strtotime($l['time']) < strtotime($from)) continue;
if($to && strtotime($l['time']) > strtotime($to . ' 23:59:59')) continue;
$results[] = $l;
}

if($export){
$filename = 'ocean_orders_'.date('YmdHis').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename='.$filename);
$out = fopen('php://output','w');
fputcsv($out, ['Time','User ID','User Name','Email','Network','Product ID','Product Name','Amount','Commission','Status','Phone']);
foreach($results as $r){
fputcsv($out, [
$r['time'],
$r['user_id'] ?? '',
$r['user_name'] ?? '',
$r['user_email'] ?? '',
$r['network'] ?? '',
$r['product_id'] ?? '',
$r['product_name'] ?? '',
$r['amount'] ?? '',
$r['commission'] ?? '',
$r['status'] ?? '',
($r['meta']['phone'] ?? '')
]);
}
exit;
}

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
$status_options = array('pending', 'processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed', 'topup');
?>
<div class="wrap">
<h1>Order History</h1>
<form method="get">
<input type="hidden" name="page" value="osc-orders">
<input type="text" name="user_id" placeholder="User ID" value="<?php echo esc_attr($user_id); ?>">
<input type="date" name="from" value="<?php echo esc_attr($from); ?>">
<input type="date" name="to" value="<?php echo esc_attr($to); ?>">
<select name="status">
<option value="">All Statuses</option>
<?php foreach($status_options as $s): ?>
<option value="<?php echo esc_attr($s); ?>" <?php selected($status, $s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
<?php endforeach; ?>
</select>
<select name="network">
<option value="">All Networks</option>
<?php foreach($networks as $n): ?>
<option value="<?php echo esc_attr($n); ?>" <?php selected($network, $n); ?>><?php echo esc_html($n); ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="button">Filter</button>
<button type="submit" name="export" value="1" class="button">Export CSV</button>
</form>

<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th>Time</th>
<th>User</th>
<th>Email</th>
<th>Network</th>
<th>Product</th>
<th>Amount</th>
<th>Commission</th>
<th>Status</th>
<th>Phone</th>
</tr>
</thead>
<tbody>
<?php if(empty($results)){
echo '<tr><td colspan="9">No orders</td></tr>';
} else {
foreach(array_reverse($results) as $r){
echo '<tr>';
echo '<td>'.esc_html($r['time']).'</td>';
echo '<td>'.esc_html($r['user_name'] ?? '').'</td>';
echo '<td>'.esc_html($r['user_email'] ?? '').'</td>';
echo '<td>'.esc_html($r['network'] ?? '').'</td>';
echo '<td>'.esc_html(($r['product_name'] ?? '') . ' (ID:' . ($r['product_id'] ?? '') . ')').'</td>';
echo '<td>GHS '.number_format($r['amount'] ?? 0,2).'</td>';
echo '<td>GHS '.number_format($r['commission'] ?? 0,2).'</td>';
echo '<td>'.esc_html($r['status'] ?? '').'</td>';
echo '<td>'.esc_html($r['meta']['phone'] ?? '').'</td>';
echo '</tr>';
}
} ?>
</tbody>
</table>
</div>
<?php
}

/* ---------------------------
New: Agent Registration Page
--------------------------- */
function osc_agent_registration_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_agent_registration_submit']) && check_admin_referer('osc_agent_registration','osc_agent_registration_nonce')){
$first_name = sanitize_text_field($_POST['osc_first_name'] ?? '');
$last_name = sanitize_text_field($_POST['osc_last_name'] ?? '');
$email = sanitize_email($_POST['osc_email'] ?? '');
$username = sanitize_user($_POST['osc_username'] ?? '');
$password = $_POST['osc_password'] ?? '';
$confirm_password = $_POST['osc_confirm_password'] ?? '';

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
// Create new user with ocean_service_customer role first (this will be the default)
$user_id = wp_insert_user(array(
'user_login' => $username,
'user_pass' => $password,
'user_email' => $email,
'first_name' => $first_name,
'last_name' => $last_name,
'role' => 'ocean_service_customer' // Start as ocean service customer
));

if(is_wp_error($user_id)) {
echo '<div class="notice notice-error"><p>' . $user_id->get_error_message() . '</p></div>';
} else {
// Now upgrade to ocean service agent role
$user = get_userdata($user_id);
$user->remove_role('ocean_service_customer');
$user->add_role('ocean_service_agent');

// Add welcome message to wallet
osc_credit_wallet($user_id, get_option('osc_agent_fee', 10), 'Welcome bonus for agent registration');

echo '<div class="notice notice-success is-dismissible"><p>Agent created successfully! Username: ' . esc_html($username) . '</p></div>';
}
}
}
?>
<div class="wrap">
<h1>Agent Registration</h1>
<div class="card">
<h2 class="title">Create a new agent account with automatic role assignment.</h2>
<form method="post">
<?php wp_nonce_field('osc_agent_registration','osc_agent_registration_nonce'); ?>
<table class="form-table">
<tr>
<th scope="row"><label for="osc_first_name">First Name</label></th>
<td><input name="osc_first_name" type="text" id="osc_first_name" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="osc_last_name">Last Name</label></th>
<td><input name="osc_last_name" type="text" id="osc_last_name" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="osc_email">Email</label></th>
<td><input name="osc_email" type="email" id="osc_email" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="osc_username">Username</label></th>
<td><input name="osc_username" type="text" id="osc_username" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="osc_password">Password</label></th>
<td><input name="osc_password" type="password" id="osc_password" class="regular-text" required></td>
</tr>
<tr>
<th scope="row"><label for="osc_confirm_password">Confirm Password</label></th>
<td><input name="osc_confirm_password" type="password" id="osc_confirm_password" class="regular-text" required></td>
</tr>
</table>
<?php submit_button('Register Agent','primary','osc_agent_registration_submit'); ?>
</form>
</div>
</div>
<?php
}

/* ---------------------------
AJAX: Search Users for Top-up
--------------------------- */
add_action('wp_ajax_osc_search_users', 'osc_ajax_search_users');
function osc_ajax_search_users(){
if(!current_user_can('manage_options')) {
wp_send_json_error('Unauthorized');
exit;
}

$query = sanitize_text_field($_GET['query'] ?? '');
if(empty($query)) {
wp_send_json_success([]);
exit;
}

$users = get_users(array(
'search' => '*' . esc_sql($query) . '*',
'search_columns' => array('user_login', 'display_name', 'user_email'),
'number' => 10
));

$results = array();
foreach($users as $user) {
$results[] = array(
'id' => $user->ID,
'email' => $user->user_email,
'username' => $user->user_login,
'display_name' => $user->display_name
);
}

wp_send_json_success($results);
}

/* ---------------------------
Purchases admin page (filters + CSV)
--------------------------- */
function osc_purchases_admin_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

$filter_user = sanitize_text_field($_GET['filter_user'] ?? '');
$filter_net = sanitize_text_field($_GET['filter_network'] ?? '');
$from = sanitize_text_field($_GET['from'] ?? '');
$to = sanitize_text_field($_GET['to'] ?? '');
$export = isset($_GET['export']) ? true : false;

$logs = get_option('osc_purchase_logs', []);
$results = [];

foreach($logs as $l){
if($filter_user && stripos($l['user_email'] ?? '', $filter_user) === false && stripos($l['user_name'] ?? '', $filter_user) === false) continue;
if($filter_net && ($l['network'] ?? '') !== $filter_net) continue;
if($from && strtotime($l['time']) < strtotime($from)) continue;
if($to && strtotime($l['time']) > strtotime($to . ' 23:59:59')) continue;
$results[] = $l;
}

if($export){
$filename = 'ocean_purchases_'.date('YmdHis').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename='.$filename);
$out = fopen('php://output','w');
fputcsv($out, ['Time','User','Email','Network','Product ID','Product Name','Amount','Commission','Status','Meta']);
foreach($results as $r){
fputcsv($out, [$r['time'],$r['user_name'] ?? '',$r['user_email'] ?? '',$r['network'] ?? '',$r['product_id'] ?? '',$r['product_name'] ?? '',$r['amount'] ?? '',$r['commission'] ?? '',$r['status'] ?? '', json_encode($r['meta'] ?? [])]);
}
exit;
}

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
?>
<div class="wrap">
<h1>Purchases Log</h1>
<form method="get">
<input type="hidden" name="page" value="osc-purchases">
<input type="text" name="filter_user" placeholder="User email/name" value="<?php echo esc_attr($filter_user); ?>">
<input type="date" name="from" value="<?php echo esc_attr($from); ?>">
<input type="date" name="to" value="<?php echo esc_attr($to); ?>">
<select name="filter_network">
<option value="">All Networks</option>
<?php foreach($networks as $n): ?>
<option value="<?php echo esc_attr($n); ?>" <?php selected($filter_net, $n); ?>><?php echo esc_html($n); ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="button">Filter</button>
<button type="submit" name="export" value="1" class="button">Export CSV</button>
</form>

<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th>Time</th>
<th>User</th>
<th>Email</th>
<th>Network</th>
<th>Product</th>
<th>Amount</th>
<th>Commission</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php if(empty($results)){
echo '<tr><td colspan="8">No purchases</td></tr>';
} else {
foreach(array_reverse($results) as $r){
echo '<tr>';
echo '<td>'.esc_html($r['time']).'</td>';
echo '<td>'.esc_html($r['user_name'] ?? '').'</td>';
echo '<td>'.esc_html($r['user_email'] ?? '').'</td>';
echo '<td>'.esc_html($r['network'] ?? '').'</td>';
echo '<td>'.esc_html(($r['product_name'] ?? '') . ' (ID:' . ($r['product_id'] ?? '') . ')').'</td>';
echo '<td>GHS '.number_format($r['amount'] ?? 0,2).'</td>';
echo '<td>GHS '.number_format($r['commission'] ?? 0,2).'</td>';
echo '<td>'.esc_html($r['status'] ?? '').'</td>';
echo '</tr>';
}
} ?>
</tbody>
</table>
</div>
<?php
}

/* ---------------------------
API Error logs admin page
--------------------------- */
function osc_api_errors_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');
$logs = array_reverse(get_option('osc_api_error_logs', []));
?>
<div class="wrap">
<h1>API / Webhook Error Logs</h1>
<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th style="width:150px;">Time</th>
<th style="width:120px;">Type</th>
<th>Message</th>
<th>Payload</th>
</tr>
</thead>
<tbody>
<?php if(empty($logs)){
echo '<tr><td colspan="4">No logs</td></tr>';
} else {
foreach($logs as $l){
echo '<tr>
<td>'.esc_html($l['time']).'</td>
<td>'.esc_html($l['type']).'</td>
<td><pre>'.esc_html($l['message']).'</pre></td>
<td><pre>'.esc_html(json_encode($l['payload'])).'</pre></td>
</tr>';
}
} ?>
</tbody>
</table>
</div>
<?php
}

/* ---------------------------
Role Pricing Page
--------------------------- */
function osc_role_pricing_page(){
if(!current_user_can('manage_options')) wp_die('Unauthorized');

if(isset($_POST['osc_role_pricing_save']) && check_admin_referer('osc_role_pricing_save','osc_role_pricing_nonce')){
$role_pricing = array();
if(isset($_POST['osc_role_pricing']) && is_array($_POST['osc_role_pricing'])){
foreach($_POST['osc_role_pricing'] as $product_id => $prices){
// Only save if prices are different from original
$product = wc_get_product($product_id);
$original_price = $product ? floatval($product->get_price()) : 0;

$agent_price = !empty($prices['agent']) ? floatval($prices['agent']) : $original_price;
$customer_price = !empty($prices['customer']) ? floatval($prices['customer']) : $original_price;

$role_pricing[$product_id] = array(
'agent' => $agent_price,
'customer' => $customer_price
);
}
}
update_option('osc_role_pricing', $role_pricing);
echo '<div class="notice notice-success is-dismissible"><p>Role prices saved successfully.</p></div>';
}

$products = array();
if(class_exists('WC_Product')) $products = wc_get_products(array('limit'=>-1,'status'=>'publish'));
$role_pricing = get_option('osc_role_pricing', array());

?>
<div class="wrap">
<h1>Role-Based Pricing</h1>
<p>Set different prices for agents and customers for each product. Leave blank to use original price.</p>
<form method="post">
<?php wp_nonce_field('osc_role_pricing_save','osc_role_pricing_nonce'); ?>
<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th>Product</th>
<th>Original Price</th>
<th>Agent Price</th>
<th>Customer Price</th>
</tr>
</thead>
<tbody>
<?php foreach($products as $p):
$pid = $p->get_id();
$original = floatval($p->get_price());
$agent_price = $role_pricing[$pid]['agent'] ?? '';
$customer_price = $role_pricing[$pid]['customer'] ?? '';
?>
<tr>
<td><?php echo $p->get_name(); ?></td>
<td><?php echo wc_price($original); ?></td>
<td><input type="number" step="0.01" name="osc_role_pricing[<?php echo $pid; ?>][agent]" value="<?php echo esc_attr($agent_price); ?>" placeholder="<?php echo wc_price($original); ?>"></td>
<td><input type="number" step="0.01" name="osc_role_pricing[<?php echo $pid; ?>][customer]" value="<?php echo esc_attr($customer_price); ?>" placeholder="<?php echo wc_price($original); ?>"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php submit_button('Save Prices','primary','osc_role_pricing_save'); ?>
</form>
</div>
<?php
}

/* ---------------------------
Utility: get price for user with role-based pricing
--------------------------- */
function osc_get_price_for_user($product_id, $user_id = 0){
$product = wc_get_product($product_id);
if(!$product) return 0.00;
$base_price = floatval($product->get_price());

// Get role pricing
$role_pricing = get_option('osc_role_pricing', array());

// Determine user role
$is_agent = false;
$is_customer = false;

if($user_id && $user = get_userdata($user_id)){
$roles = (array)$user->roles;
$is_agent = in_array('ocean_service_agent', $roles);
$is_customer = in_array('ocean_service_customer', $roles) || in_array('customer', $roles) || (!in_array('ocean_service_agent', $roles) && count(array_intersect($roles, array('administrator', 'shop_manager'))) === 0);
} else {
// Not logged in - treat as customer
$is_customer = true;
}

// Calculate final price based on role
if($is_agent && isset($role_pricing[$product_id]['agent'])) {
return floatval($role_pricing[$product_id]['agent']);
} elseif($is_customer && isset($role_pricing[$product_id]['customer'])) {
return floatval($role_pricing[$product_id]['customer']);
} else {
// Fallback to base price
return $base_price;
}
}

/* ---------------------------
AJAX: get network products (respects per-user pricing)
--------------------------- */
add_action('wp_ajax_osc_get_network_products','osc_ajax_get_network_products');
add_action('wp_ajax_nopriv_osc_get_network_products','osc_ajax_get_network_products');
function osc_ajax_get_network_products(){
$network = sanitize_text_field($_GET['network'] ?? '');
$mapping = get_option('osc_network_products', array(
'MTN'=>[],
'Telecel'=>[],
'Airteltigo'=>[],
'Airteltigo iShare'=>[],
'Bigtime'=>[],
'AFA Registration'=>[],
'Call Minutes'=>[],
'Result Checkers'=>[]
));
$res = [];

if(isset($mapping[$network]) && is_array($mapping[$network])){
foreach($mapping[$network] as $pid){
$p = wc_get_product($pid);
if(!$p) continue;
$price = osc_get_price_for_user($pid, get_current_user_id());
$res[] = array('id'=>$pid,'name'=>$p->get_name(),'price'=>$price);
}
}

wp_send_json($res);
}

/* ---------------------------
AJAX: process purchase
- Deduct wallet if method=wallet
- If method=paystack, create a pending purchase and return paystack checkout data
- Create Woo order (completed)
- Credit commission to agent (auto)
- Log purchase
- Send external webhook
- Return JSON status
--------------------------- */
add_action('wp_ajax_osc_process_purchase','osc_process_purchase');
add_action('wp_ajax_nopriv_osc_process_purchase','osc_process_purchase');
function osc_process_purchase(){
$network = sanitize_text_field($_GET['network'] ?? '');
$product_id = intval($_GET['product'] ?? 0);
$phone = sanitize_text_field($_GET['phone'] ?? '');
$method = sanitize_text_field($_GET['method'] ?? 'wallet');
$agent_phone = sanitize_text_field($_GET['agent_phone'] ?? '');

if(empty($network) || empty($product_id) || empty($phone)){
osc_json_exit(['status'=>'error','message'=>'Provide network, product and phone.']);
}

$product = wc_get_product($product_id);
if(!$product) osc_json_exit(['status'=>'error','message'=>'Product not found.']);

$user_id = is_user_logged_in() ? get_current_user_id() : 0;
$price = osc_get_price_for_user($product_id, $user_id);

// Payment handling
if($method === 'wallet'){
if(!$user_id) osc_json_exit(['status'=>'error','message'=>'Login to pay with wallet']);
if(!osc_deduct_wallet($user_id, $price, 'Bundle purchase - '.$product_id)){
osc_json_exit(['status'=>'error','message'=>'Insufficient wallet balance.']);
}
} elseif($method === 'paystack'){
// Create a pending purchase entry and return paystack init info (simple flow)
$paystack_public = get_option('osc_paystack_public','');
$paystack_secret = get_option('osc_paystack_secret','');
if(empty($paystack_public) || empty($paystack_secret)) osc_json_exit(['status'=>'error','message'=>'Paystack not configured.']);

// Create a temporary purchase log with status pending
$purchase = array(
'user_id' => $user_id,
'user_name' => $user_id ? wp_get_current_user()->display_name : 'Guest',
'user_email' => $user_id ? wp_get_current_user()->user_email : '',
'network' => $network,
'product_id' => $product_id,
'product_name' => $product->get_name(),
'amount' => $price,
'commission' => 0,
'order_id' => 0,
'status' => 'pending_paystack',
'meta' => array('phone'=>$phone)
);
$logs = get_option('osc_purchase_logs', []);
$logs[] = $purchase;
update_option('osc_purchase_logs', $logs);

// Initiate Paystack transaction via server-side (simplified: we return public key + necessary data and expect client to call Paystack)
// For production, you'd create a transaction via Paystack API and return reference here.
osc_json_exit([
'status'=>'paystack',
'message'=>'Use paystack to complete payment',
'paystack_public' => $paystack_public,
'amount' => $price,
'email' => $user_id ? wp_get_current_user()->user_email : 'no-reply@local',
'reference' => 'osc_'.time().'_'.wp_rand(1000,9999)
]);
}

// Create WooCommerce order if available
$order_id = 0;
if(class_exists('WC_Order') && function_exists('wc_get_product')){
try {
$order = wc_create_order();
$order->add_product(wc_get_product($product_id), 1);

if($user_id){
$user = wp_get_current_user();
$order->set_customer_id($user_id);
// ✅ FIX: Set billing phone from user or provided phone
if($user->billing_phone){
$order->set_billing_phone($user->billing_phone);
} else {
$order->set_billing_phone($phone);
}
$order->set_billing_email($user->user_email);
$order->set_billing_first_name($user->first_name);
$order->set_billing_last_name($user->last_name);
} else {
// ✅ FIX: For guest orders, set phone number
$order->set_billing_phone($phone);
$order->set_billing_email('guest@example.com');
$order->set_billing_first_name('Guest');
$order->set_billing_last_name('Customer');
}

$order->calculate_totals();
$default_status = get_option('osc_order_status_default', 'processing');
$order->update_status($default_status,'Order created by Ocean Service');
$order->save();
$order_id = $order->get_id();

// ✅ FIX: Save phone number to multiple meta fields for better compatibility
$order->update_meta_data('_osc_beneficiary', $phone);
$order->update_meta_data('_osc_network', $network);
$order->update_meta_data('_bundle_phone_number', $phone);
$order->update_meta_data('_customer_phone', $phone);

// ✅ FIX: Also save as regular order meta for easy access
$order->update_meta_data('bundle_recipient_phone', $phone);
$order->update_meta_data('bundle_network', $network);
if (!empty($agent_phone)) {
    $order->update_meta_data('agent_phone', $agent_phone);
}

$order->save();

} catch (Exception $e){
osc_api_log('order_create_error', $e->getMessage(), array('product'=>$product_id,'user'=>$user_id));
}
}
// Commission calculation & credit to agent (if purchaser is an agent)
$commission_percent = floatval(get_option('osc_commission_percent', 5));
$commission_amount = round(($commission_percent/100) * $price, 2);
if($user_id && in_array('ocean_service_agent', (array)wp_get_current_user()->roles)){
// credit commission to purchaser (agent)
osc_credit_wallet($user_id, $commission_amount, 'Commission for sale');
}

// Log purchase
$purchase = array(
'user_id' => $user_id,
'user_name' => $user_id ? wp_get_current_user()->display_name : '',
'user_email' => $user_id ? wp_get_current_user()->user_email : '',
'network' => $network,
'product_id' => $product_id,
'product_name' => $product->get_name(),
'amount' => $price,
'commission' => $commission_amount,
'order_id' => $order_id,
'status' => get_option('osc_order_status_default', 'processing'),
'meta' => array('phone'=>$phone)
);
osc_log_purchase($purchase);

// External webhook
$webhook = get_option('osc_notification_webhook','');
if($webhook){
$payload = array('event'=>'bundle_purchase','data'=>$purchase);
$resp = wp_remote_post($webhook, array('body'=>wp_json_encode($payload),'headers'=>array('Content-Type'=>'application/json'),'timeout'=>20));
if(is_wp_error($resp)){
osc_api_log('webhook_error', $resp->get_error_message(), $payload);
} else {
$code = wp_remote_retrieve_response_code($resp);
$body = wp_remote_retrieve_body($resp);
osc_api_log('webhook_response', 'HTTP '.$code.' Body: '.$body, $payload);
}
}

osc_json_exit(['status'=>'success','message'=>'Bundle purchased successfully. Order ID: '.$order_id]);
}

/* ---------------------------
Paystack webhook endpoint (verify and credit wallet on topup)
- listens for ?osc_paystack_webhook=1
--------------------------- */
add_action('init', function(){
if(isset($_GET['osc_paystack_webhook']) && $_GET['osc_paystack_webhook'] == '1'){
header('Content-Type: application/json');
$secret = get_option('osc_paystack_secret','');
$body = file_get_contents('php://input');
$sig = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';
// Optional verification - for now, we accept and decode
$data = json_decode($body, true);

if(!$data){
echo json_encode(['status'=>false,'message'=>'Invalid payload']); exit;
}

// Example structure check
$event = $data['event'] ?? '';
if($event === 'charge.success' || ($data['event'] ?? '') === 'charge.success'){
// parse meta to get user_id and amount
$meta = $data['data']['metadata'] ?? array();
$email = $data['data']['customer']['email'] ?? '';
$amount_ng = ($data['data']['amount'] ?? 0) / 100; // paystack uses kobo
$ref = $data['data']['reference'] ?? '';
// find user by email
$user = null;
if($email) $user = get_user_by('email', $email);
if($user){
// credit wallet
osc_credit_wallet($user->ID, $amount_ng, 'Paystack topup ref '.$ref);
// log purchase/topup
osc_log_purchase(array(
'user_id'=>$user->ID,'user_name'=>$user->display_name,'user_email'=>$user->user_email,
'network'=>'topup','product_id'=>0,'product_name'=>'Top-up','amount'=>$amount_ng,
'commission'=>0,'order_id'=>0,'status'=>'topup','meta'=>array('reference'=>$ref)
));
} else {
osc_api_log('paystack_webhook_no_user','No user for email '.$email, $data);
}
}

echo json_encode(['status'=>true]); exit;
}
});

/* ---------------------------
Modern Purchase Portal Shortcode (Updated Design)
--------------------------- */
add_shortcode('ocean_service_portal','osc_portal_shortcode');
function osc_portal_shortcode(){
ob_start();
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
<h2 class="osc-form-title">Buy Data Bundle</h2>
<div class="osc-form-group">
<label for="osc-network-select" class="osc-label">Choose your network</label>
<div class="osc-network-grid">
<?php foreach($networks as $net): ?>
<button class="osc-network-btn" data-network="<?php echo esc_attr($net); ?>"><?php echo esc_html($net); ?></button>
<?php endforeach; ?>
</div>
<input type="hidden" id="osc-selected-network">
</div>

<div class="osc-form-group">
<label for="osc-bundle-select" class="osc-label">Select Bundle</label>
<select id="osc-bundle-select" class="osc-select" disabled>
<option>Select network first</option>
</select>
</div>

<div class="osc-form-group">
<label for="osc-phone-input" class="osc-label">Recipient Phone Number</label>
<input type="tel" id="osc-phone-input" class="osc-input" placeholder="e.g., 024xxxxxxx">
</div>

<div class="osc-form-group">
<label class="osc-label">Payment Method</label>
<div class="osc-payment-methods">
<div class="osc-payment-option" data-method="wallet">
<span class="osc-payment-icon">💰</span>
<div class="osc-payment-details">
<span class="osc-payment-name">Wallet Balance</span>
<span class="osc-payment-desc">Use your available balance</span>
</div>
</div>
<div class="osc-payment-option" data-method="paystack">
<span class="osc-payment-icon">💳</span>
<div class="osc-payment-details">
<span class="osc-payment-name">Paystack</span>
<span class="osc-payment-desc">Pay with card or mobile money</span>
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
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Show the modal when the purchase button is clicked
    $('#osc-purchase-btn').on('click', function(e) {
        e.preventDefault();
        $('#osc-agent-modal').show();
    });

    // Close the modal when the cancel button is clicked
    $('#osc-modal-cancel').on('click', function() {
        $('#osc-agent-modal').hide();
    });

    // Handle the confirm button click
    $('#osc-modal-confirm').on('click', function() {
        var agentPhone = $('#osc-agent-phone').val();
        var network = $('#osc-selected-network').val();
        var productId = $('#osc-bundle-select').val();
        var recipientPhone = $('#osc-phone-input').val();
        var paymentMethod = $('#osc-selected-payment-method').val();

        // Basic validation
        if (!agentPhone) {
            alert('Please enter your agent phone number.');
            return;
        }

        // Proceed with the actual purchase
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: {
                action: 'osc_process_purchase',
                network: network,
                product: productId,
                phone: recipientPhone,
                method: paymentMethod,
                agent_phone: agentPhone
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while processing your request.');
            }
        });

        // Hide the modal
        $('#osc-agent-modal').hide();
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

if(is_user_logged_in()) {
$user = wp_get_current_user();
$roles = (array)$user->roles;

// If user is already an agent, show message
if(in_array('ocean_service_agent', $roles)) {
return '<div class="notice notice-info"><p>You are already an Ocean Service Agent.</p></div>';
}

// If user is a customer or ocean service customer, allow them to upgrade
if(in_array('ocean_service_customer', $roles) || in_array('customer', $roles)) {
// Handle upgrade form submission
if($_POST && isset($_POST['osc_agent_upgrade_submit']) && check_admin_referer('osc_agent_upgrade','osc_agent_upgrade_nonce')){
// Verify user is still eligible
$current_roles = (array)wp_get_current_user()->roles;
if(in_array('ocean_service_customer', $current_roles) || in_array('customer', $current_roles)) {
// Remove customer roles and add ocean service agent role
$user->remove_role('ocean_service_customer');
$user->remove_role('customer');
$user->add_role('ocean_service_agent');

// Add welcome message to wallet
osc_credit_wallet($user->ID, get_option('osc_agent_fee', 10), 'Welcome bonus for agent registration');

echo '<div class="notice notice-success is-dismissible"><p>You have been upgraded to an Ocean Service Agent! Welcome to our service. You have been credited with GHS ' . get_option('osc_agent_fee', 10) . ' as a welcome bonus.</p></div>';
} else {
echo '<div class="notice notice-error"><p>You are no longer eligible for agent upgrade.</p></div>';
}
}

// Show upgrade form
?>
<div class="osc-agent-upgrade-form">
<h2>Upgrade to Ocean Service Agent</h2>
<p>You are currently a customer. As a customer, you can upgrade to an Ocean Service Agent to enjoy special benefits.</p>
<form method="post">
<?php wp_nonce_field('osc_agent_upgrade','osc_agent_upgrade_nonce'); ?>
<?php submit_button('Upgrade to Agent','primary','osc_agent_upgrade_submit'); ?>
</form>
</div>
<?php
return ob_get_clean();
}

// If user has other roles, don't allow
return '<div class="notice notice-warning"><p>You cannot register as an agent. Only customers can upgrade to Ocean Service Agents.</p></div>';
}

// Not logged in - show registration form for new customers
if($_POST && isset($_POST['osc_customer_register_submit']) && check_admin_referer('osc_customer_register','osc_customer_register_nonce')){
$first_name = sanitize_text_field($_POST['osc_first_name'] ?? '');
$last_name = sanitize_text_field($_POST['osc_last_name'] ?? '');
$email = sanitize_email($_POST['osc_email'] ?? '');
$username = sanitize_user($_POST['osc_username'] ?? '');
$password = $_POST['osc_password'] ?? '';
$confirm_password = $_POST['osc_confirm_password'] ?? '';

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
}

/* ---------------------------
Shortcode: Topup button (Paystack)
Usage: [ocean_service_topup amount="50"]
--------------------------- */
add_shortcode('ocean_service_topup', 'osc_shortcode_topup');
function osc_shortcode_topup($atts){
$atts = shortcode_atts(array('amount'=>50), $atts, 'ocean_service_topup');
$amount = floatval($atts['amount']);
if(!is_user_logged_in()) return '<p>Please login to top up</p>';

$pub = get_option('osc_paystack_public','');
if(empty($pub)) return '<p>Paystack not configured</p>';


// Return a simple checkout button which expects client-side Paystack integration
$email = wp_get_current_user()->user_email;
$ref = 'topup_'.time().'_'.wp_rand(1000,9999);
ob_start();
?>
<button onclick="payWithPaystack()">Top up GHS <?php echo esc_html($amount); ?></button>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payWithPaystack() {
var handler = PaystackPop.setup({
key: '<?php echo esc_js($pub); ?>',
email: '<?php echo esc_js($email); ?>',
amount: <?php echo esc_js($amount * 100); ?>,
ref: '<?php echo esc_js($ref); ?>',
onClose: function(){},
callback: function(response){
// on success, we expect a server-side webhook to confirm
alert('Top-up successful. Ref: ' + response.reference);
}
});
handler.openIframe();
}
</script>
<?php
return ob_get_clean();
}
/* ---------------------------
Shortcode: Display wallet balance
--------------------------- */
add_shortcode('osc_wallet_balance', function(){
if(!is_user_logged_in()) return 'Login to see wallet';
return 'GHS '.number_format(osc_get_wallet_balance(get_current_user_id()),2);
});

/* ---------------------------
Shortcode: Order History (Real-time from WooCommerce)
--------------------------- */
add_shortcode('osc_order_history', 'osc_order_history_shortcode');
function osc_order_history_shortcode() {
if (!is_user_logged_in()) {
return '<div class="notice notice-info"><p>Please log in to view your order history.</p></div>';
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
return '<div class="notice notice-error"><p>WooCommerce is not active.</p></div>';
}

ob_start();
$user_id = get_current_user_id();

// Get WooCommerce orders
$orders = wc_get_orders(array(
'customer_id' => $user_id,
'limit' => 50,
'orderby' => 'date',
'order' => 'DESC',
'status' => array('completed', 'processing', 'pending', 'on-hold', 'cancelled', 'failed')
));
?>
<div class="osc-order-history-container">
<div class="osc-history-header">
<h2>📋 Order History</h2>
<p>Your recent purchases and transactions</p>
</div>

<?php if (empty($orders)): ?>
<div class="osc-no-orders">
<span>📭</span>
<h3>No orders yet</h3>
<p>Your purchase history will appear here once you make your first order.</p>
<a href="<?php echo get_permalink(wc_get_page_id('shop')); ?>" class="button">Start Shopping</a>
</div>
<?php else: ?>
<div class="osc-orders-list">
<?php foreach ($orders as $order):
$order_date = $order->get_date_created();
$order_total = $order->get_total();
$order_status = $order->get_status();
$order_items = $order->get_items();

// ✅ FIX: Get phone number from multiple possible sources
$phone_number = $order->get_billing_phone();
if (!$phone_number) {
$phone_number = $order->get_meta('_osc_beneficiary');
}
if (!$phone_number) {
$phone_number = $order->get_meta('bundle_recipient_phone');
}
if (!$phone_number) {
$phone_number = $order->get_meta('_bundle_phone_number');
}
if (!$phone_number) {
$phone_number = 'Not provided';
}
?>
<div class="osc-order-item">
<div class="osc-order-summary">
<div class="osc-order-details">
<span class="osc-order-id">📱 Order #<?php echo $order->get_order_number(); ?></span>
<span class="osc-order-date">📅 <?php echo $order_date ? $order_date->date('M j, Y g:i A') : ''; ?></span>
</div>
<div class="osc-order-total">
GHS <?php echo $order_total; ?>
</div>
<div class="osc-order-status">
<?php
$status_icons = [
'completed' => '✅',
'processing' => '🔄',
'pending' => '⏳',
'on-hold' => '📦',
'cancelled' => '❌',
'failed' => '🚫'
];
echo ($status_icons[$order_status] ?? '📝') . ' ' . wc_get_order_status_name($order_status);
?>
</div>
<button class="osc-toggle-details">▼</button>
</div>
<div class="osc-order-collapsible">
<p><strong>Items:</strong>
<?php
$product_names = array();
foreach ($order_items as $item) {
$product_names[] = $item->get_name();
}
echo esc_html(implode(', ', $product_names));
?>
</p>
<p><strong>Beneficiary Phone:</strong> <?php echo esc_html($phone_number); ?></p>
<p><strong>Contact:</strong><br>
Phone: <?php echo esc_html($order->get_billing_phone()); ?><br>
Email: <?php echo esc_html($order->get_billing_email()); ?></p>
<?php if($order->get_formatted_shipping_address()): ?>
<p><strong>Shipping:</strong><br><?php echo $order->get_formatted_shipping_address(); ?></p>
<?php endif; ?>
<p><strong>Payment:</strong> <?php echo $order->get_payment_method_title(); ?></p>
</div>
</div>
<?php endforeach; ?>
</div>

<div class="osc-history-summary">
<div class="osc-summary-card">
<h4>Total Orders</h4>
<p><?php echo count($orders); ?></p>
</div>
<div class="osc-summary-card">
<h4>Total Spent</h4>
<p>GHS
<?php
$total = 0;
foreach ($orders as $order) {
if (in_array($order->get_status(), array('completed', 'processing'))) {
$total += $order->get_total();
}
}
echo number_format($total, 2);
?>
</p>
</div>
</div>
<?php endif; ?>
</div>
<?php
return ob_get_clean();
}
/* ---------------------------
Shortcode: AFA Registration Form (WooCommerce Integrated)
--------------------------- */
add_shortcode('afa_registration', 'osc_afa_registration_shortcode');
function osc_afa_registration_shortcode() {
if (!is_user_logged_in()) {
return '<div class="notice notice-info"><p>Please log in to complete AFA registration.</p></div>';
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
return '<div class="notice notice-error"><p>WooCommerce is not active. AFA registration requires WooCommerce.</p></div>';
}

ob_start();
$afa_price = get_option('osc_afa_registration_price', 15.00);
$product_id = get_option('osc_afa_product_id');

// Check if AFA product exists
if (!$product_id || !wc_get_product($product_id)) {
return '<div class="notice notice-error"><p>AFA registration product is not configured. Please contact administrator.</p></div>';
}
?>
<div class="osc-afa-registration-form">
<h2>🏢 AFA Registration</h2>
<p>Register for Agricultural Farmers Association</p>
<form id="afa-reg-form">
<div class="form-row">
<label for="afa-full-name">Full Name</label>
<input type="text" id="afa-full-name" required>
</div>
<div class="form-row">
<label for="afa-phone-number">Phone Number</label>
<input type="tel" id="afa-phone-number" required>
</div>
<div class="form-row">
<label for="afa-ghana-card">Ghana Card Number</label>
<input type="text" id="afa-ghana-card" required>
</div>
<div class="form-row">
<label for="afa-location">Location/Address</label>
<input type="text" id="afa-location" required>
</div>
<div class="form-row">
<label for="afa-notes">Notes (Optional)</label>
<textarea id="afa-notes"></textarea>
</div>
<div class="afa-summary">
<span>Registration Fee</span>
<strong>GHS <?php echo number_format($afa_price, 2); ?></strong>
</div>
<button type="submit" class="button primary">Register Now</button>
<p class="description">This registration will be processed through our secure checkout system.</p>
</form>
<div id="afa-form-feedback"></div>
</div>

<script>
jQuery(document).ready(function($){
$('#afa-reg-form').on('submit', function(e){
e.preventDefault();
var feedback = $('#afa-form-feedback');
feedback.text('Processing...').show();

$.post(ajaxurl, {
action: 'osc_add_afa_to_cart',
afa_nonce: '<?php echo wp_create_nonce('afa_registration_nonce'); ?>',
full_name: $('#afa-full-name').val(),
phone_number: $('#afa-phone-number').val(),
ghana_card: $('#afa-ghana-card').val(),
location: $('#afa-location').val(),
notes: $('#afa-notes').val()
}, function(response){
if(response.success){
feedback.css('color','green').text(response.data);
window.location.href = '<?php echo wc_get_checkout_url(); ?>';
} else {
feedback.css('color','red').text(response.data);
}
});
});
});
</script>
<?php
return ob_get_clean();
}

/* ---------------------------
AJAX handler to add AFA registration to cart
--------------------------- */
add_action('wp_ajax_osc_add_afa_to_cart', 'osc_add_afa_to_cart');
add_action('wp_ajax_nopriv_osc_add_afa_to_cart', 'osc_add_afa_to_cart');
function osc_add_afa_to_cart() {
// Verify nonce
if (!wp_verify_nonce($_POST['afa_nonce'], 'afa_registration_nonce')) {
wp_send_json_error('Security verification failed.');
}

if (!class_exists('WooCommerce')) {
wp_send_json_error('WooCommerce is not active.');
}

$product_id = get_option('osc_afa_product_id');
if (!$product_id || !wc_get_product($product_id)) {
wp_send_json_error('AFA registration product is not configured.');
}

// Clear any existing AFA products in cart
$cart = WC()->cart;
if ($cart) {
foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
if ($cart_item['product_id'] == $product_id) {
$cart->remove_cart_item($cart_item_key);
}
}
}

// Add AFA product to cart with custom data
$cart_item_data = array(
'afa_registration_data' => array(
'full_name' => sanitize_text_field($_POST['full_name']),
'phone_number' => sanitize_text_field($_POST['phone_number']),
'ghana_card' => sanitize_text_field($_POST['ghana_card']),
'location' => sanitize_text_field($_POST['location']),
'notes' => sanitize_textarea_field($_POST['notes']),
'registration_date' => current_time('mysql')
)
);

$added = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

if ($added) {
wp_send_json_success('AFA registration added to cart. Redirecting to checkout...');
} else {
wp_send_json_error('Failed to add registration to cart.');
}
}

/* ---------------------------
Display AFA registration data in cart and checkout
--------------------------- */
add_filter('woocommerce_get_item_data', 'osc_display_afa_data_in_cart', 10, 2);
function osc_display_afa_data_in_cart($item_data, $cart_item) {
if (isset($cart_item['afa_registration_data'])) {
$afa_data = $cart_item['afa_registration_data'];

$item_data[] = array(
'name' => 'Full Name',
'value' => $afa_data['full_name']
);
$item_data[] = array(
'name' => 'Phone Number',
'value' => $afa_data['phone_number']
);
$item_data[] = array(
'name' => 'Ghana Card',
'value' => $afa_data['ghana_card']
);
$item_data[] = array(
'name' => 'Location',
'value' => $afa_data['location']
);
if (!empty($afa_data['notes'])) {
$item_data[] = array(
'name' => 'Notes',
'value' => $afa_data['notes']
);
}
}

return $item_data;
}

/* ---------------------------
Save AFA data to order meta
--------------------------- */
add_action('woocommerce_checkout_create_order_line_item', 'osc_save_afa_data_to_order', 10, 4);
function osc_save_afa_data_to_order($item, $cart_item_key, $values, $order) {
if (isset($values['afa_registration_data'])) {
$item->add_meta_data('_afa_registration_data', $values['afa_registration_data']);
}
}

/* ---------------------------
Admin configuration for AFA Registration
--------------------------- */
add_action('admin_menu', 'osc_afa_admin_menu');
function osc_afa_admin_menu() {
add_options_page(
'AFA Registration Settings',
'AFA Registration',
'manage_options',
'afa-registration',
'osc_afa_settings_page'
);
}

function osc_afa_settings_page() {
if (isset($_POST['submit_afa_settings'])) {
update_option('osc_afa_registration_price', floatval($_POST['afa_price']));

if (!empty($_POST['afa_product_id'])) {
update_option('osc_afa_product_id', intval($_POST['afa_product_id']));
}

echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

$current_price = get_option('osc_afa_registration_price', 50.00);
$current_product_id = get_option('osc_afa_product_id');
$current_product = $current_product_id ? wc_get_product($current_product_id) : null;
?>
<div class="wrap">
<h1>AFA Registration Settings</h1>
<div class="card">
<form method="post">
<table class="form-table">
<tr>
<th scope="row"><label for="afa-price">Registration Fee</label></th>
<td><input type="number" step="0.01" name="afa_price" id="afa-price" value="<?php echo esc_attr($current_price); ?>"></td>
</tr>
<tr>
<th scope="row"><label for="afa-product-id">Registration Product</label></th>
<td>
<select name="afa_product_id" class="wc-product-search" style="width: 300px;">
<?php if ($current_product): ?>
<option value="<?php echo esc_attr($current_product_id); ?>" selected="selected"><?php echo esc_html($current_product->get_formatted_name()); ?></option>
<?php endif; ?>
</select>
</td>
</tr>
</table>
<input type="submit" name="submit_afa_settings" class="button button-primary" value="Save Settings">
</form>
</div>
<div class="card" style="margin-top: 20px;">
<h2>Setup Instructions</h2>
<ol>
<li>Create a product in WooCommerce called "AFA Registration"</li>
<li>Set the product price to match your registration fee</li>
<li>Select that product in the dropdown above</li>
<li>Save settings</li>
<li>Use the shortcode <code>[afa_registration]</code> on any page</li>
</ol>
</div>
</div>
<?php
}

/* ---------------------------
Utility: log helper
--------------------------- */
function osc_api_log_once($type, $message, $payload=array()){
osc_api_log($type, $message, $payload);
}

/* ---------------------------
End of plugin
--------------------------- */
