<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings and provider data
$settings = get_option('giga_apw_settings', []);
$current_provider = get_option('giga_ai_provider', 'claude');
$current_api_key = get_option('giga_ai_api_key', '');
$current_model = get_option('giga_ai_model', 'claude-sonnet-4-5');
$license_key = get_option('giga_apw_license_key', '');
$is_pro = Giga_APW_License::get_instance()->is_pro();
$monthly_remaining = Giga_APW_License::get_instance()->get_monthly_remaining();

// Define providers with their metadata
$providers = [
    'claude' => [
        'name' => 'Anthropic Claude',
        'type' => 'paid',
        'description' => 'Most advanced AI for complex reasoning'
    ],
    'openai' => [
        'name' => 'OpenAI',
        'type' => 'paid',
        'description' => 'Powerful AI with broad capabilities'
    ],
    'gemini' => [
        'name' => 'Google Gemini',
        'type' => 'paid',
        'description' => 'Google\'s advanced language model'
    ],
    'groq' => [
        'name' => 'Groq',
        'type' => 'paid',
        'description' => 'Ultra-fast AI processing'
    ],
    'ollama' => [
        'name' => 'Ollama',
        'type' => 'free',
        'description' => 'Local AI - no API key required'
    ]
];

// Get available models for current provider
$available_models = [];
if (class_exists('Giga_AI_Client')) {
    $client = Giga_AI_Client::get_instance();
    $available_models = $client->get_available_models();
}
?>

<div class="wrap giga-apw-wrap">
    <div class="giga-apw-header">
        <h1>Settings</h1>
        <div class="giga-apw-save-status">
            <span id="giga-apw-unsaved" class="giga-apw-unsaved-badge" style="display: none;">Unsaved changes</span>
        </div>
    </div>
    
    <div class="giga-apw-tabs">
        <button class="giga-apw-tab-button active" data-tab="ai-configuration">
            <span>⚙️</span> AI Configuration
        </button>
        <button class="giga-apw-tab-button" data-tab="generation-settings">
            <span>🎯</span> Generation Settings
        </button>
        <button class="giga-apw-tab-button" data-tab="content-preferences">
            <span>📝</span> Content Preferences
        </button>
        <button class="giga-apw-tab-button" data-tab="plan-status">
            <span>📊</span> Plan Status
        </button>
    </div>
    
    <form id="giga-apw-settings-form" class="giga-apw-form">
        <?php wp_nonce_field('giga_apw_save_settings', 'giga_apw_settings_nonce'); ?>
        
        <!-- AI Configuration Section -->
        <div id="ai-configuration" class="giga-apw-tab-content active">
            <div class="giga-apw-card">
                <h3>AI Provider Configuration</h3>
                <p class="giga-apw-help-text">Configure your AI provider and model settings for content generation</p>
                
                <div class="giga-apw-field">
                    <label for="giga_ai_provider">AI Provider</label>
                    <select id="giga_ai_provider" name="giga_ai_provider" class="giga-apw-select">
                        <?php foreach ($providers as $provider_key => $provider): ?>
                            <option value="<?php echo esc_attr($provider_key); ?>" 
                                    <?php selected($current_provider, $provider_key); ?>>
                                <?php echo esc_html($provider['name']); ?> 
                                (<?php echo esc_html($provider['type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="giga-apw-help-text">Choose your AI provider for content generation</p>
                </div>
                
                <?php if ($current_provider === 'ollama'): ?>
                    <div class="giga-apw-field">
                        <label for="giga_ollama_base_url">Base URL</label>
                        <input type="url" 
                               id="giga_ollama_base_url" 
                               name="giga_ollama_base_url" 
                               value="<?php echo esc_attr(get_option('giga_ollama_base_url', 'http://localhost:11434')); ?>" 
                               class="giga-apw-input"
                               placeholder="http://localhost:11434">
                        <p class="giga-apw-help-text">Default: http://localhost:11434</p>
                    </div>
                <?php else: ?>
                    <div class="giga-apw-field">
                        <label for="giga_ai_api_key">API Key</label>
                        <div class="giga-apw-input-group">
                            <input type="password" 
                                   id="giga_ai_api_key" 
                                   name="giga_ai_api_key" 
                                   value="<?php echo $current_api_key ? '********' : ''; ?>" 
                                   class="giga-apw-input"
                                   placeholder="Enter your API key">
                            <button type="button" class="giga-apw-toggle-password" data-show="false">
                                <span class="giga-apw-eye-icon">👁️</span>
                            </button>
                        </div>
                        <p class="giga-apw-help-text">
                            <?php 
                            switch ($current_provider) {
                                case 'claude': 
                                    echo 'Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>';
                                    break;
                                case 'openai': 
                                    echo 'Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a>';
                                    break;
                                case 'gemini': 
                                    echo 'Get your API key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>';
                                    break;
                                case 'groq': 
                                    echo 'Get your API key from <a href="https://console.groq.com/" target="_blank">Groq Console</a>';
                                    break;
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="giga-apw-field">
                    <label for="giga_ai_model">Model</label>
                    <select id="giga_ai_model" name="giga_ai_model" class="giga-apw-select">
                        <?php foreach ($available_models as $model_key => $model): ?>
                            <option value="<?php echo esc_attr($model_key); ?>" 
                                    <?php selected($current_model, $model_key); ?>>
                                <?php echo esc_html($model['label']); ?>
                                <?php if ($model_key === $current_model) echo ' ★'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="giga-apw-actions">
                    <button type="button" id="giga-apw-test-connection" class="giga-apw-button-primary">
                        <span class="giga-apw-btn-text">Test Connection</span>
                        <span class="giga-apw-spinner" style="display: none;">⏳</span>
                    </button>
                    <div id="giga-apw-connection-status" class="giga-apw-connection-status"></div>
                </div>
            </div>
        </div>
        
        <!-- Generation Settings Section -->
        <div id="generation-settings" class="giga-apw-tab-content">
            <div class="giga-apw-card">
                <h3>Generation Settings</h3>
                <p class="giga-apw-help-text">Configure how AI content is generated and processed</p>
                
                <div class="giga-apw-field">
                    <label for="giga_apw_settings[default_language]">Default Language</label>
                    <div class="giga-apw-select-wrapper">
                        <select id="giga_apw_settings[default_language]" name="giga_apw_settings[default_language]" class="giga-apw-select">
                            <option value="en" <?php selected($settings['default_language'] ?? 'en', 'en'); ?>>English</option>
                            <option value="es" <?php selected($settings['default_language'] ?? '', 'es'); ?>>Spanish</option>
                            <option value="fr" <?php selected($settings['default_language'] ?? '', 'fr'); ?>>French</option>
                            <option value="de" <?php selected($settings['default_language'] ?? '', 'de'); ?>>German</option>
                            <option value="bn" <?php selected($settings['default_language'] ?? '', 'bn'); ?>>Bengali</option>
                            <option value="ar" <?php selected($settings['default_language'] ?? '', 'ar'); ?>>Arabic</option>
                            <option value="hi" <?php selected($settings['default_language'] ?? '', 'hi'); ?>>Hindi</option>
                            <option value="pt" <?php selected($settings['default_language'] ?? '', 'pt'); ?>>Portuguese</option>
                        </select>
                    </div>
                </div>
                
                <div class="giga-apw-field">
                    <label>Default Tone</label>
                    <div class="giga-apw-tone-selector">
                        <?php $tones = ['Professional', 'Friendly', 'Persuasive', 'Casual', 'Luxury']; ?>
                        <?php foreach ($tones as $tone): ?>
                            <div class="giga-apw-tone-card <?php echo ($settings['default_tone'] ?? 'Professional') === $tone ? 'selected' : ''; ?>" 
                                 data-tone="<?php echo esc_attr($tone); ?>">
                                <span class="giga-apw-tone-icon"><?php echo get_tone_icon($tone); ?></span>
                                <span class="giga-apw-tone-label"><?php echo esc_html($tone); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <input type="hidden" id="giga_apw_settings[default_tone]" name="giga_apw_settings[default_tone]" 
                               value="<?php echo esc_attr($settings['default_tone'] ?? 'Professional'); ?>">
                    </div>
                </div>
                
                <div class="giga-apw-field">
                    <div class="giga-apw-toggle-container">
                        <label class="giga-apw-toggle-label">
                            <span>Require minimum quality score</span>
                            <div class="giga-apw-toggle-switch">
                                <input type="checkbox" id="giga_apw_settings[use_quality_gate]" 
                                       name="giga_apw_settings[use_quality_gate]" 
                                       value="1" 
                                       <?php checked($settings['use_quality_gate'] ?? 1, 1); ?>>
                                <span class="giga-apw-toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                    
                    <?php if ($settings['use_quality_gate'] ?? 1): ?>
                        <div class="giga-apw-quality-gate-settings">
                            <label for="giga_apw_settings[quality_gate_threshold]">Threshold: <span id="quality-threshold-value"><?php echo esc_attr($settings['quality_gate_threshold'] ?? 70); ?></span></label>
                            <input type="range" id="giga_apw_settings[quality_gate_threshold]" 
                                   name="giga_apw_settings[quality_gate_threshold]" 
                                   min="50" max="90" 
                                   value="<?php echo esc_attr($settings['quality_gate_threshold'] ?? 70); ?>"
                                   class="giga-apw-slider">
                            <p class="giga-apw-help-text">Products below this score won't publish automatically</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="giga-apw-field">
                    <label for="giga_apw_settings[temperature]">Temperature / Creativity</label>
                    <div class="giga-apw-temperature-control">
                        <input type="range" id="giga_apw_settings[temperature]" 
                               name="giga_apw_settings[temperature]" 
                               min="0.0" max="1.0" step="0.1" 
                               value="<?php echo esc_attr($settings['temperature'] ?? 0.7); ?>"
                               class="giga-apw-slider">
                        <div class="giga-apw-temperature-labels">
                            <span>More Precise</span>
                            <span id="temperature-value"><?php echo esc_attr($settings['temperature'] ?? 0.7); ?></span>
                            <span>More Creative</span>
                        </div>
                    </div>
                    <p class="giga-apw-help-text">Higher values make the output more creative and varied</p>
                </div>
            </div>
        </div>
        
        <!-- Content Preferences Section -->
        <div id="content-preferences" class="giga-apw-tab-content">
            <div class="giga-apw-card">
                <h3>Content Preferences</h3>
                <p class="giga-apw-help-text">Configure content length and generation preferences</p>
                
                <div class="giga-apw-field">
                    <label>Long Description Length</label>
                    <div class="giga-apw-range-slider">
                        <div class="giga-apw-range-inputs">
                            <input type="number" id="giga_apw_settings[min_words]" 
                                   name="giga_apw_settings[min_words]" 
                                   min="50" max="1000" 
                                   value="<?php echo esc_attr($settings['min_words'] ?? 150); ?>"
                                   class="giga-apw-range-min">
                            <span class="giga-apw-range-separator">–</span>
                            <input type="number" id="giga_apw_settings[max_words]" 
                                   name="giga_apw_settings[max_words]" 
                                   min="50" max="1000" 
                                   value="<?php echo esc_attr($settings['max_words'] ?? 500); ?>"
                                   class="giga-apw-range-max">
                        </div>
                        <div class="giga-apw-range-display">
                            <span id="words-range-display"><?php echo esc_attr($settings['min_words'] ?? 150); ?> – <?php echo esc_attr($settings['max_words'] ?? 500); ?> words</span>
                        </div>
                    </div>
                </div>
                
                <div class="giga-apw-field">
                    <label>Short Description Length</label>
                    <div class="giga-apw-range-slider">
                        <div class="giga-apw-range-inputs">
                            <input type="number" id="giga_apw_settings[short_min_words]" 
                                   name="giga_apw_settings[short_min_words]" 
                                   min="10" max="100" 
                                   value="<?php echo esc_attr($settings['short_min_words'] ?? 20); ?>"
                                   class="giga-apw-range-min">
                            <span class="giga-apw-range-separator">–</span>
                            <input type="number" id="giga_apw_settings[short_max_words]" 
                                   name="giga_apw_settings[short_max_words]" 
                                   min="10" max="100" 
                                   value="<?php echo esc_attr($settings['short_max_words'] ?? 50); ?>"
                                   class="giga-apw-range-max">
                        </div>
                        <div class="giga-apw-range-display">
                            <span id="short-words-range-display"><?php echo esc_attr($settings['short_min_words'] ?? 20); ?> – <?php echo esc_attr($settings['short_max_words'] ?? 50); ?> words</span>
                        </div>
                    </div>
                </div>
                
                <div class="giga-apw-field">
                    <div class="giga-apw-toggle-container">
                        <label class="giga-apw-toggle-label">
                            <span>Auto-save Draft</span>
                            <div class="giga-apw-toggle-switch">
                                <input type="checkbox" id="giga_apw_settings[auto_save_draft]" 
                                       name="giga_apw_settings[auto_save_draft]" 
                                       value="1" 
                                       <?php checked($settings['auto_save_draft'] ?? 0, 1); ?>>
                                <span class="giga-apw-toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                    <p class="giga-apw-help-text">Automatically save generated content to product draft before publishing</p>
                </div>
                
                <div class="giga-apw-field">
                    <div class="giga-apw-toggle-container">
                        <label class="giga-apw-toggle-label">
                            <span>Generate SEO Meta</span>
                            <div class="giga-apw-toggle-switch">
                                <input type="checkbox" id="giga_apw_settings[generate_seo]" 
                                       name="giga_apw_settings[generate_seo]" 
                                       value="1" 
                                       <?php checked($settings['generate_seo'] ?? 0, 1); ?>>
                                <span class="giga-apw-toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                    
                    <?php if ($settings['generate_seo'] ?? 0): ?>
                        <div class="giga-apw-sub-option show">
                            <div class="giga-apw-toggle-container">
                                <label class="giga-apw-toggle-label">
                                    <span>Include focus keyword field</span>
                                    <div class="giga-apw-toggle-switch">
                                        <input type="checkbox" id="giga_apw_settings[include_focus_keyword]" 
                                               name="giga_apw_settings[include_focus_keyword]" 
                                               value="1" 
                                               <?php checked($settings['include_focus_keyword'] ?? 0, 1); ?>>
                                        <span class="giga-apw-toggle-slider"></span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="giga-apw-field">
                    <div class="giga-apw-toggle-container">
                        <label class="giga-apw-toggle-label">
                            <span>Include Product Specifications Table</span>
                            <div class="giga-apw-toggle-switch">
                                <input type="checkbox" id="giga_apw_settings[include_specs]" 
                                       name="giga_apw_settings[include_specs]" 
                                       value="1" 
                                       <?php checked($settings['include_specs'] ?? 0, 1); ?>>
                                <span class="giga-apw-toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="giga-apw-field">
                    <div class="giga-apw-toggle-container">
                        <label class="giga-apw-toggle-label">
                            <span>Generate Product Tags</span>
                            <div class="giga-apw-toggle-switch">
                                <input type="checkbox" id="giga_apw_settings[generate_tags]" 
                                       name="giga_apw_settings[generate_tags]" 
                                       value="1" 
                                       <?php checked($settings['generate_tags'] ?? 0, 1); ?>>
                                <span class="giga-apw-toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                    
                    <?php if ($settings['generate_tags'] ?? 0): ?>
                        <div class="giga-apw-sub-option show">
                            <label for="giga_apw_settings[max_tags]">Maximum tags to generate</label>
                            <input type="number" id="giga_apw_settings[max_tags]" 
                                   name="giga_apw_settings[max_tags]" 
                                   min="1" max="20" 
                                   value="<?php echo esc_attr($settings['max_tags'] ?? 5); ?>"
                                   class="giga-apw-input small">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Plan Status Section -->
        <div id="plan-status" class="giga-apw-tab-content">
            <div class="giga-apw-card">
                <h3>Plan Status</h3>
                <p class="giga-apw-help-text">Manage your subscription and usage limits</p>
                
                <div class="giga-apw-plan-overview">
                    <div class="giga-apw-plan-badge <?php echo $is_pro ? 'pro' : 'free'; ?>">
                        <?php echo $is_pro ? 'PRO - Unlimited' : 'FREE - ' . (GIGA_APW_FREE_LIMIT - $monthly_remaining) . ' of ' . GIGA_APW_FREE_LIMIT . ' used'; ?>
                    </div>
                    
                    <?php if (!$is_pro): ?>
                        <div class="giga-apw-usage-bar">
                            <div class="giga-apw-usage-progress" style="width: <?php echo ((GIGA_APW_FREE_LIMIT - $monthly_remaining) / GIGA_APW_FREE_LIMIT * 100); ?>%;"></div>
                        </div>
                        <div class="giga-apw-usage-text">
                            <?php echo (GIGA_APW_FREE_LIMIT - $monthly_remaining); ?> of <?php echo GIGA_APW_FREE_LIMIT; ?> products used this month
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="giga-apw-field">
                    <label for="giga_apw_license_key">License Key</label>
                    <div class="giga-apw-input-group">
                        <input type="text" 
                               id="giga_apw_license_key" 
                               name="giga_apw_license_key" 
                               value="<?php echo esc_attr($license_key); ?>" 
                               class="giga-apw-input"
                               placeholder="Enter your license key">
                        <button type="button" id="giga-apw-activate-license" class="giga-apw-button-primary">
                            Activate
                        </button>
                    </div>
                </div>
                
                <?php if (!$is_pro): ?>
                    <div class="giga-apw-upgrade-section">
                        <h3>Upgrade to Pro</h3>
                        <p class="giga-apw-upgrade-description">Unlock unlimited products and premium features</p>
                        <a href="https://gigaverse.io/pricing" target="_blank" class="giga-apw-button-upgrade">
                            Upgrade to Pro
                        </a>
                        
                        <div class="giga-apw-pro-features">
                            <h4>Pro Features:</h4>
                            <ul>
                                <li>✓ Unlimited products per month</li>
                                <li>✓ Bulk Generate (generate 50+ products at once)</li>
                                <li>✓ Brand Voice customization</li>
                                <li>✓ Priority support</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="giga-apw-form-actions">
            <button type="submit" class="giga-apw-button-primary giga-apw-button-large">
                Save Changes
            </button>
            <button type="button" id="giga-apw-reset-form" class="giga-apw-button-secondary">
                Reset to Defaults
            </button>
        </div>
    </form>
</div>

<!-- Toast Notification -->
<div id="giga-apw-toast" class="giga-apw-toast" style="display: none;">
    <div class="giga-apw-toast-content">
        <span class="giga-apw-toast-icon"></span>
        <span class="giga-apw-toast-message"></span>
    </div>
</div>

<?php
/**
 * Helper function to get tone icon
 */
function get_tone_icon($tone) {
    $icons = [
        'Professional' => '💼',
        'Friendly' => '😊',
        'Persuasive' => '🎯',
        'Casual' => '👕',
        'Luxury' => '💎'
    ];
    return $icons[$tone] ?? '📝';
}
?>
