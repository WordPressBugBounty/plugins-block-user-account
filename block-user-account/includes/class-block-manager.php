<?php

/**
 * Block Manager Class
 *
 * Core blocking operations only
 *
 * @package    Block_User_Account
 * @subpackage Includes
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Block_Manager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_bua_toggle_user_status', array($this, 'ajax_toggle_user_status'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Block a user
     *
     * @param int    $user_id User ID
     * @param string $reason  Optional reason
     * @param string $expiry  Optional expiry date
     * @return bool
     */
    public static function block_user($user_id, $reason = '', $expiry = '')
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $already_blocked = get_user_meta($user_id, 'user_status', true) === 'deactive';

        update_user_meta($user_id, 'user_status', 'deactive');
        update_user_meta($user_id, 'blocked_by', get_current_user_id());

        if (!$already_blocked) {
            update_user_meta($user_id, 'blocked_date', current_time('mysql'));
        }

        if (!empty($reason)) {
            update_user_meta($user_id, 'user_status_message', sanitize_textarea_field($reason));
        }

        if (!empty($expiry)) {
            update_user_meta($user_id, 'block_expiry_date', sanitize_text_field($expiry));
        } else {
            delete_user_meta($user_id, 'block_expiry_date');
        }

        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();

        BUA_Logger::log($user_id, 'user_blocked', $reason);
        do_action('bua_user_blocked', $user_id, $reason);

        return true;
    }

    /**
     * Unblock a user
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function unblock_user($user_id)
    {
        $was_blocked = get_user_meta($user_id, 'user_status', true) === 'deactive';

        delete_user_meta($user_id, 'user_status');
        delete_user_meta($user_id, 'user_status_message');
        delete_user_meta($user_id, 'block_expiry_date');
        delete_user_meta($user_id, 'blocked_by');
        delete_user_meta($user_id, 'blocked_date');

        if ($was_blocked) {
            BUA_Logger::log($user_id, 'user_unblocked');
            do_action('bua_user_unblocked', $user_id);
        }

        return true;
    }

    /**
     * Check if user is blocked
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_user_blocked($user_id)
    {
        return get_user_meta($user_id, 'user_status', true) === 'deactive';
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public function enqueue_admin_assets($hook)
    {
        if (!in_array($hook, array('users.php'))) {
            return;
        }

        wp_enqueue_script(
            'bua-admin-script',
            BUA_ASSETS_URL . 'js/admin-script.js',
            array('jquery'),
            BUA_VERSION,
            true
        );

        wp_localize_script('bua-admin-script', 'buaData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bua_ajax_toggle'),
            'i18n'     => array(
                'confirm_block'   => __('Are you sure you want to block this user?', 'block-user-account'),
                'confirm_unblock' => __('Are you sure you want to unblock this user?', 'block-user-account'),
                'error'           => __('An error occurred. Please try again.', 'block-user-account'),
                'success_block'   => __('User blocked successfully.', 'block-user-account'),
                'success_unblock' => __('User unblocked successfully.', 'block-user-account'),
            )
        ));
    }

    /**
     * Handle AJAX toggle user status
     */
    public function ajax_toggle_user_status()
    {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (
            !wp_verify_nonce($nonce, 'bua_ajax_toggle') &&
            !wp_verify_nonce($nonce, 'bua_quick_action_' . $user_id)
        ) {
            wp_send_json_error(__('Security check failed.', 'block-user-account'));
        }

        if (!current_user_can('edit_users')) {
            wp_send_json_error(__('Permission denied.', 'block-user-account'));
        }

        $action = isset($_POST['toggle_action']) ? sanitize_text_field($_POST['toggle_action']) : '';

        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID.', 'block-user-account'));
        }

        if ($user_id === get_current_user_id()) {
            wp_send_json_error(__('You cannot modify your own account.', 'block-user-account'));
        }

        if ($action === 'block') {
            self::block_user($user_id);
            wp_send_json_success(array(
                'status'        => 'blocked',
                'button_text'   => __('Unblock', 'block-user-account'),
                'button_action' => 'unblock'
            ));
        }

        if ($action === 'unblock') {
            self::unblock_user($user_id);
            wp_send_json_success(array(
                'status'        => 'active',
                'button_text'   => __('Block', 'block-user-account'),
                'button_action' => 'block'
            ));
        }

        wp_send_json_error(__('Invalid action.', 'block-user-account'));
    }

    /**
     * Display admin notices for bulk actions
     */
    public function display_admin_notices()
    {
        $screen = get_current_screen();
        if ($screen->id !== 'users') {
            return;
        }

        $notices = array(
            'bua_blocked'      => __('%d user(s) blocked successfully.', 'block-user-account'),
            'bua_temp_blocked'  => __('%d user(s) temporarily blocked for 30 days.', 'block-user-account'),
            'bua_unblocked'     => __('%d user(s) unblocked successfully.', 'block-user-account'),
        );

        foreach ($notices as $param => $message) {
            if (isset($_GET[$param])) {
                $count = intval($_GET[$param]);
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    sprintf($message, $count)
                );
            }
        }
    }
}
