<?php
class FT_Player_Admin {
    private $player;
    private $encryption;

    public function __construct($player, $encryption) {
        $this->player = $player;
        $this->encryption = $encryption;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_ft_player_save_player', array($this, 'ajax_save_player'));
        add_action('wp_ajax_ft_player_delete_player', array($this, 'ajax_delete_player'));
        add_action('wp_ajax_ft_player_test_stream', array($this, 'ajax_test_stream'));
        add_action('wp_ajax_ft_player_generate_signing_key', array($this, 'ajax_generate_signing_key'));
        add_action('wp_ajax_ft_player_add_player', array($this, 'ajax_add_player'));
        add_action('admin_init', array($this, 'validate_settings'));
    }

    public function enqueue_admin_assets($hook) {
        if (!strpos($hook, 'ft-player')) {
            return;
        }

        wp_enqueue_style(
            'ft-player-admin',
            FT_PLAYER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'ft-player-admin',
            FT_PLAYER_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('ft-player-admin', 'ftPlayer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ft_player_nonce')
        ));
    }

    public function add_admin_menu() {
        // Main menu item
        add_menu_page(
            'FT PLAYER Players',
            'FT PLAYER',
            'manage_options',
            'ft-player-players',
            array($this, 'render_players_page'),
            'dashicons-video-alt3'
        );

        // Settings submenu under FT PLAYER
        add_submenu_page(
            'ft-player-players',
            'FT PLAYER Settings',
            'Settings',
            'manage_options',
            'ft-player-settings',
            array($this, 'display_settings_page')
        );

        // Also add to WordPress Settings menu for easier access
        add_options_page(
            'FT PLAYER Settings',
            'FT PLAYER',
            'manage_options',
            'ft-player-settings',
            array($this, 'display_settings_page')
        );
    }

    public function render_players_page() {
        $players = $this->player->get_players();
        include FT_PLAYER_PLUGIN_DIR . 'admin/views/players.php';
    }

    public function register_settings() {
        add_action('update_option', function($option_name, $old_value, $value) {
            if ($option_name === 'ft_player_account_id' || $option_name === 'ft_player_api_token') {
                error_log('FT PLAYER: Option being saved - ' . $option_name);
                error_log('FT PLAYER: New value length: ' . strlen($value));
            }
        }, 10, 3);

        // Register the settings
        register_setting(
            'ft_player_settings',
            'ft_player_account_id',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'encrypt_setting'),
                'default' => '',
                'show_in_rest' => false
            )
        );

        register_setting(
            'ft_player_settings',
            'ft_player_api_token',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'encrypt_setting'),
                'default' => '',
                'show_in_rest' => false
            )
        );

        register_setting('ft_player_settings', 'ft_player_signing_token');

        // Add new setting for Cloudflare subdomain
        register_setting('ft_player_settings', 'ft_player_cloudflare_subdomain', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        // Add settings section
        add_settings_section(
            'ft_player_main_section',
            'Cloudflare Stream Settings',
            array($this, 'settings_section_callback'),
            'ft_player_settings'
        );

        // Add settings fields
        add_settings_field(
            'ft_player_account_id',
            'Account ID',
            array($this, 'account_id_field_callback'),
            'ft_player_settings',
            'ft_player_main_section'
        );

        add_settings_field(
            'ft_player_api_token',
            'API Token',
            array($this, 'api_token_field_callback'),
            'ft_player_settings',
            'ft_player_main_section'
        );

        add_settings_field(
            'ft_player_signing_token',
            'Signing Token',
            array($this, 'signing_token_field_callback'),
            'ft_player_settings',
            'ft_player_main_section'
        );

        // Add new field for Cloudflare subdomain
        add_settings_field(
            'ft_player_cloudflare_subdomain',
            'Cloudflare Subdomain',
            array($this, 'cloudflare_subdomain_field_callback'),
            'ft_player_settings',
            'ft_player_main_section'
        );
    }

    public function encrypt_setting($value) {
        error_log('FT PLAYER: Encrypting setting value of length: ' . strlen($value));
        
        if (empty($value)) {
            error_log('FT PLAYER: Empty value, returning empty string');
            return '';
        }
        
        try {
            // Always encrypt as new value
            $encrypted = $this->encryption->encrypt($value);
            if (!empty($encrypted)) {
                error_log('FT PLAYER: Successfully encrypted value to length: ' . strlen($encrypted));
                return $encrypted;
            }
            throw new Exception('Encryption returned empty string');
        } catch (Exception $e) {
            error_log('FT PLAYER: Error encrypting value: ' . $e->getMessage());
            add_settings_error(
                'ft_player_settings',
                'encryption_error',
                'Error encrypting setting: ' . $e->getMessage()
            );
            return '';
        }
    }

    public function settings_section_callback() {
        echo '<p>Configure your Cloudflare Stream settings below.</p>';
    }

    public function account_id_field_callback() {
        $account_id = get_option('ft_player_account_id');
        try {
            $value = $account_id ? $this->encryption->decrypt($account_id) : '';
        } catch (Exception $e) {
            $value = '';
        }
        printf(
            '<input type="text" id="ft_player_account_id" name="ft_player_account_id" value="%s" class="regular-text">',
            esc_attr($value)
        );
    }

    public function api_token_field_callback() {
        $api_token = get_option('ft_player_api_token');
        try {
            $value = $api_token ? $this->encryption->decrypt($api_token) : '';
        } catch (Exception $e) {
            $value = '';
        }
        printf(
            '<input type="password" id="ft_player_api_token" name="ft_player_api_token" value="%s" class="regular-text">',
            esc_attr($value)
        );
    }

    public function signing_token_field_callback() {
        $signing_token = get_option('ft_player_signing_token');
        try {
            $value = $signing_token ? $this->encryption->decrypt($signing_token) : '';
        } catch (Exception $e) {
            $value = '';
        }
        printf(
            '<input type="password" id="ft_player_signing_token" name="ft_player_signing_token" value="%s" class="regular-text">',
            esc_attr($value)
        );
    }

    public function cloudflare_subdomain_field_callback() {
        $subdomain = get_option('ft_player_cloudflare_subdomain');
        ?>
        <input type="text" id="ft_player_cloudflare_subdomain" 
               name="ft_player_cloudflare_subdomain" 
               class="regular-text" 
               value="<?php echo esc_attr($subdomain); ?>" required>
        <p class="description">Your Cloudflare Stream subdomain (e.g., customer-fitpc906ebhogc6m.cloudflarestream.com)</p>
        <?php
    }

    public function display_settings_page() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include the settings page view
        include FT_PLAYER_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function ajax_save_player() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
            return;
        }

        $name = sanitize_text_field($_POST['name']);
        $live_input_id = sanitize_text_field($_POST['live_input_id']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $background_image = esc_url_raw($_POST['background_image']);

        $result = $this->player->add_player($name, $live_input_id, $event_date, $background_image);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to save player'));
            return;
        }

        wp_send_json_success(array(
            'message' => 'Player saved successfully'
        ));
    }

    public function ajax_delete_player() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
            return;
        }

        $player_id = sanitize_text_field($_POST['player_id']);

        $result = $this->player->delete_player($player_id);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to delete player'));
            return;
        }

        wp_send_json_success(array(
            'message' => 'Player deleted successfully'
        ));
    }

    public function ajax_test_stream() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
            return;
        }

        $account_id = get_option('ft_player_account_id');
        $api_token = get_option('ft_player_api_token');

        if (empty($account_id) || empty($api_token)) {
            wp_send_json_error(array('message' => 'Account ID and API Token are required'));
            return;
        }

        try {
            $decrypted_account = $this->encryption->decrypt($account_id);
            $decrypted_token = $this->encryption->decrypt($api_token);
            
            if (empty($decrypted_account) || empty($decrypted_token)) {
                throw new Exception('Failed to decrypt credentials');
            }

            error_log('FT PLAYER: Test connection with decrypted credentials');
            error_log('FT PLAYER: Account ID length: ' . strlen($decrypted_account));
            error_log('FT PLAYER: Token length: ' . strlen($decrypted_token));
            
            $url = sprintf(
                'https://api.cloudflare.com/client/v4/accounts/%s/stream/live_inputs',
                trim($decrypted_account)
            );
            error_log('FT PLAYER: Testing URL: ' . preg_replace('/[^\/]*$/', 'HIDDEN', $url));

            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . trim($decrypted_token),
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                error_log('FT PLAYER: Connection error - ' . $response->get_error_message());
                wp_send_json_error(array(
                    'message' => 'Connection error: ' . $response->get_error_message()
                ));
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log('FT PLAYER: Response code: ' . $response_code);
            error_log('FT PLAYER: Response body: ' . $response_body);

            if ($response_code !== 200) {
                wp_send_json_error(array(
                    'message' => 'Invalid API credentials (HTTP ' . $response_code . '). Please check your Account ID and API Token.'
                ));
                return;
            }

            wp_send_json_success(array(
                'message' => 'Test connection successful'
            ));
        } catch (Exception $e) {
            error_log('FT PLAYER: Error - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
            return;
        }
    }

    public function generate_signing_key() {
        try {
            // Get credentials
            $credentials = $this->player->get_decrypted_credentials();
            if (empty($credentials['account_id']) || empty($credentials['api_token'])) {
                throw new Exception('Missing required credentials');
            }

            // Make API request
            $response = wp_remote_post(
                sprintf('https://api.cloudflare.com/client/v4/accounts/%s/stream/keys', $credentials['account_id']),
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $credentials['api_token'],
                        'Content-Type' => 'application/json'
                    ),
                    'timeout' => 15
                )
            );

            if (is_wp_error($response)) {
                throw new Exception('Request failed: ' . $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response));
            if (!$body || !$body->success) {
                $error_msg = isset($body->errors[0]->message) ? $body->errors[0]->message : 'Unknown error';
                throw new Exception('Failed to generate signing key: ' . $error_msg);
            }

            // Save the signing key
            update_option('ft_player_signing_token', $this->encryption->encrypt($body->result->id));
            
            return array(
                'success' => true,
                'message' => 'Signing key generated successfully'
            );
        } catch (Exception $e) {
            error_log('FT PLAYER: Error generating signing key - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    public function ajax_generate_signing_key() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
            return;
        }

        $result = $this->generate_signing_key();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_add_player() {
        check_ajax_referer('ft_player_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
            return;
        }

        // Debug the entire POST data
        error_log('FT Player: POST data received: ' . print_r($_POST, true));

        $name = sanitize_text_field($_POST['name']);
        $live_input_id = sanitize_text_field($_POST['live_input_id']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $background_image = isset($_POST['background_image']) ? esc_url_raw($_POST['background_image']) : '';

        // Add debugging
        error_log('FT Player: Processing add_player with data:');
        error_log('Name: ' . $name);
        error_log('Live Input ID: ' . $live_input_id);
        error_log('Event Date: ' . $event_date);
        error_log('Background Image: ' . $background_image);

        $result = $this->player->add_player($name, $live_input_id, $event_date, $background_image);
        
        if ($result === false) {
            error_log('FT Player: Failed to save player. DB Error: ' . $this->wpdb->last_error);
            wp_send_json_error(array('message' => 'Failed to save player'));
            return;
        }

        error_log('FT Player: Player added successfully');
        wp_send_json_success(array(
            'message' => 'Player saved successfully'
        ));
    }

    public function validate_settings() {
        // Validate Cloudflare subdomain
        $subdomain = get_option('ft_player_cloudflare_subdomain');
        if (!empty($subdomain)) {
            // Remove any protocol and trailing slashes
            $subdomain = preg_replace('#^https?://#', '', rtrim($subdomain, '/'));
            
            // Validate format
            if (!preg_match('/^[\w-]+\.cloudflarestream\.com$/', $subdomain)) {
                add_settings_error(
                    'ft_player_settings',
                    'invalid_subdomain',
                    'Invalid Cloudflare Stream subdomain format. It should end with .cloudflarestream.com'
                );
            }
            
            // Update the cleaned value
            update_option('ft_player_cloudflare_subdomain', $subdomain);
        }
    }
} 