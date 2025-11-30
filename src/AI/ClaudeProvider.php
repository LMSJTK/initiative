<?php

namespace StartupGame\AI;

/**
 * Anthropic Claude AI Provider
 */
class ClaudeProvider implements AIProvider
{
    private string $apiKey;
    private string $modelVersion;
    private string $apiEndpoint = 'https://api.anthropic.com/v1/messages';

    public function __construct(string $apiKey, string $modelVersion = 'claude-sonnet-4.5')
    {
        $this->apiKey = $apiKey;
        $this->modelVersion = $modelVersion;
    }

    public function chat(array $messages, array $context = []): string
    {
        $systemPrompt = $context['system'] ?? '';
        $temperature = $context['temperature'] ?? 0.7;
        $maxTokens = $context['max_tokens'] ?? 4096;

        $payload = [
            'model' => $this->getModelIdentifier(),
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = $this->makeRequest($payload);

        if (isset($response['content'][0]['text'])) {
            return $response['content'][0]['text'];
        }

        throw new \RuntimeException('Invalid response from Claude API');
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
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
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
            'claude-sonnet-4.5' => 'claude-sonnet-4-20250514',
            'claude-opus-4.5' => 'claude-opus-4-20250514',
            'sonnet-4.5' => 'claude-sonnet-4-20250514',
            'opus-4.5' => 'claude-opus-4-20250514'
        ];

        return $modelMap[$this->modelVersion] ?? $this->modelVersion;
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }
}
