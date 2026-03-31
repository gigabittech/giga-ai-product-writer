<?php
if (!defined('ABSPATH')) {
    exit;
}

class Giga_AI_Client {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Generate content using the selected AI provider
     * 
     * @param string $prompt User prompt
     * @param string $system_prompt System prompt
     * @return array Response with 'text' key or 'error' key
     */
    public function generate($prompt, $system_prompt = '') {
        $provider = get_option('giga_ai_provider', 'claude');
        $api_key = $this->get_decrypted_key();
        $model = get_option('giga_ai_model', 'claude-sonnet-4-5');
        
        switch ($provider) {
            case 'claude':
                return $this->call_claude($prompt, $system_prompt, $api_key, $model);
            case 'openai':
                return $this->call_openai($prompt, $system_prompt, $api_key, $model);
            case 'gemini':
                return $this->call_gemini($prompt, $system_prompt, $api_key, $model);
            case 'groq':
                return $this->call_groq($prompt, $system_prompt, $api_key, $model);
            case 'ollama':
                return $this->call_ollama($prompt, $system_prompt, $model);
            default:
                return ['error' => __('Unknown AI provider selected.', 'giga-ai-product-writer')];
        }
    }
    
    /**
     * Test connection to the selected AI provider
     * 
     * @return array Connection test result
     */
    public function test_connection() {
        $start = microtime(true);
        $result = $this->generate('Say "OK" and nothing else.', 'You are a helpful assistant.');
        $time = round((microtime(true) - $start) * 1000);
        
        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }
        
        $provider = get_option('giga_ai_provider', 'claude');
        $model = get_option('giga_ai_model', 'claude-sonnet-4-5');
        
        return [
            'success' => true, 
            'latency' => $time . 'ms', 
            'model' => $model,
            'provider' => $provider
        ];
    }
    
    /**
     * Get available models for the selected provider
     * 
     * @return array List of available models
     */
    public function get_available_models() {
        $provider = get_option('giga_ai_provider', 'claude');
        
        $models = [
            'claude' => [
                'claude-opus-4-5' => ['name' => 'claude-opus-4-5', 'label' => 'Most Capable'],
                'claude-sonnet-4-5' => ['name' => 'claude-sonnet-4-5', 'label' => 'Recommended ★'],
                'claude-haiku-4-5' => ['name' => 'claude-haiku-4-5', 'label' => 'Fastest']
            ],
            'openai' => [
                'gpt-4o' => ['name' => 'gpt-4o', 'label' => 'Most Capable'],
                'gpt-4o-mini' => ['name' => 'gpt-4o-mini', 'label' => 'Recommended ★'],
                'gpt-3.5-turbo' => ['name' => 'gpt-3.5-turbo', 'label' => 'Fastest']
            ],
            'gemini' => [
                'gemini-2.0-flash' => ['name' => 'gemini-2.0-flash', 'label' => 'Recommended ★'],
                'gemini-1.5-pro' => ['name' => 'gemini-1.5-pro', 'label' => 'Most Capable'],
                'gemini-1.5-flash' => ['name' => 'gemini-1.5-flash', 'label' => 'Fastest']
            ],
            'groq' => [
                'llama-3.3-70b-versatile' => ['name' => 'llama-3.3-70b-versatile', 'label' => 'Recommended ★'],
                'mixtral-8x7b-32768' => ['name' => 'mixtral-8x7b-32768', 'label' => 'Mixtral 8x7B'],
                'gemma2-9b-it' => ['name' => 'gemma2-9b-it', 'label' => 'Gemma 2 9B']
            ],
            'ollama' => [
                'llama3' => ['name' => 'llama3', 'label' => 'Llama 3'],
                'mistral' => ['name' => 'mistral', 'label' => 'Mistral'],
                'codellama' => ['name' => 'codellama', 'label' => 'Code Llama'],
                'mixtral' => ['name' => 'mixtral', 'label' => 'Mixtral']
            ]
        ];
        
        return $models[$provider] ?? [];
    }
    
    /**
     * Call Anthropic Claude API
     */
    private function call_claude($prompt, $system, $key, $model) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => 2048,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Unknown Claude API error'];
        }
        
        if (!isset($body['content'][0]['text'])) {
            return ['error' => 'Invalid response format from Claude API'];
        }
        
        return ['text' => $body['content'][0]['text']];
    }
    
    /**
     * Call OpenAI API
     */
    private function call_openai($prompt, $system, $key, $model) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 2048,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Unknown OpenAI API error'];
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Invalid response format from OpenAI API'];
        }
        
        return ['text' => $body['choices'][0]['message']['content']];
    }
    
    /**
     * Call Google Gemini API
     */
    private function call_gemini($prompt, $system, $key, $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [['parts' => [['text' => $system . "\n\n" . $prompt]]]],
                'generationConfig' => ['maxOutputTokens' => 2048]
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Unknown Gemini API error'];
        }
        
        if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return ['error' => 'Invalid response format from Gemini API'];
        }
        
        return ['text' => $body['candidates'][0]['content']['parts'][0]['text']];
    }
    
    /**
     * Call Groq API (OpenAI-compatible)
     */
    private function call_groq($prompt, $system, $key, $model) {
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 2048,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Unknown Groq API error'];
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Invalid response format from Groq API'];
        }
        
        return ['text' => $body['choices'][0]['message']['content']];
    }
    
    /**
     * Call Ollama API (local)
     */
    private function call_ollama($prompt, $system, $model) {
        $base_url = get_option('giga_ollama_base_url', 'http://localhost:11434');
        $response = wp_remote_post($base_url . '/api/generate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $model,
                'prompt' => $system . "\n\n" . $prompt,
                'stream' => false,
            ]),
            'timeout' => 120,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['response'])) {
            return ['error' => 'Invalid response format from Ollama API'];
        }
        
        return ['text' => $body['response']];
    }
    
    /**
     * Get decrypted API key
     */
    private function get_decrypted_key() {
        $provider = get_option('giga_ai_provider', 'claude');
        
        // For Ollama, no API key needed
        if ($provider === 'ollama') {
            return '';
        }
        
        // For other providers, get the encrypted key
        $encrypted = get_option('giga_ai_api_key', '');
        if (empty($encrypted)) {
            return '';
        }
        
        // Use WordPress auth key as encryption key
        $key = wp_salt('auth');
        return $this->decrypt($encrypted, $key);
    }
    
    /**
     * Encrypt data
     */
    private function encrypt($data, $key) {
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16)));
    }
    
    /**
     * Decrypt data
     */
    private function decrypt($data, $key) {
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }
}