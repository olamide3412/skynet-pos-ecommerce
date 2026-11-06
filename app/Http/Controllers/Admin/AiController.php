<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Ai\AiManager;
use App\Services\Ai\ProductDescriptionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiController extends Controller
{
    public function __construct(
        protected AiManager $aiManager,
        protected ProductDescriptionGenerator $descriptionGenerator
    ) {}

    /**
     * Generate AI-crafted short plain text or rich HTML product description.
     */
    public function generateProductDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name'  => 'required|string|max:255',
            'type'          => 'required|string|in:short,full',
            'category_name' => 'nullable|string|max:100',
            'price'         => 'nullable|numeric|min:0',
            'keywords'      => 'nullable|string|max:500',
            'tone'          => 'nullable|string|max:50',
        ]);

        try {
            if ($validated['type'] === 'short') {
                $text = $this->descriptionGenerator->generateShortDescription($validated);
            } else {
                $text = $this->descriptionGenerator->generateFullDescription($validated);
            }

            ActivityLogger::product("Generated AI {$validated['type']} description for product '{$validated['product_name']}'", [
                'type'     => $validated['type'],
                'provider' => $this->aiManager->getActiveProviderName(),
                'model'    => $this->aiManager->getModel($this->aiManager->getActiveProviderName()),
            ]);

            return response()->json([
                'success'  => true,
                'type'     => $validated['type'],
                'text'     => $text,
                'content'  => $text,
                'provider' => $this->aiManager->getActiveProviderName(),
                'model'    => $this->aiManager->getModel($this->aiManager->getActiveProviderName()),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Test connection to a specific AI provider API.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $user = $request->user();
        $canManage = $user && ($user->isSuperAdmin() || $user->can('settings.store') || in_array($user->role, ['administrator', 'super administrator'], true));

        if (!$canManage) {
            abort(403, 'Unauthorized to test AI connection.');
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:gemini,openai,deepseek,claude',
            'api_key'  => 'nullable|string|max:500',
            'model'    => 'nullable|string|max:100',
        ]);

        $result = $this->aiManager->testProvider(
            $validated['provider'],
            $validated['api_key'] ?? null,
            $validated['model'] ?? null
        );

        $result['provider'] = $validated['provider'];

        return response()->json($result);
    }
}
