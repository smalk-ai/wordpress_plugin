<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Registration

function smalk_register_settings() {
    // Define settings arguments as constants
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
        'sanitize_callback' => 'smalk_sanitize_checkbox',
        'show_in_rest' => false,
        'default' => '1'
    ));

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
}

function smalk_sanitize_checkbox($input) {
    return isset($input) ? '1' : '0';
}

add_action('admin_init', 'smalk_register_settings');

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
        'smalk_page',
        'data:image/svg+xml;base64,' . $logo_data
    );
}

add_action('admin_menu', 'smalk_menu');

// Enqueue CSS for Admin Page
function smalk_admin_enqueue_scripts($hook) {
    // Only load on this plugin’s admin page.
    if ( $hook !== 'toplevel_page_smalk-ai' ) {
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

// Settings Page

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
                            <input type="text"
                                placeholder="Paste your project's access token here"
                                id="<?php echo esc_attr(SMALK_AI_ACCESS_TOKEN); ?>" 
                                name="<?php echo esc_attr(SMALK_AI_ACCESS_TOKEN); ?>" 
                                value="<?php echo esc_attr(get_option(SMALK_AI_ACCESS_TOKEN, '')); ?>"
                            />
                            <p>Create &amp; Copy your API Key from your Smalk AI project's settings page (Settings -&gt; API Keys).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <div class="table-header-step-number-label">Step 3:</div>
                            <div class="table-header-step-text-label">Set Up AI Agent Analytics</div>
                        </th>
                        <td>
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>"
                                name="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>"
                                <?php checked(get_option(SMALK_AI_IS_ANALYTICS_ENABLED, '1') == '1'); ?>
                                value="1"
                            />
                            <label for="<?php echo esc_attr(SMALK_AI_IS_ANALYTICS_ENABLED); ?>">Enable Agent Analytics</label><br>
                            <p>
                                Track the activity of all known AI agents crawling your website and Users coming from AI Search Engines. Insights will appear on your Smalk AI Dashboard. Visit our website for more 
                                <a href="https://www.smalk.ai/" target="_blank">infos</a>.
                            </p>
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
        </div>
    </div>
    <?php
}