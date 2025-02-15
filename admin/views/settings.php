<div class="wrap">
    <h1>FightTicket Player Settings</h1>
    
    <div class="notice notice-error" style="display:none;" id="ft-player-error"></div>
    <div class="notice notice-success" style="display:none;" id="ft-player-notice"></div>

    <?php if ($account_id && $api_token): ?>
    <div class="notice notice-info">
        <p>API credentials are currently saved and encrypted.</p>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="options.php">
            <?php
            settings_fields('ft_player_settings');
            do_settings_sections('ft_player_settings');
            submit_button();
            ?>
        </form>
    </div>

    <?php if ($this->player->is_cloudflare_configured()): ?>
    <div class="card">
        <h2>Signing Key</h2>
        <p>Generate a signing key for secure stream URLs:</p>
        <button class="button button-secondary" id="generate-signing-key">Generate Signing Key</button>
        <div id="signing-key-result" style="margin-top: 10px;"></div>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#generate-signing-key').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $result = $('#signing-key-result');
        
        $button.prop('disabled', true);
        $result.html('Generating signing key...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ft_player_generate_signing_key',
                nonce: '<?php echo wp_create_nonce('ft_player_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Failed to generate signing key</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
</script> 