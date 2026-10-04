<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::withCount('products')
                ->with(['attributes:id,name', 'parent:id,name', 'children:id,name,parent_id'])
                ->orderBy('menu_position')
                ->orderBy('name')
                ->paginate(50),
            'attributes'        => Attribute::orderBy('name')->get(['id', 'name', 'type']),
            'parent_categories' => Category::whereNull('parent_id')->orderBy('menu_position', 'asc')->get(['id', 'name', 'menu_position']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => 'required|string|max:255|unique:categories,slug',
            'description'      => 'nullable|string',
            'parent_id'        => 'nullable|exists:categories,id',
            'visible_in_menu'  => 'boolean',
            'menu_position'    => 'nullable|integer|min:0',
            'attribute_ids'    => 'nullable|array',
            'attribute_ids.*'  => 'exists:attributes,id',
            'image'            => 'nullable|image|max:2048',
        ]);

        $attributeIds = $validated['attribute_ids'] ?? [];
        unset($validated['attribute_ids']);

        // ensure slug is sanitized if any spaces slipped in
        $validated['slug'] = Str::slug($validated['slug']);
        $validated['visible_in_menu'] = $validated['visible_in_menu'] ?? true;
        $validated['menu_position']   = $validated['menu_position'] ?? 0;
        $validated['parent_id']       = $validated['parent_id'] ?? null;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($validated);
        $category->attributes()->sync($attributeIds);

        return back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => "required|string|max:255|unique:categories,slug,{$category->id}",
            'description'      => 'nullable|string',
            'parent_id'        => 'nullable|exists:categories,id',
            'visible_in_menu'  => 'boolean',
            'menu_position'    => 'nullable|integer|min:0',
            'attribute_ids'    => 'nullable|array',
            'attribute_ids.*'  => 'exists:attributes,id',
            'image'            => 'nullable|image|max:2048',
            'remove_image'     => 'nullable|boolean',
        ]);

        $attributeIds = $validated['attribute_ids'] ?? [];
        unset($validated['attribute_ids']);

        $validated['slug'] = Str::slug($validated['slug']);
        $validated['parent_id'] = $validated['parent_id'] ?? null;
        $validated['visible_in_menu'] = $validated['visible_in_menu'] ?? true;
        $validated['menu_position']   = $validated['menu_position'] ?? 0;

        if ($request->hasFile('image')) {
            if ($category->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($category->image);
            }
            $validated['image'] = $request->file('image')->store('categories', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($category->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($category->image);
            }
            $validated['image'] = null;
        } else {
            // Neither uploading a new image nor removing the old one, so don't update the image column
            unset($validated['image']);
        }

        unset($validated['remove_image']);

        $category->update($validated);
        $category->attributes()->sync($attributeIds);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        if ($category->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($category->image);
        }
        $category->attributes()->detach();
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }
}
