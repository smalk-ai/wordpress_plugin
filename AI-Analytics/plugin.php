<?php

/*
Plugin Name: AI Agent Analytics by Smalk AI
Description: Get realtime analytics on the AI agents, crawlers, scrapers, and other bots visiting your website and manage your brand visibility on Answer Engines (ChatGPT, Perplexity, etc.)
Version: 1.0.1
Author URI: https://www.smalk.me
Author: Smalk AI
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 1.0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
*/

if (!defined('ABSPATH')) {
    exit;
}

define('SMALK_AI_PLUGIN_FILE', __FILE__);

require_once plugin_dir_path(__FILE__) . 'includes/constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/variables.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/robots-txt.php';
require_once plugin_dir_path(__FILE__) . 'includes/analytics.php';
