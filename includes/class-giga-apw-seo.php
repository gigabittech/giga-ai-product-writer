<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_SEO {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function detect_active_seo_plugin() {
        if (class_exists('WPSEO_Options') || get_option('wpseo')) {
            return 'yoast';
        }
        if (class_exists('RankMath') || get_option('rank_math_modules')) {
            return 'rankmath';
        }
        return 'none';
    }

    public function get_seo_plugin_label() {
        $plugin = $this->detect_active_seo_plugin();
        if ($plugin === 'yoast') return __('Yoast SEO', 'giga-ai-product-writer');
        if ($plugin === 'rankmath') return __('Rank Math', 'giga-ai-product-writer');
        return __('Saved to product meta', 'giga-ai-product-writer');
    }

    public function write_meta($product_id, $meta_title = null, $meta_description = null) {
        $plugin = $this->detect_active_seo_plugin();

        if ($plugin === 'yoast') {
            if ($meta_title !== null) update_post_meta($product_id, '_yoast_wpseo_title', $meta_title);
            if ($meta_description !== null) update_post_meta($product_id, '_yoast_wpseo_metadesc', $meta_description);
        } elseif ($plugin === 'rankmath') {
            if ($meta_title !== null) update_post_meta($product_id, 'rank_math_title', $meta_title);
            if ($meta_description !== null) update_post_meta($product_id, 'rank_math_description', $meta_description);
        } else {
            if ($meta_title !== null) update_post_meta($product_id, '_giga_apw_meta_title', $meta_title);
            if ($meta_description !== null) update_post_meta($product_id, '_giga_apw_meta_description', $meta_description);
        }

        return ['plugin' => $plugin, 'written' => true];
    }

    public function read_existing_meta($product_id) {
        $plugin = $this->detect_active_seo_plugin();
        $title = '';
        $desc = '';

        if ($plugin === 'yoast') {
            $title = get_post_meta($product_id, '_yoast_wpseo_title', true);
            $desc = get_post_meta($product_id, '_yoast_wpseo_metadesc', true);
        } elseif ($plugin === 'rankmath') {
            $title = get_post_meta($product_id, 'rank_math_title', true);
            $desc = get_post_meta($product_id, 'rank_math_description', true);
        } else {
            $title = get_post_meta($product_id, '_giga_apw_meta_title', true);
            $desc = get_post_meta($product_id, '_giga_apw_meta_description', true);
        }

        return [
            'meta_title' => $title,
            'meta_description' => $desc
        ];
    }
}
