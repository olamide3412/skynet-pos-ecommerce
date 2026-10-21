<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ThemeController extends Controller
{
    /**
     * Display the Dual-Color Palette & Dark Mode Theme Configuration.
     */
    public function index()
    {
        return Inertia::render('Admin/Themes/Index', [
            'theme_settings' => [
                'theme_preset'               => StoreSetting::get('theme_preset', 'skynet'),
                'theme_primary_color'        => StoreSetting::get('theme_primary_color', '#7B00FF'),
                'theme_secondary_color'      => StoreSetting::get('theme_secondary_color', '#FBA43D'),
                'theme_dark_mode_preset'     => StoreSetting::get('theme_dark_mode_preset', 'white'),
                'theme_dark_mode_text_color' => StoreSetting::get('theme_dark_mode_text_color', '#FFFFFF'),
                'theme_dark_mode_tint_color' => StoreSetting::get('theme_dark_mode_tint_color', '#C084FC'),
            ],
            'company_name' => StoreSetting::get('company_name', 'Skynet Digital Store'),
            'company_tagline' => StoreSetting::get('company_tagline', 'Modern E-Commerce Storefront & Terminal'),
        ]);
    }

    /**
     * Update the color palette and dark mode legibility theme.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme_preset'               => 'required|string|max:50',
            'theme_primary_color'        => 'required|string|max:25',
            'theme_secondary_color'      => 'required|string|max:25',
            'theme_dark_mode_preset'     => 'required|string|max:50',
            'theme_dark_mode_text_color' => 'required|string|max:25',
            'theme_dark_mode_tint_color' => 'nullable|string|max:25',
        ]);

        foreach ($validated as $key => $value) {
            StoreSetting::set($key, $value ?? '');
        }

        return back()->with('success', 'Dual-Color palette and Dark Mode theme updated successfully!');
    }
}
