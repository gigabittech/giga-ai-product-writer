<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Bulk {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_giga_apw_bulk_get_counts', [$this, 'ajax_get_counts']);
        add_action('wp_ajax_giga_apw_bulk_start_job', [$this, 'ajax_start_job']);
        add_action('wp_ajax_giga_apw_bulk_get_progress', [$this, 'ajax_get_progress']);
        add_action('wp_ajax_giga_apw_bulk_cancel', [$this, 'ajax_cancel']);
        
        // Cron handler
        add_action('giga_apw_process_bulk', [$this, 'process_bulk_batch']);
    }

    public function ajax_get_counts() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error([], 403);

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        $args = ['status' => 'publish', 'limit' => 50, 'return' => 'ids'];
        if ($category_id > 0) $args['category'] = [get_term($category_id, 'product_cat')->slug];

        $products = wc_get_products($args);
        wp_send_json_success(['count' => count($products), 'ids' => $products]);
    }

    public function ajax_start_job() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error([], 403);
        if (!Giga_APW_License::get_instance()->is_pro()) {
            wp_send_json_error(['message' => 'Pro license required for bulk operations.']);
        }

        $ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
        if (empty($ids)) wp_send_json_error(['message' => 'No products selected.']);

        $options = [
            'tone' => sanitize_text_field($_POST['tone'] ?? ''),
            'language' => sanitize_text_field($_POST['language'] ?? ''),
            'use_brand_voice' => isset($_POST['use_brand_voice']) && $_POST['use_brand_voice'] === 'true',
            'auto_publish' => isset($_POST['auto_publish']) && $_POST['auto_publish'] === 'true',
        ];

        $job = [
            'status' => 'running',
            'total' => count($ids),
            'completed' => 0,
            'failed' => 0,
            'remaining_ids' => $ids,
            'options' => $options,
            'started_at' => time()
        ];

        update_option('giga_apw_bulk_progress', $job);
        
        // Schedule first batch immediately
        wp_schedule_single_event(time(), 'giga_apw_process_bulk');

        wp_send_json_success(['message' => 'Bulk job started.']);
    }

    public function process_bulk_batch() {
        $job = get_option('giga_apw_bulk_progress');
        if (!$job || $job['status'] !== 'running') return;

        $batch_size = GIGA_APW_BULK_BATCH_SIZE; // 3 products per run
        $to_process = array_splice($job['remaining_ids'], 0, $batch_size);

        $generator = Giga_APW_Generator::get_instance();

        foreach ($to_process as $id) {
            $result = $generator->generate($id, $job['options']);
            
            if (is_wp_error($result)) {
                $job['failed']++;
            } else {
                $job['completed']++;
                if ($job['options']['auto_publish']) {
                    $generator->publish($result['generation_id'], ['long_description', 'short_description', 'meta_title', 'meta_description', 'tags', 'alt_text']);
                }
            }
        }

        if (empty($job['remaining_ids'])) {
            $job['status'] = 'completed';
        } else {
            // Schedule next batch in 1 minute
            wp_schedule_single_event(time() + 60, 'giga_apw_process_bulk');
        }

        update_option('giga_apw_bulk_progress', $job);
    }

    public function ajax_get_progress() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        $job = get_option('giga_apw_bulk_progress');
        wp_send_json_success(['job' => $job]);
    }

    public function ajax_cancel() {
        check_ajax_referer('giga_apw_nonce', 'nonce');
        $job = get_option('giga_apw_bulk_progress');
        if ($job) {
            $job['status'] = 'cancelled';
            update_option('giga_apw_bulk_progress', $job);
        }
        wp_clear_scheduled_hook('giga_apw_process_bulk');
        wp_send_json_success();
    }
}
