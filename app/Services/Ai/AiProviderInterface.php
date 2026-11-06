<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    /**
     * Get provider technical identifier ('gemini', 'openai', 'deepseek', 'claude').
     */
    public function getIdentifier(): string;

    /**
     * Get human-readable provider name.
     */
    public function getName(): string;

    /**
     * Generate text from a single prompt.
     *
     * @param string $prompt
     * @param array $options [model, temperature, max_tokens, system_instruction]
     * @return string
     */
    public function generateText(string $prompt, array $options = []): string;

    /**
     * Multi-turn chat conversation.
     *
     * @param array $messages [['role' => 'user'|'assistant'|'system', 'content' => string]]
     * @param array $options
     * @return string
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Test connection to provider API with credentials.
     *
     * @return array ['success' => bool, 'message' => string, 'latency_ms' => int, 'model' => string]
     */
    public function testConnection(): array;
}

