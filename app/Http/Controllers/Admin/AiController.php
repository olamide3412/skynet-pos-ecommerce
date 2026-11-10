<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreSetting;
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
            'type'          => 'required|string|in:short,full,seo',
            'category_name' => 'nullable|string|max:100',
            'brand_name'    => 'nullable|string|max:120',
            'price'         => 'nullable|numeric|min:0',
            'keywords'      => 'nullable|string|max:500',
            'tone'          => 'nullable|string|max:50',
        ]);

        try {
            $seo = null;
            if ($validated['type'] === 'short') {
                $text = $this->descriptionGenerator->generateShortDescription($validated);
            } elseif ($validated['type'] === 'full') {
                $text = $this->descriptionGenerator->generateFullDescription($validated);
            } else {
                $seo = $this->descriptionGenerator->generateSeoMetadata($validated);
                $text = $seo['meta_description'] ?? '';
            }

            ActivityLogger::product("Generated AI {$validated['type']} metadata for product '{$validated['product_name']}'", [
                'type'     => $validated['type'],
                'provider' => $this->aiManager->getActiveProviderName(),
                'model'    => $this->aiManager->getModel($this->aiManager->getActiveProviderName()),
            ]);

            return response()->json([
                'success'  => true,
                'type'     => $validated['type'],
                'text'     => $text,
                'content'  => $text,
                'seo'      => $seo,
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
     * Suggest and rank related products using AI.
     */
    public function suggestRelatedProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name'        => 'required|string|max:255',
            'category_id'         => 'nullable|integer',
            'category_name'       => 'nullable|string|max:255',
            'current_product_id'  => 'nullable|integer',
            'description'         => 'nullable|string',
            'limit'               => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $validated['limit'] ?? 10;
        $currentId = $validated['current_product_id'] ?? null;
        $categoryId = $validated['category_id'] ?? null;
        $strategy = StoreSetting::get('ai_related_strategy', 'hybrid'); // hybrid, category_only, ai_only

        try {
            $matchedProducts = [];
            $selectedIds = [];

            // 1. Category-Only Strategy
            if ($strategy === 'category_only') {
                if ($categoryId) {
                    $catProducts = Product::where('status', true)
                        ->where('category_id', $categoryId)
                        ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                        ->with('category:id,name')
                        ->select('id', 'name', 'category_id', 'price', 'image')
                        ->latest('id')
                        ->limit($limit)
                        ->get();

                    foreach ($catProducts as $cp) {
                        $arr = $cp->toArray();
                        $arr['ai_reason'] = 'Same category companion';
                        $matchedProducts[] = $arr;
                        $selectedIds[] = $cp->id;
                    }
                }
            }
            // 2. Hybrid Strategy: 5 from Same Category + 5 AI smart cross-sell suggestions (up to 10 total)
            elseif ($strategy === 'hybrid') {
                $categoryTarget = 5;
                if ($categoryId) {
                    $catProducts = Product::where('status', true)
                        ->where('category_id', $categoryId)
                        ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                        ->with('category:id,name')
                        ->select('id', 'name', 'category_id', 'price', 'image')
                        ->latest('id')
                        ->limit($categoryTarget)
                        ->get();

                    foreach ($catProducts as $cp) {
                        $arr = $cp->toArray();
                        $arr['ai_reason'] = 'Same category companion';
                        $matchedProducts[] = $arr;
                        $selectedIds[] = $cp->id;
                    }
                }

                $aiNeeded = max(5, $limit - count($matchedProducts));
                $excludedIds = array_filter(array_merge([$currentId], $selectedIds));

                $aiCandidates = Product::where('status', true)
                    ->whereNotIn('id', $excludedIds)
                    ->with('category:id,name')
                    ->select('id', 'name', 'category_id', 'price', 'image')
                    ->latest('id')
                    ->limit(40)
                    ->get();

                if ($aiCandidates->isNotEmpty()) {
                    $candidatePayload = $aiCandidates->map(fn($p) => [
                        'id'            => $p->id,
                        'name'          => $p->name,
                        'category_name' => $p->category?->name ?? 'General',
                        'price'         => $p->price,
                    ])->toArray();

                    $suggestions = $this->descriptionGenerator->suggestRelatedProducts([
                        'name'          => $validated['product_name'],
                        'category_name' => $validated['category_name'] ?? '',
                        'description'   => $validated['description'] ?? '',
                    ], $candidatePayload, $aiNeeded);

                    $reasonMap = [];
                    foreach ($suggestions as $s) {
                        if (!empty($s['id'])) {
                            $reasonMap[$s['id']] = $s['reason'] ?? 'Recommended complement';
                        }
                    }

                    foreach (array_column($suggestions, 'id') as $sid) {
                        $cand = $aiCandidates->firstWhere('id', $sid);
                        if ($cand && !in_array($cand->id, $selectedIds, true)) {
                            $arr = $cand->toArray();
                            $arr['ai_reason'] = $reasonMap[$sid] ?? 'Recommended complement';
                            $matchedProducts[] = $arr;
                            $selectedIds[] = $cand->id;
                        }
                    }
                }

                // If still under desired limit and more candidates exist, supplement
                if (count($matchedProducts) < $limit) {
                    $fallbackCandidates = Product::where('status', true)
                        ->whereNotIn('id', array_filter(array_merge([$currentId], $selectedIds)))
                        ->with('category:id,name')
                        ->select('id', 'name', 'category_id', 'price', 'image')
                        ->latest('id')
                        ->limit($limit - count($matchedProducts))
                        ->get();

                    foreach ($fallbackCandidates as $fc) {
                        $arr = $fc->toArray();
                        $arr['ai_reason'] = 'Catalog complement';
                        $matchedProducts[] = $arr;
                        $selectedIds[] = $fc->id;
                    }
                }
            }
            // 3. AI-Only Strategy
            else {
                $candidates = Product::where('status', true)
                    ->when($currentId, fn($q) => $q->where('id', '!=', $currentId))
                    ->with('category:id,name')
                    ->select('id', 'name', 'category_id', 'price', 'image')
                    ->when($categoryId, function ($q) use ($categoryId) {
                        $q->orderByRaw("CASE WHEN category_id = ? THEN 0 ELSE 1 END", [$categoryId]);
                    })
                    ->latest('id')
                    ->limit(40)
                    ->get();

                if ($candidates->isNotEmpty()) {
                    $candidatePayload = $candidates->map(fn($p) => [
                        'id'            => $p->id,
                        'name'          => $p->name,
                        'category_name' => $p->category?->name ?? 'General',
                        'price'         => $p->price,
                    ])->toArray();

                    $suggestions = $this->descriptionGenerator->suggestRelatedProducts([
                        'name'          => $validated['product_name'],
                        'category_name' => $validated['category_name'] ?? '',
                        'description'   => $validated['description'] ?? '',
                    ], $candidatePayload, $limit);

                    $reasonMap = [];
                    foreach ($suggestions as $s) {
                        if (!empty($s['id'])) {
                            $reasonMap[$s['id']] = $s['reason'] ?? 'Recommended complement';
                        }
                    }

                    foreach (array_column($suggestions, 'id') as $sid) {
                        $item = $candidates->firstWhere('id', $sid);
                        if ($item) {
                            $arr = $item->toArray();
                            $arr['ai_reason'] = $reasonMap[$sid] ?? null;
                            $matchedProducts[] = $arr;
                            $selectedIds[] = $item->id;
                        }
                    }
                }
            }

            if (empty($matchedProducts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No other active products available in your store catalog to relate.',
                    'products' => [],
                ], 422);
            }

            ActivityLogger::product("AI suggested " . count($matchedProducts) . " related products for '{$validated['product_name']}'", [
                'target_product' => $validated['product_name'],
                'suggested_ids'  => $selectedIds,
                'strategy'       => $strategy,
                'provider'       => $this->aiManager->getActiveProviderName(),
            ]);

            return response()->json([
                'success'  => true,
                'products' => $matchedProducts,
                'ids'      => $selectedIds,
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
