<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Home', [
            'sliders' => \App\Models\StoreSetting::get('home_slider_enabled') !== '0' 
                ? \App\Models\Slider::where('is_active', true)->orderBy('order')->get() 
                : [],
            'featured_products' => Product::with('category')->where('status', true)->inRandomOrder()->take(4)->get(),
            'new_arrivals' => Product::with('category')->where('status', true)->latest()->take(4)->get(),
            'home_categories' => Category::whereNull('parent_id')
                ->orderBy('menu_position')
                ->take(8)
                ->get(['id', 'name', 'slug', 'image']),
        ]);
    }
}
