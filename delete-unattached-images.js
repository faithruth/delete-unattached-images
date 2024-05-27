jQuery(document).ready(function($) {
    $('#delete-unattached-images-button').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete all unattached images? This action cannot be undone.')) {
            return;
        }

        // Start the deletion process
        startDeleteUnattachedImages();
    });

    function startDeleteUnattachedImages() {
        $.ajax({
            url: deleteUnattachedImages.ajax_url,
            method: 'POST',
            data: {
                action: 'start_delete_unattached_images',
                nonce: deleteUnattachedImages.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#total-count').text(response.data.total_count);
                    $('#batch-count').text(response.data.message);
                    // Background task started, monitor progress if needed
                } else {
                    alert('An error occurred while starting the deletion process.');
                }
            },
            error: function() {
                alert('An error occurred while communicating with the server.');
            }
        });
    }
});