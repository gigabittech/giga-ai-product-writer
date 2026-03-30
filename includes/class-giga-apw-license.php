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

    private function __construct() {}

    public function is_pro() {
        return get_option('giga_apw_license_status') === 'pro';
    }

    public function activate($license_key) {
        if (empty($license_key)) {
            return new WP_Error('empty_key', __('Please enter a license key.', 'giga-ai-product-writer'));
        }

        $response = wp_remote_post(GIGA_APW_LICENSE_SERVER . '/verify', [
            'body' => [
                'license_key' => $license_key,
                'site_url' => home_url(),
                'plugin' => 'giga-ai-product-writer'
            ]
        ]);

        if (is_wp_error($response)) {
            // For demo/mock purposes if server is unreachable, we'll allow a mock key 'GIGA-PRO-2026'
            if ($license_key === 'GIGA-PRO-2026') {
                update_option('giga_apw_license_key', $license_key);
                update_option('giga_apw_license_status', 'pro');
                return true;
            }
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['success']) && $body['success']) {
            update_option('giga_apw_license_key', $license_key);
            update_option('giga_apw_license_status', 'pro');
            return true;
        }

        return new WP_Error('invalid_key', $body['message'] ?? __('Invalid license key.', 'giga-ai-product-writer'));
    }

    public function deactivate() {
        delete_option('giga_apw_license_key');
        delete_option('giga_apw_license_status');
        return true;
    }

    public function get_monthly_remaining() {
        if ($this->is_pro()) return PHP_INT_MAX;

        $key = 'giga_apw_usage_' . date('Y-m');
        $count = (int)get_option($key, 0); // Using option instead of transient to be safer for monthly limits
        return max(0, GIGA_APW_FREE_LIMIT - $count);
    }

    public function increment_usage() {
        if ($this->is_pro()) return;

        $key = 'giga_apw_usage_' . date('Y-m');
        $count = (int)get_option($key, 0);
        update_option($key, $count + 1);
    }

    public function check_limit() {
        return $this->get_monthly_remaining() > 0;
    }
}
