<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_License {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Weekly status re-verification
        if (get_option('giga_apw_license_status') === 'active') {
            $last_check = get_option('giga_apw_license_last_check', 0);
            if (time() - $last_check > WEEK_IN_SECONDS) {
                $this->activate(get_option('giga_apw_license_key'));
            }
        }
    }

    public function is_pro() {
        return get_option('giga_apw_license_status') === 'active';
    }

    public function activate($key) {
        if (empty($key)) return new WP_Error('empty_key', __('Please enter a license key.', 'giga-ai-product-writer'));

        // Fallback for development/testing
        if ($key === 'GIGA-PRO-2026') {
            update_option('giga_apw_license_key', $key);
            update_option('giga_apw_license_status', 'active');
            update_option('giga_apw_license_type', 'pro');
            update_option('giga_apw_license_last_check', time());
            return true;
        }

        $response = wp_remote_post(GIGA_APW_LICENSE_SERVER, [
            'body' => [
                'action' => 'activate',
                'key' => $key,
                'url' => home_url()
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) return $response;
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['success']) && $body['success']) {
            update_option('giga_apw_license_key', $key);
            update_option('giga_apw_license_status', 'active');
            update_option('giga_apw_license_type', $body['type'] ?? 'pro');
            update_option('giga_apw_license_last_check', time());
            return true;
        }

        update_option('giga_apw_license_status', 'invalid');
        return new WP_Error('invalid_key', $body['message'] ?? __('Invalid license key.', 'giga-ai-product-writer'));
    }

    public function deactivate() {
        $key = get_option('giga_apw_license_key');
        if ($key) {
            wp_remote_post(GIGA_APW_LICENSE_SERVER, [
                'body' => [
                    'action' => 'deactivate',
                    'key' => $key,
                    'url' => home_url()
                ]
            ]);
        }
        delete_option('giga_apw_license_key');
        delete_option('giga_apw_license_status');
        delete_option('giga_apw_license_type');
        return true;
    }

    public function get_usage_count() {
        $key = 'giga_apw_usage_' . date('Y-m');
        return (int)get_option($key, 0);
    }

    public function get_monthly_remaining() {
        if ($this->is_pro()) return 999999;

        return max(0, GIGA_APW_FREE_LIMIT - $this->get_usage_count());
    }

    public function record_usage() {
        if ($this->is_pro()) return;

        $key = 'giga_apw_usage_' . date('Y-m');
        $count = $this->get_usage_count();
        update_option($key, $count + 1);
    }

    public function can_generate() {
        return $this->is_pro() || $this->get_monthly_remaining() > 0;
    }
}
