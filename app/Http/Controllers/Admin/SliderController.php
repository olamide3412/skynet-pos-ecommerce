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
            'subtitle'    => 'nullable|string|max:1000',
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
            'subtitle'    => 'nullable|string|max:1000',
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

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'home_slider_enabled'  => 'boolean',
            'home_marquee_enabled' => 'boolean',
            'home_marquee_text'    => 'nullable|string|max:500',
            'hero_enabled'         => 'boolean',
            'hero_badge'           => 'nullable|string|max:100',
            'hero_title'           => 'nullable|string|max:255',
            'hero_title_highlight' => 'nullable|string|max:100',
            'hero_subtitle'        => 'nullable|string|max:500',
            'hero_cta_primary'     => 'nullable|string|max:50',
            'hero_cta_secondary'   => 'nullable|string|max:50',
            'feature_1_title'      => 'nullable|string|max:100',
            'feature_1_desc'       => 'nullable|string|max:255',
            'feature_2_title'      => 'nullable|string|max:100',
            'feature_2_desc'       => 'nullable|string|max:255',
            'feature_3_title'      => 'nullable|string|max:100',
            'feature_3_desc'       => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('hero_image')) {
            $request->validate(['hero_image' => 'image|max:4096']);
            $heroPath = $request->file('hero_image')->store('settings', 'public');
            \App\Models\StoreSetting::set('hero_image', $heroPath);
        }

        foreach ($data as $key => $value) {
            \App\Models\StoreSetting::set($key, is_bool($value) ? ($value ? '1' : '0') : ($value ?? ''));
        }

        return back()->with('success', 'Homepage content settings saved successfully.');
    }
}
