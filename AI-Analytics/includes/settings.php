<?php

// Registration

function smalk_register_settings() {
    register_setting(SMALK_AI_SETTINGS_GROUP, SMALK_AI_ACCESS_TOKEN);
    register_setting(SMALK_AI_SETTINGS_GROUP, SMALK_AI_IS_ANALYTICS_ENABLED);
    // Removed settings related to blocking AI agents and enforcing robots.txt
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

// Settings Page

function smalk_page() {
    ?>
    <style>
        .fake-header {
            display: none;
        }
        .container {
            max-width: 40rem;
            margin-left: auto;
            margin-right: auto;
        }
        .header-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .header-container img {
            height: 2rem;
        }
        .header-container h1 {
            padding: 0;
        }
        .header-container a {
            margin-left: auto;
        }
        h1 {
            font-weight: bold !important;
        }
        h2 {
            font-weight: bold;
        }
        hr {
            border: none;
            height: 1px;
            background-color: rgba(0, 0, 0, 0.2);
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        input[type="text"] {
            width: 100%;
        }
        input[type="checkbox"]:disabled {
            border-color: revert;
            opacity: revert;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        table, th, td {
            border: 1px solid rgba(0, 0, 0, 0.2);
        }
        th, td {
            padding: 1rem;
        }
        th {
            background-color: rgba(0, 0, 0, 0.05);
        }
        td p {
            color: rgba(0, 0, 0, 0.5);
        }
        td p:first-child {
            margin-top: 0;
        }
        td p:last-child {
            margin-bottom: 0;
        }
        .table-header-step-number-label {
            margin-bottom: 0.5rem;
        }
        .table-header-step-text-label {
            font-weight: normal;
        }
    </style>
    <div class="wrap">
        <h1 class="fake-header"></h1>
        <div class="container">
            <div class="header-container">
                <img src="<?php echo esc_url(SMALK_AI_LOGO_URL); ?>">
                <h1>Smalk AI Agent Analytics</h1>
                <a href="https://www.smalk.ai" target="_blank">Go to the Smalk AI Website</a>
            </div>
            <p>Gain real-time insights into AI agents, crawlers, and scrapers accessing your website and manage your visibility on the AI Search Era.</p>
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
                                <a href="https://www.app.smalk.ai/login" target="_blank">Sign up</a> for Smalk AI Agent Analytics and create a new project for this website. This will take less than 30 seconds.
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
                            <p>Copy your API Key from your Smalk AI project's settings page.</p>
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
                    href="https://app.smalk.ai/login" 
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
