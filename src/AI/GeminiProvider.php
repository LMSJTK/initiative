<?php

namespace StartupGame\AI;

/**
 * Google Gemini Provider
 */
class GeminiProvider implements AIProvider
{
    private string $apiKey;
    private string $modelVersion;
    private string $apiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(string $apiKey, string $modelVersion = 'gemini-3.0')
    {
        $this->apiKey = $apiKey;
        $this->modelVersion = $modelVersion;
    }

    public function chat(array $messages, array $context = []): string
    {
        $systemPrompt = $context['system'] ?? '';
        $temperature = $context['temperature'] ?? 0.7;
        $maxTokens = $context['max_tokens'] ?? 4096;

        // Convert messages to Gemini format
        $contents = $this->convertMessagesToGeminiFormat($messages, $systemPrompt);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens
            ]
        ];

        $response = $this->makeRequest($payload);

        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new \RuntimeException('Invalid response from Gemini API');
    }

    public function generate(string $prompt, array $context = []): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        return $this->chat($messages, $context);
    }

    private function convertMessagesToGeminiFormat(array $messages, string $systemPrompt = ''): array
    {
        $contents = [];

        // Add system prompt as first user message if provided
        if ($systemPrompt) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => 'System: ' . $systemPrompt]]
            ];
        }

        foreach ($messages as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]]
            ];
        }

        return $contents;
    }

    private function makeRequest(array $payload): array
    {
        $modelId = $this->getModelIdentifier();
        $url = $this->apiEndpoint . $modelId . ':generateContent?key=' . $this->apiKey;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
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
            'gemini-3.0' => 'gemini-pro',
            '3.0' => 'gemini-pro',
            'gemini-3' => 'gemini-pro'
        ];

        return $modelMap[$this->modelVersion] ?? 'gemini-pro';
    }

    public function getProviderName(): string
    {
        return 'google';
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }
}
