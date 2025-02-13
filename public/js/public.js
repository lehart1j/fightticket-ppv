// Suppress ALL console messages from Cloudflare player
const originalError = console.error;
const originalLog = console.log;
const originalWarn = console.warn;

// Function to check if we should suppress the message
const shouldSuppressMessage = (args) => {
    return args.some(arg => {
        const str = String(arg);
        return str.includes('top-level') ||
               str.includes('optout') ||
               str.includes('cloudflarestream') ||
               str.includes('customer-') ||
               str.includes('sentry') ||
               str.includes('dash.cloudflare') ||
               str.includes('Permissions policy') ||
               str.includes('frame');
    });
};

// Override all console methods
console.error = (...args) => !shouldSuppressMessage(args) && originalError.apply(console, args);
console.log = (...args) => !shouldSuppressMessage(args) && originalLog.apply(console, args);
console.warn = (...args) => !shouldSuppressMessage(args) && originalWarn.apply(console, args);

jQuery(document).ready(function($) {
    $('.ft-ppv-player').each(function() {
        const $player = $(this);
        const $countdown = $player.find('.countdown');
        const $countdownContainer = $player.find('.countdown-container');
        const $videoContainer = $player.find('.video-container');
        const $videoPlayer = $player.find('.video-player');
        const $muteNotification = $player.find('.mute-notification');
        
        const playerId = $player.data('player-id');
        const eventDate = new Date($countdown.data('event-date')).getTime();
        let checkInterval;
        let isVideoUnlocked = false;

        function updateCountdown() {
            // Don't update if video is already unlocked
            if (isVideoUnlocked) {
                clearInterval(countdownInterval);
                return;
            }

            const now = new Date().getTime();
            const diff = eventDate - now;
            
            if (diff <= 0) {
                checkStreamStatus(true); // Force check when countdown reaches zero
                return;
            }
            
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            $countdown.html(`<strong>EVENT STARTS IN</strong><br>${days} DAYS ${hours} HOURS ${minutes} MINUTES ${seconds} SECONDS`);
        }

        function checkStreamStatus(force = false) {
            if (isVideoUnlocked && !force) {
                clearInterval(checkInterval);
                return;
            }

            const $statusIndicator = $player.find('.stream-status');
            if (!$statusIndicator.length) {
                $countdownContainer.append('<div class="stream-status"></div>');
            }

            $.ajax({
                url: ftPpv.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ft_ppv_check_stream',
                    nonce: ftPpv.nonce,
                    player_id: playerId
                },
                success: function(response) {
                    if (response.success) {
                        const $status = $player.find('.stream-status');
                        if (response.data.is_live) {
                            $status.text('Stream is Live!').removeClass('status-waiting status-error').addClass('status-live');
                            unlockVideo();
                        } else {
                            $status.text('Waiting for stream...').removeClass('status-live status-error').addClass('status-waiting');
                        }
                    }
                },
                error: function() {
                    const $status = $player.find('.stream-status');
                    $status.text('Error checking stream status').removeClass('status-live status-waiting').addClass('status-error');
                }
            });
        }

        function updatePlayerSource(videoSrc) {
            if ($videoPlayer.attr('src') !== videoSrc) {
                $videoPlayer.attr('src', videoSrc);
                
                // Force autoplay after a short delay
                setTimeout(() => {
                    try {
                        const iframe = $videoPlayer[0];
                        if (iframe.contentWindow) {
                            iframe.contentWindow.postMessage({ type: 'play' }, '*');
                        }
                    } catch (e) {
                        console.error('Error forcing autoplay:', e);
                    }
                }, 1000);
                
                $muteNotification.show();
                setTimeout(() => {
                    $muteNotification.fadeOut();
                }, 5000);
            }
        }

        function getStreamUrl(callback) {
            $.ajax({
                url: ftPpv.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ft_ppv_get_stream_url',
                    nonce: ftPpv.nonce,
                    player_id: playerId
                },
                success: function(response) {
                    if (response.success && response.data.url) {
                        callback && callback(response.data.url);
                    } else {
                        console.error('Failed to get stream URL');
                        setTimeout(() => getStreamUrl(callback), 30000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error getting stream URL:', error);
                    setTimeout(() => getStreamUrl(callback), 30000);
                }
            });
        }

        function unlockVideo() {
            if (isVideoUnlocked) return;
            
            isVideoUnlocked = true;
            $countdownContainer.hide();
            $videoContainer.show();

            getStreamUrl(updatePlayerSource);
            
            // Check URL every 5 minutes
            setInterval(() => {
                getStreamUrl(updatePlayerSource);
            }, 5 * 60 * 1000);

            clearInterval(checkInterval);
        }

        // Initialize with both intervals
        const countdownInterval = setInterval(updateCountdown, 1000);
        checkInterval = setInterval(() => checkStreamStatus(false), 30000);
        
        // Initial checks
        updateCountdown();
        checkStreamStatus(true);
    });
}); 