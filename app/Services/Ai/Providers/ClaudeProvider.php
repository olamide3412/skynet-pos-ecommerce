<?php

namespace App\Services\Ai\Providers;

use Illuminate\Support\Facades\Http;

class ClaudeProvider extends BaseAiProvider
{
    public function getIdentifier(): string
    {
        return 'claude';
    }

    public function getName(): string
    {
        return 'Anthropic Claude';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        return $this->chat($messages, $options);
    }

    public function chat(array $messages, array $options = []): string
    {
        $model = $options['model'] ?? $this->getModel();
        $temperature = $options['temperature'] ?? (float) config('ai.defaults.temperature', 0.7);
        $maxTokens = $options['max_tokens'] ?? (int) config('ai.defaults.max_tokens', 2048);

        $systemPrompt = $options['system_instruction'] ?? null;
        $claudeMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
                continue;
            }

            $claudeMessages[] = [
                'role'    => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $payload = [
            'model'       => $model,
            'messages'    => $claudeMessages,
            'max_tokens'  => (int) $maxTokens,
            'temperature' => (float) $temperature,
        ];

        if (!empty($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if ($response->failed()) {
            $errorJson = $response->json();
            $errorMsg = $errorJson['error']['message'] ?? $response->body();
            throw new \RuntimeException("Claude API Error: " . $errorMsg);
        }

        $json = $response->json();
        $text = $json['content'][0]['text'] ?? '';

        return trim($text);
    }
}

