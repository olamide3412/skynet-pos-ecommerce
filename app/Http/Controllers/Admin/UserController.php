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
    public function index(Request $request)
    {
        $currentUser = auth('web')->user();
        $isSuperAdmin = $currentUser->isSuperAdmin();

        if (!$isSuperAdmin && !in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true) && !$currentUser->can('users.view')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }

        $query = User::with('roles:id,name')->latest();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPageInput = $request->input('per_page', 25);
        if ($perPageInput === 'all' || $perPageInput === 'ALL') {
            $totalCount = (clone $query)->count();
            $perPage = max(1, $totalCount);
        } else {
            $perPage = in_array((int) $perPageInput, [10, 25, 50, 100], true) ? (int) $perPageInput : 25;
        }

        $users = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total'    => User::count(),
            'active'   => User::where('status', StatusEnums::Enable->value)->count(),
            'disabled' => User::where('status', StatusEnums::Disable->value)->count(),
            'admins'   => User::whereIn('role', [
                RoleEnums::SuperAdministrator->value,
                RoleEnums::Administrator->value,
            ])->count(),
            'staff'    => User::where('role', RoleEnums::Staff->value)->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users'        => $users,
            'roles'        => array_map(fn($role) => $role->value, RoleEnums::cases()),
            'allRoles'     => \Spatie\Permission\Models\Role::select('id', 'name')->orderBy('name')->get(),
            'statuses'     => array_map(fn($status) => $status->value, StatusEnums::cases()),
            'stats'        => $stats,
            'isSuperAdmin' => $isSuperAdmin,
            'filters'      => [
                'search'   => $request->input('search', ''),
                'role'     => $request->input('role', ''),
                'status'   => $request->input('status', ''),
                'per_page' => (string) $perPageInput,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = auth('web')->user();
        if (!$currentUser->isSuperAdmin() && !$currentUser->can('users.create')) {
            abort(403, 'You do not have permission to create users.');
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'password'    => 'required|string|min:8',
            'phone'       => 'nullable|string|max:50',
            'address'     => 'nullable|string|max:500',
            'role'        => ['required', Rule::in(array_map(fn($role) => $role->value, RoleEnums::cases()))],
            'status'      => ['required', Rule::in(array_map(fn($status) => $status->value, StatusEnums::cases()))],
            'roles'       => 'nullable|array',
            'roles.*'     => 'string|exists:roles,name',
        ]);

        $rolesToSync = $validated['roles'] ?? [];
        unset($validated['roles']);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // ONLY Super Admin can assign roles
        if ($currentUser->isSuperAdmin() && !empty($rolesToSync)) {
            $user->syncRoles($rolesToSync);
        }

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth('web')->user();
        if (!$currentUser->isSuperAdmin() && !$currentUser->can('users.edit')) {
            abort(403, 'You do not have permission to edit users.');
        }

        if ($user->id === auth()->id() && $request->status === StatusEnums::Disable->value) {
            return redirect()->back()->with('error', 'You cannot disable your own account.');
        }

        // Prevent demoting the last super admin
        if ($user->isSuperAdmin() && $request->role !== RoleEnums::SuperAdministrator->value) {
            $superAdminCount = User::where('role', RoleEnums::SuperAdministrator->value)->count();
            if ($superAdminCount <= 1) {
                return redirect()->back()->with('error', 'Cannot remove the last Super Administrator from the system.');
            }
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'phone'    => 'nullable|string|max:50',
            'address'  => 'nullable|string|max:500',
            'role'     => ['required', Rule::in(array_map(fn($role) => $role->value, RoleEnums::cases()))],
            'status'   => ['required', Rule::in(array_map(fn($status) => $status->value, StatusEnums::cases()))],
            'roles'    => 'nullable|array',
            'roles.*'  => 'string|exists:roles,name',
        ]);

        $rolesToSync = $validated['roles'] ?? null;
        unset($validated['roles']);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // ONLY Super Admin can assign/update roles
        if ($currentUser->isSuperAdmin() && is_array($rolesToSync)) {
            $user->syncRoles($rolesToSync);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot disable your own account.');
        }

        $newStatus = $user->status === StatusEnums::Enable->value
            ? StatusEnums::Disable->value
            : StatusEnums::Enable->value;

        $user->update(['status' => $newStatus]);

        $label = $newStatus === StatusEnums::Enable->value ? 'enabled' : 'disabled';

        return redirect()->back()->with('success', "User account {$label} successfully.");
    }

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', "Password for {$user->name} has been reset successfully.");
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
