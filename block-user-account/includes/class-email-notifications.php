<?php

/**
 * Email Notifications Class
 *
 * Handles all email notifications for user blocking activities
 *
 * @package    Block_User_Account
 * @subpackage Includes
 * @since      2.0.0
 */

defined('ABSPATH') || exit;

class BUA_Email_Notifications
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('bua_user_blocked', array($this, 'send_blocked_notification'), 10, 2);
        add_action('bua_user_unblocked', array($this, 'send_unblocked_notification'), 10, 1);

        add_action('bua_user_blocked', array($this, 'notify_admin_on_block'), 10, 2);
        add_action('bua_user_unblocked', array($this, 'notify_admin_on_unblock'), 10, 1);
    }

    /**
     * Send notification to user when their account is blocked
     *
     * @param int    $user_id Blocked user ID
     * @param string $reason  Block reason
     */
    public function send_blocked_notification($user_id, $reason = '')
    {
        if (get_option('bua_email_notifications', 'yes') !== 'yes') {
            return;
        }

        if (!apply_filters('bua_send_user_blocked_email', true, $user_id)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = sprintf(__('[%s] Your account has been blocked', 'block-user-account'), get_bloginfo('name'));

        $message = $this->get_email_template('blocked', array(
            'user_name'      => $user->display_name,
            'user_login'     => $user->user_login,
            'site_name'      => get_bloginfo('name'),
            'site_url'       => home_url(),
            'reason'         => $reason,
            'block_date'     => current_time('mysql'),
            'admin_email'    => get_option('admin_email'),
            'expiry_date'    => get_user_meta($user_id, 'block_expiry_date', true)
        ));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        wp_mail($to, $subject, $message, $headers);

        BUA_Logger::log($user_id, 'blocked_email_sent');
    }

    /**
     * Send notification to user when their account is unblocked
     *
     * @param int $user_id Unblocked user ID
     */
    public function send_unblocked_notification($user_id)
    {
        if (get_option('bua_email_notifications', 'yes') !== 'yes') {
            return;
        }

        if (!apply_filters('bua_send_user_unblocked_email', true, $user_id)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $to = $user->user_email;
        $subject = sprintf(__('[%s] Your account has been unblocked', 'block-user-account'), get_bloginfo('name'));

        $message = $this->get_email_template('unblocked', array(
            'user_name'   => $user->display_name,
            'user_login'  => $user->user_login,
            'site_name'   => get_bloginfo('name'),
            'site_url'    => home_url(),
            'unblock_date' => current_time('mysql'),
            'login_url'   => wp_login_url()
        ));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        wp_mail($to, $subject, $message, $headers);

        BUA_Logger::log($user_id, 'unblocked_email_sent');
    }

    /**
     * Notify admin when a user is blocked
     *
     * @param int    $user_id Blocked user ID
     * @param string $reason  Block reason
     */
    public function notify_admin_on_block($user_id, $reason = '')
    {
        if (get_option('bua_admin_notifications', 'yes') !== 'yes') {
            return;
        }

        $admin_email = get_option('admin_email');
        $user = get_userdata($user_id);
        $admin = get_userdata(get_current_user_id());

        if (!$user) {
            return;
        }

        $subject = sprintf(
            __('[%s] User Account Blocked: %s', 'block-user-account'),
            get_bloginfo('name'),
            $user->user_login
        );

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    background: #e74c3c;
                    color: #fff;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }

                .content {
                    background: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                }

                .info-box {
                    background: #fff;
                    border-left: 4px solid #e74c3c;
                    padding: 15px;
                    margin: 15px 0;
                }

                .info-item {
                    margin: 10px 0;
                }

                .info-label {
                    font-weight: bold;
                    color: #555;
                }

                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #888;
                    font-size: 12px;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h2><?php _e('User Account Blocked', 'block-user-account'); ?></h2>
                </div>
                <div class="content">
                    <p><?php _e('A user account has been blocked on your website.', 'block-user-account'); ?></p>

                    <div class="info-box">
                        <div class="info-item">
                            <span class="info-label"><?php _e('User:', 'block-user-account'); ?></span>
                            <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_login); ?>)
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?php _e('Email:', 'block-user-account'); ?></span>
                            <?php echo esc_html($user->user_email); ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?php _e('Blocked by:', 'block-user-account'); ?></span>
                            <?php echo $admin ? esc_html($admin->display_name) : __('System', 'block-user-account'); ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?php _e('Date:', 'block-user-account'); ?></span>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?>
                        </div>
                        <?php if ($reason): ?>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Reason:', 'block-user-account'); ?></span>
                                <?php echo esc_html($reason); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <p>
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $user_id); ?>"
                            style="background: #e74c3c; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">
                            <?php _e('View User Profile', 'block-user-account'); ?>
                        </a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php printf(__('This is an automated notification from %s', 'block-user-account'), get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>

        </html>
    <?php
        $message = ob_get_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Notify admin when a user is unblocked
     *
     * @param int $user_id Unblocked user ID
     */
    public function notify_admin_on_unblock($user_id)
    {
        if (get_option('bua_admin_notifications', 'yes') !== 'yes') {
            return;
        }

        $admin_email = get_option('admin_email');
        $user = get_userdata($user_id);
        $admin = get_userdata(get_current_user_id());

        if (!$user) {
            return;
        }

        $subject = sprintf(
            __('[%s] User Account Unblocked: %s', 'block-user-account'),
            get_bloginfo('name'),
            $user->user_login
        );

        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    background: #27ae60;
                    color: #fff;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }

                .content {
                    background: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                }

                .info-box {
                    background: #fff;
                    border-left: 4px solid #27ae60;
                    padding: 15px;
                    margin: 15px 0;
                }

                .info-item {
                    margin: 10px 0;
                }

                .info-label {
                    font-weight: bold;
                    color: #555;
                }

                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #888;
                    font-size: 12px;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h2><?php _e('User Account Unblocked', 'block-user-account'); ?></h2>
                </div>
                <div class="content">
                    <p><?php _e('A user account has been unblocked on your website.', 'block-user-account'); ?></p>

                    <div class="info-box">
                        <div class="info-item">
                            <span class="info-label"><?php _e('User:', 'block-user-account'); ?></span>
                            <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_login); ?>)
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?php _e('Email:', 'block-user-account'); ?></span>
                            <?php echo esc_html($user->user_email); ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?php _e('Unblocked by:', 'block-user-account'); ?></span>
                            <?php echo $admin ? esc_html($admin->display_name) : __('System', 'block-user-account'); ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?php _e('Date:', 'block-user-account'); ?></span>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?>
                        </div>
                    </div>

                    <p>
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $user_id); ?>"
                            style="background: #27ae60; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">
                            <?php _e('View User Profile', 'block-user-account'); ?>
                        </a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php printf(__('This is an automated notification from %s', 'block-user-account'), get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>

        </html>
        <?php
        $message = ob_get_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Get email template
     *
     * @param string $template Template name
     * @param array  $data     Template data
     * @return string HTML email content
     */
    private function get_email_template($template, $data = array())
    {
        $data = wp_parse_args($data, array(
            'user_name'    => '',
            'user_login'   => '',
            'site_name'    => get_bloginfo('name'),
            'site_url'     => home_url(),
            'reason'       => '',
            'block_date'   => '',
            'unblock_date' => '',
            'expiry_date'  => '',
            'admin_email'  => get_option('admin_email'),
            'login_url'    => wp_login_url()
        ));

        ob_start();

        if ($template === 'blocked') {
        ?>
            <!DOCTYPE html>
            <html>

            <head>
                <meta charset="UTF-8">
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        margin: 0;
                        padding: 0;
                    }

                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }

                    .header {
                        background: #e74c3c;
                        color: #fff;
                        padding: 30px;
                        text-align: center;
                    }

                    .content {
                        background: #fff;
                        padding: 30px;
                        border: 1px solid #ddd;
                        border-top: none;
                    }

                    .alert {
                        background: #fdf7f7;
                        border: 1px solid #f5c6cb;
                        color: #721c24;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }

                    .info {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }

                    .info p {
                        margin: 5px 0;
                    }

                    .footer {
                        text-align: center;
                        padding: 20px;
                        color: #888;
                        font-size: 12px;
                    }

                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background: #0073aa;
                        color: #fff;
                        text-decoration: none;
                        border-radius: 3px;
                    }
                </style>
            </head>

            <body>
                <div class="container">
                    <div class="header">
                        <h1><?php _e('Account Blocked', 'block-user-account'); ?></h1>
                    </div>
                    <div class="content">
                        <p><?php printf(__('Hello %s,', 'block-user-account'), esc_html($data['user_name'])); ?></p>

                        <div class="alert">
                            <p><?php _e('Your account has been blocked on our website.', 'block-user-account'); ?></p>
                        </div>

                        <?php if ($data['reason']): ?>
                            <div class="info">
                                <p><strong><?php _e('Reason:', 'block-user-account'); ?></strong></p>
                                <p><?php echo esc_html($data['reason']); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="info">
                            <p><strong><?php _e('Account Details:', 'block-user-account'); ?></strong></p>
                            <p><?php _e('Username:', 'block-user-account'); ?> <?php echo esc_html($data['user_login']); ?></p>
                            <p><?php _e('Block Date:', 'block-user-account'); ?> <?php echo esc_html($data['block_date']); ?></p>
                            <?php if ($data['expiry_date']): ?>
                                <p><?php _e('Expiry Date:', 'block-user-account'); ?> <?php echo esc_html($data['expiry_date']); ?></p>
                            <?php else: ?>
                                <p><?php _e('Duration: Permanent', 'block-user-account'); ?></p>
                            <?php endif; ?>
                        </div>

                        <p><?php _e('If you believe this is a mistake, please contact the site administrator.', 'block-user-account'); ?></p>

                        <p>
                            <a href="mailto:<?php echo esc_attr($data['admin_email']); ?>" class="button">
                                <?php _e('Contact Administrator', 'block-user-account'); ?>
                            </a>
                        </p>
                    </div>
                    <div class="footer">
                        <p><?php echo esc_html($data['site_name']); ?> | <?php echo esc_url($data['site_url']); ?></p>
                        <p><?php _e('This is an automated message. Please do not reply to this email.', 'block-user-account'); ?></p>
                    </div>
                </div>
            </body>

            </html>
        <?php
        } elseif ($template === 'unblocked') {
        ?>
            <!DOCTYPE html>
            <html>

            <head>
                <meta charset="UTF-8">
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        margin: 0;
                        padding: 0;
                    }

                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }

                    .header {
                        background: #27ae60;
                        color: #fff;
                        padding: 30px;
                        text-align: center;
                    }

                    .content {
                        background: #fff;
                        padding: 30px;
                        border: 1px solid #ddd;
                        border-top: none;
                    }

                    .success {
                        background: #f0faf4;
                        border: 1px solid #c3e6cb;
                        color: #155724;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }

                    .info {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }

                    .footer {
                        text-align: center;
                        padding: 20px;
                        color: #888;
                        font-size: 12px;
                    }

                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background: #27ae60;
                        color: #fff;
                        text-decoration: none;
                        border-radius: 3px;
                    }
                </style>
            </head>

            <body>
                <div class="container">
                    <div class="header">
                        <h1><?php _e('Account Unblocked', 'block-user-account'); ?></h1>
                    </div>
                    <div class="content">
                        <p><?php printf(__('Hello %s,', 'block-user-account'), esc_html($data['user_name'])); ?></p>

                        <div class="success">
                            <p><?php _e('Your account has been unblocked and you can now login again.', 'block-user-account'); ?></p>
                        </div>

                        <div class="info">
                            <p><strong><?php _e('Account Details:', 'block-user-account'); ?></strong></p>
                            <p><?php _e('Username:', 'block-user-account'); ?> <?php echo esc_html($data['user_login']); ?></p>
                            <p><?php _e('Unblock Date:', 'block-user-account'); ?> <?php echo esc_html($data['unblock_date']); ?></p>
                        </div>

                        <p>
                            <a href="<?php echo esc_url($data['login_url']); ?>" class="button">
                                <?php _e('Login to Your Account', 'block-user-account'); ?>
                            </a>
                        </p>
                    </div>
                    <div class="footer">
                        <p><?php echo esc_html($data['site_name']); ?> | <?php echo esc_url($data['site_url']); ?></p>
                        <p><?php _e('This is an automated message. Please do not reply to this email.', 'block-user-account'); ?></p>
                    </div>
                </div>
            </body>

            </html>
<?php
        }

        return ob_get_clean();
    }

    /**
     * Test email configuration
     *
     * @param string $email Email address to send test to
     * @return bool Whether the test email was sent successfully
     */
    public static function send_test_email($email)
    {
        $subject = sprintf(__('[%s] Email Test - Block User Account Plugin', 'block-user-account'), get_bloginfo('name'));

        $message = sprintf(
            __('This is a test email from the Block User Account plugin on %s. If you received this email, your email configuration is working correctly.', 'block-user-account'),
            get_bloginfo('name')
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($email, $subject, $message, $headers);
    }
}
