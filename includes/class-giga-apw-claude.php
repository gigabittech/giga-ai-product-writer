<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Giga_APW_Claude {
    private static $instance = null;
    private $api_key;
    private $model;
    private $max_tokens;
    private $temperature;
    private $api_endpoint;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = Giga_APW_Admin::get_api_key();
        $this->model = GIGA_APW_CLAUDE_MODEL;
        $this->max_tokens = GIGA_APW_CLAUDE_MAX_TOKENS;
        $this->temperature = GIGA_APW_CLAUDE_TEMPERATURE;
        $this->api_endpoint = GIGA_APW_API_ENDPOINT;
    }

    /**
     * @param string $system_prompt
     * @param string $user_prompt
     * @return string|WP_Error
     */
    public function generate($system_prompt, $user_prompt) {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', __('Claude API key is not configured. Please add it in settings.', 'giga-ai-product-writer'));
        }

        $body = [
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'system' => $system_prompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $user_prompt
                ]
            ]
        ];

        $args = [
            'body'        => wp_json_encode($body),
            'headers'     => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'timeout'     => 60,
            'data_format' => 'body'
        ];

        $response = wp_remote_post($this->api_endpoint, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (strpos($error_message, 'cURL error 28') !== false) {
                return new WP_Error('api_timeout', __('The request to Claude API timed out. Please try again.', 'giga-ai-product-writer'));
            }
            return new WP_Error('api_error', sprintf(__('API Request failed: %s', 'giga-ai-product-writer'), $error_message));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $api_error = $data['error']['message'] ?? __('Unknown API error', 'giga-ai-product-writer');
            
            if ($response_code === 401) {
                return new WP_Error('auth_error', __('Invalid Claude API key. Please check your settings.', 'giga-ai-product-writer'));
            } elseif ($response_code === 429) {
                return new WP_Error('rate_limit', __('Claude API rate limit exceeded. Please wait a moment and try again.', 'giga-ai-product-writer'));
            }

            return new WP_Error('api_error', sprintf(__('Claude API returned an error: %s', 'giga-ai-product-writer'), $api_error));
        }

        if (!isset($data['content'][0]['text'])) {
            return new WP_Error('invalid_response', __('Invalid response format from Claude API.', 'giga-ai-product-writer'));
        }

        // Token usage can be extracted from $data['usage'] and stored later by the caller
        return $data; // Return full data so caller can log tokens
    }

    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', __('Please enter and save your API key first.', 'giga-ai-product-writer'));
        }

        $system_prompt = "You are a helpful assistant.";
        $user_prompt = "Say OK";

        $result = $this->generate($system_prompt, $user_prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}
