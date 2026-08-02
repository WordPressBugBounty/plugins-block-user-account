<?php

/**
 * Authentication Class
 *
 * Handles login authentication and blocked user checks
 *
 * @package    Block_User_Account
 * @subpackage Includes
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Authentication
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('authenticate', array($this, 'check_user_status'), 99, 2);
        add_filter('wp_authenticate_user', array($this, 'validate_blocked_user'), 10, 2);
        add_action('wp_login_failed', array($this, 'log_failed_attempt'));
        add_action('wp_login', array($this, 'log_successful_login'), 10, 2);

        add_filter('login_errors', array($this, 'customize_login_errors'));
        add_filter('login_message', array($this, 'add_login_message'));

        // Block REST API access for blocked users
        add_filter('rest_authentication_errors', array($this, 'rest_auth_check'), 99);
    }

    /**
     * Check user status before authentication
     *
     * @param WP_User|WP_Error|null $user     User object
     * @param string                $username Username or email
     * @return WP_User|WP_Error
     */
    public function check_user_status($user, $username)
    {
        $userinfo = get_user_by('login', $username);

        if (!$userinfo && is_email($username)) {
            $userinfo = get_user_by('email', $username);
        }

        if (!$userinfo) {
            return $user;
        }

        if (get_user_meta($userinfo->ID, 'user_status', true) !== 'deactive') {
            return $user;
        }

        $expiry = get_user_meta($userinfo->ID, 'block_expiry_date', true);

        if ($expiry && strtotime($expiry) < time()) {
            $this->auto_unblock_user($userinfo->ID);
            return $user;
        }

        $message = $this->get_block_message($userinfo->ID);

        return new WP_Error('account_disabled', $message);
    }

    /**
     * Validate blocked user after password check
     *
     * @param WP_User|WP_Error $user     User object
     * @param string           $password User password
     * @return WP_User|WP_Error
     */
    public function validate_blocked_user($user, $password)
    {
        if (is_wp_error($user)) {
            return $user;
        }

        if (get_user_meta($user->ID, 'user_status', true) === 'deactive') {
            $message = $this->get_block_message($user->ID);
            return new WP_Error('account_disabled', $message);
        }

        return $user;
    }

    /**
     * Auto unblock user when expiry time has passed
     *
     * @param int $user_id User ID
     */
    private function auto_unblock_user($user_id)
    {
        delete_user_meta($user_id, 'user_status');
        delete_user_meta($user_id, 'user_status_message');
        delete_user_meta($user_id, 'block_expiry_date');
        delete_user_meta($user_id, 'blocked_by');
        delete_user_meta($user_id, 'blocked_date');

        BUA_Logger::log($user_id, 'auto_unblock');
        do_action('bua_user_unblocked', $user_id);
    }

    /**
     * Get block message for user
     *
     * @param int $user_id User ID
     * @return string Block message
     */
    private function get_block_message($user_id)
    {
        $message = get_user_meta($user_id, 'user_status_message', true);

        if (empty($message)) {
            $message = get_option(
                'bua_default_message',
                __('Your account has been temporarily disabled. Please contact the administrator.', 'block-user-account')
            );
        }

        $expiry = get_user_meta($user_id, 'block_expiry_date', true);
        if ($expiry) {
            $formatted_date = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($expiry)
            );
            $message .= ' ' . sprintf(
                __('Your account will be unblocked on %s.', 'block-user-account'),
                $formatted_date
            );
        }

        return apply_filters('bua_block_message', $message, $user_id);
    }

    /**
     * Log failed login attempt
     *
     * @param string $username Username attempted
     */
    public function log_failed_attempt($username)
    {
        $user = get_user_by('login', $username);

        if (!$user && is_email($username)) {
            $user = get_user_by('email', $username);
        }

        if ($user) {
            $is_blocked = get_user_meta($user->ID, 'user_status', true) === 'deactive';
            $action = $is_blocked ? 'blocked_login_attempt' : 'failed_login';
            BUA_Logger::log($user->ID, $action);
        }
    }

    /**
     * Log successful login
     *
     * @param string  $username Username
     * @param WP_User $user     User object
     */
    public function log_successful_login($username, $user)
    {
        BUA_Logger::log($user->ID, 'successful_login');
    }

    /**
     * Customize login error messages
     *
     * @param string $errors Error message
     * @return string Modified error message
     */
    public function customize_login_errors($errors)
    {
        global $wp_error;

        if (!is_wp_error($wp_error)) {
            return $errors;
        }

        $codes = $wp_error->get_error_codes();

        if (in_array('account_disabled', $codes)) {
            return $wp_error->get_error_message('account_disabled');
        }

        return $errors;
    }

    /**
     * Add login page message
     *
     * @param string $message Login message
     * @return string Modified message
     */
    public function add_login_message($message)
    {
        if (isset($_GET['bua_unblocked']) && $_GET['bua_unblocked'] === '1') {
            $message .= '<p class="message bua-login-message">';
            $message .= __('Your account has been unblocked. You can now login.', 'block-user-account');
            $message .= '</p>';
        }

        return $message;
    }

    /**
     * Check if user can access specific content
     *
     * @param int $user_id User ID
     * @return bool Whether user can access
     */
    public static function can_user_access($user_id = 0)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return true;
        }

        return get_user_meta($user_id, 'user_status', true) !== 'deactive';
    }

    /**
     * Force logout blocked users
     */
    public static function force_logout_blocked_users()
    {
        $user_id = get_current_user_id();

        if ($user_id && get_user_meta($user_id, 'user_status', true) === 'deactive') {
            wp_logout();
            wp_redirect(wp_login_url());
            exit;
        }
    }

    /**
     * Block REST API access for blocked users
     *
     * @param WP_Error|bool|null $result Authentication result
     * @return WP_Error|bool|null
     */
    public function rest_auth_check($result)
    {
        // If already authenticated or already an error, pass through
        if ($result !== null) {
            return $result;
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            return $result;
        }

        if (get_user_meta($user_id, 'user_status', true) === 'deactive') {
            // Check for auto-unblock
            $expiry = get_user_meta($user_id, 'block_expiry_date', true);
            if ($expiry && strtotime($expiry) < time()) {
                $this->auto_unblock_user($user_id);
                return $result;
            }

            return new WP_Error(
                'account_disabled',
                __('Your account has been disabled.', 'block-user-account'),
                array('status' => 403)
            );
        }

        return $result;
    }
}
