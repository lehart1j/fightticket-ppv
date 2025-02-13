<?php
class FT_PPV_Player {
    private $encryption;
    private $wpdb;

    public function __construct($encryption) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->encryption = $encryption;
    }

    public function get_players() {
        $table_name = $this->wpdb->prefix . 'ft_ppv_players';
        return $this->wpdb->get_results("SELECT * FROM $table_name");
    }

    public function add_player($name, $live_input_id, $event_date, $background_image = '') {
        $table_name = $this->wpdb->prefix . 'ft_ppv_players';
        return $this->wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'live_input_id' => $this->encryption->encrypt($live_input_id),
                'event_date' => $event_date,
                'background_image' => $background_image
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    public function is_cloudflare_configured() {
        $account_id = get_option('ft_ppv_account_id');
        $api_token = get_option('ft_ppv_api_token');
        
        return !empty($account_id) && !empty($api_token);
    }

    public function get_decrypted_credentials() {
        $account_id = get_option('ft_ppv_account_id');
        $api_token = get_option('ft_ppv_api_token');
        $signing_token = get_option('ft_ppv_signing_token');
        
        if (empty($account_id) || empty($api_token)) {
            throw new Exception('Missing Cloudflare credentials');
        }
        
        try {
            return array(
                'account_id' => trim($this->encryption->decrypt($account_id)),
                'api_token' => trim($this->encryption->decrypt($api_token)),
                'signing_token' => !empty($signing_token) ? trim($this->encryption->decrypt($signing_token)) : null
            );
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt credentials: ' . $e->getMessage());
        }
    }

    private function generate_signed_url($video_id) {
        try {
            $credentials = $this->get_decrypted_credentials();
            if (empty($credentials['signing_token'])) {
                error_log('FT PPV: No signing token available');
                throw new Exception('Signing token not configured');
            }
            
            // The signing token should be used as-is, not base64 decoded
            $signing_token = $credentials['signing_token'];
            error_log('FT PPV: Using signing token of length: ' . strlen($signing_token));
            
            // Set expiration to 2 hours
            $exp = time() + (2 * 60 * 60);
            
            // Generate the policy
            $policy = array(
                'exp' => $exp,
                'sub' => $video_id,
                'kid' => $credentials['account_id']
            );
            
            error_log('FT PPV: Generated policy: ' . json_encode($policy));
            
            // Base64 encode the policy (URL safe)
            $encoded_policy = rtrim(strtr(base64_encode(json_encode($policy)), '+/', '-_'), '=');
            
            // Generate the signature
            $signature = hash_hmac('sha256', $encoded_policy, $signing_token, true);
            $encoded_signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            
            error_log('FT PPV: Generated signature of length: ' . strlen($encoded_signature));
            
            // Build the signed URL
            $url = sprintf(
                'https://customer-fitpc906ebhogc6m.cloudflarestream.com/%s/iframe?signature=%s&policy=%s',
                $video_id,
                $encoded_signature,
                $encoded_policy
            );
            
            error_log('FT PPV: Full URL structure: ' . preg_replace('/signature=([^&]+)/', 'signature=HIDDEN', $url));
            return $url;
        } catch (Exception $e) {
            error_log('FT PPV: Error generating signed URL - ' . $e->getMessage());
            return false;
        }
    }

    public function check_stream_status($player_id) {
        try {
            if (!$this->is_cloudflare_configured()) {
                error_log('FT PPV: Cloudflare not configured');
                return false;
            }
            
            $credentials = $this->get_decrypted_credentials();
            $player = $this->get_player($player_id);
            
            if (!$player) {
                error_log('FT PPV: Player not found');
                return false;
            }

            $live_input_id = trim($this->encryption->decrypt($player->live_input_id));
            
            $url = sprintf(
                'https://api.cloudflare.com/client/v4/accounts/%s/stream/live_inputs/%s',
                $credentials['account_id'],
                $live_input_id
            );
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $credentials['api_token'],
                    'Content-Type' => 'application/json'
                )
            ));

            if (is_wp_error($response)) {
                error_log('FT PPV: Error checking stream status');
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            // Check for the correct status path and value
            if (!empty($body['result']['status']['current']['state']) && 
                $body['result']['status']['current']['state'] === 'connected') {
                error_log('FT PPV: Stream is live');
                return true;
            }

            error_log('FT PPV: Stream is not live');
            return false;

        } catch (Exception $e) {
            error_log('FT PPV: Error checking stream status');
            return false;
        }
    }

    public function get_player($id) {
        $table_name = $this->wpdb->prefix . 'ft_ppv_players';
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $id
            )
        );
    }

    public function delete_player($id) {
        $table_name = $this->wpdb->prefix . 'ft_ppv_players';
        return $this->wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
    }

    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ft_ppv_players';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            live_input_id varchar(255) NOT NULL,
            event_date datetime NOT NULL,
            background_image varchar(255) DEFAULT '',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function stream_endpoint($request) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        if (!wp_doing_ajax() && !defined('REST_REQUEST')) {
            error_log('FT PPV: Invalid request method');
            return new WP_Error('invalid_request', 'Invalid request method', array('status' => 403));
        }

        $stream_token = $request->get_param('stream_token');
        if (empty($stream_token)) {
            error_log('FT PPV: Missing stream token');
            return new WP_Error('missing_token', 'Stream token is required', array('status' => 400));
        }

        try {
            error_log('FT PPV: Processing stream request with token: ' . $stream_token);
            
            // Get stream data from transient
            $stream_data = get_transient('ft_ppv_stream_' . $stream_token);
            if (empty($stream_data) || !is_array($stream_data)) {
                error_log('FT PPV: Invalid or expired stream token');
                throw new Exception('Invalid or expired stream token');
            }
            error_log('FT PPV: Retrieved stream data: ' . print_r($stream_data, true));

            $live_input_id = $stream_data['live_input_id'];
            
            // Get credentials and check signing token
            $credentials = $this->get_decrypted_credentials();
            if (empty($credentials['signing_token'])) {
                error_log('FT PPV: No signing token found in credentials');
                throw new Exception('Signing token not configured');
            }
            
            // Generate signed URL
            try {
                $signed_url = $this->generate_signed_url($live_input_id);
                if (!$signed_url) {
                    throw new Exception('Failed to generate signed URL');
                }
                error_log('FT PPV: Generated signed URL (masked): ' . preg_replace('/signature=([^&]+)&policy=([^&]+)/', 'signature=HIDDEN&policy=HIDDEN', $signed_url));
                
                wp_redirect($signed_url);
                exit;
            } catch (Exception $e) {
                error_log('FT PPV: Error generating signed URL: ' . $e->getMessage());
                throw $e;
            }
        } catch (Exception $e) {
            error_log('FT PPV: Stream error - ' . $e->getMessage());
            return new WP_Error('stream_error', $e->getMessage(), array('status' => 500));
        }
    }

    private function get_player_from_token($stream_token) {
        try {
            error_log('FT PPV: Looking up player for token: ' . $stream_token);
            
            // Decode the stream token to get player data
            $stream_data = json_decode($this->encryption->decrypt($stream_token), true);
            error_log('FT PPV: Stream data from token: ' . print_r($stream_data, true));
            
            if (!$stream_data || empty($stream_data['live_input_id'])) {
                error_log('FT PPV: Invalid stream data in token');
                return null;
            }
            
            // Find player by live_input_id
            $table_name = $this->wpdb->prefix . 'ft_ppv_players';
            $player = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM $table_name WHERE live_input_id = %s",
                    $this->encryption->encrypt($stream_data['live_input_id'])
                )
            );
            
            if ($player) {
                error_log('FT PPV: Found player with ID: ' . $player->id);
            } else {
                error_log('FT PPV: No player found for live input ID');
            }
            
            return $player;
        } catch (Exception $e) {
            error_log('FT PPV: Error getting player from token - ' . $e->getMessage());
            return null;
        }
    }

    public function get_player_iframe($player_id, $stream_token = null) {
        try {
            $player = $this->get_player($player_id);
            if (!$player) {
                error_log('FT PPV: Player not found with ID: ' . $player_id);
                return '';
            }

            // Create a new stream token if not provided
            if (empty($stream_token)) {
                $stream_data = array(
                    'live_input_id' => $this->encryption->decrypt($player->live_input_id),
                    'session_id' => wp_generate_uuid4(),
                    'timestamp' => time()
                );
                $stream_token = $this->encryption->encrypt(json_encode($stream_data));
                error_log('FT PPV: Generated new stream token with data: ' . print_r($stream_data, true));
            }

            // Use the REST endpoint URL
            $stream_url = add_query_arg(array(
                'stream_token' => $stream_token
            ), rest_url('ft-ppv/v1/stream'));

            error_log('FT PPV: Generated iframe URL: ' . $stream_url);

            return sprintf(
                '<iframe 
                    src="%s" 
                    class="ft-ppv-player" 
                    allow="autoplay; encrypted-media; fullscreen" 
                    allowfullscreen
                    loading="lazy"
                    referrerpolicy="no-referrer"
                    disablePictureInPicture
                    muted
                    playsinline
                ></iframe>',
                esc_url($stream_url)
            );
        } catch (Exception $e) {
            error_log('FT PPV: Error generating player - ' . $e->getMessage());
            return '';
        }
    }

    public function register_endpoints() {
        register_rest_route('ft-ppv/v1', '/stream', array(
            'methods' => 'GET',
            'callback' => array($this, 'stream_endpoint'),
            'permission_callback' => '__return_true',
            'args' => array(
                'stream_token' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));

        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function($value) {
                header('Access-Control-Allow-Origin: https://customer-fitpc906ebhogc6m.cloudflarestream.com');
                header('Access-Control-Allow-Methods: GET');
                header('Access-Control-Allow-Credentials: true');
                header("Content-Security-Policy: frame-ancestors 'self' https://customer-fitpc906ebhogc6m.cloudflarestream.com");
                return $value;
            });
        });
    }

    public function get_signed_url($video_id) {
        try {
            $credentials = $this->get_decrypted_credentials();
            if (empty($credentials['api_token'])) {
                error_log('FT PPV: No API token available');
                throw new Exception('API token not configured');
            }

            $site_domain = parse_url(get_site_url(), PHP_URL_HOST);
            
            // First set allowed origins on the live input
            $settings_url = sprintf(
                'https://api.cloudflare.com/client/v4/accounts/%s/stream/live_inputs/%s',
                $credentials['account_id'],
                $video_id
            );

            $settings_data = array(
                'allowedOrigins' => array($site_domain),
                'requireSignedURLs' => true
            );

            // Mask sensitive data in logs
            $masked_settings = json_encode($settings_data);
            error_log('FT PPV: Updating live input settings');

            $settings_response = wp_remote_post($settings_url, array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $credentials['api_token'],
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($settings_data)
            ));

            if (is_wp_error($settings_response)) {
                error_log('FT PPV: Error setting allowed origins');
                return false;
            }

            // Now get a simple signed token
            $token_url = sprintf(
                'https://api.cloudflare.com/client/v4/accounts/%s/stream/%s/token',
                $credentials['account_id'],
                $video_id
            );

            $token_data = array(
                'exp' => time() + (2 * 60 * 60), // 2 hours expiry
                'nbf' => time()
            );

            $response = wp_remote_post($token_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $credentials['api_token'],
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($token_data)
            ));

            if (is_wp_error($response)) {
                error_log('FT PPV: Error requesting token');
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (empty($body['success']) || empty($body['result']['token'])) {
                error_log('FT PPV: Invalid token response');
                throw new Exception('Invalid token response');
            }

            // Build the final URL with the token BEFORE /iframe
            $url = sprintf(
                'https://customer-fitpc906ebhogc6m.cloudflarestream.com/%s/iframe?autoplay=true&muted=true&controls=true',
                $body['result']['token']
            );

            // Mask the token in the URL for logging
            error_log('FT PPV: Generated signed URL');
            return $url;

        } catch (Exception $e) {
            error_log('FT PPV: Error generating signed URL');
            return false;
        }
    }

    private function update_stream_settings($video_id) {
        try {
            $credentials = $this->get_decrypted_credentials();
            $site_domain = parse_url(get_site_url(), PHP_URL_HOST);
            
            $url = sprintf(
                'https://api.cloudflare.com/client/v4/accounts/%s/stream/%s',
                $credentials['account_id'],
                $video_id
            );
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $credentials['api_token'],
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'uid' => $video_id,
                    'allowedOrigins' => array($site_domain, 'www.' . $site_domain),
                    'requireSignedURLs' => true
                ))
            ));
            
            if (is_wp_error($response)) {
                error_log('FT PPV: Failed to update stream settings - ' . $response->get_error_message());
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log('FT PPV: Error updating stream settings - ' . $e->getMessage());
            return false;
        }
    }
} 