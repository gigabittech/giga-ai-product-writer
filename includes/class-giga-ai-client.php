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
            case 'zai':
                return $this->call_zai($prompt, $system_prompt, $api_key, $model);
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
        $provider = get_option('giga_ai_provider', 'claude');
        $model = get_option('giga_ai_model', 'claude-sonnet-4-5');
        $api_key = $this->get_decrypted_key();
        
        // Log the test attempt with detailed debugging
        error_log("Giga AI: Testing connection for provider: {$provider}, model: {$model}");
        error_log("Giga AI: API key length: " . (empty($api_key) ? 0 : strlen($api_key)));
        
        // Validate required parameters
        if ($provider !== 'ollama' && empty($api_key)) {
            error_log("Giga AI Error: API key required for provider: {$provider}");
            return ['success' => false, 'error' => 'API key is required for ' . ucfirst($provider)];
        }
        
        // Use specific test based on provider
        switch ($provider) {
            case 'claude':
                $result = $this->test_claude_connection($api_key, $model);
                break;
            case 'openai':
                $result = $this->test_openai_connection($api_key, $model);
                break;
            case 'gemini':
                $result = $this->test_gemini_connection($api_key, $model);
                break;
            case 'groq':
                $result = $this->test_groq_connection($api_key, $model);
                break;
            case 'zai':
                $result = $this->test_zai_connection($api_key, $model);
                break;
            case 'ollama':
                $result = $this->test_ollama_connection($model);
                break;
            default:
                $result = ['error' => 'Unknown provider: ' . $provider];
        }
        
        $time = round((microtime(true) - $start) * 1000);
        
        if (isset($result['error'])) {
            error_log("Giga AI Connection Error: " . $result['error']);
            return ['success' => false, 'error' => $result['error']];
        }
        
        error_log("Giga AI Connection Success: Provider {$provider}, Model {$model}, Latency {$time}ms");
        
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
            'zai' => [
                'z-pro' => ['name' => 'z-pro', 'label' => 'z-pro (Recommended ★)'],
                'z-fast' => ['name' => 'z-fast', 'label' => 'z-fast'],
                'z-mini' => ['name' => 'z-mini', 'label' => 'z-mini']
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
     * Call Z.ai API (OpenAI-compatible)
     */
    private function call_zai($prompt, $system, $key, $model) {
        $response = wp_remote_post('https://api.z.ai/v1/chat/completions', [
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
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Unknown Z.ai API error'];
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Invalid response format from Z.ai API'];
        }
        
        return ['text' => $body['choices'][0]['message']['content']];
    }
    
    /**
     * Test Claude connection
     */
    private function test_claude_connection($key, $model) {
        error_log("Giga AI: Testing Claude connection with model: {$model}");
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => 'Hi']]
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log("Giga AI Claude Error: " . $error_msg);
            return ['error' => 'Claude API Error: ' . $error_msg];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        error_log("Giga AI Claude Response Code: {$response_code}");
        error_log("Giga AI Claude Response Body: " . json_encode($body));
        
        if ($response_code !== 200) {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'HTTP Error ' . $response_code;
            error_log("Giga AI Claude Error: " . $error_msg);
            return ['error' => 'Claude API Error: ' . $error_msg];
        }
        
        if (!isset($body['content'][0]['text'])) {
            error_log("Giga AI Claude Error: Invalid response format");
            return ['error' => 'Invalid response format from Claude API'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test OpenAI connection
     */
    private function test_openai_connection($key, $model) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => 'Hi']]
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'OpenAI API error'];
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Invalid response format from OpenAI'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Gemini connection
     */
    private function test_gemini_connection($key, $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [['parts' => [['text' => 'Hi']]]],
                'generationConfig' => ['maxOutputTokens' => 10]
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Gemini API error'];
        }
        
        if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return ['error' => 'Invalid response format from Gemini'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Groq connection
     */
    private function test_groq_connection($key, $model) {
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => 'Hi']]
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Groq API error'];
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Invalid response format from Groq'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Z.ai connection
     */
    private function test_zai_connection($key, $model) {
        $response = wp_remote_post('https://api.z.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => 'Hi']]
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Z.ai API error'];
        }
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Invalid response format from Z.ai'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Ollama connection
     */
    private function test_ollama_connection($model) {
        $base_url = get_option('giga_ollama_base_url', 'http://localhost:11434');
        $response = wp_remote_post($base_url . '/api/generate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $model,
                'prompt' => 'Hi',
                'stream' => false,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['response'])) {
            return ['error' => 'Invalid response format from Ollama'];
        }
        
        return ['success' => true];
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