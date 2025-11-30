<?php

namespace StartupGame\AI;

/**
 * Base interface for AI providers
 */
interface AIProvider
{
    /**
     * Send a message and get a response
     *
     * @param array $messages Message history in format: [['role' => 'user|assistant', 'content' => '...']]
     * @param array $context Additional context (system prompt, temperature, etc.)
     * @return string The AI response
     */
    public function chat(array $messages, array $context = []): string;

    /**
     * Generate text based on a prompt
     *
     * @param string $prompt The prompt
     * @param array $context Additional context
     * @return string The generated text
     */
    public function generate(string $prompt, array $context = []): string;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Get model version
     *
     * @return string
     */
    public function getModelVersion(): string;
}
