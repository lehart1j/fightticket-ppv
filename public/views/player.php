<div class="ft-ppv-player" data-player-id="<?php echo esc_attr($atts['id']); ?>">
    <div class="countdown-container"<?php 
        if (!empty($player_data->background_image)) {
            echo ' style="background-image: url(\'' . esc_url($player_data->background_image) . '\');"';
        }
    ?>>
        <div class="countdown" data-event-date="<?php echo esc_attr($player_data->event_date); ?>"></div>
    </div>
    
    <div class="video-container" style="display: none;">
        <div class="mute-notification">🔇 Video is muted. Click to unmute.</div>
        <iframe 
            class="video-player" 
            src="about:blank"
            allow="autoplay; encrypted-media; fullscreen" 
            allowfullscreen
            loading="lazy"
            referrerpolicy="no-referrer"
            disablePictureInPicture
            muted
            playsinline>
        </iframe>
    </div>
</div> 