<?php
/**
 * Central API configuration for Smalk Analytics.
 *
 * Provides a single place to configure all API endpoints.
 * Change API_BASE_URL to point to development server if needed.
 *
 * @package Smalk_AI_Analytics
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Smalk_API {

    /**
     * Base URL for the Smalk API.
     *
     * Production: https://api.smalk.ai
     * Local development: http://127.0.0.1:8000
     */
    const API_BASE_URL = 'https://api.smalk.ai';
    // const API_BASE_URL = 'http://host.docker.internal:8000';                                                                                                                                                           

    /**
     * Get the base API URL.
     *
     * @return string The base API URL.
     */
    public static function get_base_url() {
        return self::API_BASE_URL;
    }

    /**
     * Get the API v1 base URL.
     *
     * @return string The API v1 base URL.
     */
    public static function get_api_v1_base_url() {
        return self::API_BASE_URL . '/api/v1';
    }

    /**
     * Get the projects API endpoint.
     *
     * @return string The projects API endpoint URL.
     */
    public static function get_projects_url() {
        return self::get_api_v1_base_url() . '/projects/';
    }

    /**
     * Get the tracking API endpoint.
     *
     * @return string The tracking API endpoint URL.
     */
    public static function get_tracking_url() {
        return self::get_api_v1_base_url() . '/tracking/visit/';
    }

    /**
     * Get the tracker JavaScript URL.
     *
     * @return string The tracker JavaScript URL.
     */
    public static function get_tracker_js_url() {
        return self::API_BASE_URL . '/tracker.js';
    }
}
