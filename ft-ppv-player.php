<?php
/**
 * Plugin Name: FightTicket PPV Player
 * Plugin URI: https://github.com/lehart1j/fightticket-ppv
 * Description: FightTicket PPV Player using Cloudflare Stream signed URLs
 * Version: 1.0.0
 * Author: James Lehart
 * Author URI: https://fightticket.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ft-ppv
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('FT_PPV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FT_PPV_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once FT_PPV_PLUGIN_DIR . 'includes/class-ft-ppv-encryption.php';
require_once FT_PPV_PLUGIN_DIR . 'includes/class-ft-ppv-player.php';
require_once FT_PPV_PLUGIN_DIR . 'admin/class-ft-ppv-admin.php';
require_once FT_PPV_PLUGIN_DIR . 'public/class-ft-ppv-public.php';
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Initialize plugin
function ft_ppv_init() {
    $encryption = new FT_PPV_Encryption();
    $player = new FT_PPV_Player($encryption);
    
    if (is_admin()) {
        new FT_PPV_Admin($player, $encryption);
    }
    
    new FT_PPV_Public($player, $encryption);
    
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/lehart1j/fightticket-ppv/', // Your GitHub repository
        __FILE__, // Full path to the main plugin file
        'ft-ppv-player' // Plugin slug
    );

    // Optional: Set the branch that contains the stable release
    $updateChecker->setBranch('main');
}
add_action('init', 'ft_ppv_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create tables
    $player = new FT_PPV_Player(new FT_PPV_Encryption());
    $player->create_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Add this to ensure REST API is available
add_filter('rest_enabled', '__return_true');
add_filter('rest_jsonp_enabled', '__return_true'); 