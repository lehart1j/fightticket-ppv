<div class="ft-player-player" data-player-id="<?php echo esc_attr($atts['id']); ?>">
    <div class="countdown-container"<?php
    if(!empty($player_data->background_image)) {
        echo ' style="background-image: url(\'' . esc_url($player_data->background_image) . '\');"';
    } else {
        error_log('Background image is empty for player ID: ' . $atts['id']);
    }
    // Debug output (remove in production)
    error_log('Player data: ' . print_r($player_data, true));
    ?>>
    <div class="countdown" data-event-date="<?php echo esc_attr($player_data->event_date); ?>"></div>    
</div>

<div class="video-container" style="display: none;">
    <div class="mute-notifcation">🔇 Video is muted. Click to unmute.</div>
    <iframe    
    class="video-player"
        src="about:blank"
        allow="accelerometer; gyroscope; autoplay; encrypted-media;"
        allowfullscreen="true"
        style="border: none; position: absolute; top: 0; left: 0; height: 100%; width: 100%;"
        referrerpolicy="no-referrer"
        disablePictureInPicture
        mute
        playsinline
    ></iframe>
    </div>
</div>