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
}
