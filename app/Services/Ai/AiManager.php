<?php

namespace App\Services\Ai;

use App\Models\StoreSetting;
use App\Services\Ai\Providers\ClaudeProvider;
use App\Services\Ai\Providers\DeepSeekProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;

class AiManager
{
    /**
     * Resolve the currently active provider instance.
     */
    public function getActiveProvider(): ?AiProviderInterface
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $providerName = $this->getActiveProviderName();
        $apiKey = $this->getApiKey($providerName);

        if (empty($apiKey)) {
            return null;
        }

        $model = $this->getModel($providerName);

        return $this->getProvider($providerName, $apiKey, $model);
    }

    /**
     * Check if AI engine is enabled storewide.
     */
    public function isEnabled(): bool
    {
        // Check StoreSetting first, then fallback to .env flag
        $val = StoreSetting::get('ai_enabled');
        if ($val !== null) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }
        return env('AI_ENABLED', true);
    }

    /**
     * Get active provider key.
     */
    public function getActiveProviderName(): string
    {
        $setting = StoreSetting::get('ai_active_provider');
        if (!empty($setting)) {
            return strtolower(trim($setting));
        }
        return config('ai.default_provider', 'gemini');
    }

    /**
     * Get API key for a specific provider.
     */
    public function getApiKey(string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        $dbKey = StoreSetting::get("ai_{$provider}_key");
        if (!empty($dbKey)) {
            return trim($dbKey);
        }

        // Fallback to environment variables
        return match ($provider) {
            'openai'   => env('OPENAI_API_KEY'),
            'deepseek' => env('DEEPSEEK_API_KEY'),
            'claude'   => env('CLAUDE_API_KEY') ?: env('ANTHROPIC_API_KEY'),
            default    => env('GEMINI_API_KEY'),
        };
    }

    /**
     * Get configured or default model for a specific provider.
     */
    public function getModel(string $provider): string
    {
        $provider = strtolower(trim($provider));
        $dbModel = StoreSetting::get("ai_{$provider}_model");
        if (!empty($dbModel)) {
            return trim($dbModel);
        }

        return config("ai.providers.{$provider}.default_model", 'default');
    }

    /**
     * Factory: Build a concrete provider instance.
     */
    public function getProvider(string $providerName, ?string $apiKey = null, ?string $model = null): AiProviderInterface
    {
        $providerName = strtolower(trim($providerName));
        $effectiveKey = $apiKey ?: $this->getApiKey($providerName);
        $effectiveModel = $model ?: $this->getModel($providerName);
        $timeout = (int) config('ai.defaults.timeout', 30);

        return match ($providerName) {
            'openai'   => new OpenAiProvider($effectiveKey ?? '', $effectiveModel, $timeout),
            'deepseek' => new DeepSeekProvider($effectiveKey ?? '', $effectiveModel, $timeout),
            'claude'   => new ClaudeProvider($effectiveKey ?? '', $effectiveModel, $timeout),
            default    => new GeminiProvider($effectiveKey ?? '', $effectiveModel, $timeout),
        };
    }

    /**
     * Test connection to a provider with specified or stored credentials.
     */
    public function testProvider(string $providerName, ?string $apiKey = null, ?string $model = null): array
    {
        $provider = $this->getProvider($providerName, $apiKey, $model);
        return $provider->testConnection();
    }
}

