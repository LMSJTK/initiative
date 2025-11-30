<?php

namespace StartupGame\AI;

/**
 * Factory for creating AI provider instances
 */
class AIFactory
{
    /**
     * Create an AI provider instance
     *
     * @param string $provider Provider name (anthropic, openai, google)
     * @param string $apiKey API key for the provider
     * @param string $modelVersion Model version to use
     * @return AIProvider
     */
    public static function create(string $provider, string $apiKey, string $modelVersion): AIProvider
    {
        switch (strtolower($provider)) {
            case 'anthropic':
            case 'claude':
                return new ClaudeProvider($apiKey, $modelVersion);

            case 'openai':
            case 'chatgpt':
                return new ChatGPTProvider($apiKey, $modelVersion);

            case 'google':
            case 'gemini':
                return new GeminiProvider($apiKey, $modelVersion);

            default:
                throw new \InvalidArgumentException('Unknown AI provider: ' . $provider);
        }
    }

    /**
     * Create provider from teammate configuration
     *
     * @param \StartupGame\Models\Teammate $teammate
     * @param string $apiKey
     * @return AIProvider
     */
    public static function createFromTeammate(\StartupGame\Models\Teammate $teammate, string $apiKey): AIProvider
    {
        return self::create($teammate->modelProvider, $apiKey, $teammate->modelVersion);
    }
}
