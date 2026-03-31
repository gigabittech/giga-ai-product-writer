<?php
if (!defined('ABSPATH')) {
    exit;
}

class Giga_APW_Prompt
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    public function build_system_prompt($brand_voice_profile = null)
    {
        $banned_phrases = [
            "game-changer",
            "elevate your",
            "seamlessly",
            "in today's fast-paced world",
            "look no further",
            "designed with you in mind",
            "take your X to the next level",
            "whether you're a seasoned pro or just starting out",
            "unlock the power of",
            "redefine the way you",
            "revolutionize",
            "cutting-edge",
            "state-of-the-art",
            "robust solution",
            "leverage",
            "synergy",
            "paradigm shift",
            "best-in-class",
            "world-class",
            "next-level",
            "game changer",
            "move the needle",
            "deep dive"
        ];

        $prompt = "You are an expert WooCommerce product copywriter. Your goal is to write natural, human-sounding product descriptions that convert well and rank high in search engines.\n\n";

        $prompt .= "CRITICAL REQUIREMENT: Always respond with valid JSON only. Do not include markdown blocks, preamble, or any other conversational text.\n\n";

        $prompt .= "Quality standards:\n- Write like a human professional, not a generic AI assistant.\n- Avoid clichés and overused marketing jargon.\n\n";

        $prompt .= "BANNED PHRASES. You MUST NOT use any of these phrases under any circumstances:\n- " . implode("\n- ", $banned_phrases) . "\n\n";

        if ($brand_voice_profile && !empty($brand_voice_profile)) {
            $prompt .= "Brand Voice Requirements:\n";
            $prompt .= "Write in a {$brand_voice_profile['tone']} tone. ";
            $prompt .= "Use {$brand_voice_profile['vocabulary_level']} vocabulary. ";
            $prompt .= "Sentences should be {$brand_voice_profile['avg_sentence_length']}. ";
            $prompt .= "{$brand_voice_profile['formatting_style']}. ";
            $prompt .= "Write from the {$brand_voice_profile['perspective']} perspective. ";

            if (!empty($brand_voice_profile['key_patterns'])) {
                $prompt .= "Emulate these patterns: " . implode(', ', $brand_voice_profile['key_patterns']) . ". ";
            }
            if (!empty($brand_voice_profile['brand_adjectives'])) {
                $prompt .= "Always use these brand adjectives where natural: " . implode(', ', $brand_voice_profile['brand_adjectives']) . ".\n\n";
            }
        }

        $prompt .= "WooCommerce context:\n- HTML formatting rules: Use <p>, <ul>, <li>, <strong> tags for structure.\n- Return the exact JSON structure requested by the user, adhering strictly to length constraints.\n\n";

        return $prompt;
    }

    public function build_user_prompt($product_data, $settings)
    {
        $min_words = $settings['min_words'];
        $max_words = $settings['max_words'];
        $language = $product_data['language'] ?? $settings['default_language'];
        $tone = $product_data['tone_override'] ?? $settings['default_tone'];

        $prompt = "Product Data:\n";
        $prompt .= "- Title: {$product_data['title']}\n";
        if (!empty($product_data['existing_description'])) {
            $prompt .= "- Existing Description: " . wp_strip_all_tags($product_data['existing_description']) . "\n";
        }
        if (!empty($product_data['short_description'])) {
            $prompt .= "- Existing Short Description: " . wp_strip_all_tags($product_data['short_description']) . "\n";
        }
        if (!empty($product_data['attributes'])) {
            $prompt .= "- Attributes: " . implode(', ', array_map(function ($a) {
                return "{$a['name']}: {$a['value']}"; }, $product_data['attributes'])) . "\n";
        }
        $prompt .= "- Price: {$product_data['price']}\n";
        if (!empty($product_data['sale_price'])) {
            $prompt .= "- Sale Price: {$product_data['sale_price']}\n";
        }
        if (!empty($product_data['category_names'])) {
            $prompt .= "- Categories: " . implode(', ', $product_data['category_names']) . "\n";
        }
        if (!empty($product_data['tag_names'])) {
            $prompt .= "- Current Tags: " . implode(', ', $product_data['tag_names']) . "\n";
        }
        $prompt .= "- Number of Images: {$product_data['image_count']}\n";
        if (!empty($product_data['sku'])) {
            $prompt .= "- SKU: {$product_data['sku']}\n";
        }
        if (!empty($product_data['weight'])) {
            $prompt .= "- Weight: {$product_data['weight']}\n";
        }
        if (!empty($product_data['dimensions'])) {
            $prompt .= "- Dimensions: {$product_data['dimensions']}\n";
        }
        if (!empty($product_data['target_keywords'])) {
            $prompt .= "- Target Keywords: {$product_data['target_keywords']}\n";
        }
        if (!empty($product_data['additional_instructions'])) {
            $prompt .= "- Additional Instructions: {$product_data['additional_instructions']}\n";
        }

        $prompt .= "\nWrite all content in language code: {$language}\n";
        $prompt .= "Target Tone: {$tone}\n\n";

        $prompt .= "EXACTLY return JSON in the following format:\n";
        $prompt .= "{\n  \"long_description\": \"<p>HTML formatted... word count must be between {$min_words} and {$max_words}</p>\",\n";
        $prompt .= "  \"short_description\": \"Plain text 30-80 words...\",\n";
        $prompt .= "  \"meta_title\": \"50-60 char keyword-optimized title\",\n";
        $prompt .= "  \"meta_description\": \"150-160 char benefit-led description\",\n";
        $prompt .= "  \"alt_texts\": [\"alt text for image 1\"" . ($product_data['image_count'] > 1 ? ", \"alt text for image 2\", ..." : "") . "],\n";
        $prompt .= "  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\", \"tag5\"],\n";
        $prompt .= "  \"generation_notes\": \"Brief note on writing approach taken\"\n}\n\n";

        $prompt .= "Hard constraints:\n";
        $prompt .= "- meta_title MUST be between 50-60 characters.\n";
        $prompt .= "- meta_description MUST be between 150-160 characters.\n";
        $prompt .= "- long_description MUST be valid HTML and between {$min_words} and {$max_words} words.\n";
        $prompt .= "- short_description MUST be plain text only, no HTML.\n";
        $prompt .= "- tags must be lowercase, no special characters.\n";
        $prompt .= "- length of alt_texts array must match the number of images ({$product_data['image_count']}).\n";
        $prompt .= "- Note for alt texts: Since direct image analysis is pending v1.2, generate highly descriptive, SEO-optimized alt text using the product title, features, and available attributes.\n";

        return $prompt;
    }

    public function build_brand_voice_analysis_prompt($examples)
    {
        $prompt = "You are a brand voice analyst. Analyze the following product description examples and extract the brand's voice profile.\n\n";
        foreach ($examples as $index => $example) {
            $prompt .= "Example " . ($index + 1) . ":\n" . $example . "\n\n";
        }

        $prompt .= "Return your analysis ONLY in the following JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"tone\": \"e.g. professional and authoritative\",\n";
        $prompt .= "  \"vocabulary_level\": \"e.g. intermediate\",\n";
        $prompt .= "  \"avg_sentence_length\": \"e.g. medium (12-18 words)\",\n";
        $prompt .= "  \"formatting_style\": \"e.g. uses bullet points for features, paragraphs for benefits\",\n";
        $prompt .= "  \"perspective\": \"e.g. second person (you/your)\",\n";
        $prompt .= "  \"key_patterns\": [\"pattern1\", \"pattern2\"],\n";
        $prompt .= "  \"brand_adjectives\": [\"adj1\", \"adj2\"],\n";
        $prompt .= "  \"avoid_patterns\": [\"pattern1\", \"pattern2\"],\n";
        $prompt .= "  \"example_opening\": \"First 30 chars of style example...\"\n";
        $prompt .= "}\n";

        return $prompt;
    }
}
