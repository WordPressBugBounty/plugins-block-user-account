=== Block User Account ===
Contributors: dangoweb
Donate link: https://dangoweb.ir
Tags: block user, ban user, disable account, user management, account suspension
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 2.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block, manage, and monitor user accounts with temporary or permanent restrictions, custom messages, email notifications, and activity logs.

== Description ==

Block User Account gives administrators complete control over user account management. Block users temporarily or permanently, set custom block messages, send email notifications, and monitor all blocking activities with detailed logs.

**Features:**

* **Easy User Blocking:** Block any user with one click from the users list or their profile page
* **Temporary Blocks:** Set expiry dates for automatic unblocking after hours, days, or months
* **Custom Messages:** Show personalized messages to blocked users when they try to login
* **Bulk Actions:** Block or unblock multiple users at once with duration options
* **Email Notifications:** Automatically notify users and admins about blocking activities
* **Activity Logs:** Track all blocking and unblocking actions with detailed logs
* **Dashboard Widget:** Quick overview of blocked users and recent activities
* **Admin Bar Menu:** See blocked user count at a glance
* **Export Logs:** Download activity logs as CSV files
* **Statistics Page:** View blocking statistics and trends
* **RTL Support:** Fully compatible with right-to-left languages
* **Translation Ready:** Includes Persian translation

== Installation ==

1. Upload `block-user-account` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Users page to see block options
4. Configure settings in Settings > Block User Account

== Frequently Asked Questions ==

= Can I block users temporarily? =

Yes, you can set an expiry date for each block. The user will be automatically unblocked when the time expires.

= Will blocked users receive notifications? =

Yes, you can enable email notifications to inform users when their account is blocked or unblocked.

= Can I customize the block message? =

Yes, you can set a default message in settings or customize it per user in their profile page.

= Is there a log of all blocking activities? =

Yes, the plugin maintains a detailed activity log with timestamps, admin users, and reasons.

= Can I block multiple users at once? =

Yes, use the bulk actions dropdown in the users list to block or unblock multiple users simultaneously.

== Screenshots ==

1. Users list with status columns and quick actions
2. User profile block settings with toggle switch
3. Wordpress login Permanent blockage error
4. Wordpress login Custom time blockage error
5. Dashboard widget
6. Settings page
7. Settings page
8. Settings page

== Changelog ==

=2.0.0=
* Fixed REST API authentication bypass for blocked users (security fix)

= 2.0.0 =
* Complete plugin rewrite with OOP architecture
* Added temporary block functionality with expiry dates
* Added activity logging system with CSV export
* Added email notifications for users and admins
* Added bulk actions with duration options
* Added statistics page with charts and trends
* Added admin bar menu with blocked user count
* Added dashboard widget with expiring blocks overview
* Added settings page with professional UI
* Added user profile block history
* Added auto-unblock via cron jobs
* Added tools section (check expired, clean logs, export)
* Improved security with nonce verification

= 1.4 =
* Added Persian translation
* Fixed login authentication issues
* Improved toggle switch design

= 1.3 =
* Added bulk actions
* Added user status columns

= 1.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update with new features. Please backup your database before upgrading.

== Credits ==

Developed by [DangoWeb](https://dangoweb.ir)