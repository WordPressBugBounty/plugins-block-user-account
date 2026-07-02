/**
 * Block User Account - Admin Script
 *
 * @package    Block_User_Account
 * @subpackage Assets
 * @since      2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    var BUA_Admin = {

        /**
         * Initialize
         */
        init: function() {
            this.handleToggleSwitch();
            this.handleQuickActions();
            this.handleBlockDuration();
            this.displayBulkNotices();
        },

        /**
         * Handle toggle switch in user profile
         */
        handleToggleSwitch: function() {
            $('#bua_user_status').on('change', function() {
                var isChecked = $(this).is(':checked');
                var blockOptions = $('.bua-block-options');
                var statusIndicator = $('#bua-status-indicator');

                if (isChecked) {
                    blockOptions.slideDown(300);
                    statusIndicator.html(
                        '<span style="color:#e74c3c;">&#9679;</span> ' +
                        buaData.i18n.blocked
                    );
                } else {
                    blockOptions.slideUp(300);
                    statusIndicator.html(
                        '<span style="color:#27ae60;">&#9679;</span> ' +
                        buaData.i18n.active
                    );
                }
            });
        },

        /**
         * Handle quick action buttons in users list
         */
        handleQuickActions: function() {
            $(document).on('click', '.bua-quick-action', function(e) {
                e.preventDefault();

                var button = $(this);
                var userId = button.data('user-id');
                var action = button.data('action');
                var confirmMsg = action === 'block' ? 
                    buaData.i18n.confirm_block : 
                    buaData.i18n.confirm_unblock;

                if (!confirm(confirmMsg)) {
                    return false;
                }

                var originalHTML = button.html();
                button.prop('disabled', true)
                      .html('<span class="bua-spinner"></span> ' + buaData.i18n.processing);

                $.ajax({
                    url: buaData.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'bua_toggle_user_status',
                        user_id: userId,
                        toggle_action: action,
                        nonce: buaData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            BUA_Admin.updateUserRow(button, response.data);
                            BUA_Admin.showNotice(
                                response.data.message,
                                'success'
                            );
                        } else {
                            BUA_Admin.showNotice(
                                response.data || buaData.i18n.error,
                                'error'
                            );
                            button.prop('disabled', false).html(originalHTML);
                        }
                    },
                    error: function() {
                        BUA_Admin.showNotice(buaData.i18n.error, 'error');
                        button.prop('disabled', false).html(originalHTML);
                    }
                });
            });
        },

        /**
         * Update user row after status change
         *
         * @param {Object} button jQuery button element
         * @param {Object} data   Response data
         */
        updateUserRow: function(button, data) {
            var row = button.closest('tr');
            var statusCell = row.find('.column-bua_status');
            var reasonCell = row.find('.column-bua_reason');
            var expiryCell = row.find('.column-bua_expiry');

            button.prop('disabled', false)
                  .text(data.button_text)
                  .data('action', data.button_action);

            if (data.status === 'blocked') {
                statusCell.html(
                    '<span class="bua-badge bua-perma-blocked">' +
                    '<span class="dashicons dashicons-lock"></span> ' +
                    buaData.i18n.blocked +
                    '</span>'
                );
                expiryCell.html(
                    '<span class="bua-permanent">' +
                    buaData.i18n.permanent +
                    '</span>'
                );
                reasonCell.html('<em>' + buaData.i18n.no_reason + '</em>');
            } else {
                statusCell.html(
                    '<span class="bua-badge bua-active">' +
                    '<span class="dashicons dashicons-yes-alt"></span> ' +
                    buaData.i18n.active +
                    '</span>'
                );
                expiryCell.html('<span aria-hidden="true" class="bua-na">—</span>');
                reasonCell.html('<span aria-hidden="true" class="bua-na">—</span>');
            }

            row.addClass('bua-highlight-row');
            setTimeout(function() {
                row.removeClass('bua-highlight-row');
            }, 2000);
        },

        /**
         * Handle block duration selection
         */
        handleBlockDuration: function() {
            $('#bua_block_duration').on('change', function() {
                var value = $(this).val();
                var customWrapper = $('#bua-custom-expiry-wrapper');
                var expiryInput = $('#bua_block_expiry');

                if (value === 'custom') {
                    customWrapper.slideDown(300);
                } else {
                    customWrapper.slideUp(300);

                    if (value !== 'permanent' && value !== '') {
                        var days = parseInt(value.replace('_days', ''));
                        var expiryDate = new Date();
                        expiryDate.setDate(expiryDate.getDate() + days);
                        
                        var formatted = expiryDate.getFullYear() + '-' +
                            String(expiryDate.getMonth() + 1).padStart(2, '0') + '-' +
                            String(expiryDate.getDate()).padStart(2, '0') + 'T' +
                            String(expiryDate.getHours()).padStart(2, '0') + ':' +
                            String(expiryDate.getMinutes()).padStart(2, '0');
                        
                        expiryInput.val(formatted);
                    }
                }
            });
        },

        /**
         * Display bulk action result notices
         */
        displayBulkNotices: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var result = urlParams.get('bua_bulk_result');
            var processed = urlParams.get('bua_processed');

            if (!result || !processed) {
                return;
            }

            var messages = {
                'permanent_blocked': processed + ' user(s) permanently blocked.',
                'temp30_blocked': processed + ' user(s) blocked for 30 days.',
                'temp7_blocked': processed + ' user(s) blocked for 7 days.',
                'temp1_blocked': processed + ' user(s) blocked for 1 day.',
                'unblocked': processed + ' user(s) unblocked successfully.'
            };

            if (messages[result]) {
                this.showNotice(messages[result], 'success');
            }
        },

        /**
         * Show floating notice
         *
         * @param {string} message Notice message
         * @param {string} type    Notice type
         */
        showNotice: function(message, type) {
            var noticeClass = 'notice-' + type + ' bua-admin-notice';
            var notice = $(
                '<div class="notice ' + noticeClass + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>'
            );

            $('.wp-header-end').after(notice);

            setTimeout(function() {
                notice.fadeOut(400, function() {
                    notice.remove();
                });
            }, 4000);
        }
    };

    BUA_Admin.init();
});