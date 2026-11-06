<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

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
        $this->staff->assignRole('Sales / Cashier');
    }

    public function test_super_admin_can_access_activity_logs(): void
    {
        ActivityLogger::system('Test system bot background task', ['key' => 'val']);
        ActivityLogger::product('Test product created', ['product_id' => 12]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.activity-logs.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ActivityLogs/Index')
            ->has('logs.data', 2)
            ->has('metrics')
            ->has('users')
        );
    }

    public function test_regular_admin_and_staff_are_forbidden(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.activity-logs.index'));
        $response->assertForbidden();

        $response2 = $this->actingAs($this->staff)->get(route('admin.activity-logs.index'));
        $response2->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.activity-logs.index'));
        $response->assertRedirect('/admin/login');
    }

    public function test_filter_by_actor_system_and_user(): void
    {
        ActivityLog::create([
            'user_id'     => null,
            'actor_type'  => 'system',
            'actor_name'  => 'System Bot',
            'action_type' => 'system',
            'description' => 'Automated nightly sweeper',
            'created_at'  => now(),
        ]);

        ActivityLog::create([
            'user_id'     => $this->superAdmin->id,
            'actor_type'  => 'admin',
            'actor_name'  => $this->superAdmin->name,
            'action_type' => 'setting',
            'description' => 'Updated store configuration',
            'created_at'  => now(),
        ]);

        // Filter system only
        $resSystem = $this->actingAs($this->superAdmin)->get(route('admin.activity-logs.index', ['actor' => 'system']));
        $resSystem->assertOk();
        $resSystem->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.actor_type', 'system')
        );

        // Filter user only
        $resUser = $this->actingAs($this->superAdmin)->get(route('admin.activity-logs.index', ['actor' => $this->superAdmin->id]));
        $resUser->assertOk();
        $resUser->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.user_id', $this->superAdmin->id)
        );
    }

    public function test_filter_by_action_type_and_search(): void
    {
        ActivityLog::create([
            'user_id'     => $this->superAdmin->id,
            'actor_type'  => 'admin',
            'actor_name'  => $this->superAdmin->name,
            'action_type' => 'product',
            'description' => 'Created product Deluxe Widget SKU-999',
            'created_at'  => now(),
        ]);

        ActivityLog::create([
            'user_id'     => $this->superAdmin->id,
            'actor_type'  => 'admin',
            'actor_name'  => $this->superAdmin->name,
            'action_type' => 'auth',
            'description' => 'Staff member logged in',
            'created_at'  => now(),
        ]);

        // Category filter
        $resCat = $this->actingAs($this->superAdmin)->get(route('admin.activity-logs.index', ['action_type' => 'product']));
        $resCat->assertOk();
        $resCat->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action_type', 'product')
        );

        // Search term
        $resSearch = $this->actingAs($this->superAdmin)->get(route('admin.activity-logs.index', ['search' => 'SKU-999']));
        $resSearch->assertOk();
        $resSearch->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'Created product Deluxe Widget SKU-999')
        );
    }

    public function test_super_admin_can_export_csv(): void
    {
        ActivityLog::create([
            'user_id'     => $this->superAdmin->id,
            'actor_type'  => 'admin',
            'actor_name'  => $this->superAdmin->name,
            'action_type' => 'setting',
            'description' => 'Export verification activity entry',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.activity-logs.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
