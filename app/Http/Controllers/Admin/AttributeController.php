<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttributeController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Attributes/Index', [
            'attributes' => Attribute::withCount('categories')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:100|unique:attributes,name',
            'type'    => 'required|in:select,color,range,text',
            'options' => 'nullable|array',
            'options.*' => 'string|max:100',
        ]);

        Attribute::create($validated);

        return back()->with('success', 'Attribute created successfully.');
    }

    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:100|unique:attributes,name,' . $attribute->id,
            'type'    => 'required|in:select,color,range,text',
            'options' => 'nullable|array',
            'options.*' => 'string|max:100',
        ]);

        $attribute->update($validated);

        return back()->with('success', 'Attribute updated successfully.');
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return back()->with('success', 'Attribute deleted.');
    }
}
