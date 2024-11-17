jQuery(document).ready(function ($) {
    // Initialize Select2 with AJAX for user search
    $('#user').select2({
        placeholder: select2AjaxUsers.select_user_placeholder,
        allowClear: true,
        minimumInputLength: 2,  // Start searching after typing 2 characters
        ajax: {
            url: select2AjaxUsers.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: function (params) {
                return {
                    action: 'select2_user_search',
                    nonce: select2AjaxUsers.nonce,
                    search: params.term  // Send search term
                };
            },
            processResults: function (data) {
                return {
                    results: data.data  // Process response data
                };
            }
        }
    });
});