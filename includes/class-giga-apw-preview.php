<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Preview {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function render($field_id, $label, $current_content, $generated_content, $seo_badge = '', $indicator = '') {
        ob_start();
        include GIGA_APW_PLUGIN_DIR . 'templates/metabox-preview.php';
        return ob_get_clean();
    }
}
