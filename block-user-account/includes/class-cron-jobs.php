<?php

/**
 * Cron Jobs Class
 *
 * Manages scheduled tasks for automatic user unblocking and maintenance
 *
 * @package    Block_User_Account
 * @subpackage Includes
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Cron_Jobs
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('bua_check_expired_blocks', array($this, 'check_expired_blocks'));
        add_action('bua_daily_report', array($this, 'send_daily_report'));
        add_action('bua_cleanup_logs', array($this, 'cleanup_old_logs'));
        add_action('bua_weekly_summary', array($this, 'send_weekly_summary'));

        add_filter('cron_schedules', array($this, 'add_cron_intervals'));

        $this->schedule_events();
    }

    /**
     * Add custom cron intervals
     *
     * @param array $schedules Existing cron schedules
     * @return array Modified cron schedules
     */
    public function add_cron_intervals($schedules)
    {
        $schedules['every_15_minutes'] = array(
            'interval' => 900,
            'display'  => __('Every 15 Minutes', 'block-user-account')
        );

        $schedules['every_6_hours'] = array(
            'interval' => 21600,
            'display'  => __('Every 6 Hours', 'block-user-account')
        );

        $schedules['weekly'] = array(
            'interval' => 604800,
            'display'  => __('Weekly', 'block-user-account')
        );

        return $schedules;
    }

    /**
     * Schedule all plugin events
     */
    private function schedule_events()
    {
        if (!wp_next_scheduled('bua_check_expired_blocks')) {
            wp_schedule_event(time(), 'every_15_minutes', 'bua_check_expired_blocks');
        }

        if (!wp_next_scheduled('bua_daily_report') && get_option('bua_daily_report', 'no') === 'yes') {
            $tomorrow = strtotime('tomorrow 08:00:00');
            wp_schedule_event($tomorrow, 'daily', 'bua_daily_report');
        }

        if (!wp_next_scheduled('bua_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'bua_cleanup_logs');
        }

        if (!wp_next_scheduled('bua_weekly_summary') && get_option('bua_weekly_summary', 'no') === 'yes') {
            $next_monday = strtotime('next Monday 09:00:00');
            wp_schedule_event($next_monday, 'weekly', 'bua_weekly_summary');
        }
    }

    /**
     * Check for expired blocks and unblock users
     */
    public function check_expired_blocks()
    {
        $blocked_users = get_users(array(
            'meta_key'   => 'user_status',
            'meta_value' => 'deactive',
            'fields'     => 'ID',
            'number'     => -1
        ));

        if (empty($blocked_users)) {
            return;
        }

        $now = time();
        $now_formatted = date('Y-m-d H:i:s', $now);

        foreach ($blocked_users as $user_id) {
            $expiry = get_user_meta($user_id, 'block_expiry_date', true);

            if (empty($expiry)) {
                continue;
            }

            $expiry_timestamp = strtotime($expiry);

            if ($expiry_timestamp && $expiry_timestamp <= $now) {
                delete_user_meta($user_id, 'user_status');
                delete_user_meta($user_id, 'user_status_message');
                delete_user_meta($user_id, 'block_expiry_date');
                delete_user_meta($user_id, 'blocked_by');
                delete_user_meta($user_id, 'blocked_date');

                BUA_Logger::log($user_id, 'auto_unblock_cron');
                do_action('bua_user_unblocked', $user_id);
            }
        }
    }

    /**
     * Unblock a user and clean up metadata
     *
     * @param int    $user_id User ID
     * @param string $action  Log action type
     */
    private function unblock_user($user_id, $action = 'auto_unblock_cron')
    {
        delete_user_meta($user_id, 'user_status');
        delete_user_meta($user_id, 'user_status_message');
        delete_user_meta($user_id, 'block_expiry_date');
        delete_user_meta($user_id, 'blocked_by');
        delete_user_meta($user_id, 'blocked_date');

        BUA_Logger::log($user_id, $action);
        do_action('bua_user_unblocked', $user_id);
    }

    /**
     * Send daily report to administrator
     */
    public function send_daily_report()
    {
        if (get_option('bua_daily_report', 'no') !== 'yes') {
            return;
        }

        $admin_email = get_option('admin_email');

        if (empty($admin_email)) {
            return;
        }

        $stats = $this->get_daily_stats();

        $subject = sprintf(
            __('[%s] Daily Block Report - %s', 'block-user-account'),
            get_bloginfo('name'),
            date_i18n(get_option('date_format'))
        );

        $message = $this->build_report_email($stats, 'daily');

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Send weekly summary to administrator
     */
    public function send_weekly_summary()
    {
        if (get_option('bua_weekly_summary', 'no') !== 'yes') {
            return;
        }

        $admin_email = get_option('admin_email');

        if (empty($admin_email)) {
            return;
        }

        $stats = $this->get_weekly_stats();

        $subject = sprintf(
            __('[%s] Weekly Block Summary - Week %s', 'block-user-account'),
            get_bloginfo('name'),
            date('W')
        );

        $message = $this->build_report_email($stats, 'weekly');

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Clean up old log entries
     */
    public function cleanup_old_logs()
    {
        $days = apply_filters('bua_log_retention_days', 90);
        $deleted = BUA_Logger::clean_old_logs($days);

        if ($deleted > 0) {
            update_option('bua_last_cleanup', current_time('mysql'));
            update_option('bua_last_cleanup_count', $deleted);
        }
    }

    /**
     * Get statistics for daily report
     *
     * @return array Statistics array
     */
    private function get_daily_stats()
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');

        $stats = array(
            'period'            => __('Last 24 Hours', 'block-user-account'),
            'currently_blocked' => BUA_Logger::get_blocked_users_count(),
            'blocked_today'     => $this->count_actions(array('user_blocked', 'bulk_blocked', 'quick_blocked', 'temp_blocked'), $today),
            'unblocked_today'   => $this->count_actions(array('user_unblocked', 'bulk_unblocked', 'quick_unblocked', 'auto_unblock', 'auto_unblock_cron'), $today),
            'auto_unblocked'    => $this->count_actions(array('auto_unblock', 'auto_unblock_cron'), $today),
            'failed_attempts'   => $this->count_actions(array('blocked_login_attempt', 'failed_login'), $today),
            'expiring_soon'     => $this->get_expiring_soon_count(2),
            'recent_activities' => BUA_Logger::get_recent_activities(10)
        );

        return $stats;
    }

    /**
     * Get statistics for weekly report
     *
     * @return array Statistics array
     */
    private function get_weekly_stats()
    {
        $week_start = date('Y-m-d', strtotime('last Monday'));
        $week_end = date('Y-m-d', strtotime('next Sunday'));

        $stats = array(
            'period'            => sprintf(__('Week %s (%s - %s)', 'block-user-account'), date('W'), $week_start, $week_end),
            'currently_blocked' => BUA_Logger::get_blocked_users_count(),
            'blocked_this_week' => $this->count_actions(array('user_blocked', 'bulk_blocked', 'quick_blocked', 'temp_blocked'), $week_start),
            'unblocked_this_week' => $this->count_actions(array('user_unblocked', 'bulk_unblocked', 'quick_unblocked', 'auto_unblock', 'auto_unblock_cron'), $week_start),
            'full_stats'        => BUA_Logger::get_statistics('week'),
            'expiring_soon'     => $this->get_expiring_soon_count(7)
        );

        return $stats;
    }

    /**
     * Count actions from a specific date
     *
     * @param array  $actions Action types to count
     * @param string $since   Date string
     * @return int Action count
     */
    private function count_actions($actions, $since)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_activity_log';

        $placeholders = array_fill(0, count($actions), '%s');

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN (" . implode(',', $placeholders) . ") 
             AND timestamp >= %s",
            array_merge($actions, array($since . ' 00:00:00'))
        );

        return intval($wpdb->get_var($query));
    }

    /**
     * Get count of users whose block expires soon
     *
     * @param int $days Number of days to check
     * @return int Number of users
     */
    private function get_expiring_soon_count($days = 2)
    {
        $future = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $now = current_time('mysql');

        $users = get_users(array(
            'meta_key'     => 'block_expiry_date',
            'meta_value'   => array($now, $future),
            'meta_compare' => 'BETWEEN',
            'meta_type'    => 'DATETIME',
            'fields'       => 'ID'
        ));

        return count($users);
    }

    /**
     * Build HTML email report
     *
     * @param array  $stats Statistics data
     * @param string $type  Report type (daily/weekly)
     * @return string HTML email content
     */
    private function build_report_email($stats, $type)
    {
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    background: #3498db;
                    color: #fff;
                    padding: 30px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }

                .content {
                    background: #fff;
                    padding: 30px;
                    border: 1px solid #ddd;
                    border-top: none;
                }

                .stats-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin: 20px 0;
                }

                .stat-box {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    text-align: center;
                    border-left: 4px solid #3498db;
                }

                .stat-box.blocked {
                    border-left-color: #e74c3c;
                }

                .stat-box.unblocked {
                    border-left-color: #27ae60;
                }

                .stat-box.auto {
                    border-left-color: #f39c12;
                }

                .stat-value {
                    font-size: 28px;
                    font-weight: bold;
                    margin: 10px 0;
                }

                .stat-label {
                    color: #666;
                    font-size: 13px;
                    text-transform: uppercase;
                }

                .alert {
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }

                .activity-list {
                    list-style: none;
                    padding: 0;
                }

                .activity-list li {
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }

                .activity-list li:last-child {
                    border-bottom: none;
                }

                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #888;
                    font-size: 12px;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo $type === 'daily' ? __('Daily Block Report', 'block-user-account') : __('Weekly Block Summary', 'block-user-account'); ?></h2>
                    <p><?php echo esc_html($stats['period']); ?></p>
                </div>
                <div class="content">
                    <div class="stats-grid">
                        <div class="stat-box blocked">
                            <div class="stat-label"><?php _e('Currently Blocked', 'block-user-account'); ?></div>
                            <div class="stat-value"><?php echo esc_html($stats['currently_blocked']); ?></div>
                        </div>

                        <?php if (isset($stats['blocked_today'])): ?>
                            <div class="stat-box blocked">
                                <div class="stat-label"><?php _e('Blocked Today', 'block-user-account'); ?></div>
                                <div class="stat-value"><?php echo esc_html($stats['blocked_today']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($stats['blocked_this_week'])): ?>
                            <div class="stat-box blocked">
                                <div class="stat-label"><?php _e('Blocked This Week', 'block-user-account'); ?></div>
                                <div class="stat-value"><?php echo esc_html($stats['blocked_this_week']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($stats['unblocked_today'])): ?>
                            <div class="stat-box unblocked">
                                <div class="stat-label"><?php _e('Unblocked Today', 'block-user-account'); ?></div>
                                <div class="stat-value"><?php echo esc_html($stats['unblocked_today']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($stats['auto_unblocked'])): ?>
                            <div class="stat-box auto">
                                <div class="stat-label"><?php _e('Auto Unblocked', 'block-user-account'); ?></div>
                                <div class="stat-value"><?php echo esc_html($stats['auto_unblocked']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($stats['failed_attempts'])): ?>
                            <div class="stat-box">
                                <div class="stat-label"><?php _e('Failed Attempts', 'block-user-account'); ?></div>
                                <div class="stat-value"><?php echo esc_html($stats['failed_attempts']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($stats['expiring_soon'] > 0): ?>
                            <div class="stat-box auto">
                                <div class="stat-label"><?php _e('Expiring Soon', 'block-user-account'); ?></div>
                                <div class="stat-value"><?php echo esc_html($stats['expiring_soon']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($stats['expiring_soon'] > 0): ?>
                        <div class="alert">
                            <strong><?php _e('Attention:', 'block-user-account'); ?></strong>
                            <?php printf(__('%d user(s) will be automatically unblocked within the next %d days.', 'block-user-account'), $stats['expiring_soon'], 2); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($stats['recent_activities']) && !empty($stats['recent_activities'])): ?>
                        <h3><?php _e('Recent Activities', 'block-user-account'); ?></h3>
                        <ul class="activity-list">
                            <?php foreach (array_slice($stats['recent_activities'], 0, 5) as $activity):
                                $user = get_userdata($activity->user_id);
                                $user_name = $user ? $user->display_name : __('Unknown', 'block-user-account');
                            ?>
                                <li>
                                    <strong><?php echo esc_html($user_name); ?></strong>
                                    — <?php echo esc_html(BUA_Logger::get_action_label($activity->action)); ?>
                                    <br>
                                    <small><?php echo human_time_diff(strtotime($activity->timestamp), current_time('timestamp')) . ' ' . __('ago', 'block-user-account'); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <p style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo admin_url('users.php'); ?>"
                            style="background: #3498db; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 3px; display: inline-block;">
                            <?php _e('View All Users', 'block-user-account'); ?>
                        </a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php printf(__('Automated report from %s', 'block-user-account'), get_bloginfo('name')); ?></p>
                    <p><?php _e('To disable these reports, go to Block User Account settings.', 'block-user-account'); ?></p>
                </div>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    /**
     * Log auto unblock summary
     *
     * @param int $count Number of users unblocked
     */
    private function log_auto_unblocks($count)
    {
        $admin_email = get_option('admin_email');

        $subject = sprintf(
            __('[%s] %d User(s) Automatically Unblocked', 'block-user-account'),
            get_bloginfo('name'),
            $count
        );

        $message = sprintf(
            __('The cron job has automatically unblocked %d user(s) because their block period has expired.', 'block-user-account'),
            $count
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Clear all scheduled events
     */
    public static function clear_scheduled_events()
    {
        wp_clear_scheduled_hook('bua_check_expired_blocks');
        wp_clear_scheduled_hook('bua_daily_report');
        wp_clear_scheduled_hook('bua_cleanup_logs');
        wp_clear_scheduled_hook('bua_weekly_summary');
    }

    /**
     * Get next scheduled event time
     *
     * @param string $hook Hook name
     * @return string|false Next run time or false
     */
    public static function get_next_scheduled($hook)
    {
        $timestamp = wp_next_scheduled($hook);

        if ($timestamp) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        }

        return false;
    }

    /**
     * Manually trigger a cron job
     *
     * @param string $job Job name to run
     * @return bool Whether the job ran successfully
     */
    public static function run_manual_job($job)
    {
        switch ($job) {
            case 'check_expired':
                $instance = new self();
                $instance->check_expired_blocks();
                return true;

            case 'daily_report':
                $instance = new self();
                $instance->send_daily_report();
                return true;

            case 'cleanup_logs':
                $instance = new self();
                $instance->cleanup_old_logs();
                return true;

            case 'weekly_summary':
                $instance = new self();
                $instance->send_weekly_summary();
                return true;

            default:
                return false;
        }
    }
}
