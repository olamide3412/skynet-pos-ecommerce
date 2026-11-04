<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionsReceivedTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'web']);
        $riderRole = Role::firstOrCreate(['name' => 'Rider / Dispatch', 'guard_name' => 'web']);

        $permReconcile = Permission::firstOrCreate(['name' => 'pod.reconcile', 'guard_name' => 'web']);
        $permViewAll = Permission::firstOrCreate(['name' => 'orders.view_all', 'guard_name' => 'web']);
        $permViewAssigned = Permission::firstOrCreate(['name' => 'pod.view_assigned', 'guard_name' => 'web']);

        $adminRole->syncPermissions([$permReconcile, $permViewAll]);
        $cashierRole->syncPermissions([$permReconcile]);
        $riderRole->syncPermissions([$permViewAssigned]);

        $this->admin = User::factory()->create([
            'role'   => 'administrator',
            'status' => 'enable',
        ]);
        $this->admin->assignRole($adminRole);

        $this->cashier = User::factory()->create([
            'role'   => 'staff',
            'status' => 'enable',
        ]);
        $this->cashier->assignRole($cashierRole);

        $this->rider = User::factory()->create([
            'role'   => 'staff',
            'status' => 'enable',
        ]);
        $this->rider->assignRole($riderRole);
    }

    public function test_guest_cannot_access_collections_received()
    {
        $response = $this->get(route('admin.collections-received.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_collections_received_with_kpis_and_filters()
    {
        // Order reconciled by admin
        Order::create([
            'guest_email'            => 'cust1@example.com',
            'total_amount'           => 15000,
            'payment_method'         => 'pod',
            'assigned_staff_id'      => $this->rider->id,
            'remitted_by_staff_id'   => $this->rider->id,
            'reconciled_by_admin_id' => $this->admin->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 15000,
            'pod_collected_at'       => now(),
            'pod_remitted_at'        => now(),
            'pod_reconciled_at'      => now(),
            'pod_reconciliation_note'=> 'Collected via Bank Transfer',
            'status'                 => 'completed',
        ]);

        // Order reconciled by cashier
        Order::create([
            'guest_email'            => 'cust2@example.com',
            'total_amount'           => 8000,
            'payment_method'         => 'pod',
            'assigned_staff_id'      => $this->rider->id,
            'remitted_by_staff_id'   => $this->rider->id,
            'reconciled_by_admin_id' => $this->cashier->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 8000,
            'pod_collected_at'       => now(),
            'pod_remitted_at'        => now(),
            'pod_reconciled_at'      => now(),
            'pod_reconciliation_note'=> 'Direct cash deposit in drawer',
            'status'                 => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.collections-received.index', [
            'preset' => 'today',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/CollectionsReceived/Index')
            ->has('kpis')
            ->where('kpis.total_received', 23000)
            ->where('kpis.orders_count', 2)
            ->has('cashierBreakdown', 2)
            ->has('orders.data', 2)
        );
    }

    public function test_filter_by_specific_cashier()
    {
        Order::create([
            'guest_email'            => 'cust1@example.com',
            'total_amount'           => 10000,
            'payment_method'         => 'pod',
            'assigned_staff_id'      => $this->rider->id,
            'reconciled_by_admin_id' => $this->cashier->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 10000,
            'pod_reconciled_at'      => now(),
            'status'                 => 'completed',
        ]);

        Order::create([
            'guest_email'            => 'cust2@example.com',
            'total_amount'           => 25000,
            'payment_method'         => 'pod',
            'assigned_staff_id'      => $this->rider->id,
            'reconciled_by_admin_id' => $this->admin->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 25000,
            'pod_reconciled_at'      => now(),
            'status'                 => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.collections-received.index', [
            'cashier_id' => $this->cashier->id,
            'preset'     => 'all',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.total_received', 10000)
            ->where('kpis.orders_count', 1)
            ->has('orders.data', 1)
        );
    }

    public function test_custom_date_range_filtering()
    {
        // Yesterday's collection
        Order::create([
            'guest_email'            => 'old@example.com',
            'total_amount'           => 5000,
            'payment_method'         => 'pod',
            'reconciled_by_admin_id' => $this->admin->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 5000,
            'pod_reconciled_at'      => now()->subDays(5),
            'status'                 => 'completed',
        ]);

        // Collection in range
        Order::create([
            'guest_email'            => 'range@example.com',
            'total_amount'           => 12000,
            'payment_method'         => 'pod',
            'reconciled_by_admin_id' => $this->admin->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 12000,
            'pod_reconciled_at'      => now()->subDays(2),
            'status'                 => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.collections-received.index', [
            'preset'     => 'custom',
            'start_date' => now()->subDays(3)->format('Y-m-d'),
            'end_date'   => now()->subDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.total_received', 12000)
            ->where('kpis.orders_count', 1)
        );
    }

    public function test_export_csv_streams_collections_received_report()
    {
        Order::create([
            'guest_email'            => 'csv@example.com',
            'total_amount'           => 14500,
            'payment_method'         => 'pod',
            'assigned_staff_id'      => $this->rider->id,
            'reconciled_by_admin_id' => $this->admin->id,
            'pod_status'             => 'reconciled',
            'pod_amount_collected'   => 14500,
            'pod_reconciled_at'      => now(),
            'pod_reconciliation_note'=> 'Collected via POS terminal',
            'status'                 => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.collections-received.export', [
            'preset' => 'today',
        ]));

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('content-type'));
        $this->assertStringContainsString('collections_received_', $response->headers->get('content-disposition'));
    }
}

