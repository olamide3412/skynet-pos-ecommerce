<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ShippingZoneController extends Controller
{
    public function index(Request $request)
    {
        $query = ShippingZone::query();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('state', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $zones = $query->orderByDesc('is_default')->orderBy('state')->orderBy('city')->paginate(50)->withQueryString();

        return Inertia::render('Admin/ShippingZones/Index', [
            'zones'   => $zones,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? '',
            ],
            'stats'   => [
                'total'   => ShippingZone::count(),
                'active'  => ShippingZone::where('is_active', true)->count(),
                'default' => ShippingZone::where('is_default', true)->first(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'state'          => ['required', 'string', 'max:100'],
            'city'           => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('shipping_zones')->where(function ($query) use ($request) {
                    return $query->where('state', $request->state)
                                 ->where('city', $request->city);
                }),
            ],
            'fee'            => ['required', 'numeric', 'min:0'],
            'estimated_days' => ['required', 'string', 'max:100'],
            'is_active'      => ['boolean'],
            'is_default'     => ['boolean'],
        ], [
            'city.unique' => 'A shipping rate for this State and City/Town already exists.',
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['is_default'] = $request->boolean('is_default', false);

        if ($validated['is_default']) {
            ShippingZone::where('is_default', true)->update(['is_default' => false]);
        }

        $zone = ShippingZone::create($validated);
        $name = $zone->city ? "{$zone->state} - {$zone->city}" : $zone->state;

        return back()->with('success', "Shipping zone for '{$name}' added successfully.");
    }

    public function update(Request $request, ShippingZone $shippingZone)
    {
        $validated = $request->validate([
            'state'          => ['required', 'string', 'max:100'],
            'city'           => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('shipping_zones')->where(function ($query) use ($request) {
                    return $query->where('state', $request->state)
                                 ->where('city', $request->city);
                })->ignore($shippingZone->id),
            ],
            'fee'            => ['required', 'numeric', 'min:0'],
            'estimated_days' => ['required', 'string', 'max:100'],
            'is_active'      => ['boolean'],
            'is_default'     => ['boolean'],
        ], [
            'city.unique' => 'A shipping rate for this State and City/Town already exists.',
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['is_default'] = $request->boolean('is_default', false);

        if ($validated['is_default']) {
            ShippingZone::where('id', '!=', $shippingZone->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $shippingZone->update($validated);
        $name = $shippingZone->city ? "{$shippingZone->state} - {$shippingZone->city}" : $shippingZone->state;

        return back()->with('success', "Shipping zone for '{$name}' updated successfully.");
    }

    public function destroy(ShippingZone $shippingZone)
    {
        $name = $shippingZone->city ? "{$shippingZone->state} - {$shippingZone->city}" : $shippingZone->state;
        $shippingZone->delete();

        return back()->with('success', "Shipping zone '{$name}' deleted successfully.");
    }

    public function toggleStatus(ShippingZone $shippingZone)
    {
        $shippingZone->update(['is_active' => !$shippingZone->is_active]);

        $status = $shippingZone->is_active ? 'activated' : 'deactivated';
        $name = $shippingZone->city ? "{$shippingZone->state} - {$shippingZone->city}" : $shippingZone->state;
        return back()->with('success', "Shipping zone '{$name}' {$status}.");
    }

    public function setDefault(ShippingZone $shippingZone)
    {
        ShippingZone::where('is_default', true)->update(['is_default' => false]);
        $shippingZone->update([
            'is_default' => true,
            'is_active'  => true,
        ]);

        $name = $shippingZone->city ? "{$shippingZone->state} - {$shippingZone->city}" : $shippingZone->state;
        return back()->with('success', "'{$name}' is now set as the default shipping zone.");
    }
}

