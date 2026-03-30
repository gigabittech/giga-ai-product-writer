<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_pro = Giga_APW_License::get_instance()->is_pro();
// Handle case if voice class doesn't exist yet but being called
$profile = class_exists('Giga_APW_Voice') ? Giga_APW_Voice::get_instance()->get_profile() : null;
?>

<div class="wrap giga-apw-wrap">
    <h1><?php esc_html_e('Brand Voice Training (Pro)', 'giga-ai-product-writer'); ?></h1>
    
    <p><?php esc_html_e('Train your brand voice — paste 3-5 example product descriptions below. Claude will analyze your tone, vocabulary, and style. All future generations will match.', 'giga-ai-product-writer'); ?></p>

    <div style="position:relative;">
        <?php if (!$is_pro): ?>
            <div class="giga-apw-lock-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:10; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(2px);">
                <div class="giga-apw-lock-message" style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.1); text-align:center;">
                    <h3 style="margin-top:0;"><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Pro Feature', 'giga-ai-product-writer'); ?></h3>
                    <p><?php esc_html_e('Upgrade to Pro to train your custom brand voice.', 'giga-ai-product-writer'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=giga-apw'); ?>" class="button button-primary"><?php esc_html_e('Upgrade / Enter License', 'giga-ai-product-writer'); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <div class="giga-apw-voice-container <?php echo !$is_pro ? 'giga-apw-blurred' : ''; ?>" style="<?php echo !$is_pro ? 'opacity:0.3; pointer-events:none;' : ''; ?>">
            <?php if ($profile): ?>
                <div class="giga-apw-voice-profile card" style="max-width:800px; padding:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Active Voice Profile', 'giga-ai-product-writer'); ?></h2>
                    <p><strong><?php esc_html_e('Detected Tone:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html($profile['tone'] ?? ''); ?></p>
                    <p><strong><?php esc_html_e('Vocabulary Level:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html($profile['vocabulary_level'] ?? ''); ?></p>
                    <p><strong><?php esc_html_e('Sentence Style:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html($profile['avg_sentence_length'] ?? ''); ?></p>
                    <p><strong><?php esc_html_e('Formatting:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html($profile['formatting_style'] ?? ''); ?></p>
                    <p><strong><?php esc_html_e('Perspective:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html($profile['perspective'] ?? ''); ?></p>
                    
                    <?php if (!empty($profile['brand_adjectives'])): ?>
                        <p><strong><?php esc_html_e('Brand Keywords:', 'giga-ai-product-writer'); ?></strong> 
                            <?php foreach ($profile['brand_adjectives'] as $adj): ?>
                                <span class="giga-apw-chip" style="background:#f0f0f1; padding:3px 8px; border-radius:12px; margin-right:5px; font-size:12px;"><?php echo esc_html($adj); ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($profile['avoid_patterns'])): ?>
                        <p class="giga-apw-error-text" style="color:#d63638;"><strong>⚠️ <?php esc_html_e('Patterns to avoid:', 'giga-ai-product-writer'); ?></strong> <?php echo esc_html(implode(', ', $profile['avoid_patterns'])); ?></p>
                    <?php endif; ?>

                    <p style="margin-top:20px;">
                        <button class="button button-secondary" id="giga-apw-clear-voice"><?php esc_html_e('Clear & Retrain', 'giga-ai-product-writer'); ?></button>
                    </p>
                </div>
            <?php else: ?>
                <div class="giga-apw-voice-form card" style="max-width:800px; padding:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Analyze New Voice', 'giga-ai-product-writer'); ?></h2>
                    
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="giga-apw-field" style="margin-bottom:15px;">
                            <label style="display:block; font-weight:600; margin-bottom:5px;"><?php printf(__('Example %d%s', 'giga-ai-product-writer'), $i, $i <= 3 ? ' *' : ''); ?></label>
                            <textarea class="giga-apw-voice-example" <?php echo $i <= 3 ? 'required' : ''; ?> placeholder="<?php esc_attr_e('Paste a product description that represents your brand voice...', 'giga-ai-product-writer'); ?>" rows="4" style="width:100%"></textarea>
                            <small class="giga-apw-word-count" style="color:#646970;">0 words</small>
                        </div>
                    <?php endfor; ?>

                    <p style="margin-top:20px;">
                        <button class="button button-primary button-large" id="giga-apw-analyze-voice">
                            <span class="giga-apw-analyze-text">🎙️ <?php esc_html_e('Analyze My Brand Voice', 'giga-ai-product-writer'); ?></span>
                            <span class="giga-apw-spinner" style="display:none;">⏳</span>
                        </button>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
