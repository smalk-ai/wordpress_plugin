<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// INTEGRATED SOLUTIONS 1, 2, 3: Cache-Resistant Analytics

// SOLUTION 2: Server Analytics - Non-Blocking Asynchronous Tracking
function smalk_send_visit_request() {
    $access_token = get_option(SMALK_AI_ACCESS_TOKEN);
    $user_agent_raw = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    $user_agent = $user_agent_raw ? $user_agent_raw : false;
    $request_path = isset($_SERVER['REQUEST_URI']) ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) : '';

    $should_send_visit_request = smalk_is_analytics_enabled_and_allowed();

    $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : false;
    $request_headers = smalk_get_request_headers();

    error_log('[Smalk Analytics] Checking tracking - enabled: ' . ($should_send_visit_request ? 'yes' : 'no') . ', token: ' . (!empty($access_token) ? 'yes' : 'no') . ', path: ' . $request_path);

    // Send the visit request if needed
    if ($should_send_visit_request && $access_token && $request_path && $request_method && !smalk_is_system_request($request_path)) {
        // Non-blocking asynchronous request with cache-busting headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Api-Key ' . $access_token,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Smalk-CMS' => 'wordpress/' . get_bloginfo('version'),
            'X-Smalk-Plugin-Version' => SMALK_AI_WORDPRESS_PLUGIN_VERSION,
        );

        $body = array(
            'request_path' => $request_path,
            'request_method' => $request_method,
            'request_headers' => $request_headers,
            'wordpress_plugin_version' => SMALK_AI_WORDPRESS_PLUGIN_VERSION,
            'timestamp' => time(),
            'unique_id' => uniqid('smalk_', true),
            'server_tracking' => true
        );

        // Send asynchronous non-blocking request
        $tracking_url = class_exists('Smalk_API') ? Smalk_API::get_tracking_url() : 'https://api.smalk.ai/api/v1/tracking/visit/';

        error_log('[Smalk Analytics] Sending async tracking request to: ' . $tracking_url);

        // IMPORTANT: 'blocking' => false makes this asynchronous
        // WordPress will send the request and immediately continue without waiting for response
        wp_remote_post($tracking_url, array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 0.5, // Reduced timeout since we don't wait for response anyway
            'blocking' => false, // Asynchronous - don't wait for response
            'sslverify' => false, // Disable SSL verification for local development
            'user-agent' => 'Smalk-Analytics/' . SMALK_AI_WORDPRESS_PLUGIN_VERSION
        ));
    }
}

// Hook to 'init' for better cache bypass
add_action('init', 'smalk_send_visit_request', 1);

// Client Analytics - Dynamic Script Loading (loads tracker.js which handles all browser-side tracking)

function smalk_add_analytics_script_tag() {
    if (!smalk_is_analytics_enabled_and_allowed()) {
        return;
    }
    
    $project_id = smalk_get_user_analytics_script_tag();
    if (empty($project_id)) {
        return;
    }

    // Enhanced dynamic script loading with better execution handling
    ?>
    <script type="text/javascript">
    /* Smalk AI Agent Analytics - Enhanced Dynamic Loading */
    (function() {
        // Check if already loaded to prevent duplicates
        if (window.smalkAnalyticsLoaded) {
            return;
        }
        window.smalkAnalyticsLoaded = true;
        
        // Create script element
        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.defer = false;
        script.src = '<?php echo esc_js(class_exists('Smalk_API') ? Smalk_API::get_tracker_js_url() : 'https://api.smalk.ai/tracker.js'); ?>?PROJECT_KEY=<?php echo esc_js($project_id); ?>&ver=<?php echo esc_js(SMALK_AI_WORDPRESS_PLUGIN_VERSION); ?>';
        script.id = 'smalk-analytics-dynamic';
        
        // Add attributes to prevent caching/minification
        script.setAttribute('data-no-minify', '1');
        script.setAttribute('data-cfasync', 'false');
        script.setAttribute('data-no-optimize', '1');
        script.setAttribute('data-skip-minification', '1');
        
        // Enhanced error and load handling
        script.onload = function() {
            window.smalkTrackerLoaded = true;
        };
        
        script.onerror = function() {
            // Fallback: try loading without cache busting
            var fallbackScript = document.createElement('script');
            fallbackScript.src = '<?php echo esc_js(class_exists('Smalk_API') ? Smalk_API::get_tracker_js_url() : 'https://api.smalk.ai/tracker.js'); ?>?PROJECT_KEY=<?php echo esc_js($project_id); ?>&ver=<?php echo esc_js(SMALK_AI_WORDPRESS_PLUGIN_VERSION); ?>';
            fallbackScript.async = true;
            document.head.appendChild(fallbackScript);
        };
        
        // Enhanced loading strategy
        function loadScript() {
            var head = document.getElementsByTagName('head')[0];
            if (head) {
                head.appendChild(script);
            } else {
                // Wait for head to be available
                var checkHead = setInterval(function() {
                    var head = document.getElementsByTagName('head')[0];
                    if (head) {
                        clearInterval(checkHead);
                        head.appendChild(script);
                    }
                }, 10);
            }
        }
        
        // Load immediately or wait for DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadScript);
        } else {
            loadScript();
        }
        
        // Additional fallback for very slow loading
        setTimeout(function() {
            if (!document.getElementById('smalk-analytics-dynamic')) {
                loadScript();
            }
        }, 2000);
        
    })();
    </script>
    <?php
}
add_action('wp_head', 'smalk_add_analytics_script_tag', 999);

// Alternative method: Traditional script enqueue as backup
function smalk_add_analytics_script_backup() {
    if (!smalk_is_analytics_enabled_and_allowed()) {
        return;
    }
    
    $project_id = smalk_get_user_analytics_script_tag();
    if (empty($project_id)) {
        return;
    }

    // Backup method using wp_enqueue_script with anti-cache measures
    $tracker_js_url = class_exists('Smalk_API') ? Smalk_API::get_tracker_js_url() : 'https://api.smalk.ai/tracker.js';
    $script_url = "{$tracker_js_url}?PROJECT_KEY={$project_id}&ver=" . SMALK_AI_WORDPRESS_PLUGIN_VERSION;
    
    wp_register_script(
        'smalk-analytics-backup',
        $script_url,
        array(),
        SMALK_AI_WORDPRESS_PLUGIN_VERSION,
        false // Load in head
    );
    
    // Add multiple data attributes to prevent minification
    wp_script_add_data('smalk-analytics-backup', 'data-no-minify', '1');
    wp_script_add_data('smalk-analytics-backup', 'data-cfasync', 'false');
    wp_script_add_data('smalk-analytics-backup', 'data-no-optimize', '1');
    wp_script_add_data('smalk-analytics-backup', 'data-skip-minification', '1');
    
    wp_enqueue_script('smalk-analytics-backup');
    
    // Add inline script to initialize if needed
    wp_add_inline_script('smalk-analytics-backup', 
        '/* Smalk Analytics Backup Method */ 
        console.log("Smalk Analytics: Backup script loaded");
        if (typeof window.smalkInit === "function") { 
            try { window.smalkInit(); } catch(e) { console.warn("Smalk init error:", e); } 
        }', 
        'after'
    );
}
// Uncomment the line below to enable backup method
// add_action('wp_enqueue_scripts', 'smalk_add_analytics_script_backup', 999);

// SOLUTION 3: Cache Plugin Exclusions
function smalk_exclude_from_cache_plugins() {
    // Exclude from WP Rocket minification
    if (function_exists('rocket_exclude_js')) {
        rocket_exclude_js(array('api.smalk.ai/tracker.js'));
    }
    
    // WP Rocket - exclude from minification via filter
    add_filter('rocket_exclude_js', function($excluded_js) {
        $excluded_js[] = 'api.smalk.ai/tracker.js';
        $excluded_js[] = 'smalk-analytics';
        return $excluded_js;
    });
    
    // Exclude from Autoptimize
    add_filter('autoptimize_filter_js_exclude', function($exclude) {
        return $exclude . ', api.smalk.ai/tracker.js, smalk-analytics';
    });
    
    // Exclude from W3 Total Cache
    add_filter('w3tc_minify_js_do_tag_minification', function($do_tag_minification, $script_tag) {
        if (strpos($script_tag, 'api.smalk.ai/tracker.js') !== false || 
            strpos($script_tag, 'smalk-analytics') !== false ||
            strpos($script_tag, 'data-no-minify') !== false) {
            return false;
        }
        return $do_tag_minification;
    }, 10, 2);
    
    // LiteSpeed Cache exclusions
    add_filter('litespeed_optimize_js_excludes', function($excludes) {
        $excludes[] = 'api.smalk.ai/tracker.js';
        $excludes[] = 'smalk-analytics';
        return $excludes;
    });
    
    // WP Fastest Cache exclusions
    add_filter('wpfc_exclude_current_page', function($exclude) {
        if (strpos($_SERVER['REQUEST_URI'], 'smalk') !== false) {
            return true;
        }
        return $exclude;
    });
    
    // Exclude from WP Super Cache for pages with smalk tracking
    if (function_exists('wp_cache_no_cache_for_me')) {
        add_action('wp_head', function() {
            if (smalk_is_analytics_enabled_and_allowed()) {
                wp_cache_no_cache_for_me();
            }
        }, 1);
    }
    
    // SG Optimizer exclusions
    add_filter('sgo_js_minify_exclude', function($exclude_list) {
        $exclude_list[] = 'api.smalk.ai/tracker.js';
        $exclude_list[] = 'smalk-analytics';
        return $exclude_list;
    });
    
    // Hummingbird exclusions
    add_filter('wp_hummingbird_is_minify_excluded_url', function($excluded, $url) {
        if (strpos($url, 'api.smalk.ai/tracker.js') !== false) {
            return true;
        }
        return $excluded;
    }, 10, 2);
    
    // Flying Press exclusions
    add_filter('flying_press_exclude_js', function($excludes) {
        $excludes[] = 'api.smalk.ai/tracker.js';
        return $excludes;
    });
    
    // Asset CleanUp exclusions
    add_filter('wpacu_skip_assets_settings_call', function($skip, $data) {
        if (isset($data['handle']) && strpos($data['handle'], 'smalk') !== false) {
            return true;
        }
        return $skip;
    }, 10, 2);
}
add_action('init', 'smalk_exclude_from_cache_plugins', 1);

// Additional cache bypass for specific cache plugins
function smalk_additional_cache_bypass() {
    // Define no-cache constants for various plugins
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!defined('DONOTCACHEOBJECT')) {
        define('DONOTCACHEOBJECT', true);
    }
    if (!defined('DONOTCACHEDB')) {
        define('DONOTCACHEDB', true);
    }
    
    // Set headers to prevent caching of tracking requests
    if (smalk_is_analytics_enabled_and_allowed()) {
        add_action('send_headers', function() {
            if (!headers_sent()) {
                header('Cache-Control: no-cache, no-store, must-revalidate', false);
                header('Pragma: no-cache', false);
                header('Expires: 0', false);
            }
        });
    }
}
add_action('template_redirect', 'smalk_additional_cache_bypass', 1);

// Force script attributes to prevent minification
function smalk_add_script_attributes($tag, $handle, $src) {
    // Add attributes to prevent caching and minification
    if (strpos($handle, 'smalk') !== false || strpos($src, 'api.smalk.ai') !== false) {
        $tag = str_replace('<script', '<script data-no-minify="1" data-cfasync="false" data-no-optimize="1"', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'smalk_add_script_attributes', 10, 3);

// Helpers (keeping your original functions)
function smalk_get_request_headers() {
    $header_names = [
        'User-Agent',
        'Sec-Ch-Ua',
        'Sec-Ch-Ua-Platform',
        'Referer',
        'Origin',
        'From',
        'Accept-Language',
        'Content-Language',
        'X-Country-Code',
        'CF-IPCountry',
        'X-Geo-Country',
        'X-Geo-City',
        'X-Geo-Region',
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
        'Connection',
        'Via'
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
            return sanitize_text_field($headers_with_lowercase_keys[$lowercased_header_name]);
        }
    }
    return null;
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