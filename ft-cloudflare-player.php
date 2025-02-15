<?php
/**
 * Plugin Name: FightTicket Cloudflare Player
 * Description: Secure Video Player Using Cloudlfare Signed URLs and a Countdown Timer
 * Version: 1.0.1
 * Author: James Lehart | FightTicket Ltd
 * Plugin URI: https://github.com/lehart1j/fightticket-ppv
 * Text Domain: ft-player
 */

 if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('FT_PLAYER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FT_PLAYER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once FT_PLAYER_PLUGIN_DIR . 'includes/class-ft-player-encryption.php';
require_once FT_PLAYER_PLUGIN_DIR . 'includes/class-ft-player-player.php';
require_once FT_PLAYER_PLUGIN_DIR . 'admin/class-ft-player-admin.php';
require_once FT_PLAYER_PLUGIN_DIR . 'public/class-ft-player-public.php';

// Initialize plugin
function ft_player_init() {
    $encryption = new FT_Player_Encryption();
    $player = new FT_Player_Player($encryption);
    
    if (is_admin()) {
        new FT_Player_Admin($player, $encryption);
    }
    
    new FT_Player_Public($player, $encryption);
}
add_action('init', 'ft_player_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create tables
    $player = new FT_Player_Player(new FT_Player_Encryption());
    $player->create_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Add this to ensure REST API is available
add_filter('rest_enabled', '__return_true');
add_filter('rest_jsonp_enabled', '__return_true'); 

