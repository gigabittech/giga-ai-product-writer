<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Ajax {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_giga_apw_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_giga_apw_generate', [$this, 'generate']);
        add_action('wp_ajax_giga_apw_publish', [$this, 'publish']);
        add_action('wp_ajax_giga_apw_get_history', [$this, 'get_history']);
        add_action('wp_ajax_giga_apw_revert', [$this, 'revert']);
        add_action('wp_ajax_giga_apw_activate_license', [$this, 'activate_license']);
        add_action('wp_ajax_giga_apw_deactivate_license', [$this, 'deactivate_license']);
        add_action('wp_ajax_giga_apw_get_current_content', [$this, 'get_current_content']);
        
        // New AJAX handlers for multi-provider system
        add_action('wp_ajax_giga_test_connection', [$this, 'test_connection_new']);
        add_action('wp_ajax_giga_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_giga_get_models', [$this, 'get_models']);
        add_action('wp_ajax_giga_activate_license', [$this, 'activate_license']);
    }

    public function test_connection() {
        check_ajax_referer('giga_apw_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $result = Giga_APW_Claude::get_instance()->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Connection successful!', 'giga-ai-product-writer')]);
    }

    public function generate() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'giga-ai-product-writer')]);
        }

        $options = [
            'target_keywords' => sanitize_text_field($_POST['target_keywords'] ?? ''),
            'tone' => sanitize_text_field($_POST['tone'] ?? ''),
            'language' => sanitize_text_field($_POST['language'] ?? ''),
            'additional_instructions' => sanitize_textarea_field($_POST['additional_instructions'] ?? ''),
            'use_brand_voice' => isset($_POST['use_brand_voice']) && $_POST['use_brand_voice'] === 'true',
        ];

        $generator = Giga_APW_Generator::get_instance();
        $result = $generator->generate($product_id, $options);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public function publish() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $generation_id = isset($_POST['generation_id']) ? intval($_POST['generation_id']) : 0;
        $approved_fields = isset($_POST['approved_fields']) && is_array($_POST['approved_fields']) 
            ? array_map('sanitize_text_field', $_POST['approved_fields']) : [];

        if (!$generation_id || empty($approved_fields)) {
            wp_send_json_error(['message' => __('Invalid input data.', 'giga-ai-product-writer')]);
        }

        $generator = Giga_APW_Generator::get_instance();
        $result = $generator->publish($generation_id, $approved_fields);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'published_fields' => $result,
            'message' => __('Content published successfully.', 'giga-ai-product-writer')
        ]);
    }

    public function get_history() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) wp_send_json_error();

        global $wpdb;
        $table_name = $wpdb->prefix . 'giga_apw_generations';
        $limit = Giga_APW_License::get_instance()->is_pro() ? 20 : 3;
        
        $history = $wpdb->get_results($wpdb->prepare("SELECT id, generated_at, quality_score, status FROM $table_name WHERE product_id = %d ORDER BY generated_at DESC LIMIT %d", $product_id, $limit));

        wp_send_json_success(['history' => $history]);
    }

    public function revert() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $generation_id = isset($_POST['generation_id']) ? intval($_POST['generation_id']) : 0;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'giga_apw_generations';
        $gen = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $generation_id));

        if (!$gen) wp_send_json_error(['message' => 'Generation not found.']);

        $old_data_json = get_post_meta($gen->product_id, "_giga_apw_revert_{$generation_id}", true);
        if (!$old_data_json) wp_send_json_error(['message' => 'No revert data found.']);

        $old_data = json_decode($old_data_json, true);

        if (isset($old_data['long_description'])) {
            wp_update_post(['ID' => $gen->product_id, 'post_content' => wp_kses_post($old_data['long_description'])]);
        }

        if (isset($old_data['short_description'])) {
            wp_update_post(['ID' => $gen->product_id, 'post_excerpt' => wp_kses_post($old_data['short_description'])]);
        }

        $seo_class = class_exists('Giga_APW_SEO') ? Giga_APW_SEO::get_instance() : null;
        if ($seo_class) {
            $curr_meta = $seo_class->read_existing_meta($gen->product_id);
            $new_title = isset($old_data['meta_title']) ? $old_data['meta_title'] : ($curr_meta['meta_title'] ?? '');
            $new_desc = isset($old_data['meta_description']) ? $old_data['meta_description'] : ($curr_meta['meta_description'] ?? '');
            $seo_class->write_meta($gen->product_id, $new_title, $new_desc);
        }

        if (isset($old_data['tags'])) {
            $tags = array_map('trim', explode(',', $old_data['tags']));
            wp_set_post_terms($gen->product_id, $tags, 'product_tag', false);
        }

        if (isset($old_data['alt_text'])) {
            $alts = json_decode($old_data['alt_text'], true);
            if (is_array($alts)) {
                foreach ($alts as $img_id => $alt) {
                    update_post_meta($img_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
                }
            }
        }

        $wpdb->update($table_name, ['status' => 'rejected'], ['id' => $generation_id]);

        wp_send_json_success(['message' => 'Successfully reverted.']);
    }

    public function activate_license() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error([], 403);

        $key = sanitize_text_field($_POST['license_key'] ?? '');
        $result = Giga_APW_License::get_instance()->activate($key);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Pro license activated successfully!', 'giga-ai-product-writer')]);
    }

    public function deactivate_license() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error([], 403);

        Giga_APW_License::get_instance()->deactivate();
        wp_send_json_success(['message' => __('License deactivated.', 'giga-ai-product-writer')]);
    }

    public function get_current_content() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) wp_send_json_error();

        $product = wc_get_product($product_id);
        if (!$product) wp_send_json_error();

        $seo_class = class_exists('Giga_APW_SEO') ? Giga_APW_SEO::get_instance() : null;
        $meta = $seo_class ? $seo_class->read_existing_meta($product_id) : [];

        $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);

        wp_send_json_success([
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'meta_title' => $meta['meta_title'] ?? '',
            'meta_description' => $meta['meta_description'] ?? '',
            'tags' => is_array($tags) ? $tags : []
        ]);
    }
    
    /**
     * New test connection method using unified AI client
     */
    public function test_connection_new() {
        check_ajax_referer('giga_apw_settings_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $client = Giga_AI_Client::get_instance();
        
        // If testing from settings page without saving yet
        if (isset($_POST['provider'])) {
            $provider = sanitize_text_field($_POST['provider']);
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $model = sanitize_text_field($_POST['model'] ?? '');
            
            // Temporary override for testing
            add_filter('pre_option_giga_ai_provider', fn() => $provider);
            add_filter('pre_option_giga_ai_model', fn() => $model);
            
            if ($provider === 'ollama') {
                $base_url = sanitize_text_field($_POST['ollama_base_url'] ?? 'http://localhost:11434');
                add_filter('pre_option_giga_ollama_base_url', fn() => $base_url);
            } else if (!empty($api_key) && trim($api_key, '*') !== '') {
                $encrypted = $client->encrypt_key($api_key);
                add_filter('pre_option_giga_ai_api_key', fn() => $encrypted);
            }
        }

        $result = $client->test_connection();

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        wp_send_json_success($result);
    }
    
    /**
     * Save all settings via AJAX
     */
    public function save_settings() {
        check_ajax_referer('giga_apw_settings_nonce', 'giga_apw_settings_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        // Get provider data
        $provider = sanitize_text_field($_POST['giga_ai_provider'] ?? 'claude');
        $api_key = sanitize_text_field($_POST['giga_ai_api_key'] ?? '');
        $model = sanitize_text_field($_POST['giga_ai_model'] ?? '');
        
        // Get settings data
        $settings = isset($_POST['giga_apw_settings']) && is_array($_POST['giga_apw_settings'])
            ? $this->sanitize_settings($_POST['giga_apw_settings']) : [];
        
        // Save provider data
        update_option('giga_ai_provider', $provider);
        
        // Save API key (Double encryption is avoided because sanitize_api_key handles it)
        if ($provider !== 'ollama') {
            update_option('giga_ai_api_key', $api_key);
        } else {
            // For Ollama, save base URL
            $base_url = sanitize_text_field($_POST['giga_ollama_base_url'] ?? 'http://localhost:11434');
            update_option('giga_ollama_base_url', $base_url);
        }
        
        // Save model
        if (empty($model)) {
            $client = Giga_AI_Client::get_instance();
            $model = $client->get_default_model($provider);
        }
        update_option('giga_ai_model', $model);
        
        // Save settings
        update_option('giga_apw_settings', $settings);
        
        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'giga-ai-product-writer'),
            'provider' => $provider,
            'model' => $model
        ]);
    }
    
    /**
     * Get available models for selected provider
     */
    public function get_models() {
        check_ajax_referer('giga_apw_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? 'claude');
        $client = Giga_AI_Client::get_instance();
        $models = $client->get_available_models();
        
        $formatted_models = [];
        foreach ($models as $model_key => $model) {
            $formatted_models[] = [
                'name' => $model_key,
                'label' => $model['label']
            ];
        }
        
        wp_send_json_success(['models' => $formatted_models]);
    }
    
    /**
     * Sanitize settings data
     */
    private function sanitize_settings($settings) {
        $sanitized = [];
        
        if (isset($settings['default_language'])) {
            $sanitized['default_language'] = sanitize_text_field($settings['default_language']);
        }
        
        if (isset($settings['default_tone'])) {
            $sanitized['default_tone'] = sanitize_text_field($settings['default_tone']);
        }
        
        if (isset($settings['use_quality_gate'])) {
            $sanitized['use_quality_gate'] = $settings['use_quality_gate'] ? 1 : 0;
        }
        
        if (isset($settings['quality_gate_threshold'])) {
            $sanitized['quality_gate_threshold'] = absint($settings['quality_gate_threshold']);
        }
        
        if (isset($settings['min_words'])) {
            $sanitized['min_words'] = absint($settings['min_words']);
        }
        
        if (isset($settings['max_words'])) {
            $sanitized['max_words'] = absint($settings['max_words']);
        }
        
        if (isset($settings['short_min_words'])) {
            $sanitized['short_min_words'] = absint($settings['short_min_words']);
        }
        
        if (isset($settings['short_max_words'])) {
            $sanitized['short_max_words'] = absint($settings['short_max_words']);
        }
        
        if (isset($settings['auto_save_draft'])) {
            $sanitized['auto_save_draft'] = $settings['auto_save_draft'] ? 1 : 0;
        }
        
        if (isset($settings['generate_seo'])) {
            $sanitized['generate_seo'] = $settings['generate_seo'] ? 1 : 0;
        }
        
        if (isset($settings['include_focus_keyword'])) {
            $sanitized['include_focus_keyword'] = $settings['include_focus_keyword'] ? 1 : 0;
        }
        
        if (isset($settings['include_specs'])) {
            $sanitized['include_specs'] = $settings['include_specs'] ? 1 : 0;
        }
        
        if (isset($settings['generate_tags'])) {
            $sanitized['generate_tags'] = $settings['generate_tags'] ? 1 : 0;
        }
        
        if (isset($settings['max_tags'])) {
            $sanitized['max_tags'] = absint($settings['max_tags']);
        }
        
        if (isset($settings['temperature'])) {
            $sanitized['temperature'] = floatval($settings['temperature']);
        }
        
        return $sanitized;
    }
}
