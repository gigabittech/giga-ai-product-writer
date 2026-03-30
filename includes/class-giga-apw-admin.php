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
    }

    public function register_settings() {
        register_setting('giga_apw_settings_group', 'giga_apw_settings', [$this, 'sanitize_settings']);
        register_setting('giga_apw_settings_group', 'giga_apw_api_key', [$this, 'sanitize_api_key']);
        register_setting('giga_apw_settings_group', 'giga_apw_license_key', 'sanitize_text_field');
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        $sanitized['default_language'] = sanitize_text_field($input['default_language'] ?? 'en');
        $sanitized['default_tone'] = sanitize_text_field($input['default_tone'] ?? 'Professional');
        $sanitized['use_quality_gate'] = isset($input['use_quality_gate']) ? 1 : 0;
        $sanitized['quality_gate_threshold'] = absint($input['quality_gate_threshold'] ?? 70);
        $sanitized['min_words'] = absint($input['min_words'] ?? 150);
        $sanitized['max_words'] = absint($input['max_words'] ?? 500);
        $sanitized['auto_save_draft'] = isset($input['auto_save_draft']) ? 1 : 0;
        return $sanitized;
    }

    public function sanitize_api_key($input) {
        if (empty($input)) {
            return '';
        }
        $key = wp_generate_password(64, true, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($input, 'aes-256-cbc', wp_salt(), 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function get_api_key() {
        $encrypted = get_option('giga_apw_api_key');
        if (empty($encrypted)) return '';
        
        $parts = explode('::', base64_decode($encrypted), 2);
        if (count($parts) !== 2) return '';
        
        return openssl_decrypt($parts[0], 'aes-256-cbc', wp_salt(), 0, $parts[1]);
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

    public function render_bulk_page() {
        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-bulk.php';
    }

    public function render_voice_page() {
        require_once GIGA_APW_PLUGIN_DIR . 'admin/templates/page-brand-voice.php';
    }
}
