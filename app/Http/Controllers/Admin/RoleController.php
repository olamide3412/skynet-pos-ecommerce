<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Display all custom roles, templates, and available permissions.
     */
    public function index()
    {
        $roles = Role::with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles'               => $roles,
            'permissionModules'   => PermissionCatalog::MODULES,
            'defaultRoleTemplates'=> PermissionCatalog::DEFAULT_ROLES,
        ]);
    }

    /**
     * Create a new custom role.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:50|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($data['permissions'])) {
            $perms = Permission::whereIn('name', $data['permissions'])->get();
            $role->syncPermissions($perms);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Role '{$role->name}' created successfully.");
    }

    /**
     * Update an existing role's name and permissions.
     */
    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:50|unique:roles,name,' . $role->id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => $data['name']]);

        $perms = Permission::whereIn('name', $data['permissions'] ?? [])->get();
        $role->syncPermissions($perms);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Role '{$role->name}' updated successfully.");
    }

    /**
     * Delete a custom role.
     */
    public function destroy(Role $role)
    {
        // Protected template roles that cannot be deleted
        if (in_array(strtolower($role->name), ['administrator', 'super administrator'])) {
            return back()->with('error', "Cannot delete core system role '{$role->name}'.");
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Cannot delete role '{$role->name}' because {$role->users()->count()} user(s) currently hold it. Reassign them first.");
        }

        $name = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Role '{$name}' deleted successfully.");
    }
}

