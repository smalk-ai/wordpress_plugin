<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Registration

// First, define the sanitization function
function smalk_sanitize_checkbox($input) {
    return $input === '1' ? '1' : '0';
}

function smalk_register_settings() {
    // Then define settings arguments
    define('SMALK_AI_ACCESS_TOKEN_ARGS', array(
        'type' => 'string',
        'group' => SMALK_AI_SETTINGS_GROUP,
        'description' => 'Smalk AI Access Token',
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest' => false,
        'default' => ''
    ));

    define('SMALK_AI_ANALYTICS_ENABLED_ARGS', array(
        'type' => 'boolean',
        'group' => SMALK_AI_SETTINGS_GROUP,
        'description' => 'Enable/Disable Analytics',
        'sanitize_callback' => 'smalk_sanitize_checkbox',  // Now the function exists
        'show_in_rest' => false,
        'default' => '1'
    ));

    // Register settings...
    register_setting(
        SMALK_AI_SETTINGS_GROUP,
        SMALK_AI_ACCESS_TOKEN,
        SMALK_AI_ACCESS_TOKEN_ARGS
    );

    register_setting(
        SMALK_AI_SETTINGS_GROUP,
        SMALK_AI_IS_ANALYTICS_ENABLED,
        SMALK_AI_ANALYTICS_ENABLED_ARGS
    );

    // Workspace info settings
    register_setting(
        SMALK_AI_SETTINGS_GROUP,
        'smalk_ai_workspace_key',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        )
    );

    register_setting(
        SMALK_AI_SETTINGS_GROUP,
        'smalk_ai_workspace_name',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        )
    );

    register_setting(
        SMALK_AI_SETTINGS_GROUP,
        'smalk_ai_publisher_activated',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        )
    );
}

// Move this outside the function
if (get_option(SMALK_AI_IS_ANALYTICS_ENABLED) === false) {
    add_option(SMALK_AI_IS_ANALYTICS_ENABLED, '1');
}

/**
 * Fetch workspace info from Smalk API.
 *
 * @param string $api_key The API key.
 * @return array|null Workspace info or NULL on failure.
 */
function smalk_fetch_workspace_info($api_key) {
    $api_url = Smalk_API::get_projects_url();
    error_log('[Smalk] Calling API: ' . $api_url);

    $response = wp_remote_get($api_url, array(
        'timeout' => 5,
        'headers' => array(
            'Authorization' => 'Api-Key ' . $api_key,
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        error_log('[Smalk] API error: ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    error_log('[Smalk] API response - Status: ' . $status_code . ', Body: ' . substr($body, 0, 500));

    $data = json_decode($body, true);

    if ($status_code === 200 && is_array($data) && !empty($data)) {
        // API returns array of projects, use first one
        $project_data = isset($data[0]) ? $data[0] : $data;

        if (isset($project_data['id'])) {
            error_log('[Smalk] Successfully parsed project data: ' . $project_data['name']);
            return array(
                'key' => $project_data['id'],
                'name' => $project_data['name'] ?? '',
                'publisher_activated' => $project_data['publisher_ads_enabled'] ?? false,
            );
        }
    }

    error_log('[Smalk] API returned invalid response. Status: ' . $status_code . ', Data type: ' . gettype($data));
    return null;
}

/**
 * Mask an API key for display.
 *
 * @param string $api_key The API key to mask.
 * @return string Masked API key.
 */
function smalk_mask_api_key($api_key) {
    $length = strlen($api_key);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }

    $masked_part = str_repeat('*', $length - 4);
    $visible_part = substr($api_key, -4);

    return $masked_part . $visible_part;
}

add_action('admin_init', 'smalk_register_settings');

/**
 * Validate API key and fetch workspace info when API key is updated.
 */
function smalk_fetch_workspace_on_api_key_update($old_value, $new_value, $option) {
    // Skip if API key hasn't changed or is empty
    if ($old_value === $new_value || empty($new_value)) {
        error_log('[Smalk] API key unchanged or empty, skipping workspace fetch');
        return;
    }

    error_log('[Smalk] API key updated, fetching workspace info');
    smalk_fetch_and_save_workspace_info($new_value);
}
add_action('update_option_' . SMALK_AI_ACCESS_TOKEN, 'smalk_fetch_workspace_on_api_key_update', 10, 3);

/**
 * Fetch workspace info on first save (when option is added for first time).
 */
function smalk_fetch_workspace_on_first_save($option, $value) {
    if (empty($value)) {
        return;
    }

    error_log('[Smalk] API key added for first time, fetching workspace info');
    smalk_fetch_and_save_workspace_info($value);
}
add_action('add_option_' . SMALK_AI_ACCESS_TOKEN, 'smalk_fetch_workspace_on_first_save', 10, 2);

/**
 * Fetch and save workspace info from API.
 *
 * @param string $api_key The API key.
 */
function smalk_fetch_and_save_workspace_info($api_key) {
    error_log('[Smalk] Attempting to fetch workspace info with API key: ' . substr($api_key, 0, 10) . '...');
    $workspace_info = smalk_fetch_workspace_info($api_key);

    if ($workspace_info !== null) {
        error_log('[Smalk] Workspace info fetched successfully: ' . print_r($workspace_info, true));

        // Save workspace info for Analytics plugin
        update_option('smalk_ai_workspace_key', $workspace_info['key']);
        update_option('smalk_ai_workspace_name', $workspace_info['name']);
        update_option('smalk_ai_publisher_activated', $workspace_info['publisher_activated']);

        // Also save project key for Ads Pro plugin compatibility
        update_option('smalk_ai_project_key', $workspace_info['key']);

        error_log('[Smalk] Workspace options updated - key: ' . $workspace_info['key']);

        // Set a transient to show success message on next page load
        set_transient('smalk_workspace_fetch_success', $workspace_info['name'], 30);
    } else {
        error_log('[Smalk] Failed to fetch workspace info - API returned null');

        // Set a transient to show error message on next page load
        set_transient('smalk_workspace_fetch_error', true, 30);
    }
}

/**
 * Display workspace fetch messages and refresh workspace info if needed.
 */
function smalk_display_workspace_messages() {
    // Check if we're on the settings page and settings were just updated
    if (isset($_GET['page']) && $_GET['page'] === 'smalk-ai-analytics' && isset($_GET['settings-updated'])) {
        $api_key = get_option(SMALK_AI_ACCESS_TOKEN, '');
        $workspace_key = get_option('smalk_ai_workspace_key', '');

        // If we have an API key but no workspace key, fetch it now
        if (!empty($api_key) && empty($workspace_key)) {
            error_log('[Smalk] Settings saved with API key but no workspace - fetching now');
            smalk_fetch_and_save_workspace_info($api_key);
        }
    }

    $workspace_name = get_transient('smalk_workspace_fetch_success');
    if ($workspace_name) {
        delete_transient('smalk_workspace_fetch_success');
        add_settings_error(
            SMALK_AI_SETTINGS_GROUP,
            'smalk_workspace_fetched',
            'Workspace info fetched successfully: ' . esc_html($workspace_name),
            'success'
        );
    }

    if (get_transient('smalk_workspace_fetch_error')) {
        delete_transient('smalk_workspace_fetch_error');
        add_settings_error(
            SMALK_AI_SETTINGS_GROUP,
            'smalk_api_error',
            'Could not connect to Smalk API at ' . Smalk_API::get_base_url() . '. Please check your API key and ensure your local backend is running.',
            'error'
        );
    }
}
add_action('admin_notices', 'smalk_display_workspace_messages');

// Menu Item

function smalk_menu() {
    // Ensure the logo file exists to avoid weird characters in the menu icon.
    $logo_data = '';
    if ( file_exists(SMALK_AI_LOGO_PATH) ) {
        $logo_data = base64_encode(file_get_contents(SMALK_AI_LOGO_PATH));
    }
    
    add_menu_page(
        'AI Analytics',
        'AI Analytics',
        'manage_options',
        'smalk-ai',
        'smalk_overview_page',
        'data:image/svg+xml;base64,' . $logo_data
    );

    // Keep a visible "Overview" entry (same slug as parent) for clarity.
    add_submenu_page(
        'smalk-ai',
        'Overview',
        'Overview',
        'manage_options',
        'smalk-ai',
        'smalk_overview_page'
    );

    // Existing Analytics settings screen as its own submenu.
    add_submenu_page(
        'smalk-ai',
        'AI Analytics',
        'AI Analytics',
        'manage_options',
        'smalk-ai-analytics',
        'smalk_page'
    );
}

add_action('admin_menu', 'smalk_menu');

// Enqueue CSS for Admin Page
function smalk_admin_enqueue_scripts($hook) {
    // Only load on this plugin’s admin pages (Overview + AI Analytics).
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if (!in_array($page, array('smalk-ai', 'smalk-ai-analytics'), true)) {
        return;
    }
    wp_enqueue_style(
        'smalk-admin-style',
        plugin_dir_url( dirname(__FILE__) ) . 'css/admin-styles.css',
        array(),
        SMALK_AI_WORDPRESS_PLUGIN_VERSION
    );
}
add_action('admin_enqueue_scripts', 'smalk_admin_enqueue_scripts');

// Overview Page

function smalk_overview_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden');
    }

    // Detect AI Search Ads child plugin status
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $ads_plugin_file = 'smalk-ai-ads-pro/smalk-ai-ads-pro.php';
    $ads_installed   = file_exists(WP_PLUGIN_DIR . '/' . $ads_plugin_file);
    $ads_active      = function_exists('is_plugin_active') ? is_plugin_active($ads_plugin_file) : false;

    // Card CTAs
    $analytics_href  = admin_url('admin.php?page=smalk-ai-analytics');

    if ($ads_active) {
        $ads_btn_label = 'View AI Search Ads 👉';
        $ads_btn_href  = admin_url('admin.php?page=smalk-ai-ads');
    } elseif ($ads_installed) {
        $ads_btn_label = 'Activate AI Search Ads plugin 👉';
        $ads_btn_href  = wp_nonce_url(
            admin_url('plugins.php?action=activate&plugin=' . rawurlencode($ads_plugin_file)),
            'activate-plugin_' . $ads_plugin_file
        );
    } else {
        $ads_btn_label = 'Install AI Search Ads plugin 👉';
        $ads_btn_href  = admin_url('plugin-install.php');
    }

    ?>
    <div class="wrap">
        <h1 class="fake-header"></h1>
        <div class="container smalk-overview-container">
            <div class="smalk-overview-hero">
                <h1>Welcome to Smalk AI — AI Search Visibility</h1>
                <p class="smalk-overview-tagline">
                    Smalk AI helps brands analyze and boost their visibility in AI Search and answer engines, while helping publishers and content creators unlock new revenue streams from AI-driven content consumption.
                </p>
            </div>

            <div class="smalk-overview-cards">
                <div class="smalk-overview-card">
                    <h2>AI Analytics</h2>
                    <p>
                        Track &amp; monitor AI agent traffic on your pages, and discover the most popular pages for AI agents — including the ones that drive human visits.
                    </p>
                    <a class="smalk-cta-btn" href="<?php echo esc_url($analytics_href); ?>">
                        View AI Analytics 👉
                    </a>
                </div>

                <div class="smalk-overview-card">
                    <h2>AI Search Ads</h2>
                    <p>
                        If you&rsquo;re a publisher or content creator who wants to monetize content used by AI agents, activate this feature.
                    </p>
                    <a class="smalk-cta-btn" href="<?php echo esc_url($ads_btn_href); ?>">
                        <?php echo esc_html($ads_btn_label); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Settings Page (AI Analytics)

function smalk_page() {
    ?>
    <div class="wrap">
        <h1 class="fake-header"></h1>
        <div class="container">
            <div class="header-container">
                <?php 
                $logo_url = SMALK_AI_LOGO_URL;
                $logo_filename = basename($logo_url);
                
                // First try to find existing attachment by filename
                $args = array(
                    'post_type' => 'attachment',
                    'post_status' => 'inherit',
                    'posts_per_page' => 1,
                    'title' => pathinfo($logo_filename, PATHINFO_FILENAME) // Remove extension
                );
                
                $existing_attachment = get_posts($args);
                $attachment_id = !empty($existing_attachment) ? $existing_attachment[0]->ID : attachment_url_to_postid(esc_url($logo_url));
                
                if ($attachment_id) {
                    echo wp_get_attachment_image(
                        $attachment_id,
                        array(50, 50),
                        false,
                        array(
                            'style' => 'height: 2rem; width: auto;',
                            'alt' => 'Smalk AI Logo',
                            'width' => '50',
                            'height' => '50'
                        )
                    );
                } else {
                    // If not in media library, create a temporary attachment
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    
                    // Download file to temp location
                    $tmp = download_url(esc_url($logo_url));
                    
                    if (!is_wp_error($tmp)) {
                        $file_array = array(
                            'name' => $logo_filename,
                            'tmp_name' => $tmp
                        );
                        
                        // Create the attachment only if it doesn't exist
                        $attachment_id = media_handle_sideload($file_array, 0);
                        
                        if (!is_wp_error($attachment_id)) {
                            echo wp_get_attachment_image(
                                $attachment_id,
                                array(50, 50),
                                false,
                                array(
                                    'style' => 'height: 2rem; width: auto;',
                                    'alt' => 'Smalk AI Logo',
                                    'width' => '50',
                                    'height' => '50'
                                )
                            );
                        }
                        
                        // Clean up temp file
                        wp_delete_file($tmp);
                    }
                }
                ?>
                                <h1>Smalk AI Agent Analytics</h1>
                <a href="https://www.smalk.ai" target="_blank">Go to the Smalk AI Website</a>
            </div>
            <p>Get real-time analytics on AI agents and human visitors from AI Search, and control your brand visibility on Answer Engines (ChatGPT, Perplexity, etc.).</p>
            <h2>Configuration</h2>
            <?php settings_errors(SMALK_AI_SETTINGS_GROUP); ?>
            <form method="post" action="options.php" class="smalk-form">
                <?php settings_fields(SMALK_AI_SETTINGS_GROUP); ?>
                <table>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-number-label">Step 1:</div>
                            <div class="table-header-step-text-label">Get Started</div>
                        </th>
                        <td>
                            <p>
                                <a href="https://app.smalk.ai/" target="_blank">Sign up</a> for Smalk AI Agent Analytics and create a new project for this website. This will take less than 30 seconds.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-number-label">Step 2:</div>
                            <div class="table-header-step-text-label">Connect Your Project</div>
                        </th>
                        <td>
                            <?php
                            $existing_api_key = get_option(SMALK_AI_ACCESS_TOKEN, '');
                            $has_api_key = !empty($existing_api_key);

                            if ($has_api_key) {
                                $masked_key = smalk_mask_api_key($existing_api_key);
                                ?>
                                <div style="margin-bottom: 15px;">
                                    <strong>Current API Key:</strong><br>
                                    <code style="background: #f5f5f5; padding: 8px 12px; border-radius: 4px; display: inline-block; font-size: 14px; letter-spacing: 1px; margin-top: 5px;">
                                        <?php echo esc_html($masked_key); ?>
                                    </code>
                                    <p class="description">Your API key is saved. Enter a new key below to update it.</p>
                                </div>
                                <?php
                            }
                            ?>
                            <input
                                type="text"
                                placeholder="<?php echo $has_api_key ? 'Leave empty to keep current key, or enter new key to update' : 'Paste your project\'s access token here'; ?>"
                                id="<?php echo esc_attr(SMALK_AI_ACCESS_TOKEN); ?>"
                                name="<?php echo esc_attr(SMALK_AI_ACCESS_TOKEN); ?>"
                                value=""
                                style="width: 100%;"
                            />
                            <p>Create &amp; Copy your API Key from your Smalk AI project's settings page (Settings &rarr; API Keys).</p>
                        </td>
                    </tr>
                    <?php
                    // Show workspace info and status if API key is configured
                    $workspace_name = get_option('smalk_ai_workspace_name', '');
                    $workspace_key = get_option('smalk_ai_workspace_key', '');
                    $publisher_activated = get_option('smalk_ai_publisher_activated', false);

                    if ($has_api_key) :
                    ?>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-text-label">Status</div>
                        </th>
                        <td>
                            <ul style="list-style: none; padding-left: 0; margin: 0;">
                                <li style="margin-bottom: 8px;">
                                    <span style="color: green; font-size: 16px;">✓</span>
                                    <strong>API Key:</strong> Configured
                                </li>
                                <?php if (!empty($workspace_key)) : ?>
                                    <li style="margin-bottom: 8px;">
                                        <span style="color: green; font-size: 16px;">✓</span>
                                        <strong>Workspace Key:</strong> Configured
                                    </li>
                                <?php else : ?>
                                    <li style="margin-bottom: 8px;">
                                        <span style="color: orange; font-size: 16px;">⚠</span>
                                        <strong>Workspace Key:</strong> Missing (save settings to fetch)
                                    </li>
                                <?php endif; ?>
                                <?php if ($has_api_key && get_option(SMALK_AI_IS_ANALYTICS_ENABLED, '1') === '1') : ?>
                                    <li style="margin-bottom: 8px;">
                                        <span style="color: green; font-size: 16px;">✓</span>
                                        <strong>Tracking:</strong> Active
                                    </li>
                                <?php else : ?>
                                    <li style="margin-bottom: 8px;">
                                        <span style="color: orange; font-size: 16px;">⚠</span>
                                        <strong>Tracking:</strong> Disabled (enable below)
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </td>
                    </tr>
                    <?php if (!empty($workspace_name)) : ?>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-text-label">Workspace Info</div>
                        </th>
                        <td style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
                            <p style="margin: 0 0 10px 0;">
                                <strong>Workspace:</strong> <?php echo esc_html($workspace_name); ?>
                            </p>
                            <p style="margin: 0;">
                                <strong>Publisher Status:</strong>
                                <?php if ($publisher_activated) : ?>
                                    <span style="color: green;">✓ Active</span>
                                <?php else : ?>
                                    <span style="color: orange;">⚠ Not activated</span>
                                    <br><span class="description">Activate Publisher in your Smalk Dashboard to enable ad injection.</span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php elseif (empty($existing_api_key)) : ?>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <p class="description" style="color: orange;">
                                Configure your API Key above and save to fetch workspace information.
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-number-label">Step 3:</div>
                            <div class="table-header-step-text-label">Set Up AI Agent Analytics</div>
                        </th>
                        <td>
                        <input
                        type="hidden"
                        name="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>"
                        value="0"
                        />
                        <input
                            type="checkbox"
                            id="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>"
                            name="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>"
                            <?php checked(get_option(SMALK_AI_IS_ANALYTICS_ENABLED, '1') === '1'); ?>
                            value="1"
                        />
                            <label for="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>">
                                Enable Agent Analytics
                            </label>
                            <p>
                                Track the activity of all known AI agents crawling your website and Users coming from AI Search Engines. 
                                Insights will appear on your Smalk AI Dashboard. 
                                Visit our website for more 
                                <a href="https://www.smalk.ai/" target="_blank">infos</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-number-label">Need Support?</div>
                            <div class="table-header-step-text-label">Debug Information</div>
                        </th>
                        <td>
                            <button type="button" id="smalk-debug-report" class="button button-secondary">
                                Send Debug Report
                            </button>
                            <p>
                                Need help? Click the debug report button above to collect system information that will help us assist you better.
                            </p>
                            
                            <!-- Modal -->
                            <div id="smalk-debug-modal" class="smalk-modal" style="display: none;">
                                <div class="smalk-modal-content">
                                    <span class="smalk-close">&times;</span>
                                    <h3>Debug Information</h3>
                                    <div class="smalk-debug-container">
                                        <textarea 
                                            id="smalk-debug-text" 
                                            readonly 
                                            style="width: 100%; height: 200px; margin-bottom: 10px;"
                                        ></textarea>
                                        <button type="button" id="smalk-download-debug" class="button button-primary">
                                            Download Debug Report
                                        </button>
                                    </div>
                                    <p style="margin-top: 15px;">
                                        Please send an email to <strong>hey@smalk.ai</strong> with:<br>
                                        Subject: WordPress Issue<br>
                                        Content: Attach the downloaded debug report file
                                    </p>
                                </div>
                            </div>

                            <style>
                                .smalk-modal {
                                    position: fixed;
                                    top: 0;
                                    left: 0;
                                    width: 100%;
                                    height: 100%;
                                    background: rgba(0,0,0,0.6);
                                    z-index: 999999;
                                }
                                .smalk-modal-content {
                                    position: relative;
                                    background: #fff;
                                    margin: 5% auto;
                                    padding: 20px;
                                    width: 70%;
                                    max-width: 600px;
                                    border-radius: 5px;
                                }
                                .smalk-close {
                                    position: absolute;
                                    right: 10px;
                                    top: 10px;
                                    font-size: 24px;
                                    cursor: pointer;
                                }
                            </style>

                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const debugBtn = document.getElementById('smalk-debug-report');
                                const modal = document.getElementById('smalk-debug-modal');
                                const closeBtn = document.querySelector('.smalk-close');
                                const downloadBtn = document.getElementById('smalk-download-debug');
                                const debugText = document.getElementById('smalk-debug-text');

                                debugBtn.addEventListener('click', function() {
                                    // Gather debug information
                                    const debugInfo = {
                                        phpVersion: '<?php echo esc_js( phpversion() ); ?>',
                                        wpVersion: '<?php echo esc_js( get_bloginfo("version") ); ?>',
                                        theme: '<?php echo esc_js( wp_get_theme()->get("Name") ); ?>',
                                        plugins: <?php 
                                            if ( ! function_exists('get_plugin_data') ) {
                                                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                                            }
                                            $plugins_list = array();
                                            $active_plugins = get_option('active_plugins', array());
                                            foreach( $active_plugins as $plugin_path ) {
                                                $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
                                                $plugins_list[] = $plugin_data['Name'] . ' (' . $plugin_data['Version'] . ')';
                                            }
                                            echo json_encode($plugins_list);
                                        ?>,
                                        errorLog: <?php
                                            // Attempt to load PHP error log if available
                                            $error_log = '';
                                            $log_file  = ini_get('error_log');
                                            if ( ! empty($log_file) && file_exists($log_file) && is_readable($log_file) ) {
                                                $error_log = file_get_contents($log_file);
                                            }
                                            echo json_encode($error_log);
                                        ?>
                                    };

                                    // Format debug information
                                    const debugOutput = `Debug Report
==================
PHP Version: ${debugInfo.phpVersion}
WordPress Version: ${debugInfo.wpVersion}
Active Theme: ${debugInfo.theme}

Active Plugins:
${debugInfo.plugins.join("\n")}

Error Log:
${debugInfo.errorLog || 'No errors found'}`;

                                    debugText.value = debugOutput;
                                    modal.style.display = 'block';
                                });

                                downloadBtn.addEventListener('click', function() {
                                    // Create blob from debug text
                                    const blob = new Blob([debugText.value], { type: 'text/plain' });
                                    
                                    // Create download link
                                    const url = window.URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    
                                    // Generate filename with timestamp
                                    const date = new Date();
                                    const timestamp = date.toISOString().replace(/[:.]/g, '-');
                                    const filename = `smalk-debug-report-${timestamp}.txt`;
                                    
                                    a.href = url;
                                    a.download = filename;
                                    
                                    // Trigger download
                                    document.body.appendChild(a);
                                    a.click();
                                    
                                    // Cleanup
                                    window.URL.revokeObjectURL(url);
                                    document.body.removeChild(a);
                                    
                                    // Show feedback
                                    downloadBtn.textContent = 'Downloaded!';
                                    setTimeout(() => {
                                        downloadBtn.textContent = 'Download Debug Report';
                                    }, 2000);
                                });

                                closeBtn.addEventListener('click', function() {
                                    modal.style.display = 'none';
                                });

                                window.addEventListener('click', function(event) {
                                    if (event.target === modal) {
                                        modal.style.display = 'none';
                                    }
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </table>
                <!-- Added button below the table -->
                <a 
                    href="https://app.smalk.ai/" 
                    target="_blank" 
                    style="
                        display: inline-block; 
                        width: 100%; 
                        min-height: 75px; 
                        background: #EADAEF; 
                        color: black; 
                        font-family: 'DM Sans', sans-serif; 
                        font-size: 16px; 
                        text-align: center; 
                        line-height: 75px; 
                        text-decoration: none;
                        margin-top: 1rem;
                        border-radius: 8px;
                        box-shadow: 8px 8px 12px rgba(0, 0, 0, 0.1);
                    "
                >
                    Go to your Smalk Dashboard 👉
                </a>

                <?php submit_button(); ?>
            </form>

        </div> <!-- /container (keep Analytics layout constrained) -->

        <?php
        /**
         * Ads settings are now available as a dedicated submenu page
         * to avoid form/save-button conflicts with the Analytics configuration.
         */
        ?>
        <div class="smalk-extensions-container">
            <hr />
            <div style="max-width: 40rem; margin-left: auto; margin-right: auto; text-align: center;">
                <h1 style="margin-top: 1rem;">Smalk AI Search Ads</h1>
                <p>Manage your AI Search Ads placements and syncing in the dedicated submenu for an independent interface.</p>
                <a
                    href="<?php echo esc_url(admin_url('admin.php?page=smalk-ai-ads')); ?>"
                    style="
                        display: inline-block;
                        width: 100%;
                        min-height: 75px;
                        background: #EADAEF;
                        color: black;
                        font-family: 'DM Sans', sans-serif;
                        font-size: 16px;
                        text-align: center;
                        line-height: 75px;
                        text-decoration: none;
                        margin-top: 1rem;
                        border-radius: 8px;
                        box-shadow: 8px 8px 12px rgba(0, 0, 0, 0.1);
                    "
                >
                    Open Smalk AI Search Ads Settings 👉
                </a>
            </div>
        </div>

    </div>
    <?php
}