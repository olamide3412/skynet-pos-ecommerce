<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'role'   => 'super administrator',
            'status' => 'enable',
        ]);

        $this->admin = User::factory()->create([
            'role'   => 'administrator',
            'status' => 'enable',
        ]);
        $this->admin->assignRole('Administrator');

        $this->staff = User::factory()->create([
            'role'   => 'staff',
            'status' => 'enable',
        ]);
        $this->staff->assignRole('Store Manager');
    }

    public function test_super_admin_bypasses_all_permission_checks()
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.roles.index'));
        $response->assertOk();
    }

    public function test_non_super_admin_is_forbidden_from_managing_roles()
    {
        // Admin gets 403 on roles management
        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'));
        $response->assertForbidden();

        // Staff gets 403 on roles management
        $staffResponse = $this->actingAs($this->staff)->get(route('admin.roles.index'));
        $staffResponse->assertForbidden();
    }

    public function test_super_admin_can_create_custom_role()
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.roles.store'), [
            'name'        => 'Logistics Supervisor',
            'permissions' => ['pod.view_assigned', 'pod.reconcile', 'orders.view_all'],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('roles', ['name' => 'Logistics Supervisor']);

        $role = Role::findByName('Logistics Supervisor', 'web');
        $this->assertTrue($role->hasPermissionTo('pod.reconcile'));
    }

    public function test_staff_can_hold_multiple_roles_simultaneously()
    {
        $riderRole = Role::findByName('Rider / Dispatch', 'web');
        $warehouseRole = Role::findByName('Warehouse / Fulfillment', 'web');

        $user = User::factory()->create(['role' => 'staff', 'status' => 'enable']);
        $user->assignRole([$riderRole, $warehouseRole]);

        // Has warehouse capability
        $this->assertTrue($user->can('fulfillment.pack'));

        // Has rider capability
        $this->assertTrue($user->can('pod.collect_cash'));

        // Does NOT have financial view
        $this->assertFalse($user->can('dashboard.view_financials'));
    }

    public function test_only_super_admin_can_assign_roles_to_user()
    {
        $targetUser = User::factory()->create(['role' => 'staff', 'status' => 'enable']);

        // Super Admin updates user and assigns Rider role
        $response = $this->actingAs($this->superAdmin)->put(route('admin.users.update', $targetUser->id), [
            'name'   => $targetUser->name,
            'email'  => $targetUser->email,
            'role'   => 'staff',
            'status' => 'enable',
            'roles'  => ['Rider / Dispatch'],
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue($targetUser->fresh()->hasRole('Rider / Dispatch'));

        // Regular admin tries to assign Administrator role to targetUser
        $adminResponse = $this->actingAs($this->admin)->put(route('admin.users.update', $targetUser->id), [
            'name'   => $targetUser->name,
            'email'  => $targetUser->email,
            'role'   => 'staff',
            'status' => 'enable',
            'roles'  => ['Administrator'],
        ]);

        // Ignored for non-super admin
        $this->assertFalse($targetUser->fresh()->hasRole('Administrator'));
    }

    public function test_cannot_remove_the_last_super_administrator()
    {
        // Try demoting the only super admin
        $response = $this->actingAs($this->superAdmin)->put(route('admin.users.update', $this->superAdmin->id), [
            'name'   => $this->superAdmin->name,
            'email'  => $this->superAdmin->email,
            'role'   => 'staff', // Demotion
            'status' => 'enable',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('super administrator', $this->superAdmin->fresh()->role);
    }
}

