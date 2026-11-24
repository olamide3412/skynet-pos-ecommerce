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
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->can('users.view'))) {
            abort(403, 'Access denied. You do not have permission to view system users.');
        }

        $isSuperAdmin = $currentUser->isSuperAdmin();

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

        // Prevent non-Super Admin from creating a Super Administrator
        if (!$currentUser->isSuperAdmin() && $validated['role'] === RoleEnums::SuperAdministrator->value) {
            abort(403, 'Only a Super Administrator can create a Super Administrator account.');
        }

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

        // Prevent non-Super Admin from editing a Super Admin or promoting to Super Admin
        if (!$currentUser->isSuperAdmin() && ($user->isSuperAdmin() || $request->role === RoleEnums::SuperAdministrator->value)) {
            abort(403, 'Only a Super Administrator can modify Super Administrator accounts.');
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
        $currentUser = auth('web')->user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->can('users.disable'))) {
            abort(403, 'You do not have permission to enable or disable user accounts.');
        }

        if (!$currentUser->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403, 'Only a Super Administrator can modify a Super Administrator account.');
        }

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
        $currentUser = auth('web')->user();
        if (!$currentUser || (!$currentUser->isSuperAdmin() && !$currentUser->can('users.reset_password'))) {
            abort(403, 'You do not have permission to reset user passwords.');
        }

        if (!$currentUser->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403, 'Only a Super Administrator can reset a Super Administrator password.');
        }

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
        $currentUser = auth('web')->user();
        if (!$currentUser || !$currentUser->isSuperAdmin()) {
            abort(403, 'Only a Super Administrator can delete system users.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
