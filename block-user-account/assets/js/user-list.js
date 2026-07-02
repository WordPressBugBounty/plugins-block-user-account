jQuery(document).ready(function($) {
    'use strict';

    $(document).on('click', '.bua-row-action', function(e) {
        e.preventDefault();

        var link = $(this);
        var userId = link.data('user-id');
        var action = link.data('action');
        var nonce = link.data('nonce');
        var confirmMsg = action === 'block' ?
            buaUserList.i18n.confirm_block :
            buaUserList.i18n.confirm_unblock;

        if (!confirm(confirmMsg)) {
            return false;
        }

        var row = link.closest('tr');
        row.addClass('bua-row-loading');

        $.ajax({
            url: buaUserList.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'bua_toggle_user_status',
                user_id: userId,
                toggle_action: action,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || buaUserList.i18n.error);
                    row.removeClass('bua-row-loading');
                }
            },
            error: function() {
                alert(buaUserList.i18n.error);
                row.removeClass('bua-row-loading');
            }
        });
    });
});