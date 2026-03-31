<?php
/**
 * Plugin Name: Giga AI Product Writer for WooCommerce
 * Plugin URI: https://github.com/gigabittech/giga-ai-product-writer
 * Description: Claude-powered WooCommerce product description generator with quality scoring, brand voice training, and direct Yoast/Rank Math integration.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Gigabit
 * Author URI: https://github.com/gigabittech/giga-ai-product-writer
 * License: GPL v2 or later
 * Text Domain: giga-ai-product-writer
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GIGA_APW_VERSION', '1.0.0');
define('GIGA_APW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIGA_APW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIGA_APW_PLUGIN_FILE', __FILE__);
define('GIGA_APW_DEFAULT_PROVIDER', 'claude');
define('GIGA_APW_DEFAULT_MODEL', 'claude-sonnet-4-5');
define('GIGA_APW_DEFAULT_TEMPERATURE', 0.7);
define('GIGA_APW_DEFAULT_MAX_TOKENS', 2048);
define('GIGA_APW_FREE_LIMIT', 5);
define('GIGA_APW_BULK_MAX', 50);
define('GIGA_APW_BULK_BATCH_SIZE', 3);
define('GIGA_APW_MIN_WORDS', 150);
define('GIGA_APW_MAX_WORDS', 500);
define('GIGA_APW_META_TITLE_MIN', 50);
define('GIGA_APW_META_TITLE_MAX', 60);
define('GIGA_APW_META_DESC_MIN', 150);
define('GIGA_APW_META_DESC_MAX', 160);
define('GIGA_APW_QUALITY_GATE', 70);
define('GIGA_APW_API_ENDPOINT', 'https://api.anthropic.com/v1/messages');
define('GIGA_APW_LICENSE_SERVER', 'https://gigaverse.io/api/license');

require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-core.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-ai-client.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-claude.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-prompt.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-generator.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-bulk.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-scorer.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-voice.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-seo.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-preview.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-admin.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-license.php';
require_once GIGA_APW_PLUGIN_DIR . 'includes/class-giga-apw-ajax.php';

add_action('plugins_loaded', 'giga_apw_check_wc_active', 9);
function giga_apw_check_wc_active()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'giga_apw_wc_missing_notice');
        return;
    }
}

function giga_apw_wc_missing_notice()
{
    echo '<div class="error"><p>' . esc_html__('Giga AI Product Writer requires WooCommerce to be installed and active.', 'giga-ai-product-writer') . '</p></div>';
}

add_action('plugins_loaded', ['Giga_APW_Core', 'get_instance'], 10);

register_activation_hook(__FILE__, 'giga_apw_activate');
register_deactivation_hook(__FILE__, 'giga_apw_deactivate');

function giga_apw_activate()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'giga_apw_generations';

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        model_used VARCHAR(100) NOT NULL DEFAULT 'claude-sonnet-4-20250514',
        
        long_description LONGTEXT,
        short_description TEXT,
        meta_title VARCHAR(100),
        meta_description VARCHAR(200),
        alt_text TEXT,
        tags TEXT,
        
        quality_score TINYINT UNSIGNED DEFAULT 0,
        score_readability TINYINT UNSIGNED DEFAULT 0,
        score_seo TINYINT UNSIGNED DEFAULT 0,
        score_uniqueness TINYINT UNSIGNED DEFAULT 0,
        score_benefits TINYINT UNSIGNED DEFAULT 0,
        score_length TINYINT UNSIGNED DEFAULT 0,
        
        status ENUM('generated','approved','published','rejected') DEFAULT 'generated',
        approved_fields TEXT,
        error_message TEXT,
        
        language VARCHAR(10) DEFAULT 'en',
        brand_voice_used TINYINT(1) DEFAULT 0,
        tokens_used INT UNSIGNED DEFAULT 0,
        
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY status (status),
        KEY generated_at (generated_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Set default options for new provider system
    if (!get_option('giga_ai_provider')) {
        update_option('giga_ai_provider', GIGA_APW_DEFAULT_PROVIDER);
    }
    if (!get_option('giga_ai_model')) {
        update_option('giga_ai_model', GIGA_APW_DEFAULT_MODEL);
    }
    if (!get_option('giga_ai_api_key')) {
        update_option('giga_ai_api_key', '');
    }
    if (!get_option('giga_ollama_base_url')) {
        update_option('giga_ollama_base_url', 'http://localhost:11434');
    }
    
    if (!get_option('giga_apw_settings')) {
        update_option('giga_apw_settings', [
            'default_language' => 'en',
            'default_tone' => 'Professional',
            'use_quality_gate' => 1,
            'quality_gate_threshold' => GIGA_APW_QUALITY_GATE,
            'min_words' => GIGA_APW_MIN_WORDS,
            'max_words' => GIGA_APW_MAX_WORDS,
            'auto_save_draft' => 0,
            'temperature' => GIGA_APW_DEFAULT_TEMPERATURE
        ]);
    }
}

function giga_apw_deactivate()
{
    wp_clear_scheduled_hook('giga_apw_process_bulk');
}
