<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option('giga_apw_settings', []);
$api_key = get_option('giga_apw_api_key', '');
$license_key = get_option('giga_apw_license_key', '');
$is_pro = Giga_APW_License::get_instance()->is_pro();
$monthly_remaining = Giga_APW_License::get_instance()->get_monthly_remaining();
?>

<div class="wrap giga-apw-wrap">
    <h1><?php esc_html_e('Giga AI Product Writer - Settings', 'giga-ai-product-writer'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('giga_apw_settings_group'); ?>

        <h2><?php esc_html_e('Claude API Configuration', 'giga-ai-product-writer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="giga_apw_api_key"><?php esc_html_e('API Key', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <input type="password" id="giga_apw_api_key" name="giga_apw_api_key" value="<?php echo esc_attr($api_key ? '********' : ''); ?>" class="regular-text" />
                    <button type="button" class="button" id="giga-apw-test-connection"><?php esc_html_e('Test Connection', 'giga-ai-product-writer'); ?></button>
                    <span id="giga-apw-test-result"></span>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Plan Status', 'giga-ai-product-writer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Current Plan', 'giga-ai-product-writer'); ?></th>
                <td>
                    <?php if ($is_pro) : ?>
                        <span class="badge badge-pro"><?php esc_html_e('PRO - Unlimited', 'giga-ai-product-writer'); ?></span>
                    <?php else : ?>
                        <span class="badge badge-free"><?php printf(esc_html__('FREE - %d of %d products used this month', 'giga-ai-product-writer'), GIGA_APW_FREE_LIMIT - $monthly_remaining, GIGA_APW_FREE_LIMIT); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="giga_apw_license_key"><?php esc_html_e('License Key', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <input type="text" id="giga_apw_license_key" name="giga_apw_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                    <button type="button" class="button button-primary" id="giga-apw-activate-license"><?php esc_html_e('Activate', 'giga-ai-product-writer'); ?></button>
                    <a href="https://gigaverse.io/pricing" target="_blank" class="button"><?php esc_html_e('Upgrade to Pro', 'giga-ai-product-writer'); ?></a>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Default Generation Settings', 'giga-ai-product-writer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="giga_apw_settings[default_language]"><?php esc_html_e('Default Language', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <select id="giga_apw_settings[default_language]" name="giga_apw_settings[default_language]">
                        <option value="en" <?php selected($settings['default_language'] ?? 'en', 'en'); ?>>English</option>
                        <option value="es" <?php selected($settings['default_language'] ?? '', 'es'); ?>>Spanish</option>
                        <option value="fr" <?php selected($settings['default_language'] ?? '', 'fr'); ?>>French</option>
                        <option value="de" <?php selected($settings['default_language'] ?? '', 'de'); ?>>German</option>
                        <option value="bn" <?php selected($settings['default_language'] ?? '', 'bn'); ?>>Bengali</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="giga_apw_settings[default_tone]"><?php esc_html_e('Default Tone', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <select id="giga_apw_settings[default_tone]" name="giga_apw_settings[default_tone]">
                        <?php
                        $tones = ['Professional', 'Casual', 'Technical', 'Luxury', 'Playful'];
                        foreach ($tones as $tone) {
                            echo '<option value="' . esc_attr($tone) . '" ' . selected($settings['default_tone'] ?? 'Professional', $tone, false) . '>' . esc_html($tone) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="giga_apw_settings[use_quality_gate]"><?php esc_html_e('Quality Score Gate', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="giga_apw_settings[use_quality_gate]" value="1" <?php checked($settings['use_quality_gate'] ?? 1, 1); ?> />
                            <?php esc_html_e('Require minimum quality score to publish automatically', 'giga-ai-product-writer'); ?>
                        </label>
                        <br>
                        <label>
                            <?php esc_html_e('Threshold:', 'giga-ai-product-writer'); ?>
                            <input type="number" name="giga_apw_settings[quality_gate_threshold]" value="<?php echo esc_attr($settings['quality_gate_threshold'] ?? 70); ?>" min="0" max="100" class="small-text" />
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Content Preferences', 'giga-ai-product-writer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php esc_html_e('Long Description Length', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <label>
                        <?php esc_html_e('Min Words:', 'giga-ai-product-writer'); ?>
                        <input type="number" name="giga_apw_settings[min_words]" value="<?php echo esc_attr($settings['min_words'] ?? 150); ?>" class="small-text" />
                    </label>
                    <label>
                        <?php esc_html_e('Max Words:', 'giga-ai-product-writer'); ?>
                        <input type="number" name="giga_apw_settings[max_words]" value="<?php echo esc_attr($settings['max_words'] ?? 500); ?>" class="small-text" />
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="giga_apw_settings[auto_save_draft]"><?php esc_html_e('Auto-save Draft', 'giga-ai-product-writer'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="giga_apw_settings[auto_save_draft]" value="1" <?php checked($settings['auto_save_draft'] ?? 0, 1); ?> />
                        <?php esc_html_e('Automatically save generated content to product draft before publishing', 'giga-ai-product-writer'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
