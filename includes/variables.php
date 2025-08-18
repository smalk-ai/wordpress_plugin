<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// User

function smalk_get_user() {
    $cached_user = get_option(SMALK_AI_USER);
    return $cached_user !== false ? $cached_user : false;
}

// User Helpers

function smalk_get_user_is_analytics_disallowed() {
    $access_token = get_option(SMALK_AI_ACCESS_TOKEN);
    if ($access_token) {
        $user = smalk_get_user();
        return isset($user['is_analytics_allowed']) ? !$user['is_analytics_allowed'] : false;
    } else {
        return true;
    }
}

function smalk_get_user_analytics_script_tag() {
    $access_token = get_option(SMALK_AI_ACCESS_TOKEN);
    if (!$access_token) {
        return '';
    }
    
    $api_url = 'https://api.smalk.ai/api/v1/projects';
    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Api-Key ' . $access_token
    );
    
    $response = wp_remote_get($api_url, array('headers' => $headers));
    
    if (smalk_is_network_response_code_successful($response)) {
        $project = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($project) && isset($project['id'])) {
            $project_id = esc_attr($project['id']);
            return $project_id;
        }
    }
    
    return '';
}

// Caching

function smalk_clear_caches($option_name) {
    $cache_clearing_option_names = array(
        SMALK_AI_ACCESS_TOKEN,
        SMALK_AI_IS_ANALYTICS_ENABLED
    );

    if (in_array($option_name, $cache_clearing_option_names)) {
        delete_option(SMALK_AI_USER);
    }
}
add_action('update_option', 'smalk_clear_caches');

// Helpers

function smalk_is_network_response_code_successful($response) {
    return !is_wp_error($response) && 
           wp_remote_retrieve_response_code($response) >= 200 && 
           wp_remote_retrieve_response_code($response) < 300;
}
