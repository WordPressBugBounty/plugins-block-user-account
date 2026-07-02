<?php

/**
 * Activity Logger Class
 *
 * Handles all logging operations for user blocking activities
 *
 * @package    Block_User_Account
 * @subpackage Includes
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Logger
{

    /**
     * Log an activity
     *
     * @param int    $user_id  Target user ID
     * @param string $action   Action type
     * @param string $reason   Optional reason or note
     * @return int|false Insert ID or false on failure
     */
    public static function log($user_id, $action, $reason = '')
    {
        if (get_option('bua_log_activities', 'yes') !== 'yes') {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        $data = array(
            'user_id'   => intval($user_id),
            'admin_id'  => get_current_user_id() ? get_current_user_id() : 0,
            'action'    => sanitize_key($action),
            'reason'    => sanitize_textarea_field($reason),
            'ip_address' => self::get_client_ip(),
            'timestamp' => current_time('mysql')
        );

        $format = array('%d', '%d', '%s', '%s', '%s', '%s');

        $inserted = $wpdb->insert($table_name, $data, $format);

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get activity history for a specific user
     *
     * @param int $user_id User ID
     * @param int $limit   Number of records to retrieve
     * @return array Array of activity records
     */
    public static function get_user_history($user_id, $limit = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d 
             ORDER BY timestamp DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));

        return $results ? $results : array();
    }

    /**
     * Get all blocked users count
     *
     * @return int Number of currently blocked users
     */
    public static function get_blocked_users_count()
    {
        $users = get_users(array(
            'meta_key'   => 'user_status',
            'meta_value' => 'deactive',
            'fields'     => 'ID'
        ));

        return count($users);
    }

    /**
     * Get recent activities
     *
     * @param int $limit Number of records to retrieve
     * @return array Array of recent activity records
     */
    public static function get_recent_activities($limit = 50)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             ORDER BY timestamp DESC 
             LIMIT %d",
            $limit
        ));

        return $results ? $results : array();
    }

    /**
     * Get activity statistics
     *
     * @param string $period Period (today, week, month, year)
     * @return array Statistics array
     */
    public static function get_statistics($period = 'month')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        switch ($period) {
            case 'today':
                $date = date('Y-m-d');
                break;
            case 'week':
                $date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $date = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'year':
                $date = date('Y-m-d', strtotime('-365 days'));
                break;
            default:
                $date = date('Y-m-d', strtotime('-30 days'));
        }

        $stats = array(
            'total_blocks' => 0,
            'total_unblocks' => 0,
            'auto_unblocks' => 0,
            'bulk_blocks' => 0,
            'failed_attempts' => 0,
            'by_admin' => array()
        );

        $total_blocks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN ('user_blocked', 'bulk_blocked', 'quick_blocked', 'temp_blocked') 
             AND timestamp >= %s",
            $date
        ));
        $stats['total_blocks'] = intval($total_blocks);

        $total_unblocks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN ('user_unblocked', 'bulk_unblocked', 'quick_unblocked', 'auto_unblock', 'auto_unblock_cron') 
             AND timestamp >= %s",
            $date
        ));
        $stats['total_unblocks'] = intval($total_unblocks);

        $auto_unblocks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN ('auto_unblock', 'auto_unblock_cron') 
             AND timestamp >= %s",
            $date
        ));
        $stats['auto_unblocks'] = intval($auto_unblocks);

        $bulk_blocks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN ('bulk_blocked', 'bulk_unblocked', 'temp_blocked') 
             AND timestamp >= %s",
            $date
        ));
        $stats['bulk_blocks'] = intval($bulk_blocks);

        $failed_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN ('blocked_login_attempt', 'failed_login') 
             AND timestamp >= %s",
            $date
        ));
        $stats['failed_attempts'] = intval($failed_attempts);

        $admin_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT admin_id, COUNT(*) as count 
             FROM $table_name 
             WHERE admin_id > 0 
             AND timestamp >= %s 
             GROUP BY admin_id 
             ORDER BY count DESC 
             LIMIT 10",
            $date
        ));

        foreach ($admin_stats as $admin) {
            $admin_user = get_userdata($admin->admin_id);
            $stats['by_admin'][] = array(
                'id'    => $admin->admin_id,
                'name'  => $admin_user ? $admin_user->display_name : __('Unknown', 'block-user-account'),
                'count' => intval($admin->count)
            );
        }

        return $stats;
    }

    /**
     * Delete old logs
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted rows
     */
    public static function clean_old_logs($days = 90)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %s",
            $date
        ));

        return intval($deleted);
    }

    /**
     * Export logs to CSV
     *
     * @param array $filters Optional filters
     * @return string CSV content
     */
    public static function export_logs($filters = array())
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        $where = '1=1';

        if (!empty($filters['user_id'])) {
            $where .= $wpdb->prepare(" AND user_id = %d", $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $where .= $wpdb->prepare(" AND action = %s", $filters['action']);
        }

        if (!empty($filters['date_from'])) {
            $where .= $wpdb->prepare(" AND timestamp >= %s", $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where .= $wpdb->prepare(" AND timestamp <= %s", $filters['date_to']);
        }

        $results = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY timestamp DESC");

        $csv = "ID,User ID,Admin ID,Action,Reason,IP Address,Timestamp\n";

        foreach ($results as $row) {
            $user = get_userdata($row->user_id);
            $admin = get_userdata($row->admin_id);

            $csv .= implode(',', array(
                $row->id,
                $user ? $user->user_login : $row->user_id,
                $admin ? $admin->user_login : $row->admin_id,
                $row->action,
                '"' . str_replace('"', '""', $row->reason) . '"',
                $row->ip_address,
                $row->timestamp
            )) . "\n";
        }

        return $csv;
    }

    /**
     * Get total log count
     *
     * @return int Total number of log entries
     */
    public static function get_total_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        return intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
    }

    /**
     * Create database table for logs
     */
    public static function create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            admin_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            action varchar(50) NOT NULL,
            reason text,
            ip_address varchar(45) DEFAULT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id),
            KEY action (action),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $db_version = get_option('bua_db_version', '0');

        if (version_compare($db_version, '2.0.0', '<')) {
            update_option('bua_db_version', BUA_VERSION);
        }
    }

    /**
     * Drop database table
     */
    public static function drop_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Get human-readable action label
     *
     * @param string $action Action key
     * @return string Human-readable label
     */
    public static function get_action_label($action)
    {
        $labels = array(
            'user_blocked'          => __('Blocked', 'block-user-account'),
            'user_unblocked'        => __('Unblocked', 'block-user-account'),
            'bulk_blocked'          => __('Bulk Blocked', 'block-user-account'),
            'bulk_unblocked'        => __('Bulk Unblocked', 'block-user-account'),
            'quick_blocked'         => __('Quick Blocked', 'block-user-account'),
            'quick_unblocked'       => __('Quick Unblocked', 'block-user-account'),
            'temp_blocked'          => __('Temporarily Blocked', 'block-user-account'),
            'auto_unblock'          => __('Auto Unblocked', 'block-user-account'),
            'auto_unblock_cron'     => __('Auto Unblocked (Cron)', 'block-user-account'),
            'blocked_login_attempt' => __('Blocked Login Attempt', 'block-user-account'),
            'failed_login'          => __('Failed Login', 'block-user-account'),
        );

        return isset($labels[$action]) ? $labels[$action] : $action;
    }

    /**
     * Truncate all logs
     */
    public static function truncate_logs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
}
