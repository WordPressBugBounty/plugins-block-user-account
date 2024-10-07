<?php
/*
 * Plugin Name: Block User Account
 * Plugin URI: https://dangoweb.ir/product/buacc-wordpress-user-block-plugin/
 * Description: Block Users Accounts On your Site.
 * Version: 1.3.1
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
        wp_enqueue_style('user_status_style', BUA_CSS_URL . '/style.css');
        if (current_user_can('edit_users')):
            ?>
            <table class="form-table" id="block_user">
                <tr>
                    <th>
                        <label for="user_status"><?php _e("User Account Status", 'block-user-account'); ?></label>
                    </th>
                    <td>
                        <label class="tgl">
                            <input type="checkbox" name="user_status" value="deactive"
                                   id="user_status" <?php checked(get_user_meta($user_id, 'user_status', true), 'deactive'); ?>>
                            <span data-off="<?php _e("Enabled", 'block-user-account'); ?>"
                                  data-on="<?php _e("Disabled", 'block-user-account'); ?>"></span>
                        </label>
                        <span class="description"><?php _e("Green: Account is Active. / Red: Account is Blocked.", 'block-user-account'); ?></span>
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
                <script>
                    jQuery(function ($) {
                        var checkbox = document.querySelector("#user_status");
                        var input = document.querySelector("#user_status_message");
                        var toogleInput = function (e) {
                            input.disabled = !e.target.checked;
                        };
                        toogleInput({target: checkbox});
                        checkbox.addEventListener("change", toogleInput);
                    });
                </script>
            </table>
        <?php
        endif;
    endif;

    return;
}

//Session
function bua_destroy_user_session($user_id)
{
    $sessions = WP_Session_Tokens::get_instance($user_id);
    $sessions->destroy_all();
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
        bua_destroy_user_session($user_id);
    endif;

    return;
}

//Bulk Actions
add_filter('bulk_actions-users', 'bua_bulk_actions');
function bua_bulk_actions($bulk_actions)
{
    $bulk_actions['bua_block_users'] = __('Block Users', 'block-user-account');
    $bulk_actions['bua_active_users'] = __('Active Users', 'block-user-account');
    return $bulk_actions;
}

add_filter('handle_bulk_actions-users', 'bua_bulk_actions_handle', 10, 3);
function bua_bulk_actions_handle($redirect_to, $action, $user_ids)
{
    $current_user_id = get_current_user_id();
    if ($action == 'bua_block_users') {
        foreach ($user_ids as $user_id) {
            if (current_user_can('edit_users')) {
                if ($user_id != $current_user_id) {
                    update_user_meta($user_id, 'user_status', 'deactive');
                    bua_destroy_user_session($user_id);
                }
            }
            if (!current_user_can('edit_users')) {
                wp_die(__('Sorry, you are not allowed to edit this user.'), 403);
            }

        }
        $redirect_to = add_query_arg('bua_block_users', count($user_ids), $redirect_to);
//        $redirect_to = remove_query_arg('bua_block_users', $redirect_to);
    } elseif ($action == 'bua_active_users') {
        foreach ($user_ids as $user_id) {
            if (current_user_can('edit_users')) {
                delete_user_meta($user_id, 'user_status');
            }
            if (!current_user_can('edit_users')) {
                wp_die(__('Sorry, you are not allowed to edit this user.'), 403);
            }
        }
        $redirect_to = add_query_arg('bua_active_users', count($user_ids), $redirect_to);
    }
    return $redirect_to;
}

add_action('admin_notices', 'bua_bulk_action_notice');
function bua_bulk_action_notice()
{
    if (!empty($_REQUEST['bua_block_users']) && isset($_REQUEST['bua_block_users'])) {
        $changes = intval($_REQUEST['bua_block_users']);
        printf('<div id="message" class="updated notice is-dismissable"><p>' . __('%d Users were Blocked.', 'block-user-account') . '</p></div>', $changes);
    }
    if (!empty($_REQUEST['bua_active_users']) && isset($_REQUEST['bua_active_users'])) {
        $changes = intval($_REQUEST['bua_active_users']);
        printf('<div id="message" class="updated notice is-dismissable"><p>' . __('%d Users Activated.', 'block-user-account') . '</p></div>', $changes);
    }
}

//Show User Status Columns
add_filter('manage_users_columns', 'bua_user_status_column');
function bua_user_status_column($column)
{
    $column['user_status'] = __('User Status', 'block-user-account');
    $column['user_status_reasen'] = __('Blocked Reason', 'block-user-account');
    return $column;
}

add_action('manage_users_custom_column', 'bua_show_user_status', 10, 3);
function bua_show_user_status($value, $column, $userid)
{
    wp_enqueue_style('user_status_style', BUA_CSS_URL . '/style.css');
    $active = __('Active', 'block-user-account');
    $blocked = __('Blocked', 'block-user-account');
    $user_status = get_user_meta($userid, 'user_status', true);
    $user_status_message = get_user_meta($userid, 'user_status_message', true);

    if ('user_status' == $column) {
        if ($user_status === 'deactive') {
            return "<span class='user-status-deactive'>" . $blocked . "</span>";
        } else {
            return "<span class='user-status-active'>" . $active . "</span>";
        }
    }
    if ('user_status_reasen' == $column) {
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
    if (is_email($username)):
        $userinfo = get_user_by('email', $username);
    else:
        $userinfo = get_user_by('login', $username);
    endif;

    if (!$userinfo) {
        return $user;
    } elseif (get_user_meta($userinfo->ID, 'user_status', true) === 'deactive') {
        $user_message = get_user_meta($userinfo->ID, 'user_status_message', true);
        $default_message = __('Your account is disabled.', 'block-user-account');
        $message = !empty($user_message) ? $user_message : $default_message;
        $error = new WP_Error();
        $error->add('account_disabled', $message);

        return $error;
    }

    return $user;
}