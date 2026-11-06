<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderInterface;
use Illuminate\Support\Facades\Log;

abstract class BaseAiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected ?string $model;
    protected int $timeout;

    public function __construct(string $apiKey, ?string $model = null, int $timeout = 30)
    {
        $this->apiKey  = trim($apiKey);
        $this->model   = $model;
        $this->timeout = $timeout;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getModel(): string
    {
        if (!empty($this->model)) {
            return $this->model;
        }
        return config("ai.providers.{$this->getIdentifier()}.default_model", 'default');
    }

    /**
     * Default test connection implementation using a fast 1-word ping query.
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'    => false,
                'message'    => 'API Key is missing or empty.',
                'latency_ms' => 0,
                'model'      => $this->getModel(),
            ];
        }

        $start = microtime(true);

        try {
            $response = $this->generateText("Respond with only the single word: OK", [
                'max_tokens'  => 10,
                'temperature' => 0.1,
            ]);

            $latency = (int) round((microtime(true) - $start) * 1000);

            if (!empty($response)) {
                return [
                    'success'    => true,
                    'message'    => "Successfully connected to {$this->getName()} API! Response: " . trim($response),
                    'latency_ms' => $latency,
                    'model'      => $this->getModel(),
                ];
            }

            return [
                'success'    => false,
                'message'    => "Received empty response from {$this->getName()}.",
                'latency_ms' => $latency,
                'model'      => $this->getModel(),
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            Log::warning("AI testConnection failed for {$this->getName()}: " . $e->getMessage());

            return [
                'success'    => false,
                'message'    => $e->getMessage(),
                'latency_ms' => $latency,
                'model'      => $this->getModel(),
            ];
        }
    }
}

