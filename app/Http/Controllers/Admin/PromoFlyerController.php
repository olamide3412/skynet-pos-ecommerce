<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PromoFlyerController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/PromoFlyers/Index', [
            'settings' => StoreSetting::allAsArray(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // Promotional Mid-Page Banner
            'promo_banner_enabled'        => 'boolean',
            'promo_banner_auto_optimize'  => 'boolean',
            'promo_banner_title'          => 'nullable|string|max:255',
            'promo_banner_subtitle'       => 'nullable|string|max:500',
            'promo_banner_link'           => 'nullable|string|max:500',
            'promo_banner_button_text'    => 'nullable|string|max:50',

            // Timed Promo / Coupon Flyer Popup Modal
            'promo_popup_enabled'         => 'boolean',
            'promo_popup_auto_optimize'   => 'boolean',
            'promo_popup_delay'           => 'nullable|integer|min:0|max:60',
            'promo_popup_frequency'       => 'nullable|string|in:refresh,session,24_hours,7_days,30_days,once',
            'promo_popup_title'           => 'nullable|string|max:255',
            'promo_popup_subtitle'        => 'nullable|string|max:500',
            'promo_popup_coupon_code'     => 'nullable|string|max:50',
            'promo_popup_cta_text'        => 'nullable|string|max:50',
            'promo_popup_cta_link'        => 'nullable|string|max:500',
        ]);

        // Mid-page banner image upload
        if ($request->hasFile('promo_banner_image')) {
            $request->validate(['promo_banner_image' => 'image|max:12288']);
            $file = $request->file('promo_banner_image');
            $autoOptimize = $request->boolean('promo_banner_auto_optimize', true);

            $bannerPath = $this->processUploadedImage($file, $autoOptimize, 1400);
            StoreSetting::set('promo_banner_image', $bannerPath);
        }

        // Timed popup flyer image upload
        if ($request->hasFile('promo_popup_image')) {
            $request->validate(['promo_popup_image' => 'image|max:12288']);
            $file = $request->file('promo_popup_image');
            $autoOptimize = $request->boolean('promo_popup_auto_optimize', true);

            $popupPath = $this->processUploadedImage($file, $autoOptimize, 1200);
            StoreSetting::set('promo_popup_image', $popupPath);
        }

        foreach ($data as $key => $value) {
            StoreSetting::set($key, is_bool($value) ? ($value ? '1' : '0') : ($value ?? ''));
        }

        return back()->with('success', 'Promo flyers and popup settings saved successfully.');
    }

    /**
     * Process an uploaded image with optional WebP compression & scaling.
     */
    protected function processUploadedImage($file, bool $autoOptimize, int $maxDimension = 1200): string
    {
        if (!$autoOptimize) {
            return $file->store('settings', 'public');
        }

        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            // Scale down to maximum dimension maintaining aspect ratio
            $image->scaleDown(width: $maxDimension, height: $maxDimension);

            // Encode to high-efficiency WebP format (85% quality)
            $encoded = $image->toWebp(quality: 85);
            $filename = 'settings/' . Str::random(40) . '.webp';

            Storage::disk('public')->put($filename, (string) $encoded);

            return $filename;
        } catch (\Throwable $e) {
            // Graceful fallback to original file if processing encounters an issue
            return $file->store('settings', 'public');
        }
    }
}

