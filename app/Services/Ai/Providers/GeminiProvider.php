<?php

namespace App\Services\Ai\Providers;

use Illuminate\Support\Facades\Http;

class GeminiProvider extends BaseAiProvider
{
    public function getIdentifier(): string
    {
        return 'gemini';
    }

    public function getName(): string
    {
        return 'Google Gemini';
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

        $contents = [];
        $systemInstruction = $options['system_instruction'] ?? null;

        foreach ($messages as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            if ($msg['role'] === 'system') {
                $systemInstruction = $msg['content'];
                continue;
            }

            $contents[] = [
                'role'  => $role,
                'parts' => [
                    ['text' => $msg['content']]
                ],
            ];
        }

        if (empty($contents)) {
            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => 'Hello']],
            ];
        }

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => (float) $temperature,
                'maxOutputTokens' => (int) $maxTokens,
            ],
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            $errorJson = $response->json();
            $errorMsg = $errorJson['error']['message'] ?? $response->body();
            throw new \RuntimeException("Gemini API Error: " . $errorMsg);
        }

        $json = $response->json();
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return trim($text);
    }
}

