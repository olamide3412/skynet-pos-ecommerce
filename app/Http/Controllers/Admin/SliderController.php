<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SliderController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Sliders/Index', [
            'sliders' => Slider::orderBy('order')->orderBy('id', 'desc')->get(),
            'settings' => \App\Models\StoreSetting::allAsArray(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image'       => 'required|image|max:4096',
            'title'       => 'nullable|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:255',
            'order'       => 'nullable|integer',
            'is_active'   => 'boolean',
        ]);

        $slider = new Slider();
        if ($request->hasFile('image')) {
            $slider->image_path = $request->file('image')->store('sliders', 'public');
        }
        $slider->title = $validated['title'] ?? null;
        $slider->subtitle = $validated['subtitle'] ?? null;
        $slider->button_text = $validated['button_text'] ?? null;
        $slider->button_link = $validated['button_link'] ?? null;
        $slider->order = $validated['order'] ?? 0;
        $slider->is_active = $request->has('is_active') ? $request->boolean('is_active') : true;
        
        $slider->save();

        return back()->with('success', 'Slider created successfully.');
    }

    public function update(Request $request, Slider $slider)
    {
        $validated = $request->validate([
            'image'       => 'nullable|image|max:4096',
            'title'       => 'nullable|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:255',
            'order'       => 'nullable|integer',
            'is_active'   => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($slider->image_path && Storage::disk('public')->exists($slider->image_path)) {
                Storage::disk('public')->delete($slider->image_path);
            }
            $slider->image_path = $request->file('image')->store('sliders', 'public');
        }

        $slider->title = $validated['title'] ?? null;
        $slider->subtitle = $validated['subtitle'] ?? null;
        $slider->button_text = $validated['button_text'] ?? null;
        $slider->button_link = $validated['button_link'] ?? null;
        $slider->order = $validated['order'] ?? 0;
        $slider->is_active = $request->boolean('is_active');
        
        $slider->save();

        return back()->with('success', 'Slider updated successfully.');
    }

    public function destroy(Slider $slider)
    {
        if ($slider->image_path && Storage::disk('public')->exists($slider->image_path)) {
            Storage::disk('public')->delete($slider->image_path);
        }
        $slider->delete();

        return back()->with('success', 'Slider deleted successfully.');
    }
}
