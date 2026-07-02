<?php

/**
 * User List Class
 *
 * Manages custom columns and filters in the WordPress users list table
 *
 * @package    Block_User_Account
 * @subpackage Admin
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_User_List
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('manage_users_columns', array($this, 'add_custom_columns'));
        add_action('manage_users_custom_column', array($this, 'render_custom_columns'), 10, 3);
        add_filter('manage_users_sortable_columns', array($this, 'make_columns_sortable'));
        add_action('restrict_manage_users', array($this, 'add_user_filters'));
        add_filter('users_list_table_query_args', array($this, 'filter_users_by_status'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('user_row_actions', array($this, 'add_quick_actions'), 10, 2);
    }

    /**
     * Add custom columns to users table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_custom_columns($columns)
    {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'role') {
                $new_columns['bua_status']     = __('Status', 'block-user-account');
                $new_columns['bua_reason']     = __('Block Reason', 'block-user-account');
                $new_columns['bua_expiry']     = __('Block Expiry', 'block-user-account');
                $new_columns['bua_blocked_by'] = __('Blocked By', 'block-user-account');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content
     *
     * @param string $value       Column value
     * @param string $column_name Column name
     * @param int    $user_id     User ID
     * @return string Column content
     */
    public function render_custom_columns($value, $column_name, $user_id)
    {
        $is_blocked = get_user_meta($user_id, 'user_status', true) === 'deactive';

        switch ($column_name) {
            case 'bua_status':
                return $this->render_status_column($user_id, $is_blocked);

            case 'bua_reason':
                return $this->render_reason_column($user_id, $is_blocked);

            case 'bua_expiry':
                return $this->render_expiry_column($user_id, $is_blocked);

            case 'bua_blocked_by':
                return $this->render_blocked_by_column($user_id, $is_blocked);
        }

        return $value;
    }

    /**
     * Render status column
     *
     * @param int  $user_id    User ID
     * @param bool $is_blocked Whether user is blocked
     * @return string HTML content
     */
    private function render_status_column($user_id, $is_blocked)
    {
        if ($is_blocked) {
            $expiry      = get_user_meta($user_id, 'block_expiry_date', true);
            $status_class = $expiry ? 'bua-temp-blocked' : 'bua-perma-blocked';
            $icon         = '<span class="dashicons dashicons-lock"></span>';
            $text         = __('Blocked', 'block-user-account');
        } else {
            $status_class = 'bua-active';
            $icon         = '<span class="dashicons dashicons-yes-alt"></span>';
            $text         = __('Active', 'block-user-account');
        }

        return sprintf(
            '<span class="bua-badge %s" data-user-id="%d">%s %s</span>',
            esc_attr($status_class),
            esc_attr($user_id),
            $icon,
            esc_html($text)
        );
    }

    /**
     * Render reason column
     *
     * @param int  $user_id    User ID
     * @param bool $is_blocked Whether user is blocked
     * @return string HTML content
     */
    private function render_reason_column($user_id, $is_blocked)
    {
        if (!$is_blocked) {
            return '<span aria-hidden="true" class="bua-na">—</span>';
        }

        $reason = get_user_meta($user_id, 'user_status_message', true);

        if (empty($reason)) {
            return '<em class="bua-no-reason">' . __('No reason provided', 'block-user-account') . '</em>';
        }

        $truncated = mb_strlen($reason) > 50 ? mb_substr($reason, 0, 50) . '...' : $reason;

        return sprintf(
            '<span class="bua-reason-text" title="%s">%s</span>',
            esc_attr($reason),
            esc_html($truncated)
        );
    }

    /**
     * Render expiry column
     *
     * @param int  $user_id    User ID
     * @param bool $is_blocked Whether user is blocked
     * @return string HTML content
     */
    private function render_expiry_column($user_id, $is_blocked)
    {
        if (!$is_blocked) {
            return '<span aria-hidden="true" class="bua-na">—</span>';
        }

        $expiry = get_user_meta($user_id, 'block_expiry_date', true);

        if (empty($expiry)) {
            return '<span class="bua-permanent">' . __('Permanent', 'block-user-account') . '</span>';
        }

        $timestamp = strtotime($expiry);
        $now       = current_time('timestamp');

        if ($timestamp < $now) {
            return '<span class="bua-expired">' . __('Expired', 'block-user-account') . '</span>';
        }

        $diff  = $timestamp - $now;
        $days  = floor($diff / DAY_IN_SECONDS);
        $hours = floor(($diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);

        if ($days > 0) {
            $time_left = sprintf(__('%d days', 'block-user-account'), $days);
        } elseif ($hours > 0) {
            $time_left = sprintf(__('%d hours', 'block-user-account'), $hours);
        } else {
            $time_left = __('Less than 1 hour', 'block-user-account');
        }

        if ($days <= 2 && $days > 0) {
            $class = 'bua-expiring-soon';
        } elseif ($days <= 7) {
            $class = 'bua-expiring-week';
        } else {
            $class = 'bua-expiring-later';
        }

        return sprintf(
            '<span class="%s" title="%s">%s<br><small>%s</small></span>',
            esc_attr($class),
            esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)),
            esc_html($time_left),
            esc_html(date_i18n('Y/m/d H:i', $timestamp))
        );
    }

    /**
     * Render blocked by column
     *
     * @param int  $user_id    User ID
     * @param bool $is_blocked Whether user is blocked
     * @return string HTML content
     */
    private function render_blocked_by_column($user_id, $is_blocked)
    {
        if (!$is_blocked) {
            return '<span aria-hidden="true" class="bua-na">—</span>';
        }

        $blocked_by = get_user_meta($user_id, 'blocked_by', true);

        if (empty($blocked_by)) {
            return '<em>' . __('Unknown', 'block-user-account') . '</em>';
        }

        $admin_user = get_userdata($blocked_by);

        if (!$admin_user) {
            return '<em>' . __('Deleted User', 'block-user-account') . '</em>';
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(add_query_arg('s', urlencode($admin_user->user_email), admin_url('users.php'))),
            esc_html($admin_user->display_name)
        );
    }

    /**
     * Make custom columns sortable
     *
     * @param array $columns Sortable columns
     * @return array Modified sortable columns
     */
    public function make_columns_sortable($columns)
    {
        $columns['bua_status'] = 'bua_blocked_status';
        $columns['bua_expiry'] = 'bua_block_expiry';
        return $columns;
    }

    /**
     * Add filter dropdown to users list
     *
     * @param string $which Top or bottom
     */
    public function add_user_filters($which)
    {
        if ($which !== 'top') {
            return;
        }

        $current_status = isset($_GET['bua_filter']) ? sanitize_text_field($_GET['bua_filter']) : '';
?>
        <select name="bua_filter" id="bua-filter" class="bua-filter-dropdown">
            <option value=""><?php _e('All Account Statuses', 'block-user-account'); ?></option>
            <option value="active" <?php selected($current_status, 'active'); ?>>
                <?php _e('Active Accounts', 'block-user-account'); ?>
            </option>
            <option value="blocked" <?php selected($current_status, 'blocked'); ?>>
                <?php _e('Blocked Accounts', 'block-user-account'); ?>
            </option>
            <option value="permanent" <?php selected($current_status, 'permanent'); ?>>
                <?php _e('Permanently Blocked', 'block-user-account'); ?>
            </option>
            <option value="temporary" <?php selected($current_status, 'temporary'); ?>>
                <?php _e('Temporarily Blocked', 'block-user-account'); ?>
            </option>
            <option value="expired" <?php selected($current_status, 'expired'); ?>>
                <?php _e('Expired Blocks', 'block-user-account'); ?>
            </option>
        </select>
        <input type="submit" name="bua_filter_submit" class="button" value="<?php esc_attr_e('Filter', 'block-user-account'); ?>">
<?php
    }

    /**
     * Filter users by block status
     *
     * @param array $args Query arguments
     * @return array Modified arguments
     */
    public function filter_users_by_status($args)
    {
        if (!isset($_GET['bua_filter']) || empty($_GET['bua_filter'])) {
            return $args;
        }

        $filter = sanitize_text_field($_GET['bua_filter']);

        if (!isset($args['meta_query'])) {
            $args['meta_query'] = array();
        }

        switch ($filter) {
            case 'active':
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'user_status',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key'     => 'user_status',
                        'value'   => 'deactive',
                        'compare' => '!='
                    )
                );
                break;

            case 'blocked':
                $args['meta_query'][] = array(
                    'key'   => 'user_status',
                    'value' => 'deactive'
                );
                break;

            case 'permanent':
                $args['meta_query'][] = array(
                    'relation' => 'AND',
                    array(
                        'key'   => 'user_status',
                        'value' => 'deactive'
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'block_expiry_date',
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key'     => 'block_expiry_date',
                            'value'   => '',
                            'compare' => '='
                        )
                    )
                );
                break;

            case 'temporary':
                $args['meta_query'][] = array(
                    'relation' => 'AND',
                    array(
                        'key'   => 'user_status',
                        'value' => 'deactive'
                    ),
                    array(
                        'key'     => 'block_expiry_date',
                        'value'   => current_time('mysql'),
                        'compare' => '>',
                        'type'    => 'DATETIME'
                    )
                );
                break;

            case 'expired':
                $args['meta_query'][] = array(
                    'relation' => 'AND',
                    array(
                        'key'   => 'user_status',
                        'value' => 'deactive'
                    ),
                    array(
                        'key'     => 'block_expiry_date',
                        'value'   => current_time('mysql'),
                        'compare' => '<=',
                        'type'    => 'DATETIME'
                    )
                );
                break;
        }

        return $args;
    }

    /**
     * Add quick action links to user row
     *
     * @param array   $actions Existing actions
     * @param WP_User $user    User object
     * @return array Modified actions
     */
    public function add_quick_actions($actions, $user)
    {
        if (!current_user_can('edit_users') || $user->ID === get_current_user_id()) {
            return $actions;
        }

        $is_blocked = get_user_meta($user->ID, 'user_status', true) === 'deactive';
        $nonce      = wp_create_nonce('bua_quick_action_' . $user->ID);

        if ($is_blocked) {
            $actions['bua_unblock'] = sprintf(
                '<a href="#" class="bua-row-action" data-user-id="%d" data-action="unblock" data-nonce="%s">%s</a>',
                $user->ID,
                $nonce,
                __('Unblock', 'block-user-account')
            );
        } else {
            $actions['bua_block'] = sprintf(
                '<a href="#" class="bua-row-action" data-user-id="%d" data-action="block" data-nonce="%s">%s</a>',
                $user->ID,
                $nonce,
                __('Block', 'block-user-account')
            );
        }

        return $actions;
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
            'bua-user-list-style',
            BUA_ASSETS_URL . 'css/user-list.css',
            array(),
            BUA_VERSION
        );

        wp_enqueue_script(
            'bua-user-list-script',
            BUA_ASSETS_URL . 'js/user-list.js',
            array('jquery'),
            BUA_VERSION,
            true
        );

        wp_localize_script('bua-user-list-script', 'buaUserList', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bua_ajax_toggle'),
            'i18n'     => array(
                'confirm_block'   => __('Are you sure you want to block this user?', 'block-user-account'),
                'confirm_unblock' => __('Are you sure you want to unblock this user?', 'block-user-account'),
                'error'           => __('An error occurred. Please try again.', 'block-user-account'),
            )
        ));
    }
}
