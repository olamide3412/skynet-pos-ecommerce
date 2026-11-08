<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalizedDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create([
            'role'   => RoleEnums::Administrator->value,
            'status' => 'enable',
        ]);

        $this->staff = User::factory()->create([
            'role'   => RoleEnums::Staff->value,
            'status' => 'enable',
        ]);
    }

    public function test_admin_receives_full_financial_metrics_on_dashboard()
    {
        \App\Models\PosConnection::create([
            'pos_base_url'         => 'https://pos.test/api',
            'pos_integration_id'   => 'POS-123',
            'pos_branch_id'        => 'BR-01',
            'pos_branch_name'      => 'Main Store',
            'client_id'            => 'client_test',
            'encrypted_api_secret' => \Illuminate\Support\Facades\Crypt::encryptString('secret'),
            'is_connected'         => true,
            'connected_at'         => Carbon::now()->subMinutes(10),
        ]);

        // Completed Order with revenue
        Order::create([
            'guest_email'     => 'test@example.com',
            'total_amount'    => 75000,
            'status'          => 'completed',
            'payment_method'  => 'bank_transfer',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('isAdmin', true)
            ->has('financialStats')
            ->where('financialStats.total_revenue', 75000)
            ->has('salesTrend')
            ->has('channelSplit')
            ->has('operationalStats')
            ->where('operationalStats.total_orders', 1)
        );
    }

    public function test_staff_dashboard_hides_revenue_and_shows_operational_metrics()
    {
        Order::create([
            'guest_email'     => 'staff_check@example.com',
            'total_amount'    => 50000,
            'status'          => 'pending',
            'payment_method'  => 'pod',
        ]);

        $cat = \App\Models\Category::create([
            'name' => 'Demo',
            'slug' => 'demo',
        ]);

        // Product running low
        Product::create([
            'category_id' => $cat->id,
            'name'        => 'Low Stock Item',
            'slug'        => 'low-stock-item',
            'url_key'     => 'low-stock-item',
            'sku'         => 'LOW-01',
            'price'       => 5000,
            'stock'       => 2,
            'status'      => true,
        ]);

        $response = $this->actingAs($this->staff)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('isAdmin', false)
            ->where('financialStats', null) // Revenue completely withheld from staff
            ->where('salesTrend', [])        // Financial trend withheld from staff
            ->has('operationalStats')
            ->where('operationalStats.pending_orders', 1)
            ->where('operationalStats.low_stock_count', 1)
            ->has('lowStockProducts', 1)
        );
    }

    public function test_staff_is_forbidden_from_revenue_analytics_and_user_management()
    {
        // 1. Staff cannot access revenue report
        $revResp = $this->actingAs($this->staff)->get(route('admin.revenue.index'));
        $revResp->assertForbidden();

        // 2. Staff and normal Admin cannot access system user management (Super Admin only)
        $usersResp = $this->actingAs($this->staff)->get(route('admin.users.index'));
        $usersResp->assertForbidden();

        $adminUsersResp = $this->actingAs($this->admin)->get(route('admin.users.index'));
        $adminUsersResp->assertForbidden();

        // 3. Admin CAN access revenue
        $adminRevResp = $this->actingAs($this->admin)->get(route('admin.revenue.index'));
        $adminRevResp->assertOk();

        // 4. Super Admin CAN access system user management
        $superAdmin = User::factory()->create([
            'role' => 'super administrator',
        ]);
        $superAdminUsersResp = $this->actingAs($superAdmin)->get(route('admin.users.index'));
        $superAdminUsersResp->assertOk();
    }
}
