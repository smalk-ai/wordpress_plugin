<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Server Analytics

function smalk_send_visit_request() {
    $access_token = get_option(SMALK_AI_ACCESS_TOKEN);
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : false;

    $should_send_visit_request = smalk_is_analytics_enabled_and_allowed();

    $request_path = isset($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : false;
    $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : false;
    $request_headers = smalk_get_request_headers();

    // Send the visit request if needed

    if ($should_send_visit_request && $access_token && $request_path && $request_method && !smalk_is_system_request($request_path)) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Api-Key ' . $access_token
        );

        $body = array(
            'request_path' => $request_path,
            'request_method' => $request_method,
            'request_headers' => $request_headers,
            'wordpress_plugin_version' => SMALK_AI_WORDPRESS_PLUGIN_VERSION
        );

        wp_remote_post('https://api.smalk.ai/api/v1/tracking/visit', array(
            'headers' => $headers,
            'body' => wp_json_encode($body)
        ));
    }
}

add_action('wp_loaded', 'smalk_send_visit_request');

// Client Analytics

function smalk_add_analytics_script_tag() {
    if ( smalk_is_analytics_enabled_and_allowed() ) {
        $project_id = smalk_get_user_analytics_script_tag();
        if ( !empty($project_id) ) {
            // Register the script.
            wp_register_script(
                'smalk-analytics',
                "https://api.smalk.ai/tracker.js?PROJECT_KEY={$project_id}",
                array(),
                SMALK_AI_WORDPRESS_PLUGIN_VERSION,
                false // Load in head.
            );
            
            // Add inline JavaScript (a comment in this case) using wp_add_inline_script.
            wp_add_inline_script(
                'smalk-analytics',
                '/* Smalk AI Agent Analytics (https://www.smalk.ai) */',
                'before'
            );
            
            // Enqueue the script.
            wp_enqueue_script('smalk-analytics');
        }
    }
}
add_action('wp_enqueue_scripts', 'smalk_add_analytics_script_tag', 1);

// Helpers

function smalk_get_request_headers() {
    $header_names = [
        // User Agent Information
        'User-Agent',
        'Sec-Ch-Ua', // Browser brand and version
        'Sec-Ch-Ua-Platform', // Operating system
        // Navigation & Referral
        'Referer',
        'Origin',
        'From',
        // Language & Locale
        'Accept-Language',
        'Content-Language',
        // Geolocation Headers
        'X-Country-Code',
        'CF-IPCountry',           // Cloudflare country code
        'X-Geo-Country',          // Generic geo country header
        'X-Geo-City',             // City information
        'X-Geo-Region',           // Region/State information
        // IP-related Headers
        'Remote-Addr',
        'X-Forwarded-For',
        'X-Real-IP',
        'Client-IP',
        'CF-Connecting-IP',
        'X-Cluster-Client-IP',
        'Forwarded',
        'X-Original-Forwarded-For',
        'Fastly-Client-IP',
        'True-Client-IP',
        'X-Appengine-User-IP',
        // Connection Information
        'Connection',
        'Via' // Proxy information
    ];

    $request_headers = [];

    foreach ($header_names as $header_name) {
        $header_value = smalk_get_request_header_value($header_name);

        if ($header_value) {
            $request_headers[$header_name] = $header_value;
        }
    }
    
    return $request_headers;
}

function smalk_get_request_header_value($header_name) {
    $server_key = strtoupper(str_replace('-', '_', $header_name));
    $server_key_with_http_prefix = 'HTTP_' . $server_key;

    if (isset($_SERVER[$server_key])) {
        return sanitize_text_field(wp_unslash($_SERVER[$server_key]));
    } else if (isset($_SERVER[$server_key_with_http_prefix])) {
        return sanitize_text_field(wp_unslash($_SERVER[$server_key_with_http_prefix]));
    } else if (function_exists('getallheaders')) {
        $headers_with_lowercase_keys = array_change_key_case(getallheaders(), CASE_LOWER);
        $lowercased_header_name = strtolower($header_name);

        if (isset($headers_with_lowercase_keys[$lowercased_header_name])) {
            return $headers_with_lowercase_keys[$lowercased_header_name];
        } else {
            return null;
        }
    } else {
        return null;
    }
}

function smalk_is_system_request($request_path) {
    return (
        stripos($request_path, '/wp-admin') === 0 ||
        stripos($request_path, '/wp-login') === 0 ||
        stripos($request_path, '/wp-cron') === 0 ||
        stripos($request_path, '/wp-json') === 0 ||
        stripos($request_path, '/wp-includes') === 0 ||
        stripos($request_path, '/wp-content') === 0
    );
}

function smalk_is_analytics_enabled_and_allowed() {
    $is_analytics_enabled = get_option(SMALK_AI_IS_ANALYTICS_ENABLED) === '1';
    $is_analytics_disallowed = smalk_get_user_is_analytics_disallowed();
    return $is_analytics_enabled && !$is_analytics_disallowed;
}
