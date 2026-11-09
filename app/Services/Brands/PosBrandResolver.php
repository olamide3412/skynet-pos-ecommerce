<?php

namespace App\Services\Brands;

use App\Models\Brand;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PosBrandResolver
{
    /**
     * Resolve or auto-create an eCommerce Brand from POS brand identifiers.
     *
     * Resolution Order:
     * 1. Return null if both $posBrandId and $posBrandName are empty.
     * 2. Check in-memory $cache by "id:{posBrandId}" or "name:{lowerName}".
     * 3. Match by brands.pos_brand_id = $posBrandId.
     * 4. Match case-insensitively by name or by slug; if found and pos_brand_id is empty, link it.
     * 5. If $create is true, auto-create the brand (active by default, linked to pos_brand_id).
     * 6. Populate $cache and return brand ID.
     */
    public function resolve(
        int|string|null $posBrandId,
        ?string $posBrandName,
        array &$cache = [],
        bool $create = true
    ): ?int {
        $posBrandId = ($posBrandId !== null && $posBrandId !== '' && (int) $posBrandId > 0)
            ? (int) $posBrandId
            : null;
        $cleanName = $posBrandName !== null ? trim($posBrandName) : '';

        if ($posBrandId === null && $cleanName === '') {
            return null;
        }

        $idKey = $posBrandId !== null ? "id:{$posBrandId}" : null;
        $nameKey = $cleanName !== '' ? 'name:' . mb_strtolower($cleanName) : null;

        // 2. Check in-memory cache
        if ($idKey !== null && array_key_exists($idKey, $cache)) {
            return $cache[$idKey];
        }
        if ($nameKey !== null && array_key_exists($nameKey, $cache)) {
            return $cache[$nameKey];
        }

        $brand = null;

        // 3. Match by pos_brand_id
        if ($posBrandId !== null) {
            $brand = Brand::where('pos_brand_id', $posBrandId)->first();
            if ($brand && $cleanName !== '' && $brand->name !== $cleanName) {
                $brand->update(['name' => $cleanName]);
            }
        }

        // 4. Match case-insensitively by name or slug
        if (!$brand && $cleanName !== '') {
            $slugCandidate = Str::slug($cleanName);
            $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
                ->when($slugCandidate !== '', fn ($q) => $q->orWhere('slug', $slugCandidate))
                ->first();

            if ($brand && $posBrandId !== null && empty($brand->pos_brand_id)) {
                try {
                    $brand->update(['pos_brand_id' => $posBrandId]);
                } catch (Throwable) {
                    // Ignore if another brand already claimed this pos_brand_id
                }
            }
        }

        // 5. Auto-create if missing
        if (!$brand && $create) {
            $brandName = $cleanName !== '' ? $cleanName : "POS Brand #{$posBrandId}";
            try {
                $brand = Brand::create([
                    'name'         => $brandName,
                    'slug'         => $this->generateUniqueSlug($brandName),
                    'is_active'    => true,
                    'is_featured'  => false,
                    'sort_order'   => 0,
                    'pos_brand_id' => $posBrandId,
                ]);
            } catch (UniqueConstraintViolationException|QueryException) {
                // Concurrency fallback: re-query
                if ($posBrandId !== null) {
                    $brand = Brand::where('pos_brand_id', $posBrandId)->first();
                }
                if (!$brand && $cleanName !== '') {
                    $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
                        ->orWhere('slug', Str::slug($cleanName))
                        ->first();
                }
            }
        }

        $resolvedId = $brand?->id;

        if ($resolvedId !== null) {
            if ($idKey !== null) {
                $cache[$idKey] = $resolvedId;
            }
            if ($nameKey !== null) {
                $cache[$nameKey] = $resolvedId;
            }
            if ($brand && !empty($brand->name)) {
                $cache['name:' . mb_strtolower($brand->name)] = $resolvedId;
            }
        }

        return $resolvedId;
    }

    /**
     * Upsert a brand from a POS brand payload (/brands endpoint or brand.created / brand.updated webhook).
     */
    public function upsertFromPos(array $data): ?Brand
    {
        $posBrandId = $data['pos_brand_id'] ?? $data['id'] ?? $data['brand_id'] ?? null;
        $posBrandId = ($posBrandId !== null && $posBrandId !== '' && (int) $posBrandId > 0)
            ? (int) $posBrandId
            : null;
        $name = isset($data['name']) ? trim((string) $data['name']) : (isset($data['brand_name']) ? trim((string) $data['brand_name']) : '');

        if ($posBrandId === null && $name === '') {
            return null;
        }

        $brand = null;
        if ($posBrandId !== null) {
            $brand = Brand::where('pos_brand_id', $posBrandId)->first();
        }

        if (!$brand && $name !== '') {
            $slugCandidate = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($name);
            $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($slugCandidate !== '', fn ($q) => $q->orWhere('slug', $slugCandidate))
                ->first();
        }

        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $description = $data['description'] ?? null;
        $logoUrl = $data['logo_url'] ?? $data['logo'] ?? null;

        if ($brand) {
            $updates = [];
            if ($name !== '') {
                $updates['name'] = $name;
            }
            if ($posBrandId !== null && empty($brand->pos_brand_id)) {
                $updates['pos_brand_id'] = $posBrandId;
            }
            if (array_key_exists('is_active', $data)) {
                $updates['is_active'] = $isActive;
            }
            if (!empty($description) && empty($brand->description)) {
                $updates['description'] = $description;
            }
            if (!empty($updates)) {
                $brand->update($updates);
            }
        } else {
            $brandName = $name !== '' ? $name : "POS Brand #{$posBrandId}";
            try {
                $brand = Brand::create([
                    'name'         => $brandName,
                    'slug'         => $this->generateUniqueSlug($data['slug'] ?? $brandName),
                    'description'  => $description,
                    'is_active'    => $isActive,
                    'is_featured'  => false,
                    'sort_order'   => 0,
                    'pos_brand_id' => $posBrandId,
                ]);
            } catch (UniqueConstraintViolationException|QueryException) {
                $brand = $posBrandId ? Brand::where('pos_brand_id', $posBrandId)->first() : null;
            }
        }

        // Download remote logo_url only when local brand has no logo yet
        if ($brand && empty($brand->logo) && !empty($logoUrl) && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            $this->downloadBrandLogoIfEmpty($brand, $logoUrl);
        }

        return $brand;
    }

    /**
     * Unlink a POS brand when deleted in POS, preserving the eCommerce brand and its products.
     */
    public function unlinkPosBrand(int|string $posBrandId): int
    {
        \App\Models\SyncedPosItem::where('pos_brand_id', (int) $posBrandId)
            ->update(['pos_brand_id' => null, 'pos_brand_name' => null]);

        return Brand::where('pos_brand_id', (int) $posBrandId)
            ->update(['pos_brand_id' => null]);
    }

    /**
     * Generate a unique slug for a brand.
     */
    protected function generateUniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'brand-' . Str::lower(Str::random(6));
        $slug = $base;
        $counter = 2;

        while (Brand::where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Best-effort download of a POS brand logo into local storage when brand has no logo yet.
     */
    protected function downloadBrandLogoIfEmpty(Brand $brand, string $logoUrl): void
    {
        try {
            $response = Http::timeout(5)->get($logoUrl);
            if (!$response->successful() || empty($response->body())) {
                return;
            }

            $pathPart = parse_url($logoUrl, PHP_URL_PATH) ?: '';
            $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                $ext = 'png';
            }

            $filename = 'brands/' . $brand->slug . '-' . Str::lower(Str::random(6)) . '.' . $ext;
            Storage::disk('public')->put($filename, $response->body());
            $brand->update(['logo' => $filename]);
        } catch (Throwable $e) {
            Log::debug("Skipping remote POS brand logo download for brand #{$brand->id}: " . $e->getMessage());
        }
    }
}

