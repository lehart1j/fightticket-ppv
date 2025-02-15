jQuery(document).ready(function($) {
    // Add Player Form
    $('#ft-player-add-player').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'ft_player_add_player',
            nonce: ftPlayer.nonce,
            name: $('#name').val(),
            live_input_id: $('#live_input_id').val(),
            event_date: $('#event_date').val(),
            background_image: $('#background_image').val()
        };

        // Debug logging
        console.log('Form values:', {
            name: $('#name').val(),
            live_input_id: $('#live_input_id').val(),
            event_date: $('#event_date').val(),
            background_image: $('#background_image').val()
        });
        console.log('Submitting form data:', formData);

        $.ajax({
            url: ftPlayer.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Server response:', response); // Debug response
                if (response.success) {
                    showNotice(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', {xhr, status, error}); // Debug errors
                alert('An error occurred while saving the player.');
            }
        });
    });

    // Delete Player
    $('.delete-player').on('click', function() {
        if (!confirm('Are you sure you want to delete this player?')) {
            return;
        }

        var playerId = $(this).data('id');
        
        $.ajax({
            url: ftPlayer.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft_player_delete_player',
                nonce: ftPlayer.nonce,
                player_id: playerId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });

    function showNotice(message) {
        var $notice = $('#ft-player-notice');
        $notice.html(message).show();
        setTimeout(function() {
            $notice.fadeOut();
        }, 3000);
    }

    $('#test-cloudflare').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $result = $('#test-result');
        
        $button.prop('disabled', true);
        $result.html('<span class="spinner is-active"></span> Testing connection...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ft_player_test_stream',
                nonce: ftPlayer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>Connection successful!</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>Connection failed: ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Connection test failed. Please try again.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
}); 