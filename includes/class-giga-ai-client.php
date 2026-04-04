<?php
if (!defined('ABSPATH')) {
    exit;
}

class Giga_AI_Client
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

    /**
     * Generate content using the selected AI provider
     * 
     * @param string $prompt User prompt
     * @param string $system_prompt System prompt
     * @return array Response with 'text' key or 'error' key
     */
    public function generate($prompt, $system_prompt = '', $temperature = 0.7)
    {
        $provider = get_option('giga_ai_provider', 'claude');
        $api_key = $this->get_decrypted_key();

        // Validate prompt
        if (empty(trim($prompt))) {
            return ['error' => __('Prompt cannot be empty.', 'giga-ai-product-writer')];
        }

        // Fallback to default model if none is selected
        $model = get_option('giga_ai_model', '');
        if (empty($model)) {
            $model = $this->get_default_model($provider);
        }
        
        // Smart fallback system for model validation
        $available_models = $this->get_available_models();
        if (!isset($available_models[$provider][$model])) {
            error_log("Giga AI: Model {$model} not found for provider {$provider}, switching to default");
            $model = $this->get_default_model($provider);
            error_log("Giga AI: Switched to {$model} for generation");
        }

        // Add provider-specific validation
        if ($provider !== 'ollama' && empty($api_key)) {
            return ['error' => sprintf(__('API key is required for %s provider. Please configure it in settings.', 'giga-ai-product-writer'), ucfirst($provider))];
        }

        // Log generation attempt for debugging
        error_log("Giga AI: Generating content with provider: {$provider}, model: {$model}");

        switch ($provider) {
            case 'claude':
                return $this->call_claude($prompt, $system_prompt, $api_key, $model, $temperature);
            case 'openai':
                return $this->call_openai($prompt, $system_prompt, $api_key, $model, $temperature);
            case 'gemini':
                return $this->call_gemini($prompt, $system_prompt, $api_key, $model, $temperature);
            case 'groq':
                return $this->call_groq($prompt, $system_prompt, $api_key, $model, $temperature);
            case 'zai':
                return $this->call_zai($prompt, $system_prompt, $api_key, $model, $temperature);
            case 'ollama':
                return $this->call_ollama($prompt, $system_prompt, $model, $temperature);
            default:
                return ['error' => sprintf(__('Unknown AI provider selected: %s', 'giga-ai-product-writer'), $provider)];
        }
    }

    /**
     * Test connection to the selected AI provider with smart fallback
     *
     * @return array Connection test result
     */
    public function test_connection()
    {
        $start = microtime(true);
        $provider = get_option('giga_ai_provider', 'claude');
        $api_key = $this->get_decrypted_key();

        // Log the test attempt with detailed debugging (without exposing sensitive data)
        error_log("Giga AI: Testing connection for provider: {$provider}");
        error_log("Giga AI: API key validation - " . (empty($api_key) ? 'Empty' : 'Provided (' . strlen($api_key) . ' chars)'));

        // Validate required parameters with detailed error messages
        if ($provider !== 'ollama' && empty($api_key)) {
            error_log("Giga AI Error: API key required for provider: {$provider}");
            return ['success' => false, 'error' => 'API key is required for this provider. Please configure it in settings.'];
        }

        // Validate API key format based on provider
        $key_validation = $this->validate_api_key($provider, $api_key);
        if (!$key_validation['valid']) {
            error_log("Giga AI Error: API key validation failed for {$provider}: " . $key_validation['error']);
            return ['success' => false, 'error' => $key_validation['error']];
        }

        // Get available models for the provider
        $available_models = $this->get_available_models();
        $all_models = $available_models[$provider] ?? [];
        
        // Start with the currently selected model or get default
        $original_model = get_option('giga_ai_model', '');
        if (empty($original_model)) {
            $original_model = $this->get_default_model($provider);
        }
        
        $model = $original_model;
        $model_was_switched = false;
        
        // Try models in order until we find one that works
        $test_results = [];
        $successful_connection = false;
        
        foreach ($all_models as $model_name => $model_info) {
            error_log("Giga AI: Testing model: {$model_name}");
            
            try {
                $result = $this->test_single_model($provider, $api_key, $model_name);
                $test_results[$model_name] = $result;
                
                if ($result['success']) {
                    $successful_connection = true;
                    if ($model !== $model_name) {
                        $model_was_switched = true;
                        error_log("Giga AI: Successfully connected with model {$model_name}");
                    }
                    $model = $model_name; // Update the working model
                    break; // Stop on first successful model
                } else {
                    error_log("Giga AI: Model {$model_name} failed: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                error_log("Giga AI: Exception testing model {$model_name}: " . $e->getMessage());
                $test_results[$model_name] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        $time = round((microtime(true) - $start) * 1000);

        // Handle connection results with specific error messages
        if (!$successful_connection) {
            // Analyze the errors to provide specific feedback
            $network_errors = [];
            $auth_errors = [];
            $server_errors = [];
            
            foreach ($test_results as $result) {
                if (isset($result['error'])) {
                    $error_msg = strtolower($result['error']);
                    if (strpos($error_msg, 'network') !== false ||
                        strpos($error_msg, 'timeout') !== false ||
                        strpos($error_msg, 'curl') !== false) {
                        $network_errors[] = $result['error'];
                    } else if (strpos($error_msg, 'api key') !== false ||
                              strpos($error_msg, 'unauthorized') !== false ||
                              strpos($error_msg, '401') !== false) {
                        $auth_errors[] = $result['error'];
                    } else if (strpos($error_msg, '500') !== false ||
                              strpos($error_msg, 'server') !== false ||
                              strpos($error_msg, 'service') !== false) {
                        $server_errors[] = $result['error'];
                    }
                }
            }
            
            // Provide specific error message based on error type
            if (!empty($auth_errors)) {
                $error_message = 'Invalid API key. Please check your API key and ensure it has the correct permissions.';
            } else if (!empty($network_errors)) {
                $error_message = 'Network connection error. Please check your internet connection and try again.';
            } else if (!empty($server_errors)) {
                $error_message = 'AI service is temporarily unavailable. Please try again later.';
            } else {
                $error_message = 'Unable to connect to the AI service. Please check your API key and network connection.';
            }
            
            error_log("Giga AI Connection Failed: " . $error_message);
            return ['success' => false, 'error' => $error_message];
        }

        error_log("Giga AI Connection Success: Provider {$provider}, Model {$model}, Latency {$time}ms");

        // Prepare user-friendly message
        $message = "Connection successful! Using {$model} model.";
        if ($model_was_switched) {
            $message = "Connection successful! Auto-switched to {$model} model.";
        }

        return [
            'success' => true,
            'latency' => $time . 'ms',
            'model' => $model,
            'provider' => $provider,
            'original_model' => $original_model,
            'model_was_switched' => $model_was_switched,
            'message' => $message
        ];
    }
    
    /**
     * Validate API key format based on provider
     */
    private function validate_api_key($provider, $api_key)
    {
        if (empty($api_key)) {
            return ['valid' => false, 'error' => 'API key is required'];
        }
        
        $api_key = trim($api_key);
        
        // Basic format validation for each provider
        switch ($provider) {
            case 'claude':
            case 'openai':
            case 'zai':
            case 'groq':
                // Most API keys are 20+ chars
                if (strlen($api_key) < 20) {
                    return ['valid' => false, 'error' => sprintf('Invalid %s API key format. The key is too short.', ucfirst($provider))];
                }
                break;
                
            case 'gemini':
                // Gemini API keys are usually 39 characters, but let's be safe.
                if (strlen($api_key) < 30) {
                    return ['valid' => false, 'error' => 'Invalid Google Gemini API key format. Please check your API key.'];
                }
                break;
                
            case 'ollama':
                // Ollama doesn't require API key validation
                break;
                
            default:
                return ['valid' => false, 'error' => 'Unknown provider: ' . $provider];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Test a single model connection
     */
    private function test_single_model($provider, $api_key, $model)
    {
        switch ($provider) {
            case 'claude':
                return $this->test_claude_connection($api_key, $model);
            case 'openai':
                return $this->test_openai_connection($api_key, $model);
            case 'gemini':
                return $this->test_gemini_connection($api_key, $model);
            case 'groq':
                return $this->test_groq_connection($api_key, $model);
            case 'zai':
                return $this->test_zai_connection($api_key, $model);
            case 'ollama':
                return $this->test_ollama_connection($model);
            default:
                return ['success' => false, 'error' => 'Unknown provider: ' . $provider];
        }
    }

    /**
     * Get available models for the selected provider
     * 
     * @return array List of available models
     */
    public function get_available_models()
    {
        $provider = get_option('giga_ai_provider', 'claude');

        $models = [
            'claude' => [
                'claude-3-5-sonnet-20241022' => ['name' => 'claude-3-5-sonnet-20241022', 'label' => 'Claude 3.5 Sonnet (Recommended)'],
                'claude-3-7-sonnet-20250219' => ['name' => 'claude-3-7-sonnet-20250219', 'label' => 'Claude 3.7 Sonnet (Latest)'],
                'claude-3-5-haiku-20241022' => ['name' => 'claude-3-5-haiku-20241022', 'label' => 'Claude 3.5 Haiku (Fast)']
            ],
            'openai' => [
                'gpt-4o' => ['name' => 'gpt-4o', 'label' => 'GPT-4o'],
                'gpt-4o-mini' => ['name' => 'gpt-4o-mini', 'label' => 'GPT-4o Mini (Fast)'],
                'o1-mini' => ['name' => 'o1-mini', 'label' => 'o1-mini (Smart Reasoning)']
            ],
            'gemini' => [
                'gemini-2.0-flash' => ['name' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash (Recommended)'],
                'gemini-1.5-pro' => ['name' => 'gemini-1.5-pro', 'label' => 'Gemini 1.5 Pro'],
                'gemini-1.5-flash' => ['name' => 'gemini-1.5-flash', 'label' => 'Gemini 1.5 Flash (Fast)']
            ],
            'groq' => [
                'llama-3.3-70b-versatile' => ['name' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B (Recommended)'],
                'deepseek-r1-distill-llama-70b' => ['name' => 'deepseek-r1-distill-llama-70b', 'label' => 'DeepSeek R1 Distill Llama 70B'],
                'gemma2-9b-it' => ['name' => 'gemma2-9b-it', 'label' => 'Gemma 2 9B']
            ],
            'zai' => [
                'glm-4-flash' => ['name' => 'glm-4-flash', 'label' => 'GLM-4 Flash (Fast & Free)'],
                'glm-4' => ['name' => 'glm-4', 'label' => 'GLM-4 (Balanced)'],
                'glm-4-plus' => ['name' => 'glm-4-plus', 'label' => 'GLM-4 Plus (Most Capable)']
            ],
            'ollama' => [
                'llama3' => ['name' => 'llama3', 'label' => 'Llama 3'],
                'mistral' => ['name' => 'mistral', 'label' => 'Mistral'],
                'qwen2' => ['name' => 'qwen2', 'label' => 'Qwen 2']
            ]
        ];

        return $models[$provider] ?? [];
    }

    /**
     * Get the default/recommended model for a provider
     * 
     * @param string $provider AI provider key
     * @return string Recommended model name
     */
    public function get_default_model($provider)
    {
        $defaults = [
            'claude' => 'claude-3-5-sonnet-20241022',
            'openai' => 'gpt-4o-mini',
            'gemini' => 'gemini-2.0-flash',
            'groq' => 'llama-3.3-70b-versatile',
            'zai' => 'glm-4-flash',
            'ollama' => 'llama3'
        ];

        return $defaults[$provider] ?? 'claude-3-5-sonnet-20241022';
    }

    /**
     * Call Anthropic Claude API
     */
    private function call_claude($prompt, $system, $key, $model, $temperature = 0.7)
    {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => 2048,
                'temperature' => (float)$temperature,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Giga AI Claude Error: " . $error_message);
            
            if (strpos($error_message, 'cURL error 28') !== false) {
                return ['error' => __('The request to Claude API timed out. Please try again.', 'giga-ai-product-writer')];
            }
            
            return ['error' => sprintf(__('Claude API request failed: %s', 'giga-ai-product-writer'), $error_message)];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'HTTP Error ' . $response_code;
            error_log("Giga AI Claude HTTP Error: " . $error_message);
            return ['error' => sprintf(__('Claude API error: %s', 'giga-ai-product-writer'), $error_message)];
        }

        if (!isset($body['content'][0]['text'])) {
            error_log("Giga AI Claude Invalid Response: " . json_encode($body));
            return ['error' => __('Invalid response format from Claude API', 'giga-ai-product-writer')];
        }

        // Extract token usage for logging
        $tokens_used = isset($body['usage']['input_tokens']) + isset($body['usage']['output_tokens']) ?
            (isset($body['usage']['input_tokens']) + isset($body['usage']['output_tokens'])) : 0;
        
        error_log("Giga AI Claude Success: Generated content using {$tokens_used} tokens");
        
        return [
            'text' => $body['content'][0]['text'],
            'tokens_used' => $tokens_used,
            'model' => $model
        ];
    }

    /**
     * Call OpenAI API
     */
    private function call_openai($prompt, $system, $key, $model, $temperature = 0.7)
    {
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
                'temperature' => (float)$temperature,
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response))
            return ['error' => $response->get_error_message()];
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
    private function call_gemini($prompt, $system, $key, $model, $temperature = 0.7)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [['parts' => [['text' => $system . "\n\n" . $prompt]]]],
                'generationConfig' => [
                    'maxOutputTokens' => 2048,
                    'temperature' => (float)$temperature
                ]
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response))
            return ['error' => $response->get_error_message()];
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
    private function call_groq($prompt, $system, $key, $model, $temperature = 0.7)
    {
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
                'temperature' => (float)$temperature,
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response))
            return ['error' => $response->get_error_message()];
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
    private function call_ollama($prompt, $system, $model, $temperature = 0.7)
    {
        $base_url = get_option('giga_ollama_base_url', 'http://localhost:11434');
        $response = wp_remote_post($base_url . '/api/generate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $model,
                'prompt' => $system . "\n\n" . $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => (float)$temperature
                ]
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response))
            return ['error' => $response->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['response'])) {
            return ['error' => 'Invalid response format from Ollama API'];
        }

        return ['text' => $body['response']];
    }

    /**
     * Call Z.ai API (OpenAI-compatible)
     */
    private function call_zai($prompt, $system, $key, $model, $temperature = 0.7)
    {
        $response = wp_remote_post('https://open.bigmodel.cn/api/paas/v4/chat/completions', [
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
                'temperature' => (float)$temperature,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response))
            return ['error' => $response->get_error_message()];
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
    private function test_claude_connection($key, $model)
    {
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
            
            // Provide user-friendly error messages
            if (strpos($error_msg, 'cURL error 28') !== false) {
                return ['error' => 'Connection timeout. Please check your internet connection and try again.'];
            } else if (strpos($error_msg, 'cURL error') !== false) {
                return ['error' => 'Network connection error. Please check your internet connection.'];
            } else if (strpos($error_msg, 'SSL') !== false) {
                return ['error' => 'SSL certificate error. Please check your server configuration.'];
            }
            
            return ['error' => 'Unable to connect to Claude. Please check your API key and try again.'];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        error_log("Giga AI Claude Response Code: {$response_code}");
        error_log("Giga AI Claude Response Body: " . json_encode($body));

        if ($response_code !== 200) {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'HTTP Error ' . $response_code;
            error_log("Giga AI Claude Error: " . $error_msg);
            
            // Provide user-friendly error messages
            if ($response_code === 401) {
                return ['error' => 'Invalid API key. Please check your Claude API key and try again.'];
            } else if ($response_code === 403) {
                return ['error' => 'Access denied. Please check your API key permissions.'];
            } else if ($response_code === 429) {
                return ['error' => 'Rate limit exceeded. Please wait a moment and try again.'];
            } else if ($response_code === 500) {
                return ['error' => 'Claude service is temporarily unavailable. Please try again later.'];
            }
            
            return ['error' => 'Unable to connect to Claude. Please check your API key and try again.'];
        }

        if (!isset($body['content'][0]['text'])) {
            error_log("Giga AI Claude Error: Invalid response format");
            return ['error' => 'Unexpected response from Claude. Please try again.'];
        }

        return ['success' => true];
    }

    /**
     * Test OpenAI connection
     */
    private function test_openai_connection($key, $model)
    {
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

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            return ['error' => $this->get_user_friendly_error($error_msg, 'OpenAI')];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $error_msg = $body['error']['message'] ?? 'OpenAI API error';
            return ['error' => $this->get_user_friendly_error($error_msg, 'OpenAI', $response_code)];
        }

        if ($response_code !== 200) {
            return ['error' => $this->get_user_friendly_error('HTTP Error ' . $response_code, 'OpenAI', $response_code)];
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Unexpected response format from OpenAI. Please try again.'];
        }

        return ['success' => true];
    }

    /**
     * Test Gemini connection
     */
    private function test_gemini_connection($key, $model)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [['parts' => [['text' => 'Hi']]]],
                'generationConfig' => ['maxOutputTokens' => 10]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            return ['error' => $this->get_user_friendly_error($error_msg, 'Gemini')];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $error_msg = $body['error']['message'] ?? 'Gemini API error';
            return ['error' => $this->get_user_friendly_error($error_msg, 'Gemini', $response_code)];
        }

        if ($response_code !== 200) {
            return ['error' => $this->get_user_friendly_error('HTTP Error ' . $response_code, 'Gemini', $response_code)];
        }

        if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return ['error' => 'Unexpected response from Gemini. Please try again.'];
        }

        return ['success' => true];
    }

    /**
     * Test Groq connection
     */
    private function test_groq_connection($key, $model)
    {
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

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            return ['error' => $this->get_user_friendly_error($error_msg, 'Groq')];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $error_msg = $body['error']['message'] ?? 'Groq API error';
            return ['error' => $this->get_user_friendly_error($error_msg, 'Groq', $response_code)];
        }

        if ($response_code !== 200) {
            return ['error' => $this->get_user_friendly_error('HTTP Error ' . $response_code, 'Groq', $response_code)];
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Unexpected response from Groq. Please try again.'];
        }

        return ['success' => true];
    }

    /**
     * Test Z.ai connection
     */
    private function test_zai_connection($key, $model)
    {
        $response = wp_remote_post('https://open.bigmodel.cn/api/paas/v4/chat/completions', [
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

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            return ['error' => $this->get_user_friendly_error($error_msg, 'Z.ai')];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $error_msg = $body['error']['message'] ?? 'Z.ai API error';
            return ['error' => $this->get_user_friendly_error($error_msg, 'Z.ai', $response_code)];
        }

        if ($response_code !== 200) {
            return ['error' => $this->get_user_friendly_error('HTTP Error ' . $response_code, 'Z.ai', $response_code)];
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return ['error' => 'Unexpected response from Z.ai. Please try again.'];
        }

        return ['success' => true];
    }

    /**
     * Test Ollama connection
     */
    private function test_ollama_connection($model)
    {
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

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            return ['error' => $this->get_user_friendly_error($error_msg, 'Ollama')];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            return ['error' => $this->get_user_friendly_error('HTTP Error ' . $response_code, 'Ollama', $response_code)];
        }

        if (!isset($body['response'])) {
            return ['error' => 'Unable to get response from Ollama. Please check that Ollama is running and the model is installed.'];
        }

        return ['success' => true];
    }
    
    /**
     * Get user-friendly error message
     */
    private function get_user_friendly_error($error_msg, $provider, $response_code = null)
    {
        // Network errors
        if (strpos($error_msg, 'cURL error 28') !== false) {
            return 'Connection timeout. Please check your internet connection and try again.';
        } else if (strpos($error_msg, 'cURL error') !== false) {
            return 'Network connection error. Please check your internet connection.';
        } else if (strpos($error_msg, 'SSL') !== false) {
            return 'SSL certificate error. Please check your server configuration.';
        }
        
        // Authentication errors
        if ($response_code === 401) {
            return "Invalid API key. Please check your {$provider} API key and try again.";
        } else if ($response_code === 403) {
            return "Access denied. Please check your API key permissions.";
        }
        
        // Rate limiting
        if ($response_code === 429) {
            return 'Rate limit exceeded. Please wait a moment and try again.';
        }
        
        // Server errors
        if ($response_code >= 500) {
            return "{$provider} service is temporarily unavailable. Please try again later.";
        }
        
        // Default error
        return "Unable to connect to {$provider}. Please check your API key and try again.";
    }

    /**
     * Get decrypted API key
     */
    public function get_decrypted_key()
    {
        $provider = get_option('giga_ai_provider', 'claude');
        if ($provider === 'ollama')
            return '';

        $encrypted = get_option('giga_ai_api_key', '');
        if (empty($encrypted))
            return '';

        return $this->decrypt_key($encrypted);
    }

    /**
     * Public wrapper for encryption
     */
    public function encrypt_key($data)
    {
        if (empty($data))
            return '';
        $salt = wp_salt('auth');
        return $this->encrypt($data, $salt);
    }

    /**
     * Public wrapper for decryption
     */
    public function decrypt_key($data)
    {
        if (empty($data))
            return '';
        $salt = wp_salt('auth');
        return $this->decrypt($data, $salt);
    }

    /**
     * Encrypt data
     */
    private function encrypt($data, $key)
    {
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16)));
    }

    /**
     * Decrypt data
     */
    private function decrypt($data, $key)
    {
        $decrypted = openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        return $decrypted !== false ? $decrypted : $data; // Return original if decryption fails (fallback for plain text)
    }
}