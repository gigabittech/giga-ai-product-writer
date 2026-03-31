<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_key = get_option('giga_apw_api_key', '');
$license = Giga_APW_License::get_instance();
$is_pro = $license->is_pro();
$monthly_remaining = $license->get_monthly_remaining();
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

<div class="giga-apw-metabox">
    <!-- HEADER BAR -->
    <div class="giga-apw-header">
        <div class="giga-apw-logo">🤖 Giga AI Writer</div>
        <div class="giga-apw-badge">
            <?php if ($is_pro): ?>
                <span class="badge badge-pro">PRO — Unlimited</span>
            <?php else: ?>
                <span class="badge badge-free">FREE — <?php echo esc_html(GIGA_APW_FREE_LIMIT - $monthly_remaining); ?> of <?php echo esc_html(GIGA_APW_FREE_LIMIT); ?> used this month</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($api_key)): ?>
        <div class="giga-apw-notice error">
            <p><?php printf(__('API Key is missing. Please configure it in the <a href="%s">Settings page</a> before generating content.', 'giga-ai-product-writer'), admin_url('admin.php?page=giga-apw')); ?></p>
        </div>
    <?php endif; ?>

    <!-- GENERATION OPTIONS -->
    <div class="giga-apw-options-section" id="giga-apw-options">
        <div class="giga-apw-options-grid">
            <div class="giga-apw-field">
                <label for="giga_apw_keywords"><?php _e('Target Keywords', 'giga-ai-product-writer'); ?></label>
                <input type="text" id="giga_apw_keywords" placeholder="<?php esc_attr_e('hiking boots, waterproof boots', 'giga-ai-product-writer'); ?>">
            </div>
            
            <div class="giga-apw-field">
                <label for="giga_apw_tone"><?php _e('Tone', 'giga-ai-product-writer'); ?></label>
                <select id="giga_apw_tone">
                    <?php foreach ($tones as $tone): ?>
                        <option value="<?php echo esc_attr($tone); ?>" <?php selected($default_tone, $tone); ?>><?php echo esc_html($tone); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="giga-apw-field">
                <label for="giga_apw_language"><?php _e('Language', 'giga-ai-product-writer'); ?></label>
                <select id="giga_apw_language">
                    <?php foreach ($languages as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($default_language, $code); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="giga-apw-field giga-apw-full-width">
            <label for="giga_apw_instructions"><?php _e('Additional Instructions', 'giga-ai-product-writer'); ?></label>
            <textarea id="giga_apw_instructions" placeholder="<?php esc_attr_e('Emphasize the waterproof feature. Target hikers aged 25-45.', 'giga-ai-product-writer'); ?>"></textarea>
        </div>

        <div class="giga-apw-field giga-apw-checkbox">
            <label>
                <input type="checkbox" id="giga_apw_brand_voice" <?php echo !$is_pro ? 'disabled' : ''; ?>>
                <?php _e('Use my brand voice', 'giga-ai-product-writer'); ?>
                <?php if (!$is_pro) echo '<i>(' . __('Pro only', 'giga-ai-product-writer') . ')</i>'; ?>
            </label>
        </div>

        <button type="button" id="giga-apw-generate-btn" class="button button-primary button-large giga-apw-generate-btn" <?php echo empty($api_key) ? 'disabled' : ''; ?>>
            <span class="giga-apw-btn-text">✨ <?php _e('Generate All Content', 'giga-ai-product-writer'); ?></span>
            <span class="giga-apw-spinner" style="display:none;">⏳</span>
        </button>
        <p class="giga-apw-estimate"><?php _e('Estimated time: ~8 seconds', 'giga-ai-product-writer'); ?></p>
    </div>

    <!-- QUALITY SCORE (Hidden initially) -->
    <div id="giga-apw-score-section" class="giga-apw-score-section" style="display:none;">
        <div class="giga-apw-score-header">
            <div class="giga-apw-score-badge" id="giga-apw-total-score">
                <span class="score-number">--</span><span class="score-max">/100</span>
            </div>
            <div class="giga-apw-score-label" id="giga-apw-score-label">--</div>
            <button type="button" id="giga-apw-toggle-breakdown" class="button button-secondary"><?php _e('Toggle Breakdown', 'giga-ai-product-writer'); ?></button>
        </div>
        
        <div id="giga-apw-score-breakdown" class="giga-apw-score-breakdown" style="display:none;">
            <div class="score-row"><span><?php _e('Readability', 'giga-ai-product-writer'); ?></span><strong id="giga-score-readability">--/25</strong></div>
            <div class="score-row"><span><?php _e('SEO', 'giga-ai-product-writer'); ?></span><strong id="giga-score-seo">--/25</strong></div>
            <div class="score-row"><span><?php _e('Uniqueness', 'giga-ai-product-writer'); ?></span><strong id="giga-score-uniqueness">--/25</strong></div>
            <div class="score-row"><span><?php _e('Benefit Ratio', 'giga-ai-product-writer'); ?></span><strong id="giga-score-benefits">--/15</strong></div>
            <div class="score-row"><span><?php _e('Length', 'giga-ai-product-writer'); ?></span><strong id="giga-score-length">--/10</strong></div>
        </div>
    </div>

    <!-- CONTENT PREVIEW (Hidden initially) -->
    <div id="giga-apw-preview-section" class="giga-apw-preview-section" style="display:none;">
        <input type="hidden" id="giga-apw-generation-id" value="">
        <div id="giga-apw-panels-container"></div>
        
        <!-- ACTION BAR -->
        <div class="giga-apw-action-bar" style="display: flex; justify-content: space-between; align-items: center;">
            <label><input type="checkbox" id="giga-apw-select-all" checked> <?php _e('Select All', 'giga-ai-product-writer'); ?></label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" id="giga-apw-history-btn" class="button-link"><?php _e('Version History', 'giga-ai-product-writer'); ?></button>
                <button type="button" id="giga-apw-regen-btn" class="button button-secondary"><?php _e('Regenerate', 'giga-ai-product-writer'); ?></button>
                <button type="button" id="giga-apw-publish-btn" class="giga-apw-button-primary"><?php printf(__('Publish Approved (%s)', 'giga-ai-product-writer'), '<span id="giga-apw-approved-count">6</span>'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- VERSION HISTORY MODAL (Hidden) -->
<div id="giga-apw-history-modal" class="giga-apw-modal" style="display:none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 600px; background: #fff; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); z-index: 99999;">
    <div class="giga-apw-modal-content">
        <span class="giga-apw-close" style="float: right; cursor: pointer; font-size: 20px;">&times;</span>
        <h2><?php _e('Version History', 'giga-ai-product-writer'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'giga-ai-product-writer'); ?></th>
                    <th><?php _e('Score', 'giga-ai-product-writer'); ?></th>
                    <th><?php _e('Status', 'giga-ai-product-writer'); ?></th>
                    <th><?php _e('Actions', 'giga-ai-product-writer'); ?></th>
                </tr>
            </thead>
            <tbody id="giga-apw-history-tbody">
                <!-- Populated via AJAX -->
            </tbody>
        </table>
    </div>
</div>
