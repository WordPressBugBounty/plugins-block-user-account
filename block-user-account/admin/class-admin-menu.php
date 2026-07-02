<?php

/**
 * Admin Menu Class
 *
 * Creates settings page for the plugin
 *
 * @package    Block_User_Account
 * @subpackage Admin
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Admin_Menu
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page()
    {
        add_options_page(
            __('Block User Account Settings', 'block-user-account'),
            __('Block User Account', 'block-user-account'),
            'manage_options',
            'block-user-account-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting('bua_settings_group', 'bua_email_notifications', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 'yes'
        ));
        register_setting('bua_settings_group', 'bua_admin_notifications', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 'yes'
        ));
        register_setting('bua_settings_group', 'bua_log_activities', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 'yes'
        ));
        register_setting('bua_settings_group', 'bua_daily_report', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 'no'
        ));
        register_setting('bua_settings_group', 'bua_weekly_summary', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 'no'
        ));
        register_setting('bua_settings_group', 'bua_default_message', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => __('Your account has been temporarily disabled. Please contact the administrator.', 'block-user-account')
        ));
        register_setting('bua_settings_group', 'bua_log_retention_days', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 90
        ));
        register_setting('bua_settings_group', 'bua_auto_unblock', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 'yes'
        ));

        // Notifications Section
        add_settings_section('bua_notifications_section', '', array($this, 'render_notifications_section'), 'block-user-account-settings');
        add_settings_field('bua_email_notifications', __('User Notifications', 'block-user-account'), array($this, 'render_switch_field'), 'block-user-account-settings', 'bua_notifications_section', array(
            'label_for' => 'bua_email_notifications',
            'title' => __('Email to Users', 'block-user-account'),
            'description' => __('Send email to users when their account is blocked or unblocked.', 'block-user-account')
        ));
        add_settings_field('bua_admin_notifications', '', array($this, 'render_switch_field'), 'block-user-account-settings', 'bua_notifications_section', array(
            'label_for' => 'bua_admin_notifications',
            'title' => __('Email to Admin', 'block-user-account'),
            'description' => __('Notify administrator when a user is blocked or unblocked.', 'block-user-account')
        ));
        add_settings_field('bua_daily_report', '', array($this, 'render_switch_field'), 'block-user-account-settings', 'bua_notifications_section', array(
            'label_for' => 'bua_daily_report',
            'title' => __('Daily Summary', 'block-user-account'),
            'description' => __('Receive daily email summary of blocking activities.', 'block-user-account')
        ));
        add_settings_field('bua_weekly_summary', '', array($this, 'render_switch_field'), 'block-user-account-settings', 'bua_notifications_section', array(
            'label_for' => 'bua_weekly_summary',
            'title' => __('Weekly Summary', 'block-user-account'),
            'description' => __('Receive weekly email summary of blocking activities.', 'block-user-account')
        ));

        // General Section
        add_settings_section('bua_general_section', '', array($this, 'render_general_section'), 'block-user-account-settings');
        add_settings_field('bua_default_message', __('Default Block Message', 'block-user-account'), array($this, 'render_textarea_field'), 'block-user-account-settings', 'bua_general_section', array(
            'label_for' => 'bua_default_message',
            'description' => __('Default message shown to users when blocked. Can be overridden per user.', 'block-user-account')
        ));
        add_settings_field('bua_auto_unblock', __('Auto Unblock', 'block-user-account'), array($this, 'render_switch_field'), 'block-user-account-settings', 'bua_general_section', array(
            'label_for' => 'bua_auto_unblock',
            'title' => __('Automatic Unblock', 'block-user-account'),
            'description' => __('Automatically unblock users when their block period expires.', 'block-user-account')
        ));

        // Logs Section
        add_settings_section('bua_logs_section', '', array($this, 'render_logs_section'), 'block-user-account-settings');
        add_settings_field('bua_log_activities', __('Activity Log', 'block-user-account'), array($this, 'render_switch_field'), 'block-user-account-settings', 'bua_logs_section', array(
            'label_for' => 'bua_log_activities',
            'title' => __('Enable Logging', 'block-user-account'),
            'description' => __('Log all blocking and unblocking activities.', 'block-user-account')
        ));
        add_settings_field('bua_log_retention_days', __('Log Retention', 'block-user-account'), array($this, 'render_number_field'), 'block-user-account-settings', 'bua_logs_section', array(
            'label_for' => 'bua_log_retention_days',
            'description' => __('Days to keep activity logs before automatic cleanup.', 'block-user-account'),
            'min' => 7,
            'max' => 365,
            'step' => 1
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'block-user-account'));
        }

        $this->process_tools_actions();

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
?>
        <div class="bua-settings-wrap">
            <div class="bua-settings-header">
                <div class="bua-settings-header-icon">
                    <span class="dashicons dashicons-shield"></span>
                </div>
                <div class="bua-settings-header-info">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p><?php _e('Manage blocking settings, view statistics, and use tools', 'block-user-account'); ?></p>
                </div>
            </div>

            <nav class="bua-settings-nav">
                <a href="<?php echo add_query_arg('tab', 'settings'); ?>"
                    class="bua-nav-item <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <span class="bua-nav-label"><?php _e('Settings', 'block-user-account'); ?></span>
                </a>
                <a href="<?php echo add_query_arg('tab', 'statistics'); ?>"
                    class="bua-nav-item <?php echo $active_tab === 'statistics' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <span class="bua-nav-label"><?php _e('Statistics', 'block-user-account'); ?></span>
                </a>
                <a href="<?php echo add_query_arg('tab', 'tools'); ?>"
                    class="bua-nav-item <?php echo $active_tab === 'tools' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <span class="bua-nav-label"><?php _e('Tools', 'block-user-account'); ?></span>
                </a>
            </nav>

            <div class="bua-settings-content">
                <?php if ($active_tab === 'settings'): ?>
                    <?php $this->render_settings_tab(); ?>
                <?php elseif ($active_tab === 'statistics'): ?>
                    <?php $this->render_statistics_tab(); ?>
                <?php elseif ($active_tab === 'tools'): ?>
                    <?php $this->render_tools_tab(); ?>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab()
    {
    ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('bua_settings_group');
            do_settings_sections('block-user-account-settings');
            ?>
            <div class="bua-settings-submit">
                <button type="submit" class="button button-primary button-hero">
                    <?php _e('Save All Settings', 'block-user-account'); ?>
                </button>
            </div>
        </form>
    <?php
    }

    /**
     * Render statistics tab
     */
    private function render_statistics_tab()
    {
        $stats = BUA_Logger::get_statistics('month');
        $blocked_count = BUA_Logger::get_blocked_users_count();
        $total_logs = BUA_Logger::get_total_count();
        $activities = BUA_Logger::get_recent_activities(20);
    ?>
        <div class="bua-stats-overview">
            <div class="bua-stat-box bua-stat-primary">
                <div class="bua-stat-icon"><span class="dashicons dashicons-lock"></span></div>
                <div class="bua-stat-content">
                    <div class="bua-stat-value"><?php echo esc_html($blocked_count); ?></div>
                    <div class="bua-stat-label"><?php _e('Currently Blocked', 'block-user-account'); ?></div>
                </div>
            </div>
            <div class="bua-stat-box bua-stat-danger">
                <div class="bua-stat-icon"><span class="dashicons dashicons-no"></span></div>
                <div class="bua-stat-content">
                    <div class="bua-stat-value"><?php echo esc_html($stats['total_blocks']); ?></div>
                    <div class="bua-stat-label"><?php _e('Blocked This Month', 'block-user-account'); ?></div>
                </div>
            </div>
            <div class="bua-stat-box bua-stat-success">
                <div class="bua-stat-icon"><span class="dashicons dashicons-yes"></span></div>
                <div class="bua-stat-content">
                    <div class="bua-stat-value"><?php echo esc_html($stats['total_unblocks']); ?></div>
                    <div class="bua-stat-label"><?php _e('Unblocked This Month', 'block-user-account'); ?></div>
                </div>
            </div>
            <div class="bua-stat-box bua-stat-warning">
                <div class="bua-stat-icon"><span class="dashicons dashicons-update"></span></div>
                <div class="bua-stat-content">
                    <div class="bua-stat-value"><?php echo esc_html($stats['auto_unblocks']); ?></div>
                    <div class="bua-stat-label"><?php _e('Auto Unblocked', 'block-user-account'); ?></div>
                </div>
            </div>
            <div class="bua-stat-box bua-stat-info">
                <div class="bua-stat-icon"><span class="dashicons dashicons-warning"></span></div>
                <div class="bua-stat-content">
                    <div class="bua-stat-value"><?php echo esc_html($stats['failed_attempts']); ?></div>
                    <div class="bua-stat-label"><?php _e('Failed Attempts', 'block-user-account'); ?></div>
                </div>
            </div>
            <div class="bua-stat-box bua-stat-dark">
                <div class="bua-stat-icon"><span class="dashicons dashicons-list-view"></span></div>
                <div class="bua-stat-content">
                    <div class="bua-stat-value"><?php echo esc_html($total_logs); ?></div>
                    <div class="bua-stat-label"><?php _e('Total Logs', 'block-user-account'); ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($activities)): ?>
            <div class="bua-card">
                <div class="bua-card-header">
                    <span class="dashicons dashicons-clock"></span>
                    <h3><?php _e('Recent Activities', 'block-user-account'); ?></h3>
                </div>
                <div class="bua-card-body">
                    <table class="bua-table">
                        <thead>
                            <tr>
                                <th><?php _e('User', 'block-user-account'); ?></th>
                                <th><?php _e('Action', 'block-user-account'); ?></th>
                                <th><?php _e('Admin', 'block-user-account'); ?></th>
                                <th><?php _e('Date', 'block-user-account'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity):
                                $user = get_userdata($activity->user_id);
                                $admin = get_userdata($activity->admin_id);
                                $action_label = BUA_Logger::get_action_label($activity->action);
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($user): ?>
                                            <?php echo get_avatar($user->ID, 20, '', '', array('class' => 'bua-table-avatar')); ?>
                                            <a href="<?php echo get_edit_user_link($activity->user_id); ?>">
                                                <?php echo esc_html($user->display_name); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php _e('Unknown', 'block-user-account'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($action_label); ?></td>
                                    <td><?php echo $admin ? esc_html($admin->display_name) : __('System', 'block-user-account'); ?></td>
                                    <td><?php echo human_time_diff(strtotime($activity->timestamp), current_time('timestamp')) . ' ' . __('ago', 'block-user-account'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Render tools tab
     */
    private function render_tools_tab()
    {
    ?>
        <div class="bua-tools-grid">
            <div class="bua-card">
                <div class="bua-card-header">
                    <span class="dashicons dashicons-update"></span>
                    <h3><?php _e('Check Expired Blocks', 'block-user-account'); ?></h3>
                </div>
                <div class="bua-card-body">
                    <p><?php _e('Manually check and unblock users whose block period has expired.', 'block-user-account'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('bua_tools_action', 'bua_tools_nonce'); ?>
                        <button type="submit" name="bua_run_check" class="button button-primary">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php _e('Run Check Now', 'block-user-account'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bua-card">
                <div class="bua-card-header">
                    <span class="dashicons dashicons-trash"></span>
                    <h3><?php _e('Clean Old Logs', 'block-user-account'); ?></h3>
                </div>
                <div class="bua-card-body">
                    <p><?php _e('Delete activity logs older than the retention period.', 'block-user-account'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('bua_tools_action', 'bua_tools_nonce'); ?>
                        <button type="submit" name="bua_clean_logs" class="button"
                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete old logs?', 'block-user-account')); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Clean Logs', 'block-user-account'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bua-card">
                <div class="bua-card-header">
                    <span class="dashicons dashicons-download"></span>
                    <h3><?php _e('Export Logs', 'block-user-account'); ?></h3>
                </div>
                <div class="bua-card-body">
                    <p><?php _e('Download all activity logs as a CSV file.', 'block-user-account'); ?></p>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=block-user-account-settings&tab=tools&bua_export=1'), 'bua_export_logs', 'bua_export_nonce'); ?>" class="button">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php _e('Export CSV', 'block-user-account'); ?>
                    </a>
                </div>
            </div>

            <div class="bua-card">
                <div class="bua-card-header">
                    <span class="dashicons dashicons-email"></span>
                    <h3><?php _e('Test Email', 'block-user-account'); ?></h3>
                </div>
                <div class="bua-card-body">
                    <p><?php _e('Send a test email to verify email configuration.', 'block-user-account'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('bua_tools_action', 'bua_tools_nonce'); ?>
                        <button type="submit" name="bua_test_email" class="button">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Send Test Email', 'block-user-account'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bua-card bua-card-full">
                <div class="bua-card-header">
                    <span class="dashicons dashicons-backup"></span>
                    <h3><?php _e('Scheduled Tasks', 'block-user-account'); ?></h3>
                </div>
                <div class="bua-card-body">
                    <table class="bua-table">
                        <thead>
                            <tr>
                                <th><?php _e('Task', 'block-user-account'); ?></th>
                                <th><?php _e('Next Run', 'block-user-account'); ?></th>
                                <th><?php _e('Status', 'block-user-account'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="dashicons dashicons-clock"></span> <?php _e('Check Expired Blocks', 'block-user-account'); ?></td>
                                <td><?php echo BUA_Cron_Jobs::get_next_scheduled('bua_check_expired_blocks') ?: '—'; ?></td>
                                <td><span class="bua-status-active"><?php _e('Active', 'block-user-account'); ?></span></td>
                            </tr>
                            <tr>
                                <td><span class="dashicons dashicons-email-alt"></span> <?php _e('Daily Report', 'block-user-account'); ?></td>
                                <td><?php echo BUA_Cron_Jobs::get_next_scheduled('bua_daily_report') ?: '—'; ?></td>
                                <td><?php echo get_option('bua_daily_report', 'no') === 'yes' ? '<span class="bua-status-active">' . __('Active', 'block-user-account') . '</span>' : '<span class="bua-status-inactive">' . __('Disabled', 'block-user-account') . '</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><span class="dashicons dashicons-trash"></span> <?php _e('Clean Old Logs', 'block-user-account'); ?></td>
                                <td><?php echo BUA_Cron_Jobs::get_next_scheduled('bua_cleanup_logs') ?: '—'; ?></td>
                                <td><span class="bua-status-active"><?php _e('Active', 'block-user-account'); ?></span></td>
                            </tr>
                            <tr>
                                <td><span class="dashicons dashicons-chart-bar"></span> <?php _e('Weekly Summary', 'block-user-account'); ?></td>
                                <td><?php echo BUA_Cron_Jobs::get_next_scheduled('bua_weekly_summary') ?: '—'; ?></td>
                                <td><?php echo get_option('bua_weekly_summary', 'no') === 'yes' ? '<span class="bua-status-active">' . __('Active', 'block-user-account') . '</span>' : '<span class="bua-status-inactive">' . __('Disabled', 'block-user-account') . '</span>'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Process tools form actions
     */
    private function process_tools_actions()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (isset($_POST['bua_run_check']) && check_admin_referer('bua_tools_action', 'bua_tools_nonce')) {
            $cron = new BUA_Cron_Jobs();
            $cron->check_expired_blocks();
            echo '<div class="notice notice-success is-dismissible"><p><span class="dashicons dashicons-yes-alt"></span> ' . __('Expired blocks check completed.', 'block-user-account') . '</p></div>';
        }

        if (isset($_POST['bua_clean_logs']) && check_admin_referer('bua_tools_action', 'bua_tools_nonce')) {
            $days = get_option('bua_log_retention_days', 90);
            $deleted = BUA_Logger::clean_old_logs($days);
            echo '<div class="notice notice-success is-dismissible"><p><span class="dashicons dashicons-trash"></span> ' . sprintf(__('%d old log entries deleted.', 'block-user-account'), $deleted) . '</p></div>';
        }

        if (isset($_POST['bua_test_email']) && check_admin_referer('bua_tools_action', 'bua_tools_nonce')) {
            $result = BUA_Email_Notifications::send_test_email(get_option('admin_email'));
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p><span class="dashicons dashicons-email-alt"></span> ' . __('Test email sent successfully!', 'block-user-account') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><span class="dashicons dashicons-no"></span> ' . __('Failed to send test email.', 'block-user-account') . '</p></div>';
            }
        }
    }

    /**
     * Render switch field
     */
    public function render_switch_field($args)
    {
        $option_name = $args['label_for'];
        $value = get_option($option_name, 'no');
        $title = isset($args['title']) ? $args['title'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
    ?>
        <div class="bua-setting-row">
            <?php if ($title): ?>
                <div class="bua-setting-info">
                    <strong><?php echo esc_html($title); ?></strong>
                    <?php if ($description): ?>
                        <p><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="bua-setting-control">
                <label class="bua-switch">
                    <input type="checkbox" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="yes" <?php checked($value, 'yes'); ?>>
                    <span class="bua-switch-slider"></span>
                </label>
            </div>
        </div>
    <?php
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($args)
    {
        $option_name = $args['label_for'];
        $value = get_option($option_name);
    ?>
        <div class="bua-card">
            <div class="bua-card-body">
                <textarea id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" class="bua-textarea" rows="4"><?php echo esc_textarea($value); ?></textarea>
                <?php if (isset($args['description'])): ?>
                    <p class="bua-field-desc"><?php echo esc_html($args['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render number field
     */
    public function render_number_field($args)
    {
        $option_name = $args['label_for'];
        $value = get_option($option_name, 90);
    ?>
        <div class="bua-card">
            <div class="bua-card-body">
                <div class="bua-number-field">
                    <input type="number" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($value); ?>" min="7" max="365" step="1" class="small-text">
                    <span class="bua-number-suffix"><?php _e('days', 'block-user-account'); ?></span>
                </div>
                <?php if (isset($args['description'])): ?>
                    <p class="bua-field-desc"><?php echo esc_html($args['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    public function render_notifications_section()
    {
        echo '<div class="bua-section-header"><span class="dashicons dashicons-email"></span><h2>' . __('Email Notifications', 'block-user-account') . '</h2></div>';
    }
    public function render_general_section()
    {
        echo '<div class="bua-section-header"><span class="dashicons dashicons-admin-generic"></span><h2>' . __('General Settings', 'block-user-account') . '</h2></div>';
    }
    public function render_logs_section()
    {
        echo '<div class="bua-section-header"><span class="dashicons dashicons-list-view"></span><h2>' . __('Activity Logs', 'block-user-account') . '</h2></div>';
    }

    public function sanitize_checkbox($value)
    {
        return $value === 'yes' ? 'yes' : 'no';
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'settings_page_block-user-account-settings') {
            return;
        }

        wp_enqueue_style('bua-admin-settings', BUA_ASSETS_URL . 'css/admin-settings.css', array(), BUA_VERSION);
        wp_enqueue_script('bua-admin-settings', BUA_ASSETS_URL . 'js/admin-settings.js', array('jquery'), BUA_VERSION, true);
    }
}
