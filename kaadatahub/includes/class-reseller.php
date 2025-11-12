<?php

class KDH_Reseller {

    public static function is_reseller($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table_name WHERE user_id = %d", $user_id));
        return $status === 'approved';
    }

    public static function apply_to_be_reseller($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';

        // Check if user has already applied
        $existing_application = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));
        if ($existing_application) {
            return new WP_Error('already_applied', 'You have already applied to be a reseller.');
        }

        $referral_code = self::generate_referral_code($user_id);

        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'status' => 'pending',
                'referral_code' => $referral_code,
            ],
            ['%d', '%s', '%s']
        );

        return true;
    }

    public static function approve_reseller($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';

        $wpdb->update(
            $table_name,
            ['status' => 'approved'],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        );
    }

    public static function deny_reseller($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';

        $wpdb->delete(
            $table_name,
            ['user_id' => $user_id],
            ['%d']
        );
    }

    public static function get_referral_link($user_id) {
        if (!self::is_reseller($user_id)) {
            return '';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';
        $referral_code = $wpdb->get_var($wpdb->prepare("SELECT referral_code FROM $table_name WHERE user_id = %d", $user_id));

        return add_query_arg('ref', $referral_code, home_url('/'));
    }

    private static function generate_referral_code($user_id) {
        $user_info = get_userdata($user_id);
        $username = $user_info->user_login;
        return sanitize_title($username);
    }

    public static function get_pending_reseller_applications() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = %s", 'pending'));
    }

    public static function get_all_resellers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_resellers';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = %s", 'approved'));
    }

    public static function request_withdrawal($user_id, $amount) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_withdrawals';

        // You might want to add checks here, e.g., if the user has enough commission

        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'amount' => $amount,
                'status' => 'pending',
                'request_date' => current_time('mysql'),
            ],
            ['%d', '%f', '%s', '%s']
        );

        return true;
    }

    public static function get_pending_withdrawals() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_withdrawals';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = %s", 'pending'));
    }

    public static function approve_withdrawal($withdrawal_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_withdrawals';
        $wpdb->update(
            $table_name,
            ['status' => 'approved'],
            ['id' => $withdrawal_id],
            ['%s'],
            ['%d']
        );
    }

    public static function deny_withdrawal($withdrawal_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kdh_withdrawals';
        $wpdb->update(
            $table_name,
            ['status' => 'denied'],
            ['id' => $withdrawal_id],
            ['%s'],
            ['%d']
        );
    }
}
