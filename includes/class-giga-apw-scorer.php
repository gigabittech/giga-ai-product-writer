<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Scorer {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function score($generated_content, $product_data, $settings) {
        $readability = $this->calculate_readability($generated_content['long_description']);
        $seo = $this->calculate_seo($generated_content, $product_data);
        $uniqueness = $this->calculate_uniqueness($generated_content['long_description'], $product_data['existing_description'] ?? '');
        $benefits = $this->calculate_benefits($generated_content['long_description']);
        
        $long_min = $settings['min_words'] ?? GIGA_APW_MIN_WORDS;
        $long_max = $settings['max_words'] ?? GIGA_APW_MAX_WORDS;
        $short_min = GIGA_APW_SHORT_MIN_WORDS;
        $short_max = GIGA_APW_SHORT_MAX_WORDS;
        
        $length_long = $this->calculate_length($generated_content['long_description'], $long_min, $long_max);
        $length_short = $this->calculate_length($generated_content['short_description'] ?? '', $short_min, $short_max);
        
        $length = ($length_long + $length_short) / 2;

        $total = $readability + $seo + $uniqueness + $benefits + $length;
        
        $threshold = defined('GIGA_APW_QUALITY_GATE') ? GIGA_APW_QUALITY_GATE : 70;

        if ($total >= 90) {
            $grade = 'excellent';
            $grade_label = __('Excellent', 'giga-ai-product-writer');
            $grade_color = '#22c55e';
        } elseif ($total >= $threshold) {
            $grade = 'good';
            $grade_label = __('Good', 'giga-ai-product-writer');
            $grade_color = '#3b82f6'; // Professional blue for "Good"
        } else {
            $grade = 'needs_improvement';
            $grade_label = __('Needs Improvement', 'giga-ai-product-writer');
            $grade_color = '#ef4444'; // Red for warning
        }

        return [
            'total' => (int) round($total),
            'readability' => (int) round($readability),
            'seo' => (int) round($seo),
            'uniqueness' => (int) round($uniqueness),
            'benefits' => (int) round($benefits),
            'length' => (int) round($length),
            'grade' => $grade,
            'grade_label' => $grade_label,
            'grade_color' => $grade_color,
            'warning' => $total < $threshold
        ];
    }

    private function calculate_readability($text) {
        $clean_text = wp_strip_all_tags($text);
        if (empty(trim($clean_text))) return 10;

        $word_count = str_word_count($clean_text);
        $sentences = preg_split('/[.!?]+/', $clean_text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        if ($sentence_count === 0) $sentence_count = 1;

        $words = str_word_count($clean_text, 1);
        $syllable_count = 0;
        foreach ($words as $word) {
            $syllable_count += $this->count_syllables($word);
        }

        $avg_words_per_sentence = $word_count / $sentence_count;
        $avg_syllables_per_word = $word_count > 0 ? $syllable_count / $word_count : 0;

        $fre = 206.835 - (1.015 * $avg_words_per_sentence) - (84.6 * $avg_syllables_per_word);
        
        if ($fre >= 60 && $fre <= 70) return 25;
        if ($fre >= 50 && $fre <= 80) return 20;
        if (($fre >= 30 && $fre < 50) || ($fre > 80 && $fre <= 90)) return 15;
        
        return 10;
    }

    private function count_syllables($word) {
        $word = strtolower($word);
        if (strlen($word) <= 3) return 1;
        
        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);
        $matches = [];
        preg_match_all('/[aeiouy]{1,2}/', $word, $matches);
        return count($matches[0]) ?: 1;
    }

    private function calculate_seo($content, $product_data) {
        $score = 0;
        $clean_long_desc = wp_strip_all_tags($content['long_description']);
        $total_words = str_word_count($clean_long_desc);

        if (!empty($product_data['target_keywords'])) {
            $keywords = array_map('trim', explode(',', $product_data['target_keywords']));
        } else {
            $title_clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $product_data['title']);
            $keywords = array_filter(explode(' ', strtolower($title_clean)), function($w){ return strlen($w) > 3; });
        }

        if (empty($keywords)) return 15;

        $placement_score = 0;
        $title_found = false;
        $desc_found = false;
        $meta_desc_found = false;
        $tags_found = false;

        $keyword_occurrences = 0;

        foreach ($keywords as $kw) {
            $kw = strtolower($kw);
            if (empty($kw)) continue;

            if (!$title_found && stripos($content['meta_title'], $kw) !== false) { $placement_score += 8; $title_found = true; }
            if (!$desc_found && stripos($clean_long_desc, $kw) !== false) { $placement_score += 8; $desc_found = true; }
            if (!$meta_desc_found && stripos($content['meta_description'], $kw) !== false) { $placement_score += 5; $meta_desc_found = true; }
            
            $tags_str = is_array($content['tags']) ? implode(' ', $content['tags']) : (isset($content['tags']) ? (string)$content['tags'] : '');
            if (!$tags_found && stripos($tags_str, $kw) !== false) { $placement_score += 4; $tags_found = true; }

            $keyword_occurrences += substr_count(strtolower($clean_long_desc), $kw);
        }

        $density_score = 5;
        if ($total_words > 0 && !empty($product_data['target_keywords'])) {
            $keyword_words = count($keywords);
            $density = ($keyword_occurrences) / $total_words * 100;
            if ($density >= 1 && $density <= 2) {
                $density_score = 25;
            } elseif ($density >= 0.5 && $density <= 3) {
                $density_score = 15;
            }
        } elseif (empty($product_data['target_keywords'])) {
            $density_score = $placement_score;
        }

        $total_seo = ($placement_score + $density_score) / 2;
        if (empty($product_data['target_keywords'])) {
             $total_seo = $placement_score;
        }

        return min(25, max(0, $total_seo));
    }

    private function calculate_uniqueness($long_desc, $existing_desc) {
        $long_desc = wp_strip_all_tags($long_desc);
        $existing_desc = wp_strip_all_tags($existing_desc);

        if (empty(trim($existing_desc))) return 25;

        similar_text($long_desc, $existing_desc, $perc);

        if ($perc < 20) return 25;
        if ($perc < 40) return 18;
        if ($perc < 60) return 12;
        if ($perc < 80) return 6;
        return 2;
    }

    private function calculate_benefits($long_desc) {
        $long_desc = strtolower($long_desc);
        
        $benefit_phrases = ['you will', 'you can', 'helps you', 'makes it easy', 'saves you', 'perfect for', 'ideal for', 'gives you'];
        $feature_phrases = ['made of', 'includes', 'features', 'measures', 'weighs', 'compatible with'];

        $b_count = 0;
        foreach ($benefit_phrases as $phrase) {
            $b_count += substr_count($long_desc, $phrase);
        }

        $f_count = 0;
        foreach ($feature_phrases as $phrase) {
            $f_count += substr_count($long_desc, $phrase);
        }

        if ($f_count === 0) {
            return ($b_count > 0) ? 15 : 8;
        }

        $ratio = $b_count / $f_count;

        if ($ratio > 1.5) return 15;
        if ($ratio >= 1.0) return 12;
        if ($ratio >= 0.5) return 8;
        return 4;
    }

    private function calculate_length($long_desc, $min, $max) {
        $word_count = str_word_count(wp_strip_all_tags($long_desc));
        
        if ($word_count >= $min && $word_count <= $max) return 10;
        
        $margin = 0.3; // Increase margin to 30%
        if ($word_count >= ($min * (1 - $margin)) && $word_count <= ($max * (1 + $margin))) {
            return 8; // Better score for close range
        }
        if ($word_count >= ($min * 0.8) && $word_count <= ($max * 1.2)) {
            return 5; // Still reasonable for moderate variations
        }

        return 2;
    }
}
