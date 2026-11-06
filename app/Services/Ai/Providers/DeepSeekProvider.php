<?php

namespace App\Services\Ai\Providers;

use Illuminate\Support\Facades\Http;

class DeepSeekProvider extends BaseAiProvider
{
    public function getIdentifier(): string
    {
        return 'deepseek';
    }

    public function getName(): string
    {
        return 'DeepSeek';
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

        $formattedMessages = [];
        if (!empty($options['system_instruction'])) {
            $formattedMessages[] = [
                'role'    => 'system',
                'content' => $options['system_instruction'],
            ];
        }

        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role'    => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $payload = [
            'model'       => $model,
            'messages'    => $formattedMessages,
            'temperature' => (float) $temperature,
            'max_tokens'  => (int) $maxTokens,
        ];

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->post('https://api.deepseek.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            $errorJson = $response->json();
            $errorMsg = $errorJson['error']['message'] ?? $response->body();
            throw new \RuntimeException("DeepSeek API Error: " . $errorMsg);
        }

        $json = $response->json();
        $text = $json['choices'][0]['message']['content'] ?? '';

        return trim($text);
    }
}

