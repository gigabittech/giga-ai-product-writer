<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Voice {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_giga_apw_analyze_voice', [$this, 'ajax_analyze']);
        add_action('wp_ajax_giga_apw_clear_voice', [$this, 'ajax_clear']);
    }

    public function ajax_analyze() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $examples = isset($_POST['examples']) && is_array($_POST['examples']) ? array_map('sanitize_textarea_field', wp_unslash($_POST['examples'])) : [];
        $examples = array_filter($examples, function($v) { return str_word_count($v) >= 50; });

        if (count($examples) < 3) {
            wp_send_json_error(['message' => __('Please provide at least 3 valid examples with a minimum of 50 words each.', 'giga-ai-product-writer')]);
        }

        $result = $this->analyze($examples);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public function ajax_clear() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'giga-ai-product-writer')], 403);
        }

        $this->clear_profile();
        wp_send_json_success();
    }

    public function analyze($examples) {
        $prompt_builder = Giga_APW_Prompt::get_instance();
        $user_prompt = $prompt_builder->build_brand_voice_analysis_prompt($examples);

        $system_prompt = "You are an expert brand voice analyst. Map tone, vocabulary, formatting styles, and key patterns.";

        $claude = Giga_APW_Claude::get_instance();
        $response_data = $claude->generate($system_prompt, $user_prompt);

        if (is_wp_error($response_data)) {
            return $response_data;
        }

        $text_content = $response_data['content'][0]['text'] ?? '';
        $profile = json_decode($text_content, true);

        if (!$profile || !isset($profile['tone'])) {
            return new WP_Error('invalid_json', __('Claude API returned an invalid JSON structure for voice profile.', 'giga-ai-product-writer'));
        }

        $profile['analyzed_at'] = current_time('mysql');
        update_option('giga_apw_brand_voice', $profile);

        return $profile;
    }

    public function get_profile() {
        $profile = get_option('giga_apw_brand_voice');
        return !empty($profile) ? $profile : null;
    }

    public function clear_profile() {
        delete_option('giga_apw_brand_voice');
    }
}
