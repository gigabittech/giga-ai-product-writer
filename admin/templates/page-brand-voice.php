<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_pro = Giga_APW_License::get_instance()->is_pro();
// Handle case if voice class doesn't exist yet but being called
$profile = class_exists('Giga_APW_Voice') ? Giga_APW_Voice::get_instance()->get_profile() : null;
?>

<div class="giga-apw-main-container">
    <div id="giga-apw-notices-handler"></div>
    
    <div class="wrap giga-apw-wrap">
        <div class="giga-apw-header">
            <h1 class="giga-apw-title"><?php esc_html_e('Brand Voice', 'giga-ai-product-writer'); ?></h1>
            <?php if ($is_pro): ?>
                <span class="badge badge-pro">PRO UNLIMITED</span>
            <?php endif; ?>
        </div>
        
        <p class="giga-apw-description"><?php esc_html_e('Infuse your products with personality. Train Claude to understand your unique tone, vocabulary, and style in minutes.', 'giga-ai-product-writer'); ?></p>

        <div style="position:relative;">
            <?php if (!$is_pro): ?>
                <div class="giga-apw-lock-overlay">
                    <div class="giga-apw-lock-message">
                        <div class="lock-icon">🔒</div>
                        <h3><?php esc_html_e('Pro Feature', 'giga-ai-product-writer'); ?></h3>
                        <p><?php esc_html_e('Unlock bulk processing and automate your entire store catalog with one click.', 'giga-ai-product-writer'); ?></p>
                        <div class="pro-feature-list">
                            <span>✓ Generate 50+ products at once</span>
                            <span>✓ Background processing</span>
                            <span>✓ Auto-publish to WooCommerce</span>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=giga-apw-settings'); ?>" class="giga-apw-button-primary upgrade-btn"><?php esc_html_e('Upgrade to Pro', 'giga-ai-product-writer'); ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="giga-apw-voice-container <?php echo !$is_pro ? 'giga-apw-blurred' : ''; ?>" style="<?php echo !$is_pro ? 'pointer-events:none;' : ''; ?>">
                <?php if ($profile): ?>
                    <div class="giga-apw-card">
                        <div class="giga-apw-section-title">
                            <h3>🎙️ <?php esc_html_e('Active Voice Profile', 'giga-ai-product-writer'); ?></h3>
                            <p><?php esc_html_e('Your products are being generated using this persona', 'giga-ai-product-writer'); ?></p>
                        </div>

                        <div class="giga-apw-voice-grid">
                            <div class="giga-apw-voice-item">
                                <strong><?php esc_html_e('Detected Tone', 'giga-ai-product-writer'); ?></strong>
                                <span><?php echo esc_html($profile['tone'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="giga-apw-voice-item">
                                <strong><?php esc_html_e('Vocabulary Level', 'giga-ai-product-writer'); ?></strong>
                                <span><?php echo esc_html($profile['vocabulary_level'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="giga-apw-voice-item">
                                <strong><?php esc_html_e('Sentence Style', 'giga-ai-product-writer'); ?></strong>
                                <span><?php echo esc_html($profile['avg_sentence_length'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="giga-apw-voice-details">
                            <div class="giga-apw-field">
                                <label><?php esc_html_e('Brand Adjectives', 'giga-ai-product-writer'); ?></label>
                                <div class="giga-apw-chips">
                                    <?php if (!empty($profile['brand_adjectives'])): ?>
                                        <?php foreach ($profile['brand_adjectives'] as $adj): ?>
                                            <span class="giga-apw-chip"><?php echo esc_html($adj); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($profile['avoid_patterns'])): ?>
                                <div class="giga-apw-field">
                                    <label><?php esc_html_e('Optimization Focus', 'giga-ai-product-writer'); ?></label>
                                    <div class="giga-apw-warning-box">
                                        <strong>⚠️ <?php esc_html_e('Avoiding:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html(implode(', ', $profile['avoid_patterns'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="giga-apw-actions-bar">
                            <button class="button giga-apw-button-secondary" id="giga-apw-clear-voice"><?php esc_html_e('Reset & Retrain', 'giga-ai-product-writer'); ?></button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="giga-apw-card">
                        <div class="giga-apw-section-title">
                            <h3>📝 <?php esc_html_e('Train New Personality', 'giga-ai-product-writer'); ?></h3>
                            <p><?php esc_html_e('Paste 3-5 high-quality product descriptions to analyze', 'giga-ai-product-writer'); ?></p>
                        </div>
                        
                        <div class="giga-apw-voice-inputs">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="giga-apw-field">
                                    <label><?php printf(__('Example %d%s', 'giga-ai-product-writer'), $i, $i <= 3 ? ' *' : ''); ?></label>
                                    <textarea class="giga-apw-voice-example giga-apw-textarea" <?php echo $i <= 3 ? 'required' : ''; ?> placeholder="<?php esc_attr_e('Paste an example description...', 'giga-ai-product-writer'); ?>"></textarea>
                                    <div class="field-footer">
                                        <span class="giga-apw-word-count">0 words</span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="giga-apw-actions-bar">
                            <button class="giga-apw-button-primary" id="giga-apw-analyze-voice">
                                <span class="giga-apw-btn-text">🎙️ <?php esc_html_e('Analyze Brand Voice', 'giga-ai-product-writer'); ?></span>
                                <span class="giga-apw-spinner" style="display:none; margin-left: 10px;"></span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
