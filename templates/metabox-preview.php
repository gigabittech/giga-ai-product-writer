<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="giga-apw-preview-box" id="giga-apw-preview-<?php echo esc_attr($field_id); ?>">
    <div class="giga-apw-preview-header">
        <label>
            <input type="checkbox" class="giga-apw-field-checkbox" value="<?php echo esc_attr($field_id); ?>" checked>
            <strong><?php echo esc_html($label); ?></strong>
            <?php if ($seo_badge): ?>
                <span class="badge badge-seo"><?php echo esc_html($seo_badge); ?></span>
            <?php endif; ?>
        </label>
        <span class="giga-apw-meta-indicator"><?php echo esc_html($indicator); ?></span>
    </div>
    <div class="giga-apw-preview-grid">
        <div class="giga-apw-preview-col current">
            <div class="col-label"><?php esc_html_e('CURRENT', 'giga-ai-product-writer'); ?></div>
            <div class="col-content"><?php echo wp_kses_post($current_content); ?></div>
        </div>
        <div class="giga-apw-preview-col generated">
            <div class="col-label"><?php esc_html_e('GENERATED', 'giga-ai-product-writer'); ?></div>
            <div class="col-content"><?php echo wp_kses_post($generated_content); ?></div>
        </div>
    </div>
</div>
