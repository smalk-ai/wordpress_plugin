<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// General

define('SMALK_AI_WORDPRESS_PLUGIN_VERSION', '1.0.1');
define('SMALK_AI_LOGO_PATH', plugin_dir_path(SMALK_AI_PLUGIN_FILE) . 'assets/Logo_512x512_remove_bg.svg');
define('SMALK_AI_LOGO_URL', plugin_dir_url(SMALK_AI_PLUGIN_FILE) . 'assets/Logo_512x512_remove_bg.svg');

// Settings Groups

define('SMALK_AI_SETTINGS_GROUP', 'smalk_ai_settings_group');

// Setting Options

define('SMALK_AI_ACCESS_TOKEN', 'smalk_ai_access_token');
define('SMALK_AI_IS_ANALYTICS_ENABLED', 'smalk_ai_is_analytics_enabled');

// Cached Item Options

define('SMALK_AI_USER', 'smalk_ai_user');
define('SMALK_AI_ROBOTS_TXT', 'smalk_ai_robots_txt');

// Cron Job Events

define('SMALK_AI_DAILY_CRON_EVENT', 'smalk_ai_daily_cron_event');
define('SMALK_AI_EVERY_FIVE_MINUTES_CRON_EVENT', 'smalk_ai_every_five_minutes_cron_event');