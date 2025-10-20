jQuery(document).ready(function($) {

    var $fetchButton = $('#mainwp-fluentsupport-fetch-btn');
    var $ticketDataBody = $('#fluentsupport-ticket-data');
    var $messageDiv = $('#mainwp-fluentsupport-message');

    $fetchButton.on('click', function(e) {
        e.preventDefault();
        
        $fetchButton.prop('disabled', true).text('Fetching Tickets...');
        $messageDiv.hide().removeClass().empty();
        
        // Initial loading state
        $ticketDataBody.html('<tr class="loading-row"><td colspan="4"><i class="fa fa-spinner fa-pulse"></i> Retrieving data from all child sites, please wait...</td></tr>');

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: {
                action: mainwpFluentSupport.action,
                security: mainwpFluentSupport.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the table with the returned HTML
                    $ticketDataBody.html(response.data.html);
                    
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Success!</strong> Ticket data updated.')
                        .slideDown();
                } else {
                    // Handle AJAX failure (e.g., bad nonce, server error before hitting the core logic)
                    $ticketDataBody.html('<tr><td colspan="4">An error occurred while fetching data. Please check your MainWP sync status.</td></tr>');
                    
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Error:</strong> ' + (response.data.message || 'Unknown AJAX Error'))
                        .slideDown();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle network or fatal PHP errors
                $ticketDataBody.html('<tr><td colspan="4">Network or server error: ' + textStatus + '</td></tr>');
                
                $messageDiv
                    .addClass('mainwp-notice mainwp-notice-red')
                    .html('<strong>Fatal Error:</strong> Could not complete the sync request.')
                    .slideDown();
            },
            complete: function() {
                $fetchButton.prop('disabled', false).text('Fetch All Ticket Data');
            }
        });
    });
});
