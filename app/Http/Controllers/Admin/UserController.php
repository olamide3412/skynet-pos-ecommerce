<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(10);
        
        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => array_map(fn($role) => $role->value, RoleEnums::cases()),
            'statuses' => array_map(fn($status) => $status->value, StatusEnums::cases()),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'role' => ['required', Rule::in(array_map(fn($role) => $role->value, RoleEnums::cases()))],
            'status' => ['required', Rule::in(array_map(fn($status) => $status->value, StatusEnums::cases()))],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        // Protect the current user from accidental self-demotion or disabling if they are the last super admin
        if ($user->id === auth()->id() && $request->status === StatusEnums::Disable->value) {
            return redirect()->back()->with('error', 'You cannot disable your own account.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'role' => ['required', Rule::in(array_map(fn($role) => $role->value, RoleEnums::cases()))],
            'status' => ['required', Rule::in(array_map(fn($status) => $status->value, StatusEnums::cases()))],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
