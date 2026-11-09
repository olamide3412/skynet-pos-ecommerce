<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\ActivityLogger;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BrandController extends Controller
{
    protected ImageOptimizer $imageOptimizer;

    public function __construct(ImageOptimizer $imageOptimizer)
    {
        $this->imageOptimizer = $imageOptimizer;
    }

    public function index(Request $request)
    {
        $query = Brand::withCount('products');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('source')) {
            if ($request->input('source') === 'pos') {
                $query->whereNotNull('pos_brand_id');
            } elseif ($request->input('source') === 'local') {
                $query->whereNull('pos_brand_id');
            }
        }

        $brands = $query->ordered()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Brands/Index', [
            'brands'  => $brands,
            'filters' => $request->only(['search', 'status', 'source']),
            'stats'   => [
                'total'      => Brand::count(),
                'active'     => Brand::where('is_active', true)->count(),
                'featured'   => Brand::where('is_featured', true)->count(),
                'pos_linked' => Brand::whereNotNull('pos_brand_id')->count(),
            ],
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $brands = Brand::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('slug', 'like', "%{$q}%");
            })
            ->ordered()
            ->limit(50)
            ->get(['id', 'name', 'slug', 'logo', 'is_active', 'pos_brand_id']);

        return response()->json([
            'brands' => $brands,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'is_active'        => 'nullable|boolean',
            'status'           => 'nullable|boolean',
            'is_featured'      => 'nullable|boolean',
            'sort_order'       => 'nullable|integer|min:0',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'logo'             => 'nullable|image|max:2048',
        ]);

        $slugSource = !empty($validated['slug']) ? $validated['slug'] : $validated['name'];
        $baseSlug = Str::slug($slugSource) ?: 'brand-' . Str::lower(Str::random(6));

        if (!empty($validated['slug']) && Brand::where('slug', $baseSlug)->exists()) {
            return back()->withErrors(['slug' => 'The slug has already been taken.'])->withInput();
        }

        $slug = $baseSlug;
        $counter = 2;
        while (Brand::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $this->imageOptimizer->store(
                $request->file('logo'),
                'brands',
                true,
                $slug,
                600,
                600,
                85
            );
        }

        $isActive = array_key_exists('is_active', $validated) && $validated['is_active'] !== null
            ? (bool) $validated['is_active']
            : (array_key_exists('status', $validated) && $validated['status'] !== null ? (bool) $validated['status'] : true);

        $brand = Brand::create([
            'name'             => trim($validated['name']),
            'slug'             => $slug,
            'logo'             => $logoPath,
            'description'      => $validated['description'] ?? null,
            'is_active'        => $isActive,
            'is_featured'      => isset($validated['is_featured']) ? (bool) $validated['is_featured'] : false,
            'sort_order'       => (int) ($validated['sort_order'] ?? 0),
            'meta_title'       => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        ActivityLogger::product("Created brand '{$brand->name}' (#{$brand->id})", [
            'brand_id' => $brand->id,
            'slug'     => $brand->slug,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'brand'   => $brand,
                'message' => 'Brand created successfully.',
            ], 201);
        }

        return back()->with('success', 'Brand created successfully.');
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => "nullable|string|max:255",
            'description'      => 'nullable|string|max:2000',
            'is_active'        => 'nullable|boolean',
            'status'           => 'nullable|boolean',
            'is_featured'      => 'nullable|boolean',
            'sort_order'       => 'nullable|integer|min:0',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'logo'             => 'nullable|image|max:2048',
            'remove_logo'      => 'nullable|boolean',
        ]);

        $slugSource = !empty($validated['slug']) ? $validated['slug'] : $validated['name'];
        $slug = Str::slug($slugSource) ?: $brand->slug;

        if (Brand::where('slug', $slug)->where('id', '!=', $brand->id)->exists()) {
            return back()->withErrors(['slug' => 'The slug has already been taken.'])->withInput();
        }

        $isActive = array_key_exists('is_active', $validated) && $validated['is_active'] !== null
            ? (bool) $validated['is_active']
            : (array_key_exists('status', $validated) && $validated['status'] !== null ? (bool) $validated['status'] : $brand->is_active);

        $updates = [
            'name'             => trim($validated['name']),
            'slug'             => $slug,
            'description'      => $validated['description'] ?? null,
            'is_active'        => $isActive,
            'is_featured'      => isset($validated['is_featured']) ? (bool) $validated['is_featured'] : $brand->is_featured,
            'sort_order'       => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $brand->sort_order,
            'meta_title'       => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $updates['logo'] = $this->imageOptimizer->store(
                $request->file('logo'),
                'brands',
                true,
                $slug,
                600,
                600,
                85
            );
        } elseif ($request->boolean('remove_logo')) {
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $updates['logo'] = null;
        }

        $brand->update($updates);

        ActivityLogger::product("Updated brand '{$brand->name}' (#{$brand->id})", [
            'brand_id' => $brand->id,
            'slug'     => $brand->slug,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'brand'   => $brand->fresh(),
                'message' => 'Brand updated successfully.',
            ]);
        }

        return back()->with('success', 'Brand updated successfully.');
    }

    public function destroy(Request $request, Brand $brand)
    {
        $productsCount = $brand->products()->count();
        $detach = $request->boolean('detach');

        if ($productsCount > 0 && !$detach) {
            $msg = "Cannot delete brand '{$brand->name}' because {$productsCount} product(s) are assigned to it. Choose 'Detach products & delete' to unassign them first.";
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        if ($productsCount > 0 && $detach) {
            $brand->products()->update(['brand_id' => null]);
        }

        $brandName = $brand->name;
        $brandId = $brand->id;

        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->delete();

        ActivityLogger::product("Deleted brand '{$brandName}' (#{$brandId})", [
            'brand_id'          => $brandId,
            'detached_products' => $productsCount,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Brand deleted.']);
        }

        return back()->with('success', 'Brand deleted successfully.');
    }
}

