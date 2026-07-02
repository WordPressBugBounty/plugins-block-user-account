<?php
/**
 * Uninstall Block User Account
 *
 * Fired when the plugin is uninstalled
 *
 * @package    Block_User_Account
 * @since      2.0.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Load required files if not already loaded
 */
if (!class_exists('BUA_Logger')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-logger.php';
}

/**
 * Perform uninstall cleanup
 */
function bua_uninstall() {
    global $wpdb;

    /**
     * Check if user has permission to delete plugins
     */
    if (!current_user_can('activate_plugins')) {
        return;
    }

    /**
     * Get all blocked users and clean their metadata
     */
    $blocked_users = get_users(array(
        'meta_key'   => 'user_status',
        'meta_value' => 'deactive',
        'fields'     => 'ID'
    ));

    foreach ($blocked_users as $user_id) {
        delete_user_meta($user_id, 'user_status');
        delete_user_meta($user_id, 'user_status_message');
        delete_user_meta($user_id, 'block_expiry_date');
        delete_user_meta($user_id, 'blocked_by');
        delete_user_meta($user_id, 'blocked_date');
    }

    /**
     * Clean up any remaining user metadata that might have been left
     */
    $meta_keys = array(
        'user_status',
        'user_status_message',
        'block_expiry_date',
        'blocked_by',
        'blocked_date'
    );

    foreach ($meta_keys as $key) {
        $wpdb->delete(
            $wpdb->usermeta,
            array('meta_key' => $key),
            array('%s')
        );
    }

    /**
     * Drop activity log table
     */
    if (class_exists('BUA_Logger')) {
        BUA_Logger::drop_table();
    }

    /**
     * Delete all plugin options
     */
    $options = array(
        'bua_default_message',
        'bua_email_notifications',
        'bua_admin_notifications',
        'bua_log_activities',
        'bua_daily_report',
        'bua_weekly_summary',
        'bua_log_retention_days',
        'bua_auto_unblock',
        'bua_db_version',
        'bua_last_cleanup',
        'bua_last_cleanup_count'
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    /**
     * Delete any transients
     */
    delete_transient('bua_activation_notice');

    /**
     * Clear any scheduled cron hooks
     */
    $cron_hooks = array(
        'bua_check_expired_blocks',
        'bua_daily_report',
        'bua_cleanup_logs',
        'bua_weekly_summary'
    );

    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    /**
     * Remove any user capabilities added by the plugin
     */
    $roles = wp_roles()->get_names();
    
    foreach ($roles as $role => $name) {
        $role_obj = get_role($role);
        
        if ($role_obj) {
            $role_obj->remove_cap('block_users');
            $role_obj->remove_cap('unblock_users');
            $role_obj->remove_cap('view_block_logs');
        }
    }

    /**
     * Clear rewrite rules
     */
    flush_rewrite_rules();

    /**
     * Action hook for other plugins to clean up
     */
    do_action('bua_uninstall_complete');
}

bua_uninstall();