<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportAndPodManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create([
            'role'   => 'administrator',
            'status' => 'enable',
        ]);

        $this->staff = User::factory()->create([
            'role'   => 'staff',
            'status' => 'enable',
        ]);
    }

    public function test_admin_can_view_sales_report_with_all_metrics()
    {
        Order::create([
            'guest_email'    => 'customer@example.com',
            'total_amount'   => 15000,
            'payment_method' => 'pod',
            'pod_status'     => 'awaiting_collection',
            'status'         => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.sales'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Sales')
            ->where('isAdmin', true)
            ->has('orders.data', 1)
            ->where('summary.gross_sales', 15000)
            ->where('summary.pod_floating_cash', 15000)
        );
    }

    public function test_staff_is_restricted_to_their_own_attributed_orders()
    {
        $staff2 = User::factory()->create(['role' => 'staff', 'status' => 'enable']);

        // Order 1 assigned to this->staff
        Order::create([
            'guest_email'       => 'pod1@example.com',
            'total_amount'      => 8000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->staff->id,
            'pod_status'        => 'awaiting_collection',
            'status'            => 'processing',
        ]);

        // Order 2 assigned to staff2
        Order::create([
            'guest_email'       => 'paystack@example.com',
            'total_amount'      => 12000,
            'payment_method'    => 'paystack',
            'assigned_staff_id' => $staff2->id,
            'status'            => 'completed',
        ]);

        $response = $this->actingAs($this->staff)->get(route('admin.reports.sales'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Sales')
            ->where('isAdmin', false)
            ->has('orders.data', 1) // Staff only sees their 1 order!
        );
    }

    public function test_admin_can_assign_staff_to_order()
    {
        $order = Order::create([
            'guest_email'    => 'assign@example.com',
            'total_amount'   => 5000,
            'payment_method' => 'pod',
            'pod_status'     => 'none',
            'status'         => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.orders.assign-staff', $order->id), [
            'assigned_staff_id' => $this->staff->id,
        ]);

        $response->assertSessionHas('success');
        $order->refresh();
        $this->assertEquals($this->staff->id, $order->assigned_staff_id);
        $this->assertEquals('awaiting_collection', $order->pod_status);
    }

    public function test_staff_can_mark_cash_collected_and_remitted()
    {
        $order = Order::create([
            'guest_email'       => 'cash@example.com',
            'total_amount'      => 20000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->staff->id,
            'pod_status'        => 'awaiting_collection',
            'status'            => 'processing',
        ]);

        // 1. Mark cash collected
        $response = $this->actingAs($this->staff)->post(route('admin.orders.mark-cash-collected', $order->id));
        $response->assertSessionHas('success');
        $order->refresh();
        $this->assertEquals('cash_collected', $order->pod_status);
        $this->assertEquals($this->staff->id, $order->remitted_by_staff_id);

        // 2. Rider/staff cannot self-remit (Forbidden)
        $forbidden = $this->actingAs($this->staff)->post(route('admin.orders.mark-cash-remitted', $order->id));
        $forbidden->assertForbidden();

        // 3. Store admin/cashier receives remittance and reconciles
        $response2 = $this->actingAs($this->admin)->post(route('admin.orders.mark-cash-remitted', $order->id));
        $response2->assertSessionHas('success');
        $order->refresh();
        $this->assertEquals('reconciled', $order->pod_status);
    }

    public function test_cannot_collect_lesser_amount_for_pod_order()
    {
        $order = Order::create([
            'guest_email'       => 'lesser@example.com',
            'total_amount'      => 10000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->staff->id,
            'pod_status'        => 'awaiting_collection',
            'status'            => 'processing',
        ]);

        // Try collecting 5,000 for a 10,000 order
        $response = $this->actingAs($this->staff)->post(route('admin.orders.mark-cash-collected', $order->id), [
            'amount' => 5000,
        ]);

        $response->assertSessionHasErrors(['amount']);
        $order->refresh();
        $this->assertEquals('awaiting_collection', $order->pod_status);
    }

    public function test_cannot_collect_or_remit_cash_for_cancelled_order()
    {
        $order = Order::create([
            'guest_email'       => 'cancelled_pod@example.com',
            'total_amount'      => 10000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->staff->id,
            'pod_status'        => 'awaiting_collection',
            'status'            => 'cancelled',
        ]);

        $response = $this->actingAs($this->staff)->post(route('admin.orders.mark-cash-collected', $order->id), [
            'amount' => 10000,
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('awaiting_collection', $order->fresh()->pod_status);

        $responseRemit = $this->actingAs($this->admin)->post(route('admin.orders.mark-cash-remitted', $order->id));
        $responseRemit->assertSessionHas('error');
    }

    public function test_only_admin_can_reconcile_pod_cash()
    {
        $order = Order::create([
            'guest_email'    => 'reconcile@example.com',
            'total_amount'   => 25000,
            'payment_method' => 'pod',
            'pod_status'     => 'remitted_to_store',
            'status'         => 'shipped',
        ]);

        // Staff fails with 403
        $staffResponse = $this->actingAs($this->staff)->post(route('admin.orders.reconcile-pod', $order->id), [
            'reconciliation_note' => 'Staff attempting reconciliation',
        ]);
        $staffResponse->assertForbidden();

        // Admin succeeds
        $adminResponse = $this->actingAs($this->admin)->post(route('admin.orders.reconcile-pod', $order->id), [
            'reconciliation_note' => 'Verified cash in store register',
        ]);
        $adminResponse->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('reconciled', $order->pod_status);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals($this->admin->id, $order->reconciled_by_admin_id);
        $this->assertEquals('Verified cash in store register', $order->pod_reconciliation_note);
    }

    public function test_export_csv_streams_sales_report()
    {
        Order::create([
            'guest_email'    => 'csv@example.com',
            'total_amount'   => 10000,
            'payment_method' => 'bank_transfer',
            'status'         => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.sales.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
