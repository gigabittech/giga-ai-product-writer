# 🤖 Giga AI Product Writer for WooCommerce — AI Agent Master Build Guide

> **For Antigravity AI Agent:** This is your complete project specification. Read everything before writing a single line of code. Follow the phases in order. Never skip a phase. Each phase must be fully working before moving to the next.

---

## 📋 PROJECT OVERVIEW

**Plugin Name:** Giga AI Product Writer for WooCommerce  
**WP.org Slug:** `giga-ai-product-writer`  
**Tagline:** "Write like a human. Rank like a machine. Powered by Claude."  
**AI Engine:** Anthropic Claude API (claude-sonnet-4-20250514)  
**License:** GPL v2+  
**Free Plan:** 5 products/month  
**Pro Plan:** $99/year — unlimited products, bulk generation, SEO meta writing  

### What This Plugin Does
A WooCommerce product description generator powered by Claude AI. For any WooCommerce product, it generates **6 content types in one click:**
1. Long product description (150–500 words, HTML formatted)
2. Short product description (30–80 words)
3. SEO meta title (50–60 characters) → writes directly to Yoast or Rank Math
4. SEO meta description (150–160 characters) → writes directly to Yoast or Rank Math
5. Image alt text (80–125 characters per image)
6. Product tags (5–10 tags)

Every output gets a **Quality Score (0–100)** before going live. Merchants can preview, approve per-field, then publish in one click.

---

## 🏗️ COMPLETE FILE STRUCTURE

Create this exact structure from the start:

```
giga-ai-product-writer/
├── giga-ai-product-writer.php          # Main plugin bootstrap file
├── includes/
│   ├── class-giga-apw-core.php         # Core: hooks, init, constants
│   ├── class-giga-apw-claude.php       # Claude API HTTP client
│   ├── class-giga-apw-prompt.php       # 5-layer prompt builder
│   ├── class-giga-apw-generator.php    # Single product generation engine
│   ├── class-giga-apw-bulk.php         # Bulk generation via WP Cron (Pro)
│   ├── class-giga-apw-scorer.php       # Quality scoring engine (0-100)
│   ├── class-giga-apw-seo.php          # Yoast + Rank Math meta field writer
│   ├── class-giga-apw-preview.php      # Side-by-side preview renderer
│   ├── class-giga-apw-admin.php        # All admin pages + meta box
│   ├── class-giga-apw-license.php      # Pro license verification
│   └── class-giga-apw-ajax.php         # All AJAX handlers
├── assets/
│   ├── css/
│   │   └── giga-apw-admin.css          # Admin styles only (no frontend CSS)
│   └── js/
│       └── giga-apw-admin.js           # Admin JS (generation, preview, bulk progress)
├── templates/
│   ├── metabox-main.php                # Product edit screen meta box
│   ├── metabox-preview.php             # Side-by-side preview component
│   ├── page-bulk.php                   # Bulk generation admin page
│   └── page-settings.php              # Settings page (API key, license)
├── languages/
│   └── giga-ai-product-writer.pot
├── readme.txt                          # WP.org readme
└── uninstall.php                       # Clean uninstall
```

---

## 🗄️ DATABASE SCHEMA

Create this custom table on plugin activation. Do NOT use postmeta for generation history — it won't scale with bulk operations.

```sql
CREATE TABLE {prefix}_giga_apw_generations (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id      BIGINT(20) UNSIGNED NOT NULL,
    generated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    model_used      VARCHAR(100) NOT NULL DEFAULT 'claude-sonnet-4-20250514',
    
    -- Generated content (all 6 types)
    long_description    LONGTEXT,
    short_description   TEXT,
    meta_title          VARCHAR(100),
    meta_description    VARCHAR(200),
    alt_text            TEXT,        -- JSON array: [{image_id: X, alt: "..."}]
    tags                TEXT,        -- JSON array: ["tag1", "tag2", ...]
    
    -- Quality scoring
    quality_score       TINYINT UNSIGNED DEFAULT 0,
    score_readability   TINYINT UNSIGNED DEFAULT 0,
    score_seo           TINYINT UNSIGNED DEFAULT 0,
    score_uniqueness    TINYINT UNSIGNED DEFAULT 0,
    score_benefits      TINYINT UNSIGNED DEFAULT 0,
    score_length        TINYINT UNSIGNED DEFAULT 0,
    
    -- Status tracking
    status              ENUM('generated','approved','published','rejected') DEFAULT 'generated',
    approved_fields     TEXT,   -- JSON array of approved field names
    error_message       TEXT,
    
    -- Meta
    language            VARCHAR(10) DEFAULT 'en',
    tokens_used         INT UNSIGNED DEFAULT 0,
    
    PRIMARY KEY (id),
    KEY product_id (product_id),
    KEY status (status),
    KEY generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Also use these `wp_options` keys:
```
giga_apw_api_key          → Encrypted Claude API key
giga_apw_license_key      → Pro license key
giga_apw_license_status   → 'free' | 'pro'
giga_apw_monthly_count    → Free plan usage count (reset monthly)
giga_apw_monthly_reset    → Timestamp of last monthly reset
giga_apw_bulk_progress    → Serialized bulk job progress
giga_apw_settings         → Serialized plugin settings
```

---

## 🔑 SECURITY RULES — NEVER VIOLATE THESE

```php
// 1. Every AJAX handler MUST verify nonce
check_ajax_referer('giga_apw_nonce', 'nonce');

// 2. Every AJAX handler MUST check capabilities
if (!current_user_can('edit_products')) {
    wp_send_json_error(['message' => 'Insufficient permissions'], 403);
}

// 3. API key MUST be encrypted before storing
// Store: update_option('giga_apw_api_key', giga_apw_encrypt($api_key));
// Read: giga_apw_decrypt(get_option('giga_apw_api_key'));

// 4. NEVER expose API key to frontend/JS
// 5. Sanitize ALL user inputs before using in prompts
// 6. Escape ALL outputs before rendering in HTML
// 7. Rate limit: max 10 generations per minute per site
```

---

## 📦 PHASE 1: Plugin Foundation & Settings
**Goal:** Plugin activates cleanly, creates DB table, settings page works, API key can be saved.

### 1.1 — Main Plugin File (`giga-ai-product-writer.php`)

```
Build the main plugin bootstrap file with:

Plugin headers:
- Plugin Name: Giga AI Product Writer for WooCommerce
- Plugin URI: https://gigaverse.io/plugins/giga-ai-product-writer
- Description: Claude-powered WooCommerce product description generator with quality scoring, brand voice training, and direct Yoast/Rank Math integration.
- Version: 1.0.0
- Requires at least: 6.0
- Requires PHP: 8.0
- Author: Roknuzzaman Sajib
- License: GPL v2 or later
- Text Domain: giga-ai-product-writer
- Domain Path: /languages
- WC requires at least: 7.0
- WC tested up to: 9.0

Bootstrap logic:
- Define constants: GIGA_APW_VERSION, GIGA_APW_PLUGIN_DIR, GIGA_APW_PLUGIN_URL, GIGA_APW_PLUGIN_FILE
- Check WooCommerce is active — if not, show admin notice and return
- Require all class files from includes/
- Initialize Core class on plugins_loaded hook (priority 10)
- Register activation hook → create DB table, set defaults
- Register deactivation hook → clear scheduled WP Cron events
- Register uninstall → uninstall.php handles cleanup
```

### 1.2 — Core Class (`class-giga-apw-core.php`)

```
Build the Core class that:
- Is a singleton (getInstance() pattern)
- On init: loads text domain, initializes all other classes
- Registers admin menu: 
    Main menu: "Giga AI Writer" with dashicon dashicons-edit-large
    Submenus: Bulk Generate (Pro), Brand Voice (Pro), Settings
- Enqueues admin CSS and JS only on WooCommerce product edit screens 
  and Giga AI Writer admin pages
- Passes to JS via wp_localize_script:
    ajax_url, nonce, is_pro (bool), monthly_remaining (int), strings (i18n)
```

### 1.3 — Settings Page (`templates/page-settings.php` + admin class)

```
Build the Settings admin page with these sections:

Section 1: Claude API Configuration
- API Key field (password input, masked)
- "Test Connection" button → AJAX call → attempts Claude API ping → shows success/error
- Save button → encrypts and stores key

Section 2: Plan Status
- Shows: Free Plan (X of 5 products used this month) OR Pro Plan (Unlimited)
- Pro: License key input + Activate button
- Link to upgrade

Section 3: Default Generation Settings
- Default language dropdown (20+ languages)
- Default tone dropdown: Professional, Casual, Technical, Luxury, Playful
- Include quality score gate: checkbox + threshold input (default: 70)

Section 4: Content Preferences  
- Min words for long description (default: 150)
- Max words for long description (default: 500)
- Auto-save generated content to draft (checkbox)

All settings saved to giga_apw_settings option as array.
Use WordPress Settings API (register_setting, add_settings_section, add_settings_field).
Show success notice on save.
```

---

## 📦 PHASE 2: Claude API Client & Prompt Engine
**Goal:** Claude API calls work, prompts are structured correctly, JSON responses parse cleanly.

### 2.1 — Claude API Client (`class-giga-apw-claude.php`)

```
Build the Claude API HTTP client:

Class: Giga_APW_Claude

Properties:
- $api_key (string)
- $model = 'claude-sonnet-4-20250514'
- $max_tokens = 2500
- $temperature = 0.7
- $api_endpoint = 'https://api.anthropic.com/v1/messages'

Method: generate($system_prompt, $user_prompt)
- Makes POST request via wp_remote_post()
- Headers: Content-Type: application/json, x-api-key, anthropic-version: 2023-06-01
- Body: model, max_tokens, system (system_prompt), messages array
- Timeout: 60 seconds
- On WP_Error: return WP_Error with message
- On non-200 HTTP: return WP_Error with API error message
- On success: return decoded response content text
- Log token usage to generation record

Method: test_connection()
- Sends minimal test message ("Say OK")
- Returns true on success, WP_Error on failure

Error handling:
- Catch rate limit errors (429) → return specific error message
- Catch timeout → return timeout error message
- Catch invalid API key (401) → return auth error message
- All errors should be human-readable for admin display
```

### 2.2 — Prompt Builder (`class-giga-apw-prompt.php`)

```
Build the 5-layer prompt architecture:

Class: Giga_APW_Prompt

Method: build_system_prompt($brand_voice_profile = null)
Returns system prompt string with these layers:
  Layer 1 - Role: "You are an expert WooCommerce product copywriter..."
  Layer 2 - Output format: Always respond with valid JSON only, no markdown, no preamble
  Layer 3 - Quality standards: natural human writing, no AI clichés
  Layer 4 - Brand voice overlay (if profile provided): inject tone/style instructions
  Layer 5 - WooCommerce context: HTML formatting rules, field length constraints

BANNED PHRASES — inject into system prompt as hard rules:
Never use any of these phrases:
"game-changer", "elevate your", "seamlessly", "in today's fast-paced world",
"look no further", "designed with you in mind", "take your X to the next level",
"whether you're a seasoned pro or just starting out", "unlock the power of",
"redefine the way you", "revolutionize", "cutting-edge", "state-of-the-art",
"robust solution", "leverage", "synergy", "paradigm shift", "best-in-class",
"world-class", "next-level", "game changer", "move the needle", "deep dive"

Method: build_user_prompt($product_data, $settings)
Takes product data array and returns user prompt string.

Product data array structure:
{
  title: string,
  existing_description: string,
  short_description: string,
  attributes: array [{name, value}],
  price: string,
  sale_price: string,
  category_names: array,
  tag_names: array,
  image_count: int,
  sku: string,
  weight: string,
  dimensions: string,
  target_keywords: string (optional, user-provided),
  tone_override: string (optional),
  additional_instructions: string (optional),
  language: string (e.g. 'en', 'bn', 'de')
}

Required JSON output format — instruct Claude to return EXACTLY this structure:
{
  "long_description": "<p>HTML formatted...</p>",
  "short_description": "Plain text 30-80 words...",
  "meta_title": "50-60 char keyword-optimized title",
  "meta_description": "150-160 char benefit-led description",
  "alt_texts": ["alt text for image 1", "alt text for image 2"],
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"],
  "generation_notes": "Brief note on writing approach taken"
}

Hard constraints to include in prompt:
- meta_title MUST be between 50-60 characters (count carefully)
- meta_description MUST be between 150-160 characters (count carefully)  
- long_description MUST be valid HTML with <p>, <ul>, <li>, <strong> tags
- long_description word count between {min_words} and {max_words}
- short_description is plain text only, no HTML
- tags must be lowercase, no special characters
- All content in language: {language}

Method: build_brand_voice_analysis_prompt($examples)
Returns prompt to analyze 3-5 example descriptions and extract voice profile.
Output should be JSON: {tone, vocabulary_level, avg_sentence_length, formatting_style, key_phrases, avoid_phrases}
```

---

## 📦 PHASE 3: Quality Scoring Engine
**Goal:** Every generated description gets scored 0-100 with breakdown by component.

### 3.1 — Scorer Class (`class-giga-apw-scorer.php`)

```
Build the Quality Scoring engine:

Class: Giga_APW_Scorer

Score Components (weights must add to 100):
1. Readability Score (25 points)
   - Calculate Flesch Reading Ease from long_description
   - Formula: 206.835 - (1.015 × avg words/sentence) - (84.6 × avg syllables/word)
   - Score 60-70 FRE → 25 points (ideal for product copy)
   - Score 50-80 FRE → 20 points  
   - Score 30-50 or 80-90 → 15 points
   - Below 30 or above 90 → 10 points

2. SEO Score (25 points)
   - Check if target keywords appear in: meta_title (+8), long_description (+8), meta_description (+5), tags (+4)
   - Keyword density in long_description: 1-2% is ideal (+25), 0.5-3% (+15), outside range (+5)
   - If no target keywords provided, check if product title words appear

3. Uniqueness Score (25 points)
   - Compare long_description against existing_description using similarity ratio
   - Use similar_text() PHP function to calculate % similarity
   - <20% similar → 25 points (very unique)
   - 20-40% similar → 18 points
   - 40-60% similar → 12 points  
   - 60-80% similar → 6 points
   - >80% similar → 2 points (too similar to original)

4. Benefit-to-Feature Ratio (15 points)
   - Count benefit phrases (you will, you can, helps you, makes it easy, saves you, perfect for, ideal for, gives you)
   - Count feature phrases (made of, includes, features, measures, weighs, compatible with)
   - Ratio > 1.5 benefits per feature → 15 points
   - Ratio 1.0-1.5 → 12 points
   - Ratio 0.5-1.0 → 8 points
   - Ratio < 0.5 → 4 points (too feature-heavy)

5. Length Score (10 points)
   - long_description within configured min-max range → 10 points
   - Within 20% of range → 7 points
   - Outside range → 3 points
   - meta_title 50-60 chars → +0 (hard constraint, already enforced)
   - meta_description 150-160 chars → +0 (hard constraint, already enforced)

Method: score($generated_content, $product_data, $settings)
Returns: {
  total: int (0-100),
  readability: int (0-25),
  seo: int (0-25),
  uniqueness: int (0-25),
  benefits: int (0-15),
  length: int (0-10),
  grade: 'excellent'|'good'|'needs_improvement',
  grade_label: 'Excellent'|'Good'|'Needs Improvement',
  grade_color: '#22c55e'|'#f59e0b'|'#ef4444'
}

Grade thresholds:
- 80-100: excellent (green)
- 60-79: good (yellow/amber)
- 0-59: needs_improvement (red)
```

---

## 📦 PHASE 4: Single Product Generation (Core Feature)
**Goal:** Meta box appears on product edit screen. Generate button works. Preview shows. Approve and publish works.

### 4.1 — Generator Class (`class-giga-apw-generator.php`)

```
Build the single product generation engine:

Class: Giga_APW_Generator

Method: get_product_data($product_id)
Pulls all needed data from WooCommerce product:
- title (get_name())
- existing long description (get_description())
- existing short description (get_short_description())
- attributes (get_attributes() → format as name:value pairs)
- price (get_price())
- sale_price (get_sale_price())
- category names (wp_get_post_terms)
- tag names (wp_get_post_terms with product_tag)
- image count (count of gallery images + main image)
- sku, weight, dimensions
- image attachments array (ID + current alt text)

Method: generate($product_id, $options = [])
Options: target_keywords, tone, language, additional_instructions, use_brand_voice

Steps:
1. Check free plan limit (5/month) — return error if exceeded
2. Get product data
3. Check if Pro + brand voice enabled → get voice profile
4. Build system prompt (with or without brand voice)
5. Build user prompt with product data
6. Call Claude API (claude.php)
7. Parse JSON response — if JSON invalid, return error
8. Validate field lengths (meta_title, meta_description hard constraints)
9. If meta_title not 50-60 chars, truncate/pad with ellipsis intelligently
10. If meta_description not 150-160 chars, truncate intelligently
11. Run quality scorer
12. Save generation to custom DB table
13. Increment monthly usage counter (free plan)
14. Return generation record with all content + scores

Method: publish($generation_id, $approved_fields)
approved_fields: array of field names user approved
  e.g. ['long_description', 'meta_title', 'tags']

For each approved field:
- long_description → update post_content via wp_update_post
- short_description → update post_excerpt via wp_update_post
- meta_title + meta_description → delegate to Giga_APW_SEO class
- alt_text → update each image attachment's _wp_attachment_image_alt
- tags → wp_set_post_terms with product_tag taxonomy

Save previous values to generation record as JSON (for revert)
Update generation status to 'published'
Return success with published fields list
```

### 4.2 — Meta Box (`class-giga-apw-admin.php` + `templates/metabox-main.php`)

```
Add a meta box to the WooCommerce product edit screen:

Registration:
- Hook: add_meta_boxes
- Screen: 'product'
- Title: "🤖 Giga AI Writer"
- Context: normal
- Priority: high (appears near top, below product data)

Meta box HTML structure:

[HEADER BAR]
- Plugin logo/icon
- Plan badge: "FREE — 3 of 5 used this month" or "PRO — Unlimited"
- If no API key: show warning banner with link to settings

[GENERATION OPTIONS] (collapsible, collapsed by default after first use)
- Target Keywords: text input (placeholder: "hiking boots, waterproof boots")
- Tone: dropdown (Professional / Casual / Technical / Luxury / Playful / Auto-detect)
- Language: dropdown (20+ languages, default from settings)
- Additional Instructions: textarea (placeholder: "Emphasize the waterproof feature. Target hikers aged 25-45.")
- Brand Voice toggle: checkbox "Use my brand voice" (Pro only, greyed out for free)

[GENERATE BUTTON]
- Large primary button: "✨ Generate All Content"
- Spinner shown during generation (disable button)
- Below button: "Estimated time: ~8 seconds"

[QUALITY SCORE] (hidden until generation complete)
- Large score badge: "87/100" in color-coded circle
- Score label: "Excellent" / "Good" / "Needs Improvement"
- Expandable score breakdown:
    Readability: 22/25
    SEO: 20/25  
    Uniqueness: 25/25
    Benefit Ratio: 12/15
    Length: 8/10

[CONTENT PREVIEW] (hidden until generation complete)
For each of the 6 content types, show a panel:

Panel structure:
┌─────────────────────────────────────┐
│ ☑ Meta Title          [Approve] [✗] │
├──────────────┬──────────────────────┤
│ CURRENT      │ GENERATED            │
│ (old text)   │ (new text)           │
│              │ 58 chars ✓           │
└──────────────┴──────────────────────┘

Panels for:
1. Long Description (shows word count)
2. Short Description (shows word count)
3. SEO Meta Title (shows char count + Yoast/RankMath badge)
4. SEO Meta Description (shows char count + Yoast/RankMath badge)
5. Image Alt Text (shows per-image in list)
6. Product Tags (shows as tag chips)

[ACTION BAR] (shown after generation)
- "Select All" checkbox
- "Publish Approved (X)" primary button
- "Regenerate" secondary button
- "Version History" link (shows past generations in modal)

[VERSION HISTORY MODAL]
- List of past generations with date, quality score
- Each row: "Restore this version" button
```

### 4.3 — AJAX Handlers (`class-giga-apw-ajax.php`)

```
Register and handle these AJAX actions (both logged-in):

1. wp_ajax_giga_apw_generate
   - Verify nonce: giga_apw_nonce
   - Capability: edit_products
   - Get: product_id, options (keywords, tone, language, instructions, use_brand_voice)
   - Call: Giga_APW_Generator::generate()
   - Return JSON: {success: true, data: {generation_id, content, scores, monthly_remaining}}
   - On error: {success: false, data: {message: "Human readable error"}}

2. wp_ajax_giga_apw_publish
   - Verify nonce, capability
   - Get: generation_id, approved_fields (array)
   - Call: Giga_APW_Generator::publish()
   - Return JSON: {success: true, data: {published_fields, message}}

3. wp_ajax_giga_apw_test_connection
   - Verify nonce
   - Capability: manage_woocommerce
   - Call: Giga_APW_Claude::test_connection()
   - Return JSON: {success: true/false, data: {message}}

4. wp_ajax_giga_apw_get_history
   - Verify nonce, capability
   - Get: product_id
   - Return last 10 generations for this product

5. wp_ajax_giga_apw_revert
   - Verify nonce, capability
   - Get: generation_id
   - Restore previous values saved in generation record
   - Return: {success: true}
```

### 4.4 — Admin JavaScript (`assets/js/giga-apw-admin.js`)

```
Build the admin JavaScript (vanilla JS, no jQuery dependency beyond what WP provides):

On DOM ready for product edit screen:

1. Generate button click handler:
   - Disable button, show spinner
   - Collect form values (keywords, tone, language, instructions, brand_voice)
   - AJAX POST to giga_apw_generate
   - On success:
     → Show quality score section with animation
     → Populate all 6 content panels with current vs generated content
     → Show action bar
     → Update monthly remaining count in header
   - On error: show error notice with message
   - Always: re-enable button, hide spinner

2. Per-field approve checkbox handlers:
   - Update "Publish Approved (X)" button count as checkboxes change
   - Visual state: approved panel gets green left border

3. Publish Approved button:
   - Collect approved field names from checked boxes
   - AJAX POST to giga_apw_publish
   - On success: show success notice, update panel states to "Published"
   - On error: show error

4. Select All checkbox:
   - Toggles all per-field checkboxes

5. Version history link:
   - AJAX fetch history
   - Show in modal (use WP's existing dialog or simple CSS modal)

6. Score breakdown toggle:
   - Expand/collapse score detail panel

Character counters:
   - Meta title: live count display (color red if outside 50-60)
   - Meta description: live count display (color red if outside 150-160)
```

---

## 📦 PHASE 5: SEO Plugin Integration
**Goal:** Generated meta fields write directly to Yoast or Rank Math without copy-paste.

### 5.1 — SEO Class (`class-giga-apw-seo.php`)

```
Build the SEO plugin integration class:

Class: Giga_APW_SEO

Method: detect_active_seo_plugin()
Returns: 'yoast' | 'rankmath' | 'none'
Detection logic:
- Yoast: check if class 'WPSEO_Options' exists OR option 'wpseo' exists
- Rank Math: check if class 'RankMath' exists OR option 'rank_math_modules' exists
- Neither: return 'none'

Method: write_meta($product_id, $meta_title, $meta_description)
- Detect active SEO plugin
- If Yoast:
    update_post_meta($product_id, '_yoast_wpseo_title', $meta_title)
    update_post_meta($product_id, '_yoast_wpseo_metadesc', $meta_description)
- If Rank Math:
    update_post_meta($product_id, 'rank_math_title', $meta_title)
    update_post_meta($product_id, 'rank_math_description', $meta_description)
- If neither:
    update_post_meta($product_id, '_giga_apw_meta_title', $meta_title)
    update_post_meta($product_id, '_giga_apw_meta_description', $meta_description)
- Return: {plugin: 'yoast'|'rankmath'|'none', written: true}

Method: read_existing_meta($product_id)
Returns current meta title + description from whichever plugin is active.
Used to populate "current" side of preview panel.

Method: get_seo_plugin_label()
Returns human-readable label for UI:
- "Yoast SEO" | "Rank Math" | "Saved to product meta"

Note: Show badge in meta title/description panels indicating where data will be written.
```

---

## 📦 PHASE 6: Brand Voice Training (Pro)
**Goal:** Upload 3-5 example descriptions, Claude analyzes tone, all future generations match the voice.

### 6.1 — Voice Class (`class-giga-apw-voice.php`)

```
Build the Brand Voice training system:

Class: Giga_APW_Voice

Method: analyze($examples)
$examples = array of 3-5 description strings

Steps:
1. Validate: minimum 3 examples, each at least 50 words
2. Build analysis prompt using Giga_APW_Prompt::build_brand_voice_analysis_prompt()
3. Call Claude API
4. Parse JSON response into voice profile
5. Store profile in giga_apw_brand_voice option
6. Return profile summary

Voice Profile JSON structure:
{
  "tone": "professional and authoritative",
  "vocabulary_level": "intermediate",
  "avg_sentence_length": "medium (12-18 words)",
  "formatting_style": "uses bullet points for features, paragraphs for benefits",
  "perspective": "second person (you/your)",
  "key_patterns": ["starts with a hook question", "ends with a call to action"],
  "brand_adjectives": ["premium", "durable", "thoughtfully designed"],
  "avoid_patterns": ["passive voice", "technical jargon"],
  "example_opening": "First 30 chars of style example...",
  "analyzed_at": "2026-03-01 12:00:00"
}

Method: get_profile()
Returns stored profile or null if none exists.

Method: build_voice_injection($profile)
Returns string to inject into system prompt:
"Brand Voice Requirements: Write in a {tone} tone. Use {vocabulary_level} vocabulary. 
Sentences should be {avg_sentence_length}. {formatting_style}. Write from the 
{perspective} perspective. Emulate these patterns: {key_patterns}. 
Always use these brand adjectives where natural: {brand_adjectives}."

Method: clear_profile()
Deletes stored profile. Used when merchant wants to retrain.
```

### 6.2 — Brand Voice Admin Page (`templates/page-brand-voice.php`)

```
Build the Brand Voice training admin page:

Layout:

[HEADER]
"Train your brand voice — paste 3-5 example product descriptions below.
Claude will analyze your tone, vocabulary, and style. All future generations will match."

[PRO GATE]
If free plan: show upgrade prompt with lock icon. Blur/grey the form.

[EXAMPLE INPUTS]
- 3 textarea fields (required) + 2 more (optional)
- Each labeled: "Example 1 *", "Example 2 *", "Example 3 *", "Example 4", "Example 5"
- Each has word count indicator below
- Placeholder text in each: "Paste a product description that represents your brand voice..."

[TONE NOTES] (optional)
- "Additional tone guidance" textarea
- Placeholder: "We target professional athletes. Avoid casual slang. Emphasize performance and durability."

[ANALYZE BUTTON]
- "🎙️ Analyze My Brand Voice" button
- Shows spinner during analysis (~20 seconds)

[VOICE PROFILE RESULT] (shown after analysis)
Styled card showing:
- Detected Tone: "Professional, authoritative, aspirational"
- Vocabulary Level: "Intermediate-Advanced"
- Sentence Style: "Medium length (12-18 words), active voice"
- Formatting: "Benefit paragraphs + feature bullet points"
- Perspective: "Second person (you/your)"
- Brand Keywords detected: [premium] [durable] [performance] [engineered]
- ⚠️ Patterns to avoid: passive voice, overly technical specs without benefits

[ACTIVATE TOGGLE]
- Checkbox: "Use this voice profile for all product generations"
- Toggles brand_voice_enabled in settings

[RETRAIN BUTTON]
- "Clear & Retrain" — clears current profile, resets form
```

---

## 📦 PHASE 7: Bulk Generation (Pro)
**Goal:** Select up to 50 products, generate in background, review and bulk-publish.

### 7.1 — Bulk Class (`class-giga-apw-bulk.php`)

```
Build the bulk generation engine:

Class: Giga_APW_Bulk

Method: start_job($product_ids, $options)
- Validate Pro license
- Validate max 50 products per batch
- Store job in wp_options:
    giga_apw_bulk_progress = {
      status: 'running',
      total: 50,
      completed: 0,
      failed: 0,
      results: [],  // array of {product_id, generation_id, status, error}
      options: {...},
      started_at: timestamp,
      estimated_completion: timestamp
    }
- Schedule WP Cron: giga_apw_process_bulk, every minute, pass first product_id batch
- Return: {job_started: true, estimated_minutes: X}

Estimation: ~18 seconds per product → 50 products ≈ 15 minutes

WP Cron Handler: process_bulk_batch()
- Hooked to: giga_apw_process_bulk
- Process 3 products per cron run (every minute = 3 products/min)
- For each product:
    → Call Giga_APW_Generator::generate()
    → Update progress in wp_options
    → On error: log error, continue to next product (don't abort job)
- If all products done: set status to 'completed', clear cron

Method: get_progress()
Returns current giga_apw_bulk_progress option.

Method: cancel_job()
Clears cron events. Sets status to 'cancelled'.

Method: retry_failed($product_id)
Re-runs generation for a single failed product within the job.

Method: bulk_publish($generation_ids, $approved_fields)
Batch publishes multiple approved generations.
Processes up to 50 at once.
Returns: {published: int, failed: int, errors: array}
```

### 7.2 — Bulk Admin Page (`templates/page-bulk.php`)

```
Build the Bulk Generation admin page:

[HEADER]
"Bulk Generate Product Descriptions (Pro)"
"Generate unique, SEO-optimized descriptions for up to 50 products at once."

[PRO GATE] — Show upgrade prompt if free plan.

[PRODUCT SELECTION]
Filter bar:
- Category dropdown filter
- "Description status" filter: All | Empty descriptions | Thin descriptions (<50 words) | Has manufacturer text | All products
- Search by product name

Product list:
- Checkbox | Product image thumbnail | Product name | Current description status | Last generated date
- Select All / Deselect All buttons
- Selection count: "47 products selected"

[JOB CONFIGURATION]
- Language: dropdown
- Tone: dropdown  
- Collection-level keywords: text input (applied to all products)
- Brand voice: toggle (if profile exists)
- "Override per-product" note

[START JOB BUTTON]
- "⚡ Generate X Products"
- Disabled if 0 selected or >50 selected
- Confirmation: "This will use approximately $0.75 in Claude API credits. Continue?"

[PROGRESS TRACKER] (shown once job starts, replaces button)
- Overall progress bar: "23 / 50 products complete"
- Status: "Generating: 'Blue Running Shoes XL'..."
- Speed: "~12 minutes remaining"
- Live update via AJAX polling every 3 seconds
- "Cancel Job" button
- Merchant can close this page and return — job continues in background

[BULK REVIEW SCREEN] (shown when job completes)
Header stats:
- Total generated: 50
- Average quality score: 82/100
- Excellent (80+): 38 | Good (60-79): 10 | Needs Review (<60): 2

Filter + Sort bar:
- Sort by: Quality Score ↑↓ | Product Name | Status
- Filter by: All | Excellent | Good | Needs Review | Failed

Product review table:
┌──────────────────────────────────────────────────────────┐
│ ☑ │ Product Name    │ Score  │ Status     │ Actions      │
├──────────────────────────────────────────────────────────┤
│ ☑ │ Blue Sneakers   │ 89 ✅  │ Approved   │ Preview | ✗  │
│ ☑ │ Red Boots       │ 72 🟡  │ Pending    │ Preview | ✗  │
│   │ Old Sandals     │ 44 🔴  │ Needs Work │ Regen  | ✗   │
│   │ Green Hat       │ —  ❌  │ Failed     │ Retry  | —   │
└──────────────────────────────────────────────────────────┘

Bulk actions:
- "Approve All 80+" → auto-checks all ≥80 score
- "Approve All 70+" → auto-checks all ≥70 score
- "Publish Approved (X)" → batch publish checked items

Each row has expandable preview: click to see generated content inline.
Each row: individual "Regenerate" and "Reject" buttons.
```

---

## 📦 PHASE 8: Admin Styling
**Goal:** Clean, professional WordPress admin UI. Matches WooCommerce admin aesthetic.

### 8.1 — Admin CSS (`assets/css/giga-apw-admin.css`)

```
Build admin CSS that:

Design system:
- Colors: use WordPress admin CSS variables where possible
- Primary accent: #7C3AED (purple — AI/tech feel)
- Success: #22c55e | Warning: #f59e0b | Error: #ef4444
- Font: inherit WP admin font stack

Meta box layout:
- Clean card-based layout
- Generation options section: subtle grey background
- Content panels: side-by-side grid (50/50) on desktop, stacked on mobile
- Quality score: large circular badge (CSS only, no canvas/SVG needed)
- Score breakdown: horizontal bar charts using CSS width percentages

Score badge colors:
- 80-100: background #dcfce7, text #166534, border #22c55e
- 60-79: background #fef9c3, text #854d0e, border #f59e0b  
- 0-59: background #fee2e2, text #991b1b, border #ef4444

Panel states:
- Approved: left border 4px #22c55e, subtle green tint
- Rejected: opacity 0.5, strikethrough on generated content
- Published: left border 4px #7C3AED

Button styles:
- Primary (Generate): large, full-width, purple background
- Secondary (Regenerate): outline style
- Danger (Cancel): red outline
- Disabled states for all buttons

Spinner: CSS-only rotating border animation

Responsive: 
- On screens < 782px (WP mobile breakpoint): stack preview panels vertically
- Bulk table: horizontal scroll on small screens

Animations:
- Score badge: fade + scale in when revealed
- Content panels: slide down sequentially (100ms stagger)
- Progress bar: smooth CSS transition

Bulk page:
- Progress bar: animated striped gradient when running
- Review table: zebra striping, hover highlight
- Quality score chips: inline colored badges

Settings page:
- API key field: monospace font
- Test connection result: inline success/error badge
- Plan status card: prominent, colored border based on plan
```

---

## 📦 PHASE 9: WP.org Compliance & Cleanup

### 9.1 — Compliance Checklist

```
Ensure ALL of the following before plugin is considered complete:

readme.txt (WP.org format):
- Plugin name, contributors, tags, requires at least, tested up to
- Short description (under 150 chars)
- Long description with features list
- Installation instructions
- FAQ section (at least 5 Q&As)
- Changelog for v1.0.0
- Screenshots section (describe 5 screenshots)

Tags for WP.org (choose 5):
woocommerce, product description, ai, seo, claude

Internationalization:
- ALL user-facing strings wrapped in __() or _e() or esc_html_e()
- Text domain: 'giga-ai-product-writer'  
- POT file generated in languages/ directory

External API disclosure:
In readme.txt AND plugin description, clearly state:
"This plugin connects to the Anthropic Claude API to generate product descriptions.
Your product data (title, attributes, price, category) is sent to Claude for processing.
No customer personal data is transmitted. API Terms: https://www.anthropic.com/legal/usage-policy"

Clean uninstall (uninstall.php):
- Drop custom table: {prefix}_giga_apw_generations
- Delete all options: giga_apw_*
- Delete all post meta: _giga_apw_*
- Clear scheduled cron events
- Only runs if WP_UNINSTALL_PLUGIN is defined

No frontend assets:
- Zero CSS or JS loaded on non-admin pages
- Confirm with admin_enqueue_scripts hook (not wp_enqueue_scripts)

GPL compliance:
- All PHP files have GPL v2+ header comment
- No proprietary license dependencies

PHP 8.0+ compatibility:
- No deprecated functions
- Type hints where appropriate
- No short array syntax issues
```

---

## 📦 PHASE 10: License System (Free vs Pro)

### 10.1 — License Class (`class-giga-apw-license.php`)

```
Build a simple license verification system:

Class: Giga_APW_License

Method: is_pro()
Returns true if valid Pro license is active.
Check: get_option('giga_apw_license_status') === 'pro'

Method: activate($license_key)
- Validate key format (basic sanity check)
- Send verification request to: https://gigaverse.io/api/license/verify
- POST: {license_key, site_url: home_url(), plugin: 'giga-ai-product-writer'}
- On valid response: store license key + status
- Return: {success: bool, message: string, expires: string}
- Handle network errors gracefully

Method: deactivate()
- Send deactivation request to license server
- Clear local license data

Method: get_monthly_remaining()
- If Pro: return PHP_INT_MAX (unlimited)
- If Free:
    Get current month key: 'giga_apw_usage_' . date('Y-m')
    Count = get_transient(key) ?: 0
    Return max(0, 5 - count)

Method: increment_usage()
- If Pro: do nothing
- If Free:
    Key = 'giga_apw_usage_' . date('Y-m')
    Count = get_transient(key) ?: 0
    set_transient(key, count + 1, MONTH_IN_SECONDS)

Method: check_limit()
- Returns true if usage allowed, false if limit reached
- Also returns remaining count

Pro feature gates — check before rendering Pro features:
- Bulk generation
- Brand voice training
- SEO meta field writing (Yoast/Rank Math)
- Custom prompt templates
- Version history (store only last 3 for free, unlimited for Pro)
```

---

## 🧪 TESTING CHECKLIST

After building each phase, verify these work:

**Phase 1:**
- [ ] Plugin activates without errors
- [ ] Custom DB table created on activation
- [ ] WooCommerce inactive → admin notice shown, plugin doesn't crash
- [ ] Settings page loads, API key saves (encrypted), test connection works

**Phase 2:**
- [ ] Claude API call returns valid JSON
- [ ] Banned phrases not in output (check manually)
- [ ] meta_title is exactly 50-60 characters
- [ ] meta_description is exactly 150-160 characters

**Phase 3:**
- [ ] Quality score calculates correctly for sample content
- [ ] Score grades show correct colors
- [ ] Score breakdown percentages add up correctly

**Phase 4:**
- [ ] Meta box appears on product edit screen
- [ ] Generate button triggers API call
- [ ] Preview shows current vs generated side by side
- [ ] Per-field approve works
- [ ] Publish writes to correct WooCommerce fields
- [ ] Version history shows past 3/unlimited generations
- [ ] Revert restores previous content
- [ ] Free plan limit enforced (stops at 5)

**Phase 5:**
- [ ] Yoast detected when active
- [ ] Rank Math detected when active
- [ ] Meta writes to correct meta keys for each SEO plugin
- [ ] Falls back to product meta when neither active

**Phase 6:**
- [ ] Brand voice analysis returns valid profile
- [ ] Profile stored in options
- [ ] Generation with voice ON is tonally different from voice OFF
- [ ] Pro gate blocks free users

**Phase 7:**
- [ ] Bulk job starts and runs via WP Cron
- [ ] Progress persists through page refresh
- [ ] 50 products complete in under 15 minutes
- [ ] Failed products show retry button
- [ ] "Approve All 80+" filters correctly
- [ ] Bulk publish writes all fields

**Phase 8:**
- [ ] No CSS/JS on frontend pages
- [ ] UI renders correctly on mobile (< 782px)
- [ ] All interactive states (approved, published, rejected) show visually

**Phase 9:**
- [ ] Uninstall removes all data (verify manually)
- [ ] All strings are translatable
- [ ] No PHP warnings or notices on debug mode
- [ ] External API usage disclosed

**Phase 10:**
- [ ] Free plan blocks Pro features with upgrade prompt
- [ ] License activation works (mock if server not ready)
- [ ] Monthly counter resets each month

---

## ⚙️ KEY CONSTANTS & CONFIGURATION

```php
// In main plugin file
define('GIGA_APW_VERSION', '1.0.0');
define('GIGA_APW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIGA_APW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIGA_APW_PLUGIN_FILE', __FILE__);
define('GIGA_APW_CLAUDE_MODEL', 'claude-sonnet-4-20250514');
define('GIGA_APW_CLAUDE_MAX_TOKENS', 2500);
define('GIGA_APW_CLAUDE_TEMPERATURE', 0.7);
define('GIGA_APW_FREE_LIMIT', 5);
define('GIGA_APW_BULK_MAX', 50);
define('GIGA_APW_BULK_BATCH_SIZE', 3); // products per cron run
define('GIGA_APW_MIN_WORDS', 150);
define('GIGA_APW_MAX_WORDS', 500);
define('GIGA_APW_META_TITLE_MIN', 50);
define('GIGA_APW_META_TITLE_MAX', 60);
define('GIGA_APW_META_DESC_MIN', 150);
define('GIGA_APW_META_DESC_MAX', 160);
define('GIGA_APW_QUALITY_GATE', 70); // default threshold
define('GIGA_APW_API_ENDPOINT', 'https://api.anthropic.com/v1/messages');
define('GIGA_APW_LICENSE_SERVER', 'https://gigaverse.io/api/license');
```

---

## 🚀 BUILD ORDER SUMMARY FOR AI AGENT

Follow this exact sequence. Each step must be verified before proceeding:

```
Step 1:  Create full file structure (empty files with correct PHP class skeletons)
Step 2:  Main plugin file + Core class (plugin activates cleanly)
Step 3:  Database: activation hook creates custom table
Step 4:  Settings page: API key input, encrypt/decrypt, test connection button
Step 5:  Claude API client: wp_remote_post wrapper, error handling
Step 6:  Prompt builder: system prompt, user prompt, JSON output format
Step 7:  Quality scorer: all 5 components, composite score
Step 8:  Generator class: get_product_data(), generate(), publish()
Step 9:  AJAX handlers: generate, publish, test_connection, history, revert
Step 10: Meta box template: HTML structure for product edit screen
Step 11: Admin JavaScript: generate flow, preview panels, publish flow
Step 12: Admin CSS: all styles, states, responsive
Step 13: SEO class: detect plugin, write meta, read existing
Step 14: Brand voice class + admin page (Pro)
Step 15: Bulk class + WP Cron setup (Pro)
Step 16: Bulk admin page: selection, progress, review (Pro)
Step 17: License class: free limit, Pro gate, activation
Step 18: WP.org compliance: readme.txt, i18n, uninstall.php
Step 19: Full QA pass through testing checklist
Step 20: Final review: security, performance, clean code
```

---

## 📝 FINAL NOTES FOR AI AGENT

1. **Never use GPT/OpenAI** — Claude API only. Model: `claude-sonnet-4-20250514`
2. **Never expose API keys** to frontend JavaScript or HTML source
3. **Always use WP coding standards** — `$wpdb->prepare()` for all DB queries, `esc_html()` for all output
4. **No jQuery required** — use vanilla JS with WP's built-in `fetch()` for AJAX
5. **Test with WooCommerce debug mode** — no PHP notices or warnings acceptable
6. **Admin-only plugin** — zero performance impact on public-facing store pages
7. **All monetary values** use WooCommerce's `wc_price()` formatting
8. **Cron reliability** — use Action Scheduler (WooCommerce's built-in) if WP Cron is unreliable on the server; add fallback
9. **The quality score is a trust feature** — never fake it or round up artificially
10. **Brand voice is a Pro retention feature** — make it impressive, not just functional

---

*Giga AI Product Writer for WooCommerce — Product #15 of 15 in the Gigaverse pipeline.*  
*Powered by Claude. Built for WooCommerce. Wins on quality, not just automation.*
