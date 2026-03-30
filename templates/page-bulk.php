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
    <h1><?php esc_html_e('Bulk Generation (Pro)', 'giga-ai-product-writer'); ?></h1>
    
    <p><?php esc_html_e('Scale your store effortlessly. Generate and publish product descriptions for hundreds of products in minutes.', 'giga-ai-product-writer'); ?></p>

    <div style="position:relative;">
        <?php if (!$is_pro): ?>
            <div class="giga-apw-lock-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:10; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(2px);">
                <div class="giga-apw-lock-message" style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.1); text-align:center;">
                    <h3 style="margin-top:0;"><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Pro Feature', 'giga-ai-product-writer'); ?></h3>
                    <p><?php esc_html_e('Upgrade to Pro to access bulk operations.', 'giga-ai-product-writer'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=giga-apw'); ?>" class="button button-primary"><?php esc_html_e('Upgrade / Enter License', 'giga-ai-product-writer'); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <div class="giga-apw-bulk-container <?php echo !$is_pro ? 'giga-apw-blurred' : ''; ?>" style="<?php echo !$is_pro ? 'opacity:0.3; pointer-events:none;' : ''; ?>">
            <div class="card" style="max-width:900px; padding:30px;">
                <div class="giga-apw-bulk-filters" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px;">
                    <div class="giga-apw-field">
                        <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e('Filter By Category', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_category" style="width:100%">
                            <option value="0"><?php esc_html_e('All Categories', 'giga-ai-product-writer'); ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="giga-apw-field">
                        <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e('Language', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_language" style="width:100%">
                            <?php foreach ($languages as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($default_language, $code); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="giga-apw-field">
                        <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e('Tone', 'giga-ai-product-writer'); ?></label>
                        <select id="giga_apw_bulk_tone" style="width:100%">
                            <?php foreach ($tones as $tone): ?>
                                <option value="<?php echo esc_attr($tone); ?>" <?php selected($default_tone, $tone); ?>><?php echo esc_html($tone); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="giga-apw-bulk-options" style="margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <label style="margin-right: 20px;">
                        <input type="checkbox" id="giga_apw_bulk_brand_voice" checked> <?php esc_html_e('Apply Brand Voice', 'giga-ai-product-writer'); ?>
                    </label>
                    <label>
                        <input type="checkbox" id="giga_apw_bulk_auto_publish"> <?php esc_html_e('Auto-publish (Skip manual approval)', 'giga-ai-product-writer'); ?>
                    </label>
                </div>

                <div class="giga-apw-bulk-actions">
                    <button id="giga-apw-bulk-start" class="button button-primary button-large" style="min-width: 200px;">🚀 <?php esc_html_e('Start Bulk Process', 'giga-ai-product-writer'); ?></button>
                    <button id="giga-apw-bulk-stop" class="button button-secondary" style="display:none;"><?php esc_html_e('Pause', 'giga-ai-product-writer'); ?></button>
                </div>

                <div id="giga-apw-bulk-progress-wrap" style="display:none; margin-top: 30px;">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span id="giga-apw-bulk-status"><?php esc_html_e('Processing...', 'giga-ai-product-writer'); ?></span>
                        <span id="giga-apw-bulk-percentage">0%</span>
                    </div>
                    <div style="background: #e5e7eb; height: 12px; border-radius: 6px; overflow: hidden;">
                        <div id="giga-apw-bulk-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #7C3AED 0%, #A855F7 100%); transition: width 0.3s ease;"></div>
                    </div>
                    <p style="text-align: right; margin-top: 5px; color: #6b7280; font-size: 13px;">
                        <span id="giga-apw-bulk-count">0</span> / <span id="giga-apw-bulk-total">0</span> products complete
                    </p>
                    
                    <div id="giga-apw-bulk-logs" style="margin-top: 20px; height: 150px; overflow-y: auto; background: #1f2937; color: #fff; padding: 15px; font-family: monospace; font-size: 12px; border-radius: 4px;">
                        <div>[SYSTEM] Batch initialized...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
