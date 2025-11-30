<?php

namespace StartupGame\AI;

/**
 * OpenAI ChatGPT Provider
 */
class ChatGPTProvider implements AIProvider
{
    private string $apiKey;
    private string $modelVersion;
    private string $apiEndpoint = 'https://api.openai.com/v1/chat/completions';

    public function __construct(string $apiKey, string $modelVersion = 'gpt-5.1')
    {
        $this->apiKey = $apiKey;
        $this->modelVersion = $modelVersion;
    }

    public function chat(array $messages, array $context = []): string
    {
        $systemPrompt = $context['system'] ?? '';
        $temperature = $context['temperature'] ?? 0.7;
        $maxTokens = $context['max_tokens'] ?? 4096;

        // Add system message if provided
        if ($systemPrompt) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemPrompt
            ]);
        }

        $payload = [
            'model' => $this->getModelIdentifier(),
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ];

        $response = $this->makeRequest($payload);

        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        throw new \RuntimeException('Invalid response from OpenAI API');
    }

    public function generate(string $prompt, array $context = []): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        return $this->chat($messages, $context);
    }

    private function makeRequest(array $payload): array
    {
        $ch = curl_init($this->apiEndpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('API error (HTTP ' . $httpCode . '): ' . $response);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode API response');
        }

        return $decoded;
    }

    private function getModelIdentifier(): string
    {
        $modelMap = [
            'gpt-5.1' => 'gpt-5.1-turbo',
            '5.1' => 'gpt-5.1-turbo',
            'chatgpt-5.1' => 'gpt-5.1-turbo'
        ];

        return $modelMap[$this->modelVersion] ?? $this->modelVersion;
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }
}
