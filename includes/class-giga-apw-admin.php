<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Admin {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_assets']);
    }

    public function register_settings() {
        register_setting('giga_apw_settings_group', 'giga_apw_settings', [$this, 'sanitize_settings']);
        register_setting('giga_apw_settings_group', 'giga_ai_provider', 'sanitize_text_field');
        register_setting('giga_apw_settings_group', 'giga_ai_api_key', [$this, 'sanitize_api_key']);
        register_setting('giga_apw_settings_group', 'giga_ai_model', 'sanitize_text_field');
        register_setting('giga_apw_settings_group', 'giga_ollama_base_url', 'sanitize_text_field');
        register_setting('giga_apw_settings_group', 'giga_apw_license_key', 'sanitize_text_field');
        
        // Register default values if not set
        $this->ensure_default_settings();
    }
    
    /**
     * Ensure default settings are set
     */
    private function ensure_default_settings() {
        $defaults = [
            'giga_ai_provider' => 'claude',
            'giga_ai_model' => 'claude-3-5-sonnet-20241022',
            'giga_ai_api_key' => '',
            'giga_ollama_base_url' => 'http://localhost:11434',
            'giga_apw_settings' => [
                'default_language' => 'en',
                'default_tone' => 'Professional',
                'use_quality_gate' => 1,
                'quality_gate_threshold' => 70,
                'min_words' => 150,
                'max_words' => 500,
                'auto_save_draft' => 0,
                'temperature' => 0.7
            ]
        ];
        
        foreach ($defaults as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                update_option($option_name, $default_value);
            }
        }
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Basic text fields
        $sanitized['default_language'] = sanitize_text_field($input['default_language'] ?? 'en');
        $sanitized['default_tone'] = sanitize_text_field($input['default_tone'] ?? 'Professional');
        
        // Boolean fields
        $sanitized['use_quality_gate'] = isset($input['use_quality_gate']) ? 1 : 0;
        $sanitized['auto_save_draft'] = isset($input['auto_save_draft']) ? 1 : 0;
        $sanitized['generate_seo'] = isset($input['generate_seo']) ? 1 : 0;
        $sanitized['include_focus_keyword'] = isset($input['include_focus_keyword']) ? 1 : 0;
        $sanitized['include_specs'] = isset($input['include_specs']) ? 1 : 0;
        $sanitized['generate_tags'] = isset($input['generate_tags']) ? 1 : 0;
        
        // Numeric fields with validation
        $sanitized['quality_gate_threshold'] = $this->validate_range(
            absint($input['quality_gate_threshold'] ?? 70),
            0,
            100,
            70
        );
        
        $sanitized['min_words'] = $this->validate_range(
            absint($input['min_words'] ?? 150),
            50,
            1000,
            150
        );
        
        $sanitized['max_words'] = $this->validate_range(
            absint($input['max_words'] ?? 500),
            100,
            2000,
            500
        );
        
        $sanitized['short_min_words'] = $this->validate_range(
            absint($input['short_min_words'] ?? 20),
            10,
            100,
            20
        );
        
        $sanitized['short_max_words'] = $this->validate_range(
            absint($input['short_max_words'] ?? 50),
            20,
            200,
            50
        );
        
        $sanitized['max_tags'] = $this->validate_range(
            absint($input['max_tags'] ?? 5),
            1,
            20,
            5
        );
        
        // Float validation for temperature
        $temperature = floatval($input['temperature'] ?? 0.7);
        $sanitized['temperature'] = max(0, min(2, $temperature)); // Clamp between 0 and 2
        
        return $sanitized;
    }
    
    /**
     * Validate numeric value is within range
     */
    private function validate_range($value, $min, $max, $default) {
        return max($min, min($max, $value));
    }

    public function sanitize_api_key($input) {
        // Handle empty or placeholder values
        if (empty($input) || trim($input, '*') === '') {
            return get_option('giga_ai_api_key', '');
        }
        
        // Basic validation for API key format
        $input = trim($input);
        if (strlen($input) < 10) {
            add_settings_error(
                'giga_ai_api_key',
                'invalid_api_key',
                __('API key appears to be too short. Please check your key.', 'giga-ai-product-writer'),
                'error'
            );
            return get_option('giga_ai_api_key', '');
        }
        
        return Giga_AI_Client::get_instance()->encrypt_key($input);
    }

    public static function get_api_key() {
        return Giga_AI_Client::get_instance()->get_decrypted_key();
    }

    public function register_meta_boxes() {
        add_meta_box(
            'giga_apw_meta_box',
            __('🤖 Giga AI Writer', 'giga-ai-product-writer'),
            [$this, 'render_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    public function render_dashboard_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-dashboard.php';
    }

    public function render_docs_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-docs.php';
    }

    public function render_meta_box($post) {
        require GIGA_APW_PLUGIN_DIR . 'admin/templates/metabox-main.php';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('giga_apw_messages', 'giga_apw_message', __('Settings saved successfully.', 'giga-ai-product-writer'), 'updated');
        }

        settings_errors('giga_apw_messages');

        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-settings.php';
    }
    
    public function enqueue_settings_assets($hook) {
        if (strpos($hook, 'giga-apw') !== false) {
            wp_enqueue_style(
                'giga-apw-settings',
                GIGA_APW_PLUGIN_URL . 'admin/css/giga-apw-settings.css',
                [],
                GIGA_APW_VERSION
            );

            wp_enqueue_script(
                'giga-apw-settings',
                GIGA_APW_PLUGIN_URL . 'admin/js/giga-apw-settings.js',
                ['jquery'],
                GIGA_APW_VERSION,
                true
            );

            $is_pro = Giga_APW_License::get_instance()->is_pro();
            $monthly_remaining = Giga_APW_License::get_instance()->get_monthly_remaining();

            wp_localize_script('giga-apw-settings', 'giga_apw_data', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('giga_apw_nonce'),
                'settings_nonce' => wp_create_nonce('giga_apw_settings_nonce'),
                'is_pro' => $is_pro,
                'monthly_remaining' => $monthly_remaining,
                'strings' => [
                    'generating' => __('Generating...', 'giga-ai-product-writer'),
                    'error' => __('Error', 'giga-ai-product-writer')
                ]
            ]);
        }
    }

    public function render_bulk_page() {
        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-bulk.php';
    }

    public function render_voice_page() {
        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-brand-voice.php';
    }
}
