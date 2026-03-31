<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_pro = Giga_APW_License::get_instance()->is_pro();
$categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
$settings = get_option('giga_apw_settings', []);

$tones = ['Professional', 'Casual', 'Technical', 'Luxury', 'Playful', 'Auto-detect'];
$default_tone = $settings['default_tone'] ?? 'Professional';

$languages = [
    'en' => 'English',
    'es' => 'Spanish',
    'fr' => 'French',
    'de' => 'German',
    'bn' => 'Bengali',
];

$default_language = $settings['default_language'] ?? 'en';
?>

<div class="wrap giga-apw-wrap">
    <div class="giga-apw-header">
        <h1 class="giga-apw-title"><?php esc_html_e('Bulk Generation', 'giga-ai-product-writer'); ?></h1>
        <?php if ($is_pro): ?>
            <span class="badge badge-pro">PRO UNLIMITED</span>
        <?php endif; ?>
    </div>
    
    <p class="giga-apw-description"><?php esc_html_e('Scale your e-commerce empire. Generate SEO-optimized content for hundreds of products simultaneously with a single click.', 'giga-ai-product-writer'); ?></p>

    <div style="position:relative;">
        <?php if (!$is_pro): ?>
            <div class="giga-apw-lock-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:10; display:flex; align-items:center; justify-content:center;">
                <div class="giga-apw-lock-message">
                    <h3><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Pro Feature', 'giga-ai-product-writer'); ?></h3>
                    <p><?php esc_html_e('Upgrade to Pro to access bulk operations.', 'giga-ai-product-writer'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=giga-apw'); ?>" class="giga-apw-button-primary"><?php esc_html_e('Upgrade / Enter License', 'giga-ai-product-writer'); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <div class="giga-apw-bulk-container <?php echo !$is_pro ? 'giga-apw-blurred' : ''; ?>" style="<?php echo !$is_pro ? 'pointer-events:none;' : ''; ?>">
            <div class="giga-apw-card">
                <div class="giga-apw-section-title">
                    <h3>🎯 <?php esc_html_e('Configure Batch', 'giga-ai-product-writer'); ?></h3>
                    <p><?php esc_html_e('Select target products and global generation settings', 'giga-ai-product-writer'); ?></p>
                </div>

                <div class="giga-apw-options-grid">
                    <div class="giga-apw-field">
                        <label><?php esc_html_e('Product Category', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_category" class="giga-apw-select">
                            <option value="0"><?php esc_html_e('All Categories', 'giga-ai-product-writer'); ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="giga-apw-field">
                        <label><?php esc_html_e('Selection Filter', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_filter" class="giga-apw-select">
                            <option value="all"><?php esc_html_e('All Products (Max 50)', 'giga-ai-product-writer'); ?></option>
                            <option value="thin"><?php esc_html_e('No/Thin Descriptions (< 50 words)', 'giga-ai-product-writer'); ?></option>
                        </select>
                    </div>

                    <div class="giga-apw-field">
                        <label><?php esc_html_e('Content Language', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_language" class="giga-apw-select">
                            <?php foreach ($languages as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($default_language, $code); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="giga-apw-field">
                        <label><?php esc_html_e('Generation Tone', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_tone" class="giga-apw-select">
                            <?php foreach ($tones as $tone): ?>
                                <option value="<?php echo esc_attr($tone); ?>" <?php selected($default_tone, $tone); ?>><?php echo esc_html($tone); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="giga-apw-bulk-toggles">
                    <div class="giga-apw-toggle-card">
                        <label class="giga-apw-toggle-label">
                            <input type="checkbox" id="giga_apw_bulk_brand_voice" checked>
                            <div class="toggle-info">
                                <strong><?php esc_html_e('Apply Brand Voice', 'giga-ai-product-writer'); ?></strong>
                                <span><?php esc_html_e('Use your trained AI persona', 'giga-ai-product-writer'); ?></span>
                            </div>
                        </label>
                    </div>
                    <div class="giga-apw-toggle-card">
                        <label class="giga-apw-toggle-label">
                            <input type="checkbox" id="giga_apw_bulk_auto_publish">
                            <div class="toggle-info">
                                <strong><?php esc_html_e('Auto-publish', 'giga-ai-product-writer'); ?></strong>
                                <span><?php esc_html_e('Skip manual review', 'giga-ai-product-writer'); ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="giga-apw-bulk-actions">
                    <button id="giga-apw-bulk-start" class="giga-apw-button-primary" style="min-width: 200px;">🚀 <?php esc_html_e('Start Bulk Process', 'giga-ai-product-writer'); ?></button>
                    <button id="giga-apw-bulk-stop" class="button giga-apw-button-secondary" style="display:none;"><?php esc_html_e('Pause', 'giga-ai-product-writer'); ?></button>
                </div>

                <div id="giga-apw-bulk-progress-wrap" style="display:none;">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span id="giga-apw-bulk-status" style="font-weight:600; font-size:16px;"><?php esc_html_e('Processing...', 'giga-ai-product-writer'); ?></span>
                        <span id="giga-apw-bulk-percentage" style="font-weight:800; color:var(--giga-primary); font-size:18px;">0%</span>
                    </div>
                    <div style="background: var(--giga-border); height: 16px; border-radius: 8px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                        <div id="giga-apw-bulk-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--giga-primary) 0%, var(--giga-secondary) 100%); transition: width 0.3s ease; box-shadow: 0 0 10px rgba(99,102,241,0.5);"></div>
                    </div>
                    <p style="text-align: right; margin-top: 8px; color: var(--giga-text-muted); font-size: 14px; margin-bottom: 20px;">
                        <strong id="giga-apw-bulk-count" style="color:var(--giga-text-main);">0</strong> / <span id="giga-apw-bulk-total">0</span> products complete
                    </p>
                    
                    <div id="giga-apw-bulk-logs" style="height: 180px; overflow-y: auto; background: var(--giga-bg-dark); color: #e2e8f0; padding: 20px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 13px; border-radius: var(--giga-radius); line-height:1.6;">
                        <div style="color:var(--giga-success);">[SYSTEM] Batch initialized...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
