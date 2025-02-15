<?php

public static function activate() {
    // ... existing activation code ...
    
    // Add default subdomain if not set
    if (!get_option('ft_player_cloudflare_subdomain')) {
        add_option('ft_player_cloudflare_subdomain', '');
    }
} 