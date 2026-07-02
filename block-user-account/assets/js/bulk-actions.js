/**
 * Block User Account - Bulk Actions Script
 *
 * @package    Block_User_Account
 * @subpackage Assets
 * @since      2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    var BUA_BulkActions = {

        /**
         * Custom bulk action names
         */
        customActions: [
            'bua_block_permanent',
            'bua_block_30_days',
            'bua_block_7_days',
            'bua_block_1_day',
            'bua_unblock_users'
        ],

        /**
         * Initialize
         */
        init: function() {
            this.handleBulkSubmit();
        },

        /**
         * Handle bulk action form submission
         */
        handleBulkSubmit: function() {
            var self = this;

            $('#posts-filter').on('submit', function(e) {
                var topAction = $('#bulk-action-selector-top').val();
                var bottomAction = $('#bulk-action-selector-bottom').val();
                var action = topAction !== '-1' ? topAction : bottomAction;

                if (self.customActions.indexOf(action) === -1) {
                    return true;
                }

                var selectedUsers = $('input[name="users[]"]:checked');
                
                if (selectedUsers.length === 0) {
                    alert(buaBulkActions.i18n.select_users);
                    e.preventDefault();
                    return false;
                }

                if (!self.confirmAction(action, selectedUsers.length)) {
                    e.preventDefault();
                    return false;
                }

                self.showProgress();
            });
        },

        /**
         * Show confirmation dialog
         *
         * @param {string} action  Action name
         * @param {number} count   Number of selected users
         * @return {boolean} Whether to proceed
         */
        confirmAction: function(action, count) {
            var messages = {
                'bua_block_permanent': buaBulkActions.i18n.confirm_permanent,
                'bua_block_30_days': buaBulkActions.i18n.confirm_temp_30,
                'bua_block_7_days': buaBulkActions.i18n.confirm_temp_7,
                'bua_block_1_day': buaBulkActions.i18n.confirm_temp_1,
                'bua_unblock_users': buaBulkActions.i18n.confirm_unblock
            };

            var message = messages[action];
            
            if (!message) {
                return true;
            }

            message = message.replace('%d', count);
            return confirm(message);
        },

        /**
         * Show progress indicator
         */
        showProgress: function() {
            var submitButton = $('#doaction, #doaction2');
            
            if (submitButton.length) {
                submitButton.prop('disabled', true)
                            .val(buaBulkActions.i18n.processing);
            }
        }
    };

    BUA_BulkActions.init();
});