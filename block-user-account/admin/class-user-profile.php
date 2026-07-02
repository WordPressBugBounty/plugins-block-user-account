<?php

/**
 * User Profile Class
 *
 * Manages block-related fields in user profile pages
 *
 * @package    Block_User_Account
 * @subpackage Admin
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_User_Profile
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('edit_user_profile', array($this, 'add_block_fields'));
        add_action('personal_options_update', array($this, 'save_block_fields'));
        add_action('edit_user_profile_update', array($this, 'save_block_fields'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_bua_get_user_history', array($this, 'ajax_get_user_history'));
        add_action('admin_print_footer_scripts', array($this, 'add_inline_script'));
    }

    /**
     * Add block management fields to user profile
     *
     * @param WP_User $user User object
     */
    public function add_block_fields($user)
    {
        $user_id = $user->ID;
        $current_user_id = get_current_user_id();

        if ($user_id === $current_user_id || !current_user_can('edit_users')) {
            return;
        }

        $is_blocked      = get_user_meta($user_id, 'user_status', true) === 'deactive';
        $block_message   = get_user_meta($user_id, 'user_status_message', true);
        $block_expiry    = get_user_meta($user_id, 'block_expiry_date', true);
        $blocked_by      = get_user_meta($user_id, 'blocked_by', true);
        $blocked_date    = get_user_meta($user_id, 'blocked_date', true);
?>
        <h2><?php _e('Account Blocking Management', 'block-user-account'); ?></h2>

        <table class="form-table" id="bua-block-fields">
            <tr>
                <th>
                    <label for="bua_user_status"><?php _e('Account Status', 'block-user-account'); ?></label>
                </th>
                <td>
                    <label class="bua-toggle-switch">
                        <input type="checkbox"
                            id="bua_user_status"
                            name="bua_user_status"
                            value="deactive"
                            <?php checked($is_blocked); ?>>
                        <span class="bua-toggle-slider"></span>
                    </label>
                    <span class="bua-status-text" id="bua-status-indicator">
                        <?php if ($is_blocked): ?>
                            <span style="color: #e74c3c;">&#9679;</span>
                            <?php _e('Blocked', 'block-user-account'); ?>
                        <?php else: ?>
                            <span style="color: #27ae60;">&#9679;</span>
                            <?php _e('Active', 'block-user-account'); ?>
                        <?php endif; ?>
                    </span>
                </td>
            </tr>

            <tr class="bua-block-options" <?php echo !$is_blocked ? 'style="display:none;"' : ''; ?>>
                <th>
                    <label for="bua_user_status_message"><?php _e('Block Reason', 'block-user-account'); ?></label>
                </th>
                <td>
                    <textarea id="bua_user_status_message"
                        name="bua_user_status_message"
                        class="large-text"
                        rows="4"
                        placeholder="<?php esc_attr_e('Enter the reason for blocking this user...', 'block-user-account'); ?>"><?php echo esc_textarea($block_message); ?></textarea>
                    <p class="description">
                        <?php _e('This message will be shown to the user when they try to login. Leave empty to use the default message.', 'block-user-account'); ?>
                    </p>
                </td>
            </tr>

            <tr class="bua-block-options" <?php echo !$is_blocked ? 'style="display:none;"' : ''; ?>>
                <th>
                    <label for="bua_block_duration"><?php _e('Block Duration', 'block-user-account'); ?></label>
                </th>
                <td>
                    <select id="bua_block_duration" name="bua_block_duration">
                        <option value="permanent" <?php selected(empty($block_expiry)); ?>>
                            <?php _e('Permanent', 'block-user-account'); ?>
                        </option>
                        <option value="custom" <?php selected(!empty($block_expiry)); ?>>
                            <?php _e('Custom Date', 'block-user-account'); ?>
                        </option>
                        <option value="1_day"><?php _e('1 Day', 'block-user-account'); ?></option>
                        <option value="7_days"><?php _e('7 Days', 'block-user-account'); ?></option>
                        <option value="30_days"><?php _e('30 Days', 'block-user-account'); ?></option>
                        <option value="90_days"><?php _e('90 Days', 'block-user-account'); ?></option>
                    </select>

                    <div id="bua-custom-expiry-wrapper"
                        <?php echo empty($block_expiry) ? 'style="display:none;"' : ''; ?>>
                        <br><br>
                        <input type="datetime-local"
                            id="bua_block_expiry"
                            name="bua_block_expiry"
                            value="<?php echo esc_attr($block_expiry); ?>">
                        <p class="description">
                            <?php _e('Select the date and time when this block should automatically expire.', 'block-user-account'); ?>
                        </p>
                    </div>
                </td>
            </tr>

            <?php if ($is_blocked): ?>
                <tr class="bua-block-options">
                    <th><?php _e('Block Information', 'block-user-account'); ?></th>
                    <td>
                        <div class="bua-block-details">
                            <?php if ($blocked_by):
                                $admin_user = get_userdata($blocked_by);
                                $admin_name = $admin_user ? $admin_user->display_name : __('Unknown', 'block-user-account');
                            ?>
                                <p>
                                    <strong><?php _e('Blocked By:', 'block-user-account'); ?></strong>
                                    <?php echo esc_html($admin_name); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($blocked_date): ?>
                                <p>
                                    <strong><?php _e('Blocked On:', 'block-user-account'); ?></strong>
                                    <?php echo date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($blocked_date)
                                    ); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($block_expiry): ?>
                                <p>
                                    <strong><?php _e('Expires On:', 'block-user-account'); ?></strong>
                                    <?php echo date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($block_expiry)
                                    ); ?>
                                </p>
                                <?php
                                $remaining = strtotime($block_expiry) - current_time('timestamp');
                                if ($remaining > 0):
                                    $days = floor($remaining / DAY_IN_SECONDS);
                                    $hours = floor(($remaining % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
                                ?>
                                    <p>
                                        <strong><?php _e('Time Remaining:', 'block-user-account'); ?></strong>
                                        <?php
                                        if ($days > 0) {
                                            printf(__('%d days, %d hours', 'block-user-account'), $days, $hours);
                                        } else {
                                            printf(__('%d hours', 'block-user-account'), $hours);
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>
                                    <strong><?php _e('Duration:', 'block-user-account'); ?></strong>
                                    <span style="color: #e74c3c;"><?php _e('Permanent', 'block-user-account'); ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <?php if ($is_blocked): ?>
                    <tr id="bua-history-row">
                        <th><?php _e('Block History', 'block-user-account'); ?></th>
                        <td>
                            <div id="bua-history-container" class="bua-history-container">
                                <div class="bua-history-loading">
                                    <span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span>
                                    <?php _e('Loading history...', 'block-user-account'); ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>

            <?php wp_nonce_field('bua_save_profile_' . $user_id, 'bua_profile_nonce'); ?>
        </table>
        <?php
    }

    /**
     * Save block fields from user profile
     *
     * @param int $user_id User ID
     */
    public function save_block_fields($user_id)
    {
        if (
            !isset($_POST['bua_profile_nonce']) ||
            !wp_verify_nonce($_POST['bua_profile_nonce'], 'bua_save_profile_' . $user_id)
        ) {
            return;
        }

        if (!current_user_can('edit_users')) {
            return;
        }

        if ($user_id === get_current_user_id()) {
            return;
        }

        $current_status = get_user_meta($user_id, 'user_status', true);
        $new_status     = isset($_POST['bua_user_status']) ? sanitize_text_field($_POST['bua_user_status']) : '';
        $changed        = false;

        if ($new_status === 'deactive') {
            update_user_meta($user_id, 'user_status', 'deactive');
            update_user_meta($user_id, 'blocked_by', get_current_user_id());

            if (empty($current_status) || $current_status !== 'deactive') {
                update_user_meta($user_id, 'blocked_date', current_time('mysql'));
                $changed = true;
            }

            if (!empty($_POST['bua_user_status_message'])) {
                update_user_meta(
                    $user_id,
                    'user_status_message',
                    sanitize_textarea_field($_POST['bua_user_status_message'])
                );
            } else {
                delete_user_meta($user_id, 'user_status_message');
            }

            if (!empty($_POST['bua_block_expiry']) && $_POST['bua_block_duration'] !== 'permanent') {
                update_user_meta(
                    $user_id,
                    'block_expiry_date',
                    sanitize_text_field($_POST['bua_block_expiry'])
                );
            } else {
                delete_user_meta($user_id, 'block_expiry_date');
            }

            if ($changed) {
                BUA_Logger::log($user_id, 'user_blocked', $_POST['bua_user_status_message'] ?? '');
                do_action('bua_user_blocked', $user_id, $_POST['bua_user_status_message'] ?? '');
            }
        } else {
            delete_user_meta($user_id, 'user_status');
            delete_user_meta($user_id, 'user_status_message');
            delete_user_meta($user_id, 'block_expiry_date');
            delete_user_meta($user_id, 'blocked_by');
            delete_user_meta($user_id, 'blocked_date');

            if ($current_status === 'deactive') {
                BUA_Logger::log($user_id, 'user_unblocked');
                do_action('bua_user_unblocked', $user_id);
            }
        }

        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();
    }

    /**
     * AJAX handler for loading user history
     */
    public function ajax_get_user_history()
    {
        check_ajax_referer('bua_get_history', 'nonce');

        if (!current_user_can('edit_users')) {
            wp_die(__('Permission denied.', 'block-user-account'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_die(__('Invalid user ID.', 'block-user-account'));
        }

        $history = BUA_Logger::get_user_history($user_id, 20);

        if (empty($history)) {
            echo '<div class="bua-history-empty">';
            echo '<span class="dashicons dashicons-clock"></span>';
            echo '<p>' . __('No history available for this user.', 'block-user-account') . '</p>';
            echo '</div>';
            wp_die();
        }

        echo '<ul class="bua-history-list">';
        foreach ($history as $entry) {
            $admin_user  = get_userdata($entry->admin_id);
            $admin_name  = $admin_user ? $admin_user->display_name : __('System', 'block-user-account');
            $action_label = BUA_Logger::get_action_label($entry->action);
        ?>
            <li>
                <strong><?php echo esc_html($admin_name); ?></strong>
                — <?php echo esc_html($action_label); ?>
                <small>
                    <?php echo human_time_diff(strtotime($entry->timestamp), current_time('timestamp')) . ' ' . __('ago', 'block-user-account'); ?>
                </small>
                <?php if (!empty($entry->reason)): ?>
                    <em><?php echo esc_html($entry->reason); ?></em>
                <?php endif; ?>
            </li>
        <?php
        }
        echo '</ul>';
        wp_die();
    }

    /**
     * Enqueue assets for profile pages
     *
     * @param string $hook Current admin page
     */
    public function enqueue_assets($hook)
    {
        if (!in_array($hook, array('user-edit.php', 'profile.php', 'index.php'))) {
            return;
        }

        wp_enqueue_style(
            'bua-profile-style',
            BUA_ASSETS_URL . 'css/admin-style.css',
            array(),
            BUA_VERSION
        );
    }

    /**
     * Add inline script for dynamic functionality
     */
    public function add_inline_script()
    {
        $screen = get_current_screen();

        if (!in_array($screen->id, array('user-edit', 'profile'))) {
            return;
        }


        if (did_action('bua_inline_script_loaded')) {
            return;
        }

        do_action('bua_inline_script_loaded');

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $is_blocked = get_user_meta($user_id, 'user_status', true) === 'deactive';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                console.log('BUA: Script loaded once');

                var userId = <?php echo $user_id; ?>;
                var historyLoaded = false;

                function loadHistory() {
                    if (!userId || historyLoaded) return;

                    console.log('BUA: Loading history for user', userId);
                    historyLoaded = true;

                    $('#bua-history-row').remove();

                    var row = $(
                        '<tr id="bua-history-row">' +
                        '<th><?php echo esc_js(__('Block History', 'block-user-account')); ?></th>' +
                        '<td><div id="bua-history-container" class="bua-history-container">' +
                        '<div class="bua-history-loading">' +
                        '<span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span> ' +
                        '<?php echo esc_js(__('Loading history...', 'block-user-account')); ?>' +
                        '</div></div></td>' +
                        '</tr>'
                    );

                    $('#bua-block-fields').append(row);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'bua_get_user_history',
                            user_id: userId,
                            nonce: '<?php echo wp_create_nonce('bua_get_history'); ?>'
                        },
                        success: function(response) {
                            if (response) {
                                $('#bua-history-container').html(response);
                            }
                        },
                        error: function() {
                            $('#bua-history-container').html(
                                '<div class="bua-history-empty">' +
                                '<p><?php echo esc_js(__('Failed to load history.', 'block-user-account')); ?></p>' +
                                '</div>'
                            );
                        }
                    });
                }

                $('#bua_user_status').off('change').on('change', function() {
                    var isChecked = $(this).is(':checked');

                    if (isChecked) {
                        $('.bua-block-options').slideDown(300);
                        $('#bua-status-indicator').html(
                            '<span style="color:#e74c3c;">&#9679;</span> ' +
                            '<?php echo esc_js(__('Blocked', 'block-user-account')); ?>'
                        );
                        historyLoaded = false;
                        loadHistory();
                    } else {
                        $('.bua-block-options').slideUp(300);
                        $('#bua-status-indicator').html(
                            '<span style="color:#27ae60;">&#9679;</span> ' +
                            '<?php echo esc_js(__('Active', 'block-user-account')); ?>'
                        );
                        $('#bua-history-row').remove();
                        historyLoaded = false;
                    }
                });

                $('#bua_block_duration').off('change').on('change', function() {
                    var value = $(this).val();

                    if (value === 'custom') {
                        $('#bua-custom-expiry-wrapper').slideDown(300);
                    } else {
                        $('#bua-custom-expiry-wrapper').slideUp(300);

                        var days = parseInt(value);
                        if (days > 0) {
                            var d = new Date();
                            d.setDate(d.getDate() + days);
                            var formatted = d.getFullYear() + '-' +
                                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                                String(d.getDate()).padStart(2, '0') + 'T' +
                                String(d.getHours()).padStart(2, '0') + ':' +
                                String(d.getMinutes()).padStart(2, '0');
                            $('#bua_block_expiry').val(formatted);
                        }
                    }
                });

                <?php if ($is_blocked): ?>
                    loadHistory();
                <?php endif; ?>
            });
        </script>
    <?php
    }

    /**
     * Get blocked users count for dashboard widget
     *
     * @return int Number of blocked users
     */
    public static function get_blocked_count()
    {
        return BUA_Logger::get_blocked_users_count();
    }

    /**
     * Get users expiring soon for dashboard widget
     *
     * @param int $days Number of days to check
     * @return array Array of user objects
     */
    public static function get_expiring_soon($days = 2)
    {
        $future = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $now    = current_time('mysql');

        return get_users(array(
            'meta_key'     => 'block_expiry_date',
            'meta_value'   => array($now, $future),
            'meta_compare' => 'BETWEEN',
            'meta_type'    => 'DATETIME',
            'number'       => 10
        ));
    }

    /**
     * Add dashboard widget
     */
    public static function add_dashboard_widget()
    {
        if (current_user_can('edit_users')) {
            wp_add_dashboard_widget(
                'bua_dashboard_widget',
                __('Blocked Users Overview', 'block-user-account'),
                array(__CLASS__, 'render_dashboard_widget')
            );
        }
    }

    /**
     * Render dashboard widget
     */
    public static function render_dashboard_widget()
    {
        $blocked_count  = self::get_blocked_count();
        $expiring_soon  = self::get_expiring_soon(3);
        $recent_blocks  = BUA_Logger::get_recent_activities(5);
        $total_logs     = BUA_Logger::get_total_count();
        $stats          = BUA_Logger::get_statistics('week');
    ?>
        <div class="bua-dashboard-widget">
            <div class="bua-dash-header">
                <div class="bua-dash-header-icon">
                    <span class="dashicons dashicons-shield"></span>
                </div>
                <div class="bua-dash-header-info">
                    <h3><?php _e('User Block Overview', 'block-user-account'); ?></h3>
                    <p><?php _e('Real-time account management summary', 'block-user-account'); ?></p>
                </div>
            </div>

            <div class="bua-dash-stats">
                <div class="bua-dash-stat-card">
                    <div class="bua-dash-stat-number bua-dash-blocked"><?php echo esc_html($blocked_count); ?></div>
                    <div class="bua-dash-stat-label"><?php _e('Blocked Users', 'block-user-account'); ?></div>
                </div>
                <div class="bua-dash-stat-card">
                    <div class="bua-dash-stat-number bua-dash-expiring"><?php echo esc_html(count($expiring_soon)); ?></div>
                    <div class="bua-dash-stat-label"><?php _e('Expiring Soon', 'block-user-account'); ?></div>
                </div>
                <div class="bua-dash-stat-card">
                    <div class="bua-dash-stat-number bua-dash-logs"><?php echo esc_html($total_logs); ?></div>
                    <div class="bua-dash-stat-label"><?php _e('Total Logs', 'block-user-account'); ?></div>
                </div>
                <div class="bua-dash-stat-card">
                    <div class="bua-dash-stat-number bua-dash-actions"><?php echo esc_html($stats['total_blocks'] + $stats['total_unblocks']); ?></div>
                    <div class="bua-dash-stat-label"><?php _e('Actions This Week', 'block-user-account'); ?></div>
                </div>
            </div>

            <?php if (!empty($expiring_soon)): ?>
                <div class="bua-dash-section">
                    <div class="bua-dash-section-title">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Expiring Soon', 'block-user-account'); ?>
                    </div>
                    <ul class="bua-dash-list">
                        <?php foreach ($expiring_soon as $user):
                            $expiry = get_user_meta($user->ID, 'block_expiry_date', true);
                            $remaining = strtotime($expiry) - current_time('timestamp');
                            $hours = floor($remaining / HOUR_IN_SECONDS);
                            $days = floor($remaining / DAY_IN_SECONDS);

                            if ($hours < 24) {
                                $time_text = sprintf(__('%d hours', 'block-user-account'), $hours);
                                $time_class = 'bua-time-critical';
                            } else {
                                $time_text = sprintf(__('%d days', 'block-user-account'), $days);
                                $time_class = 'bua-time-warning';
                            }
                        ?>
                            <li>
                                <?php echo get_avatar($user->ID, 16, '', '', array('class' => 'bua-dash-avatar')); ?>
                                <a href="<?php echo get_edit_user_link($user->ID); ?>" class="bua-dash-user-link">
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                                <span class="bua-dash-time-remaining <?php echo $time_class; ?>">
                                    — <?php echo esc_html($time_text); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($recent_blocks)): ?>
                <div class="bua-dash-section">
                    <div class="bua-dash-section-title">
                        <?php _e('Recent Activity', 'block-user-account'); ?>
                    </div>
                    <ul class="bua-dash-list">
                        <?php foreach ($recent_blocks as $activity):
                            $user = get_userdata($activity->user_id);
                            $user_name = $user ? $user->display_name : __('Unknown', 'block-user-account');
                            $action_label = BUA_Logger::get_action_label($activity->action);

                            $badge_class = 'bua-badge-block';
                            if (strpos($activity->action, 'unblock') !== false) {
                                $badge_class = 'bua-badge-unblock';
                            } elseif (strpos($activity->action, 'auto') !== false) {
                                $badge_class = 'bua-badge-auto';
                            }
                        ?>
                            <li>
                                <span class="bua-dash-action-badge <?php echo $badge_class; ?>">
                                    <?php echo esc_html($action_label); ?>
                                </span>
                                <strong><?php echo esc_html($user_name); ?></strong>
                                <span class="bua-dash-meta">
                                    — <?php echo human_time_diff(strtotime($activity->timestamp), current_time('timestamp')) . ' ' . __('ago', 'block-user-account'); ?>
                                </span>
                                <?php if (!empty($activity->reason)): ?>
                                    <br><span class="bua-dash-reason">📝 <?php echo esc_html($activity->reason); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="bua-dash-empty">
                    <span class="dashicons dashicons-clock"></span>
                    <p><?php _e('No recent activity', 'block-user-account'); ?></p>
                </div>
            <?php endif; ?>

            <div class="bua-dash-footer">
                <a href="<?php echo admin_url('users.php?bua_filter=blocked'); ?>" class="button">
                    <span class="dashicons dashicons-lock"></span> <?php _e('Blocked Users', 'block-user-account'); ?>
                </a>
                <a href="<?php echo admin_url('users.php'); ?>" class="button">
                    <span class="dashicons dashicons-admin-users"></span> <?php _e('All Users', 'block-user-account'); ?>
                </a>
                <a href="<?php echo admin_url('options-general.php?page=block-user-account-settings'); ?>" class="button">
                    <span class="dashicons dashicons-admin-settings"></span> <?php _e('Settings', 'block-user-account'); ?>
                </a>
            </div>
        </div>
<?php
    }
}
