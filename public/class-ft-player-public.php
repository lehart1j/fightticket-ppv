<?php
class FT_Player_Public {
    private $player;
    private $encryption;

    public function __construct($player, $encryption) {
        $this->player = $player;
        $this->encryption = $encryption;
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_shortcode('ft_player', array($this, 'render_player_shortcode'));
        add_action('wp_ajax_ft_player_check_stream', array($this, 'ajax_check_stream'));
        add_action('wp_ajax_nopriv_ft_player_check_stream', array($this, 'ajax_check_stream'));
        add_action('wp_ajax_ft_player_get_stream_url', array($this, 'ajax_get_stream_url'));
        add_action('wp_ajax_nopriv_ft_player_get_stream_url', array($this, 'ajax_get_stream_url'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_ajax_nopriv_ft_player_refresh_nonce', array($this, 'ajax_refresh_nonce'));
        add_action('wp_ajax_ft_player_refresh_nonce', array($this, 'ajax_refresh_nonce'));
    }

    public function enqueue_public_assets() {
        wp_enqueue_style(
            'ft-player-public',
            FT_PLAYER_PLUGIN_URL . 'public/css/public.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'ft-player-public',
            FT_PLAYER_PLUGIN_URL . 'public/js/public.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('ft-player-public', 'ftPlayer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ft_player_nonce')
        ));
    }

    public function render_player_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);

        if (!$atts['id']) {
            return '<p>Error: Player ID is required</p>';
        }

        $player_data = $this->player->get_player($atts['id']);
        if (!$player_data) {
            return '<p>Error: Player not found</p>';
        }

        error_log('FT Player: Rendering player for ID: ' . $atts['id']);

        // Check if we have Cloudflare credentials
        $account_id = get_option('ft_player_account_id');
        $api_token = get_option('ft_player_api_token');

        if (!$account_id || !$api_token) {
            error_log('FT Player: Missing Cloudflare credentials');
            return '<p>Error: Stream configuration incomplete</p>';
        }

        ob_start();
        include FT_PLAYER_PLUGIN_DIR . 'public/views/player.php';
        return ob_get_clean();
    }

    public function ajax_check_stream() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        $player_id = intval($_POST['player_id']);
        error_log('FT Player: Checking stream status for player ID: ' . $player_id);
        
        if (!$player_id) {
            error_log('FT Player: Invalid player ID');
            wp_send_json_error(array('message' => 'Invalid player ID'));
            return;
        }

        try {
            $is_live = $this->player->check_stream_status($player_id);
            error_log('FT Player: Stream status result: ' . ($is_live ? 'live' : 'not live'));
            
            wp_send_json_success(array(
                'is_live' => $is_live,
                'message' => $is_live ? 'Stream is live' : 'Stream is not live'
            ));
        } catch (Exception $e) {
            error_log('FT Player: Error checking stream status - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error checking stream status'));
        }
    }

    public function ajax_get_stream_url() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        $player_id = intval($_POST['player_id']);
        $player = $this->player->get_player($player_id);
        
        if (!$player) {
            error_log('FT Player: No player data found for ID: ' . $player_id);
            wp_send_json_error();
            return;
        }

        try {
            $live_input_id = $this->encryption->decrypt($player->live_input_id);
            error_log('FT Player: Decrypted live input ID: ' . $live_input_id);
            
            // Get signed URL
            $url = $this->get_player_url($live_input_id);
            if (!$url) {
                error_log('FT Player: Failed to generate signed URL');
                wp_send_json_error();
                return;
            }

            wp_send_json_success(array(
                'url' => $url
            ));
        } catch (Exception $e) {
            error_log('FT Player: Error generating stream URL - ' . $e->getMessage());
            wp_send_json_error();
        }
    }

    public function register_rest_routes() {
        register_rest_route('ft-player/v1', '/stream', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_stream_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'stream_token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }

    public function handle_stream_request($request) {
        $token = sanitize_text_field($request->get_param('stream_token'));
        
        error_log('FT Player: Handling stream request for token: ' . $token);
        
        if (empty($token)) {
            error_log('FT Player: Empty token provided');
            return new WP_Error('invalid_token', 'Invalid stream token', array('status' => 403));
        }

        $stream_data = get_transient('ft_player_stream_' . $token);
        error_log('FT Player: Stream data for token: ' . print_r($stream_data, true));

        if (empty($stream_data) || !is_array($stream_data)) {
            error_log('FT Player: Invalid or missing stream data');
            return new WP_Error('invalid_data', 'Invalid stream data', array('status' => 403));
        }

        $live_input_id = $stream_data['live_input_id'];
        
        // Build the redirect URL with all necessary parameters
        $redirect_url = esc_url_raw(
            "https://customer-fitpc906ebhogc6m.cloudflarestream.com/{$live_input_id}/iframe"
        );
        error_log('FT Player: Redirecting to: ' . $redirect_url);

        // Send proper headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        
        wp_redirect($redirect_url);
        exit;
    }

    public function ajax_refresh_nonce() {
        wp_send_json_success(array(
            'nonce' => wp_create_nonce('ft_player_nonce')
        ));
    }

    public function ajax_test_stream() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $account_id = get_option('ft_player_account_id');
        $api_token = get_option('ft_player_api_token');

        if (!$account_id || !$api_token) {
            wp_send_json_error(array('message' => 'Missing Cloudflare credentials'));
            return;
        }

        try {
            $account_id = $this->encryption->decrypt($account_id);
            $api_token = $this->encryption->decrypt($api_token);

            $response = wp_remote_get(
                "https://api.cloudflare.com/client/v4/accounts/{$account_id}/stream/live_inputs",
                array(
                    'headers' => array(
                        'Authorization' => "Bearer {$api_token}",
                        'Content-Type' => 'application/json'
                    ),
                    'timeout' => 15
                )
            );

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'API request failed: ' . $response->get_error_message()));
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            wp_send_json_success(array(
                'code' => $response_code,
                'body' => json_decode($response_body)
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }

    public function render_player($atts) {
        $player_id = isset($atts['id']) ? intval($atts['id']) : 0;
        if (!$player_id) {
            return '';
        }

        error_log('FT Player: Rendering player for ID: ' . $player_id);

        // Check if stream is live
        $is_live = $this->player->check_stream_status($player_id);
        
        if ($is_live) {
            // Get the player object to access the live_input_id
            $player = $this->player->get_player($player_id);
            if (!$player) {
                return '';
            }

            try {
                // Decrypt the live_input_id
                $live_input_id = $this->encryption->decrypt($player->live_input_id);
                // Get the signed URL using the new method
                $player_url = $this->get_player_url($live_input_id);
                
                if (!$player_url) {
                    $is_live = false;
                }
            } catch (Exception $e) {
                error_log('FT Player: Error getting player URL - ' . $e->getMessage());
                $is_live = false;
            }
        }

        // Load template
        ob_start();
        include plugin_dir_path(__FILE__) . 'views/player.php';
        return ob_get_clean();
    }

    private function get_player_url($live_input_id) {
        // Get the configured subdomain
        $subdomain = get_option('ft_player_cloudflare_subdomain');
        
        if (empty($subdomain)) {
            error_log('FT Player: Cloudflare subdomain not configured');
            return false;
        }

        // Get the signed URL from the player class
        $signed_url = $this->player->get_signed_url($live_input_id);
        if (!$signed_url) {
            error_log('FT Player: Failed to generate signed URL');
            return false;
        }

        // Build the complete URL with subdomain, signed URL and player parameters
        $redirect_url = esc_url_raw(
            "https://{$subdomain}/{$signed_url}/iframe?autoplay=true&muted=true&controls=true&preload=true"
        );

        error_log('FT Player: Generated player URL: ' . $redirect_url);
        return $redirect_url;
    }
} 