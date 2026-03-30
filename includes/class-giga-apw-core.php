<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Core {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function init() {
        load_plugin_textdomain('giga-ai-product-writer', false, dirname(plugin_basename(GIGA_APW_PLUGIN_FILE)) . '/languages');
        
        Giga_APW_Ajax::get_instance();
        Giga_APW_Admin::get_instance();
        Giga_APW_License::get_instance();
        Giga_APW_Bulk::get_instance();
        Giga_APW_Voice::get_instance();
        Giga_APW_Preview::get_instance();
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Giga AI Writer', 'giga-ai-product-writer'),
            __('Giga AI Writer', 'giga-ai-product-writer'),
            'manage_woocommerce',
            'giga-apw',
            [$this, 'settings_page'],
            'dashicons-edit-large',
            56
        );

        add_submenu_page(
            'giga-apw',
            __('Bulk Generate (Pro)', 'giga-ai-product-writer'),
            __('Bulk Generate (Pro)', 'giga-ai-product-writer'),
            'manage_woocommerce',
            'giga-apw-bulk',
            [$this, 'bulk_page']
        );

        add_submenu_page(
            'giga-apw',
            __('Brand Voice (Pro)', 'giga-ai-product-writer'),
            __('Brand Voice (Pro)', 'giga-ai-product-writer'),
            'manage_woocommerce',
            'giga-apw-voice',
            [$this, 'voice_page']
        );

        add_submenu_page(
            'giga-apw',
            __('Settings', 'giga-ai-product-writer'),
            __('Settings', 'giga-ai-product-writer'),
            'manage_woocommerce',
            'giga-apw',
            [$this, 'settings_page']
        );
    }

    public function settings_page() {
        Giga_APW_Admin::get_instance()->render_settings_page();
    }

    public function bulk_page() {
        Giga_APW_Admin::get_instance()->render_bulk_page();
    }

    public function voice_page() {
        Giga_APW_Admin::get_instance()->render_voice_page();
    }

    public function enqueue_admin_assets($hook) {
        $allowed_screens = [
            'post.php',
            'post-new.php',
            'toplevel_page_giga-apw',
            'giga-ai-writer_page_giga-apw-bulk',
            'giga-ai-writer_page_giga-apw-voice'
        ];

        global $post;
        $is_product_screen = ($post && $post->post_type === 'product' && in_array($hook, ['post.php', 'post-new.php']));
        $is_giga_screen = in_array($hook, $allowed_screens) || strpos($hook, 'giga-apw') !== false;

        if ($is_product_screen || $is_giga_screen) {
            wp_enqueue_style(
                'giga-apw-admin',
                GIGA_APW_PLUGIN_URL . 'admin/css/giga-apw-admin.css',
                [],
                GIGA_APW_VERSION
            );

            wp_enqueue_script(
                'giga-apw-admin',
                GIGA_APW_PLUGIN_URL . 'admin/js/giga-apw-admin.js',
                [],
                GIGA_APW_VERSION,
                true
            );

            $is_pro = Giga_APW_License::get_instance()->is_pro();
            $monthly_remaining = Giga_APW_License::get_instance()->get_monthly_remaining();

            wp_localize_script('giga-apw-admin', 'giga_apw_data', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('giga_apw_nonce'),
                'is_pro' => $is_pro,
                'monthly_remaining' => $monthly_remaining,
                'strings' => [
                    'generating' => __('Generating...', 'giga-ai-product-writer'),
                    'error' => __('Error', 'giga-ai-product-writer')
                ]
            ]);
        }
    }
}
