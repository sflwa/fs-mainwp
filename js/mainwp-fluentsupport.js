jQuery(document).ready(function($) {

    var $fetchButton = $('#mainwp-fluentsupport-fetch-btn');
    var $saveButton = $('#mainwp-fluentsupport-save-settings-btn');
    var $ticketDataBody = $('#fluentsupport-ticket-data');
    var $messageDiv = $('#mainwp-fluentsupport-message');
    var fetchAction = 'mainwp_fluentsupport_fetch_tickets';
    var saveAction = 'mainwp_fluentsupport_save_settings';

    // -------------------------
    // 1. FETCH TICKETS LOGIC
    // -------------------------
    $fetchButton.on('click', function(e) {
        e.preventDefault();
        
        $fetchButton.prop('disabled', true).text('Fetching Tickets...');
        $messageDiv.hide().removeClass().empty();
        
        // Initial loading state
        $ticketDataBody.html('<tr class="loading-row"><td colspan="5"><i class="fa fa-spinner fa-pulse"></i> Retrieving data from Support Site...</td></tr>');

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: {
                action: fetchAction,
                security: mainwpFluentSupport.nonce
            },
            success: function(response) {
                if (response.success) {
                    $ticketDataBody.html(response.data.html);
                    
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Success!</strong> Ticket data updated.')
                        .slideDown();
                } else {
                    // Update table with the error message from the PHP error response
                    $ticketDataBody.html(response.data.html || '<tr><td colspan="5">An error occurred while communicating with the Support Site.</td></tr>');
                    
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Error:</strong> ' + (response.data.message || 'Unknown Error'))
                        .slideDown();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $ticketDataBody.html('<tr><td colspan="5">Network or server error: ' + textStatus + '</td></tr>');
                
                $messageDiv
                    .addClass('mainwp-notice mainwp-notice-red')
                    .html('<strong>Fatal Error:</strong> Could not complete the sync request.')
                    .slideDown();
            },
            complete: function() {
                $fetchButton.prop('disabled', false).text('Fetch Latest Tickets');
            }
        });
    });
    
    // -------------------------
    // 2. SAVE SETTINGS LOGIC
    // -------------------------
    $saveButton.on('click', function(e) {
        e.preventDefault();

        $saveButton.prop('disabled', true).text('Saving...');
        $messageDiv.hide().removeClass().empty();

        var formData = {
            action: saveAction,
            security: mainwpFluentSupport.nonce,
            fluentsupport_site_id: $('#fluentsupport_site_id').val()
        };

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Success!</strong> ' + response.data.message)
                        .slideDown();
                } else {
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Error:</strong> ' + response.data.message)
                        .slideDown();
                }
            },
            error: function() {
                $messageDiv
                    .addClass('mainwp-notice mainwp-notice-red')
                    .html('<strong>Fatal Error:</strong> Failed to connect to save settings.')
                    .slideDown();
            },
            complete: function() {
                $saveButton.prop('disabled', false).text('Save Settings');
            }
        });
    });

});
