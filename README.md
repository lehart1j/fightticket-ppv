# FightTicket Cloudflare Player

A WordPress plugin for secure video playback using Cloudflare signed URLs with countdown timer functionality.

## Description

FightTicket Cloudflare Player is a specialized WordPress plugin that enables secure video streaming through Cloudflare's platform. It features:

- Secure video playback using Cloudflare signed URLs
- Integrated countdown timer for time-sensitive content
- Simple shortcode implementation
- WordPress admin integration

## Auto Installation
1. In Wordpress go to -> Plugins -> Add New Plugin
2. Uplaod the zip file
3. Activate the plugin
4. Configure your Cloudflare settings in the plugin admin panel

## Manual Installation

1. Upload the plugin files to the `/wp-content/plugins/fightticket-cloudflare-player` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Cloudflare settings in the plugin admin panel

## Usage

To embed a video player, use the following shortcode: [ft-player id="*"]

## Changelog

### 1.0.2
- Fixed various error relating to key signing
- Fixed countdown background not showing
- Added subdomain replacement in the plugin settings

-- Upcoming changes --
- Add support for editing existing players
- Add support for bulk player deletion
- Add support for add VOD players

### 1.0.1
- Fixed various error relating to key signing
- Fixed typos in error messages

-- Upcoming changes --
- Fix subdomain error
- Include plugin auto-updater

### 1.0.0 
- Inital release