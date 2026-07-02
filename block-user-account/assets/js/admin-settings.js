/**
 * Block User Account - Admin Settings Script
 *
 * @package    Block_User_Account
 * @subpackage Assets
 * @since      2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    var BUA_Settings = {

        /**
         * Initialize
         */
        init: function() {
            this.handleTabNavigation();
            this.handleTestEmail();
            this.handleCleanLogs();
            this.handleExportLogs();
            this.handleRunCheck();
            this.confirmBeforeAction();
        },

        /**
         * Handle tab navigation
         */
        handleTabNavigation: function() {
            var hash = window.location.hash;
            
            if (hash) {
                var tab = $('.nav-tab[href="' + hash + '"]');
                if (tab.length) {
                    $('.nav-tab').removeClass('nav-tab-active');
                    tab.addClass('nav-tab-active');
                }
            }

            $('.nav-tab').on('click', function() {
                window.location.hash = $(this).attr('href').split('=')[1];
            });
        },

        /**
         * Handle test email button
         */
        handleTestEmail: function() {
            $('input[name="bua_test_email"]').on('click', function(e) {
                var button = $(this);
                var originalValue = button.val();
                
                button.val(buaSettings.i18n.sending)
                      .prop('disabled', true);

                setTimeout(function() {
                    button.val(originalValue).prop('disabled', false);
                }, 3000);
            });
        },

        /**
         * Handle clean logs button
         */
        handleCleanLogs: function() {
            $('input[name="bua_clean_logs"]').on('click', function(e) {
                var confirmed = confirm(buaSettings.i18n.confirm_clean_logs);
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }

                var button = $(this);
                button.val(buaSettings.i18n.cleaning)
                      .prop('disabled', true);
            });
        },

        /**
         * Handle export logs button
         */
        handleExportLogs: function() {
            $('input[name="bua_export_logs"]').on('click', function(e) {
                var button = $(this);
                var originalValue = button.val();
                
                button.val(buaSettings.i18n.exporting)
                      .prop('disabled', true);

                setTimeout(function() {
                    button.val(originalValue).prop('disabled', false);
                }, 5000);
            });
        },

        /**
         * Handle run check button
         */
        handleRunCheck: function() {
            $('input[name="bua_run_check"]').on('click', function(e) {
                var button = $(this);
                
                button.val(buaSettings.i18n.checking)
                      .prop('disabled', true);
            });
        },

        /**
         * Confirm before destructive actions
         */
        confirmBeforeAction: function() {
            $('.bua-tool-card form').on('submit', function(e) {
                var button = $(this).find('input[type="submit"]');
                var action = button.attr('name');

                if (action === 'bua_clean_logs') {
                    return true;
                }

                return true;
            });
        }
    };

    BUA_Settings.init();
});