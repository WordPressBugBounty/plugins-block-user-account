<?php

/**
 * Plugin Name: Block User Account
 * Plugin URI: https://dangoweb.ir/product/buacc-wordpress-user-block-plugin/
 * Description: Advanced user account management - Block users temporarily or permanently with custom messages, email notifications, activity logs and bulk actions
 * Version: 2.0.0
 * Author: DangoWeb
 * Author URI: https://dangoweb.ir
 * Text Domain: block-user-account
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

/**
 * Define plugin constants
 */
define('BUA_VERSION', '2.0.0');
define('BUA_PLUGIN_FILE', __FILE__);
define('BUA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUA_ASSETS_URL', BUA_PLUGIN_URL . 'assets/');
define('BUA_CSS_URL', BUA_ASSETS_URL . 'css');
define('BUA_JS_URL', BUA_ASSETS_URL . 'js');
define('BUA_LANG_DIR', dirname(plugin_basename(__FILE__)) . '/languages/');

/**
 * Load required files
 */
require_once BUA_PLUGIN_DIR . 'includes/class-logger.php';
require_once BUA_PLUGIN_DIR . 'includes/class-email-notifications.php';
require_once BUA_PLUGIN_DIR . 'includes/class-cron-jobs.php';
require_once BUA_PLUGIN_DIR . 'includes/class-authentication.php';
require_once BUA_PLUGIN_DIR . 'includes/class-block-manager.php';
require_once BUA_PLUGIN_DIR . 'admin/class-user-profile.php';
require_once BUA_PLUGIN_DIR . 'admin/class-user-list.php';
require_once BUA_PLUGIN_DIR . 'admin/class-bulk-actions.php';
require_once BUA_PLUGIN_DIR . 'admin/class-admin-menu.php';

/**
 * Main plugin class
 */
final class Block_User_Account
{

    /**
     * Single instance
     *
     * @var Block_User_Account
     */
    private static $instance = null;

    /**
     * Plugin components
     *
     * @var array
     */
    private $components = array();

    /**
     * Get instance
     *
     * @return Block_User_Account
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->define_hooks();
    }

    /**
     * Define hooks
     */
    private function define_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'init_components'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Block_User_Account', 'uninstall'));

        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain()
    {
        $locale = determine_locale();
        $locale = apply_filters('plugin_locale', $locale, 'block-user-account');

        unload_textdomain('block-user-account');

        $loaded = load_textdomain(
            'block-user-account',
            BUA_PLUGIN_DIR . 'languages/block-user-account-' . $locale . '.mo'
        );

        error_log('BUA Locale: ' . $locale);
        error_log('BUA Loaded: ' . ($loaded ? 'true' : 'false'));
        error_log('BUA Textdomain: ' . (is_textdomain_loaded('block-user-account') ? 'true' : 'false'));
    }

    /**
     * Initialize plugin components
     */
    public function init_components()
    {
        $this->components['logger']              = new BUA_Logger();
        $this->components['email_notifications']  = new BUA_Email_Notifications();
        $this->components['cron_jobs']            = new BUA_Cron_Jobs();
        $this->components['authentication']       = new BUA_Authentication();
        $this->components['block_manager']        = new BUA_Block_Manager();
        $this->components['user_profile']         = new BUA_User_Profile();
        $this->components['user_list']            = new BUA_User_List();
        $this->components['bulk_actions']         = new BUA_Bulk_Actions();

        if (is_admin()) {
            $this->components['admin_menu'] = new BUA_Admin_Menu();
        }
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets()
    {
        BUA_User_Profile::add_dashboard_widget();
    }

    /**
     * Add admin bar menu
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object
     */
    public function add_admin_bar_menu($wp_admin_bar)
    {
        if (!current_user_can('edit_users')) {
            return;
        }

        $blocked_count = BUA_Logger::get_blocked_users_count();

        $wp_admin_bar->add_node(array(
            'id'     => 'bua-admin-bar',
            'title'  => sprintf(
                '<span class="ab-icon dashicons dashicons-shield" style="top:2px;"></span>' .
                    '<span class="ab-label">%s</span>',
                sprintf(__('Blocked (%d)', 'block-user-account'), $blocked_count)
            ),
            'href'   => admin_url('users.php?bua_filter=blocked'),
            'parent' => null
        ));

        if ($blocked_count > 0) {
            $wp_admin_bar->add_node(array(
                'id'     => 'bua-view-blocked',
                'title'  => __('View Blocked Users', 'block-user-account'),
                'href'   => admin_url('users.php?bua_filter=blocked'),
                'parent' => 'bua-admin-bar'
            ));
        }

        $wp_admin_bar->add_node(array(
            'id'     => 'bua-settings',
            'title'  => __('Block Settings', 'block-user-account'),
            'href'   => admin_url('options-general.php?page=block-user-account-settings'),
            'parent' => 'bua-admin-bar'
        ));
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_plugin_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=block-user-account-settings'),
            __('Settings', 'block-user-account')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add plugin row meta
     *
     * @param array  $links Existing links
     * @param string $file  Plugin file
     * @return array Modified links
     */
    public function add_plugin_row_meta($links, $file)
    {
        if (plugin_basename(__FILE__) !== $file) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://dangoweb.ir/product/buacc-wordpress-user-block-plugin/',
            __('Documentation', 'block-user-account')
        );

        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://wordpress.org/support/plugin/block-user-account/reviews/#new-post',
            __('Rate Us', 'block-user-account')
        );

        return $links;
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        BUA_Logger::create_table();

        add_option('bua_default_message', __('Your account has been temporarily disabled. Please contact the administrator.', 'block-user-account'));
        add_option('bua_email_notifications', 'yes');
        add_option('bua_admin_notifications', 'yes');
        add_option('bua_log_activities', 'yes');
        add_option('bua_daily_report', 'no');
        add_option('bua_weekly_summary', 'no');
        add_option('bua_log_retention_days', 90);
        add_option('bua_auto_unblock', 'yes');
        add_option('bua_db_version', BUA_VERSION);

        set_transient('bua_activation_notice', true, 30);

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        BUA_Cron_Jobs::clear_scheduled_events();
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $users = get_users(array(
            'meta_key'   => 'user_status',
            'meta_value' => 'deactive',
            'fields'     => 'ID'
        ));

        foreach ($users as $user_id) {
            delete_user_meta($user_id, 'user_status');
            delete_user_meta($user_id, 'user_status_message');
            delete_user_meta($user_id, 'block_expiry_date');
            delete_user_meta($user_id, 'blocked_by');
            delete_user_meta($user_id, 'blocked_date');
        }

        BUA_Logger::drop_table();

        delete_option('bua_default_message');
        delete_option('bua_email_notifications');
        delete_option('bua_admin_notifications');
        delete_option('bua_log_activities');
        delete_option('bua_daily_report');
        delete_option('bua_weekly_summary');
        delete_option('bua_log_retention_days');
        delete_option('bua_auto_unblock');
        delete_option('bua_db_version');
    }

    /**
     * Get component instance
     *
     * @param string $name Component name
     * @return object|null Component instance
     */
    public function get_component($name)
    {
        return isset($this->components[$name]) ? $this->components[$name] : null;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup()
    {
        wp_die(__('Cannot unserialize singleton', 'block-user-account'));
    }
}

/**
 * Initialize plugin
 *
 * @return Block_User_Account
 */
function BUA()
{
    return Block_User_Account::instance();
}

/**
 * Handle CSV export before any output
 */
add_action('admin_init', function () {
    if (!isset($_GET['page']) || $_GET['page'] !== 'block-user-account-settings') {
        return;
    }

    if (!isset($_GET['bua_export']) || $_GET['bua_export'] !== '1') {
        return;
    }

    if (!wp_verify_nonce($_GET['bua_export_nonce'], 'bua_export_logs')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $csv = BUA_Logger::export_logs();

    // Clear all buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bua-logs-' . date('Y-m-d') . '.csv"');

    echo $csv;
    exit;
});

BUA();
