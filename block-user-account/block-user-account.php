<?php
/*
 * Plugin Name: Block User Account
 * Plugin URI: https://dangoweb.ir/product/buacc-wordpress-user-block-plugin/
 * Description: Block Users Accounts On your Site.
 * Version: 1.4
 * Author: DangoWeb
 * Author URI: https://dangoweb.ir
 * Text Domain : block-user-account
 * Domain Path: /languages
 */
add_action('plugins_loaded', 'bua_translation');

function bua_translation()
{
    load_plugin_textdomain('block-user-account', false, BUA_LANG_DIR);
}

defined('ABSPATH') || exit();
define('BUA_CSS_URL', plugins_url('css', __FILE__));
define('BUA_LANG_DIR', basename(dirname(__FILE__)) . '/languages/');

//Show User Status Checkbox
add_action('show_user_profile', 'bua_block_checkbox');
add_action('edit_user_profile', 'bua_block_checkbox');
function bua_block_checkbox($user)
{
    $user_id = $user->ID;
    $current_user_id = get_current_user_id();
    if ($user_id != $current_user_id):
        if (current_user_can('edit_users')): ?>
            <table class="form-table" id="block_user">
                <tr>
                    <th>
                        <label for="user_status"><?php _e("User Account Status", 'block-user-account'); ?></label>
                    </th>
                    <td>
                        <label class="bua-toggle-switch">
                            <input type="checkbox" class="toggle-input" name="user_status" value="deactive"
                                   id="user_status" <?php checked(get_user_meta($user_id, 'user_status', true), 'deactive'); ?>>
                            <span class="bua-toggle-slider"></span>
                        </label>
                        <p class="description"><?php _e("Green: Account is Active. / Red: Account is Blocked.", 'block-user-account'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="user_status_message"><?php _e("Why the user is blocked Message", 'block-user-account'); ?></label>
                    </th>
                    <td>
                        <label class="tgl">
                            <input type="text" name="user_status_message" id="user_status_message" class="regular-text"
                                   value="<?php echo get_user_meta($user_id, 'user_status_message', true) ?>">
                        </label>
                    </td>
                </tr>
            </table>
        <?php
        endif;
    endif;

    return;
}

//Save User Status
add_action('personal_options_update', 'bua_save_user_status');
add_action('edit_user_profile_update', 'bua_save_user_status');
function bua_save_user_status($user_id)
{
    if (current_user_can('edit_users')) :
        if (filter_input(INPUT_POST, 'user_status') == 'deactive'):
            update_user_meta($user_id, 'user_status', $_POST['user_status']);
        else:
            delete_user_meta($user_id, 'user_status');
        endif;
        if (!empty(filter_input(INPUT_POST, 'user_status_message'))):
            update_user_meta($user_id, 'user_status_message', sanitize_text_field($_POST['user_status_message']));
        else:
            delete_user_meta($user_id, 'user_status_message');
        endif;
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();
    endif;


}

//Bulk Actions
add_filter('bulk_actions-users', 'bua_user_bulk_actions');
function bua_user_bulk_actions($bulk_actions)
{
    $bulk_actions['bua_block_users'] = __('Block Users', 'block-user-account');
    $bulk_actions['bua_active_users'] = __('Active Users', 'block-user-account');
    return $bulk_actions;
}

add_filter('handle_bulk_actions-users', 'bua_user_bulk_actions_handle', 10, 3);
function bua_user_bulk_actions_handle($redirect_to, $action, $user_ids)
{
    $current_user_id = get_current_user_id();

    if (!current_user_can('edit_users')) {
        return $redirect_to;
    }

    if ($action == 'bua_block_users') {
        foreach ($user_ids as $user_id) {
            if ($user_id != $current_user_id) {
                update_user_meta($user_id, 'user_status', 'deactive');
                $sessions = WP_Session_Tokens::get_instance($user_id);
                $sessions->destroy_all();
            }
        }
    } elseif ($action == 'bua_active_users') {
        foreach ($user_ids as $user_id) {
            delete_user_meta($user_id, 'user_status');
        }
    }

    return $redirect_to;
}

//Show User Status Columns
add_filter('manage_users_columns', 'bua_user_status_column');
function bua_user_status_column($column)
{
    $column['bua_user_status'] = __('User Status', 'block-user-account');
    $column['bua_user_status_reasen'] = __('Blocked Reason', 'block-user-account');
    return $column;
}

add_action('manage_users_custom_column', 'bua_show_user_status', 10, 3);
function bua_show_user_status($value, $column, $userid)
{
    $active = __('Active', 'block-user-account');
    $blocked = __('Blocked', 'block-user-account');
    $user_status = get_user_meta($userid, 'user_status', true);
    $user_status_message = get_user_meta($userid, 'user_status_message', true);

    if ('bua_user_status' == $column) {
        if ($user_status === 'deactive') {
            return '<span class="user-status-deactive"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Zm-80 160h160q17 0 28.5-11.5T600-360v-120q0-17-11.5-28.5T560-520v-40q0-33-23.5-56.5T480-640q-33 0-56.5 23.5T400-560v40q-17 0-28.5 11.5T360-480v120q0 17 11.5 28.5T400-320Zm40-200v-40q0-17 11.5-28.5T480-600q17 0 28.5 11.5T520-560v40h-80Z"/></svg>' . $blocked . '</span>';
        } else {
            return '<span class="user-status-active"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="m438-338 226-226-57-57-169 169-84-84-57 57 141 141Zm42 258q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z"/></svg>' . $active . '</span>';
        }
    }
    if ('bua_user_status_reasen' == $column) {
        if ($user_status == 'deactive') {
            return "<div>" . $user_status_message . "</div>";
        }
    }

    return $value;
}

//Login Error
add_filter('authenticate', 'bua_login_authenticate', 99, 2);
function bua_login_authenticate($user, $username)
{
    $userinfo = get_user_by('login', $username);
    if (!$userinfo && is_email($username)) {
        $userinfo = get_user_by('email', $username);
    }
    if (!$userinfo) {
        return $user;
    } elseif (get_user_meta($userinfo->ID, 'user_status', true) === 'deactive') {
        $user_message = get_user_meta($userinfo->ID, 'user_status_message', true);
        $default_message = __('Your account has been temporarily disabled. Please contact the administrator.', 'block-user-account');
        $message = !empty($user_message) ? $user_message : $default_message;
        $error = new WP_Error();
        $error->add('account_disabled', $message);

        return $error;
    }

    return $user;
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook == 'user-edit.php' || $hook == 'profile.php' || $hook == 'users.php') {
        wp_enqueue_style('bua_admin_style', BUA_CSS_URL . '/style.css');
    }
});