<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create all defined permissions
        foreach (PermissionCatalog::MODULES as $moduleName => $permissions) {
            foreach ($permissions as $permName => $description) {
                Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => 'web']
                );
            }
        }

        // 2. Create / Sync default role templates
        foreach (PermissionCatalog::DEFAULT_ROLES as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );

            // Sync permissions for this role template
            $validPerms = Permission::whereIn('name', $roleData['permissions'])->get();
            $role->syncPermissions($validPerms);
        }

        // 3. Migrate existing backoffice users to appropriate roles
        $users = User::with('roles')->get();
        foreach ($users as $user) {
            if ($user->role === 'super administrator' || $user->role === 'administrator') {
                $adminRole = Role::findByName('Administrator', 'web');
                if (!$user->roles->contains('name', 'Administrator')) {
                    $user->assignRole($adminRole);
                }
            } elseif ($user->role === 'staff') {
                $storeManagerRole = Role::findByName('Store Manager', 'web');
                if ($user->roles->isEmpty()) {
                    $user->assignRole($storeManagerRole);
                }
            }
        }
    }
}
