<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerProfileController extends Controller
{
    /**
     * Show customer account profile page.
     */
    public function show()
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return Inertia::render('Frontend/Account/Profile', [
            'customer' => [
                'id'         => $customer->id,
                'name'       => $customer->name,
                'email'      => $customer->email,
                'phone'      => $customer->phone ?? '',
                'created_at' => $customer->created_at->format('M d, Y'),
            ],
        ]);
    }

    /**
     * Update customer profile info.
     */
    public function updateProfile(Request $request)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update customer password.
     */
    public function updatePassword(Request $request)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'password'         => ['required', 'confirmed', 'min:8'],
        ]);

        $customer->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * List customer saved addresses.
     */
    public function addresses()
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $addresses = $customer->addresses()->latest()->get();

        return Inertia::render('Frontend/Account/Addresses', [
            'addresses' => $addresses,
        ]);
    }

    /**
     * Store new shipping address.
     */
    public function storeAddress(Request $request)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'phone'      => ['required', 'string', 'max:20'],
            'address'    => ['required', 'string', 'max:500'],
            'city'       => ['required', 'string', 'max:100'],
            'state'      => ['required', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        $isDefault = $request->boolean('is_default') || $customer->addresses()->count() === 0;

        if ($isDefault) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $customer->addresses()->create([
            'name'       => $validated['name'],
            'email'      => $customer->email,
            'phone'      => $validated['phone'],
            'address'    => $validated['address'],
            'city'       => $validated['city'],
            'state'      => $validated['state'],
            'country'    => $validated['country'] ?? 'Nigeria',
            'is_default' => $isDefault,
        ]);

        return back()->with('success', 'Address added successfully.');
    }

    /**
     * Update existing address.
     */
    public function updateAddress(Request $request, Address $address)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'phone'      => ['required', 'string', 'max:20'],
            'address'    => ['required', 'string', 'max:500'],
            'city'       => ['required', 'string', 'max:100'],
            'state'      => ['required', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update(array_merge($validated, [
            'country'    => $validated['country'] ?? 'Nigeria',
            'is_default' => $request->boolean('is_default'),
        ]));

        return back()->with('success', 'Address updated successfully.');
    }

    /**
     * Delete an address.
     */
    public function deleteAddress(Address $address)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $first = $customer->addresses()->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return back()->with('success', 'Address deleted successfully.');
    }

    /**
     * Mark an address as the default shipping address.
     */
    public function setDefaultAddress(Address $address)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        $customer->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address set successfully.');
    }
}

