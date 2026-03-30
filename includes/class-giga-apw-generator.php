<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Generator {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function get_product_data($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return null;

        $attributes_data = [];
        $attributes = $product->get_attributes();
        foreach ($attributes as $attr) {
            $name = wc_attribute_label($attr->get_name());
            if ($attr->is_taxonomy()) {
                $terms = wc_get_product_terms($product_id, $attr->get_name(), ['fields' => 'names']);
                $value = implode(', ', $terms);
            } else {
                $value = implode(', ', $attr->get_options());
            }
            $attributes_data[] = ['name' => $name, 'value' => $value];
        }

        $cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
        $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
        
        $gallery_ids = $product->get_gallery_image_ids();
        $main_image_id = $product->get_image_id();
        $image_attachments = [];
        if ($main_image_id) {
            $image_attachments[] = [
                'id' => $main_image_id,
                'alt' => get_post_meta($main_image_id, '_wp_attachment_image_alt', true)
            ];
        }
        foreach ($gallery_ids as $id) {
            $image_attachments[] = [
                'id' => $id,
                'alt' => get_post_meta($id, '_wp_attachment_image_alt', true)
            ];
        }

        return [
            'id' => $product_id,
            'title' => $product->get_name(),
            'existing_description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'attributes' => $attributes_data,
            'price' => wc_price($product->get_price()),
            'sale_price' => $product->get_sale_price() ? wc_price($product->get_sale_price()) : '',
            'category_names' => !is_wp_error($cats) ? $cats : [],
            'tag_names' => !is_wp_error($tags) ? $tags : [],
            'image_count' => count($image_attachments),
            'image_attachments' => $image_attachments,
            'sku' => $product->get_sku(),
            'weight' => $product->has_weight() ? wc_format_weight($product->get_weight()) : '',
            'dimensions' => $product->has_dimensions() ? wc_format_dimensions($product->get_dimensions(false)) : '',
        ];
    }

    public function generate($product_id, $options = []) {
        $license = Giga_APW_License::get_instance();
        
        if (!$license->is_pro()) {
            if ($license->get_monthly_remaining() <= 0) {
                return new WP_Error('limit_reached', __('Free plan limit reached (5 products/month). Please upgrade to Pro.', 'giga-ai-product-writer'));
            }
        }

        $product_data = $this->get_product_data($product_id);
        if (!$product_data) {
            return new WP_Error('invalid_product', __('Invalid product ID.', 'giga-ai-product-writer'));
        }

        $settings = get_option('giga_apw_settings', []);
        
        $product_data['target_keywords'] = $options['target_keywords'] ?? '';
        $product_data['tone_override'] = $options['tone'] ?? '';
        $product_data['additional_instructions'] = $options['additional_instructions'] ?? '';
        $product_data['language'] = $options['language'] ?? $settings['default_language'] ?? 'en';

        $prompt_builder = Giga_APW_Prompt::get_instance();
        $brand_voice = null;
        if (!empty($options['use_brand_voice']) && $license->is_pro()) {
            if (class_exists('Giga_APW_Voice')) {
                $brand_voice = Giga_APW_Voice::get_instance()->get_profile();
            }
        }

        $system_prompt = $prompt_builder->build_system_prompt($brand_voice);
        $user_prompt = $prompt_builder->build_user_prompt($product_data, $settings);

        $claude = Giga_APW_Claude::get_instance();
        $response_data = $claude->generate($system_prompt, $user_prompt);

        if (is_wp_error($response_data)) {
            return $response_data;
        }

        $text_content = $response_data['content'][0]['text'] ?? '';
        $json_content = json_decode($text_content, true);

        if (!$json_content || !isset($json_content['long_description'])) {
            return new WP_Error('invalid_json', __('Claude API returned an invalid JSON structure.', 'giga-ai-product-writer'));
        }

        if (isset($json_content['meta_title']) && mb_strlen($json_content['meta_title']) > GIGA_APW_META_TITLE_MAX) {
            $json_content['meta_title'] = $this->smart_truncate($json_content['meta_title'], GIGA_APW_META_TITLE_MAX);
        }
        if (isset($json_content['meta_description']) && mb_strlen($json_content['meta_description']) > GIGA_APW_META_DESC_MAX) {
            $json_content['meta_description'] = $this->smart_truncate($json_content['meta_description'], GIGA_APW_META_DESC_MAX);
        }

        $scorer = Giga_APW_Scorer::get_instance();
        $scores = $scorer->score($json_content, $product_data, $settings);

        global $wpdb;
        $table_name = $wpdb->prefix . 'giga_apw_generations';

        $tokens_used = $response_data['usage']['input_tokens'] ?? 0;
        $tokens_used += $response_data['usage']['output_tokens'] ?? 0;

        $wpdb->insert($table_name, [
            'product_id' => $product_id,
            'model_used' => GIGA_APW_CLAUDE_MODEL,
            'long_description' => $json_content['long_description'],
            'short_description' => $json_content['short_description'] ?? '',
            'meta_title' => $json_content['meta_title'] ?? '',
            'meta_description' => $json_content['meta_description'] ?? '',
            'alt_text' => wp_json_encode($this->map_alt_texts($product_data['image_attachments'], $json_content['alt_texts'] ?? [])),
            'tags' => wp_json_encode($json_content['tags'] ?? []),
            'quality_score' => $scores['total'],
            'score_readability' => $scores['readability'],
            'score_seo' => $scores['seo'],
            'score_uniqueness' => $scores['uniqueness'],
            'score_benefits' => $scores['benefits'],
            'score_length' => $scores['length'],
            'status' => 'generated',
            'language' => $product_data['language'],
            'brand_voice_used' => $brand_voice ? 1 : 0,
            'tokens_used' => $tokens_used
        ]);

        $generation_id = $wpdb->insert_id;

        if (!$license->is_pro()) {
            $license->increment_usage();
        }

        return [
            'generation_id' => $generation_id,
            'content' => $json_content,
            'scores' => $scores,
            'monthly_remaining' => $license->get_monthly_remaining()
        ];
    }

    private function smart_truncate($string, $length) {
        if (mb_strlen($string) <= $length) return $string;
        $string = mb_substr($string, 0, $length - 3);
        $last_space = mb_strrpos($string, ' ');
        if ($last_space !== false) {
            $string = mb_substr($string, 0, $last_space);
        }
        return rtrim($string, '.,!?') . '...';
    }

    private function map_alt_texts($attachments, $generated_alts) {
        $mapped = [];
        foreach ($attachments as $index => $att) {
            $mapped[] = [
                'image_id' => $att['id'],
                'alt' => $generated_alts[$index] ?? ''
            ];
        }
        return $mapped;
    }

    public function publish($generation_id, $approved_fields) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'giga_apw_generations';
        $gen = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $generation_id));

        if (!$gen) return new WP_Error('not_found', __('Generation not found.', 'giga-ai-product-writer'));
        if (!is_array($approved_fields)) return new WP_Error('invalid_fields', __('Approved fields must be an array.', 'giga-ai-product-writer'));

        $product_id = $gen->product_id;
        $product = wc_get_product($product_id);
        if (!$product) return new WP_Error('invalid_product', __('Product no longer exists.', 'giga-ai-product-writer'));

        $seo_class = class_exists('Giga_APW_SEO') ? Giga_APW_SEO::get_instance() : null;
        $published_features = [];
        $old_data = [];

        foreach ($approved_fields as $field) {
            if ($field === 'long_description') {
                $old_data['long_description'] = $product->get_description();
                wp_update_post([
                    'ID' => $product_id,
                    'post_content' => wp_kses_post($gen->long_description)
                ]);
                $published_features[] = 'long_description';
            } elseif ($field === 'short_description') {
                $old_data['short_description'] = $product->get_short_description();
                wp_update_post([
                    'ID' => $product_id,
                    'post_excerpt' => wp_kses_post($gen->short_description)
                ]);
                $published_features[] = 'short_description';
            } elseif ($field === 'meta_title' && $seo_class) {
                $old_meta = $seo_class->read_existing_meta($product_id);
                $old_data['meta_title'] = $old_meta['meta_title'] ?? '';
                $curr_desc = $old_meta['meta_description'] ?? '';
                $seo_class->write_meta($product_id, $gen->meta_title, $curr_desc);
                $published_features[] = 'meta_title';
            } elseif ($field === 'meta_description' && $seo_class) {
                $old_meta = $seo_class->read_existing_meta($product_id);
                $old_data['meta_description'] = $old_meta['meta_description'] ?? '';
                $curr_title = $old_meta['meta_title'] ?? '';
                if (in_array('meta_title', $approved_fields)) {
                    $curr_title = $gen->meta_title;
                }
                $seo_class->write_meta($product_id, $curr_title, $gen->meta_description);
                $published_features[] = 'meta_description';
            } elseif ($field === 'tags') {
                $old_tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
                $old_data['tags'] = is_array($old_tags) ? implode(', ', $old_tags) : '';
                
                $tags = json_decode($gen->tags, true);
                if (is_array($tags)) {
                    wp_set_post_terms($product_id, $tags, 'product_tag', false);
                    $published_features[] = 'tags';
                }
            } elseif ($field === 'alt_text') {
                $alts = json_decode($gen->alt_text, true);
                if (is_array($alts)) {
                    $old_alts = [];
                    foreach ($alts as $alt_data) {
                        $old_alts[$alt_data['image_id']] = get_post_meta($alt_data['image_id'], '_wp_attachment_image_alt', true);
                        update_post_meta($alt_data['image_id'], '_wp_attachment_image_alt', sanitize_text_field($alt_data['alt']));
                    }
                    $old_data['alt_text'] = wp_json_encode($old_alts);
                    $published_features[] = 'alt_text';
                }
            }
        }

        update_post_meta($product_id, "_giga_apw_revert_{$generation_id}", wp_json_encode($old_data));

        $wpdb->update(
            $table_name,
            [
                'status' => 'published',
                'approved_fields' => wp_json_encode($published_features)
            ],
            ['id' => $generation_id]
        );

        return $published_features;
    }
}
