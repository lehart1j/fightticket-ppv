jQuery(document).ready(function($) {
    // Add Player Form
    $('#ft-ppv-add-player').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: ftPpv.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft_ppv_save_player',
                nonce: ftPpv.nonce,
                name: $('#name').val(),
                live_input_id: $('#live_input_id').val(),
                event_date: $('#event_date').val()
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message);
                    location.reload();
                }
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
            url: ftPpv.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ft_ppv_delete_player',
                nonce: ftPpv.nonce,
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
        var $notice = $('#ft-ppv-notice');
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
                action: 'ft_ppv_test_stream',
                nonce: ftPpv.nonce
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