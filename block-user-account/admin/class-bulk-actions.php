<?php

/**
 * Bulk Actions Class
 *
 * Handles bulk operations for blocking and unblocking users
 *
 * @package    Block_User_Account
 * @subpackage Admin
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Bulk_Actions
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('bulk_actions-users', array($this, 'register_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_actions'), 10, 3);

        add_action('admin_notices', array($this, 'display_bulk_action_notices'));
        add_action('admin_footer-users.php', array($this, 'add_bulk_action_modals'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Register custom bulk actions
     *
     * @param array $bulk_actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function register_bulk_actions($bulk_actions)
    {
        $bulk_actions['bua_block_permanent'] = __('Block Users (Permanent)', 'block-user-account');
        $bulk_actions['bua_block_30_days']   = __('Block Users (30 Days)', 'block-user-account');
        $bulk_actions['bua_block_7_days']    = __('Block Users (7 Days)', 'block-user-account');
        $bulk_actions['bua_block_1_day']     = __('Block Users (1 Day)', 'block-user-account');
        $bulk_actions['bua_unblock_users']   = __('Unblock Users', 'block-user-account');

        return $bulk_actions;
    }

    /**
     * Handle bulk actions
     *
     * @param string $redirect_to Redirect URL
     * @param string $action      Action name
     * @param array  $user_ids    Selected user IDs
     * @return string Modified redirect URL
     */
    public function handle_bulk_actions($redirect_to, $action, $user_ids)
    {
        if (!current_user_can('edit_users')) {
            wp_die(__('You do not have permission to perform this action.', 'block-user-account'));
        }

        $current_user_id = get_current_user_id();
        $processed       = 0;
        $skipped         = 0;
        $errors          = array();

        switch ($action) {
            case 'bua_block_permanent':
                foreach ($user_ids as $user_id) {
                    if ($user_id == $current_user_id) {
                        $skipped++;
                        continue;
                    }

                    if ($this->block_user($user_id)) {
                        $processed++;
                    } else {
                        $errors[] = $user_id;
                    }
                }

                $redirect_to = add_query_arg('bua_bulk_result', 'permanent_blocked', $redirect_to);
                break;

            case 'bua_block_30_days':
                $expiry = date('Y-m-d\TH:i', strtotime('+30 days'));

                foreach ($user_ids as $user_id) {
                    if ($user_id == $current_user_id) {
                        $skipped++;
                        continue;
                    }

                    if ($this->block_user($user_id, $expiry)) {
                        $processed++;
                    } else {
                        $errors[] = $user_id;
                    }
                }

                $redirect_to = add_query_arg('bua_bulk_result', 'temp30_blocked', $redirect_to);
                break;

            case 'bua_block_7_days':
                $expiry = date('Y-m-d\TH:i', strtotime('+7 days'));

                foreach ($user_ids as $user_id) {
                    if ($user_id == $current_user_id) {
                        $skipped++;
                        continue;
                    }

                    if ($this->block_user($user_id, $expiry)) {
                        $processed++;
                    } else {
                        $errors[] = $user_id;
                    }
                }

                $redirect_to = add_query_arg('bua_bulk_result', 'temp7_blocked', $redirect_to);
                break;

            case 'bua_block_1_day':
                $expiry = date('Y-m-d\TH:i', strtotime('+1 day'));

                foreach ($user_ids as $user_id) {
                    if ($user_id == $current_user_id) {
                        $skipped++;
                        continue;
                    }

                    if ($this->block_user($user_id, $expiry)) {
                        $processed++;
                    } else {
                        $errors[] = $user_id;
                    }
                }

                $redirect_to = add_query_arg('bua_bulk_result', 'temp1_blocked', $redirect_to);
                break;

            case 'bua_unblock_users':
                foreach ($user_ids as $user_id) {
                    if ($this->unblock_user($user_id)) {
                        $processed++;
                    } else {
                        $errors[] = $user_id;
                    }
                }

                $redirect_to = add_query_arg('bua_bulk_result', 'unblocked', $redirect_to);
                break;

            default:
                return $redirect_to;
        }

        $redirect_to = add_query_arg('bua_processed', $processed, $redirect_to);

        if ($skipped > 0) {
            $redirect_to = add_query_arg('bua_skipped', $skipped, $redirect_to);
        }

        if (!empty($errors)) {
            $redirect_to = add_query_arg('bua_errors', count($errors), $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * Block a user
     *
     * @param int    $user_id User ID
     * @param string $expiry  Optional expiry date
     * @return bool Success or failure
     */
    private function block_user($user_id, $expiry = '')
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $already_blocked = get_user_meta($user_id, 'user_status', true) === 'deactive';

        update_user_meta($user_id, 'user_status', 'deactive');
        update_user_meta($user_id, 'blocked_by', get_current_user_id());

        if (!$already_blocked) {
            update_user_meta($user_id, 'blocked_date', current_time('mysql'));
        }

        if (!empty($expiry)) {
            update_user_meta($user_id, 'block_expiry_date', $expiry);
        } else {
            delete_user_meta($user_id, 'block_expiry_date');
        }

        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();

        $action_type = empty($expiry) ? 'bulk_permanent_blocked' : 'bulk_temp_blocked';
        BUA_Logger::log($user_id, $action_type);

        do_action('bua_user_blocked', $user_id);

        return true;
    }

    /**
     * Unblock a user
     *
     * @param int $user_id User ID
     * @return bool Success or failure
     */
    private function unblock_user($user_id)
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $was_blocked = get_user_meta($user_id, 'user_status', true) === 'deactive';

        delete_user_meta($user_id, 'user_status');
        delete_user_meta($user_id, 'user_status_message');
        delete_user_meta($user_id, 'block_expiry_date');
        delete_user_meta($user_id, 'blocked_by');
        delete_user_meta($user_id, 'blocked_date');

        if ($was_blocked) {
            BUA_Logger::log($user_id, 'bulk_unblocked');
            do_action('bua_user_unblocked', $user_id);
        }

        return true;
    }

    /**
     * Display admin notices after bulk actions
     */
    public function display_bulk_action_notices()
    {
        $screen = get_current_screen();

        if ($screen->id !== 'users') {
            return;
        }

        if (!isset($_GET['bua_bulk_result'])) {
            return;
        }

        $result     = sanitize_text_field($_GET['bua_bulk_result']);
        $processed  = isset($_GET['bua_processed']) ? intval($_GET['bua_processed']) : 0;
        $skipped    = isset($_GET['bua_skipped']) ? intval($_GET['bua_skipped']) : 0;
        $errors     = isset($_GET['bua_errors']) ? intval($_GET['bua_errors']) : 0;

        $messages = array(
            'permanent_blocked' => __('%d user(s) permanently blocked.', 'block-user-account'),
            'temp30_blocked'    => __('%d user(s) blocked for 30 days.', 'block-user-account'),
            'temp7_blocked'     => __('%d user(s) blocked for 7 days.', 'block-user-account'),
            'temp1_blocked'     => __('%d user(s) blocked for 1 day.', 'block-user-account'),
            'unblocked'         => __('%d user(s) unblocked successfully.', 'block-user-account')
        );

        if (!isset($messages[$result])) {
            return;
        }

        $type    = 'success';
        $message = sprintf($messages[$result], $processed);

        if ($skipped > 0) {
            $message .= ' ' . sprintf(__('%d user(s) skipped.', 'block-user-account'), $skipped);
        }

        if ($errors > 0) {
            $type     = 'warning';
            $message .= ' ' . sprintf(__('%d user(s) failed to process.', 'block-user-account'), $errors);
        }

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    /**
     * Add bulk action confirmation modals
     */
    public function add_bulk_action_modals()
    {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var bulkActionSelectTop = $('#bulk-action-selector-top');
                var bulkActionSelectBottom = $('#bulk-action-selector-bottom');
                var customActions = [
                    'bua_block_permanent',
                    'bua_block_30_days',
                    'bua_block_7_days',
                    'bua_block_1_day',
                    'bua_unblock_users'
                ];

                function confirmBulkAction(form, action) {
                    if (customActions.indexOf(action) === -1) {
                        return true;
                    }

                    var selectedUsers = form.find('input[name="users[]"]:checked').length;

                    if (selectedUsers === 0) {
                        alert('<?php echo esc_js(__('Please select at least one user.', 'block-user-account')); ?>');
                        return false;
                    }

                    var messages = {
                        'bua_block_permanent': '<?php echo esc_js(__('Are you sure you want to permanently block %d user(s)? They will not be able to login until manually unblocked.', 'block-user-account')); ?>',
                        'bua_block_30_days': '<?php echo esc_js(__('Are you sure you want to block %d user(s) for 30 days?', 'block-user-account')); ?>',
                        'bua_block_7_days': '<?php echo esc_js(__('Are you sure you want to block %d user(s) for 7 days?', 'block-user-account')); ?>',
                        'bua_block_1_day': '<?php echo esc_js(__('Are you sure you want to block %d user(s) for 1 day?', 'block-user-account')); ?>',
                        'bua_unblock_users': '<?php echo esc_js(__('Are you sure you want to unblock %d user(s)?', 'block-user-account')); ?>'
                    };

                    var message = messages[action].replace('%d', selectedUsers);
                    return confirm(message);
                }

                $('#posts-filter').on('submit', function(e) {
                    var action = bulkActionSelectTop.val();

                    if (action === '-1') {
                        action = bulkActionSelectBottom.val();
                    }

                    if (!confirmBulkAction($(this), action)) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        </script>
<?php
    }

    /**
     * Enqueue assets for users list page
     *
     * @param string $hook Current admin page
     */
    public function enqueue_assets($hook)
    {
        if ($hook !== 'users.php') {
            return;
        }

        wp_enqueue_style(
            'bua-bulk-actions-style',
            BUA_ASSETS_URL . 'css/bulk-actions.css',
            array(),
            BUA_VERSION
        );

        wp_enqueue_script(
            'bua-bulk-actions-script',
            BUA_ASSETS_URL . 'js/bulk-actions.js',
            array('jquery'),
            BUA_VERSION,
            true
        );

        wp_localize_script('bua-bulk-actions-script', 'buaBulkActions', array(
            'i18n' => array(
                'select_users'         => __('Please select at least one user.', 'block-user-account'),
                'confirm_permanent'    => __('Are you sure you want to permanently block %d user(s)?', 'block-user-account'),
                'confirm_temp_30'      => __('Are you sure you want to block %d user(s) for 30 days?', 'block-user-account'),
                'confirm_temp_7'       => __('Are you sure you want to block %d user(s) for 7 days?', 'block-user-account'),
                'confirm_temp_1'       => __('Are you sure you want to block %d user(s) for 1 day?', 'block-user-account'),
                'confirm_unblock'      => __('Are you sure you want to unblock %d user(s)?', 'block-user-account'),
                'processing'           => __('Processing bulk action...', 'block-user-account')
            )
        ));
    }

    /**
     * Get bulk action statistics
     *
     * @param string $period Period to check
     * @return array Statistics
     */
    public static function get_bulk_stats($period = 'month')
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
            default:
                $date = date('Y-m-d', strtotime('-30 days'));
        }

        $stats = array(
            'total_bulk_blocks'   => 0,
            'total_bulk_unblocks' => 0,
            'average_per_bulk'    => 0
        );

        $total_bulk_blocks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action IN ('bulk_permanent_blocked', 'bulk_temp_blocked') 
             AND timestamp >= %s",
            $date
        ));
        $stats['total_bulk_blocks'] = intval($total_bulk_blocks);

        $total_bulk_unblocks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action = 'bulk_unblocked' 
             AND timestamp >= %s",
            $date
        ));
        $stats['total_bulk_unblocks'] = intval($total_bulk_unblocks);

        return $stats;
    }
}
