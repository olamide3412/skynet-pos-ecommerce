<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodCashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected User $rider2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'   => 'administrator',
            'status' => 'enable',
        ]);

        $this->rider = User::factory()->create([
            'role'   => 'staff',
            'status' => 'enable',
        ]);

        $this->rider2 = User::factory()->create([
            'role'   => 'staff',
            'status' => 'enable',
        ]);
    }

    public function test_rider_can_view_their_own_pod_cash_records_only()
    {
        // Order 1 assigned to rider
        Order::create([
            'guest_email'       => 'cust1@example.com',
            'total_amount'      => 15000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'cash_collected',
            'pod_amount_collected' => 15000,
            'status'            => 'processing',
        ]);

        // Order 2 assigned to rider2
        Order::create([
            'guest_email'       => 'cust2@example.com',
            'total_amount'      => 25000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider2->id,
            'pod_status'        => 'cash_collected',
            'pod_amount_collected' => 25000,
            'status'            => 'processing',
        ]);

        $response = $this->actingAs($this->rider)->get(route('admin.pod-cash.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/PodCash/Index')
            ->where('canViewAll', false)
            ->where('kpis.in_hand_cash', 15000)
            ->where('kpis.in_hand_count', 1)
            ->has('orders.data', 1)
        );
    }

    public function test_admin_can_view_all_rider_floats_and_orders()
    {
        Order::create([
            'guest_email'       => 'cust1@example.com',
            'total_amount'      => 10000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'cash_collected',
            'pod_amount_collected' => 10000,
            'status'            => 'processing',
        ]);

        Order::create([
            'guest_email'       => 'cust2@example.com',
            'total_amount'      => 20000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider2->id,
            'pod_status'        => 'cash_collected',
            'pod_amount_collected' => 20000,
            'status'            => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.pod-cash.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/PodCash/Index')
            ->where('canViewAll', true)
            ->where('kpis.in_hand_cash', 30000)
            ->where('kpis.in_hand_count', 2)
            ->has('riderFloats', 2)
        );
    }

    public function test_staff_can_record_doorstep_collection()
    {
        $order = Order::create([
            'guest_email'       => 'cust@example.com',
            'total_amount'      => 18500,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'awaiting_collection',
            'status'            => 'processing',
        ]);

        $response = $this->actingAs($this->rider)->post(route('admin.pod-cash.collect', $order->id), [
            'amount'  => 18500,
            'channel' => 'cash',
        ]);

        $response->assertRedirect();
        $order->refresh();

        $this->assertEquals('cash_collected', $order->pod_status);
        $this->assertEquals(18500, (float) $order->pod_amount_collected);
        $this->assertNotNull($order->pod_collected_at);
        $this->assertEquals($this->rider->id, $order->remitted_by_staff_id);
    }

    public function test_rider_cannot_self_remit_orders_but_admin_can()
    {
        $order = Order::create([
            'guest_email'          => 'cust@example.com',
            'total_amount'         => 12000,
            'payment_method'       => 'pod',
            'assigned_staff_id'    => $this->rider->id,
            'pod_status'           => 'cash_collected',
            'pod_amount_collected' => 12000,
            'status'               => 'processing',
        ]);

        // Rider trying to self-remit should be blocked (403 Forbidden)
        $forbidden = $this->actingAs($this->rider)->post(route('admin.pod-cash.remit', $order->id));
        $forbidden->assertForbidden();

        // Admin/cashier receiving the cash remittance can settle & reconcile it
        $response = $this->actingAs($this->admin)->post(route('admin.pod-cash.remit', $order->id));
        $response->assertRedirect();
        $order->refresh();

        $this->assertEquals('reconciled', $order->pod_status);
        $this->assertNotNull($order->pod_remitted_at);
        $this->assertNotNull($order->pod_reconciled_at);
        $this->assertEquals($this->admin->id, $order->reconciled_by_admin_id);
    }

    public function test_rider_cannot_bulk_remit_orders_but_admin_can()
    {
        $order1 = Order::create([
            'guest_email'          => 'cust1@example.com',
            'total_amount'         => 5000,
            'payment_method'       => 'pod',
            'assigned_staff_id'    => $this->rider->id,
            'pod_status'           => 'cash_collected',
            'pod_amount_collected' => 5000,
            'status'               => 'processing',
        ]);

        $order2 = Order::create([
            'guest_email'          => 'cust2@example.com',
            'total_amount'         => 7000,
            'payment_method'       => 'pod',
            'assigned_staff_id'    => $this->rider->id,
            'pod_status'           => 'cash_collected',
            'pod_amount_collected' => 7000,
            'status'               => 'processing',
        ]);

        // Rider trying to bulk-remit should be blocked (403 Forbidden)
        $forbidden = $this->actingAs($this->rider)->post(route('admin.pod-cash.bulk-remit'), [
            'order_ids' => [$order1->id, $order2->id],
        ]);
        $forbidden->assertForbidden();

        // Admin can receive and bulk settle
        $response = $this->actingAs($this->admin)->post(route('admin.pod-cash.bulk-remit'), [
            'order_ids' => [$order1->id, $order2->id],
        ]);

        $response->assertRedirect();
        $order1->refresh();
        $order2->refresh();

        $this->assertEquals('reconciled', $order1->pod_status);
        $this->assertEquals('reconciled', $order2->pod_status);
        $this->assertEquals($this->admin->id, $order1->reconciled_by_admin_id);
        $this->assertEquals($this->admin->id, $order2->reconciled_by_admin_id);
    }

    public function test_cannot_collect_lesser_amount_in_pod_cash_station()
    {
        $order = Order::create([
            'guest_email'       => 'less@example.com',
            'total_amount'      => 15000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'awaiting_collection',
            'status'            => 'processing',
        ]);

        $response = $this->actingAs($this->rider)->post(route('admin.pod-cash.collect', $order->id), [
            'amount' => 8000,
        ]);

        $response->assertSessionHasErrors(['amount']);
        $order->refresh();
        $this->assertEquals('awaiting_collection', $order->pod_status);
    }

    public function test_admin_can_reconcile_single_and_bulk_pod_orders()
    {
        $order1 = Order::create([
            'guest_email'       => 'cust1@example.com',
            'total_amount'      => 15000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'remitted_to_store',
            'pod_amount_collected' => 15000,
            'status'            => 'processing',
        ]);

        $order2 = Order::create([
            'guest_email'       => 'cust2@example.com',
            'total_amount'      => 20000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'remitted_to_store',
            'pod_amount_collected' => 20000,
            'status'            => 'processing',
        ]);

        // Non-admin rider cannot reconcile
        $forbidden = $this->actingAs($this->rider)->post(route('admin.pod-cash.reconcile', $order1->id));
        $forbidden->assertForbidden();

        // Admin can single reconcile
        $response = $this->actingAs($this->admin)->post(route('admin.pod-cash.reconcile', $order1->id), [
            'reconciliation_note' => 'Envelope checked against cash register',
        ]);
        $response->assertRedirect();
        $order1->refresh();
        $this->assertEquals('reconciled', $order1->pod_status);
        $this->assertEquals($this->admin->id, $order1->reconciled_by_admin_id);

        // Admin can bulk reconcile
        $bulkResponse = $this->actingAs($this->admin)->post(route('admin.pod-cash.bulk-reconcile'), [
            'order_ids' => [$order2->id],
            'reconciliation_note' => 'Batch shift reconcile',
        ]);
        $bulkResponse->assertRedirect();
        $order2->refresh();
        $this->assertEquals('reconciled', $order2->pod_status);
    }

    public function test_export_csv_streams_pod_audit_report()
    {
        Order::create([
            'guest_email'       => 'csv@example.com',
            'total_amount'      => 33000,
            'payment_method'    => 'pod',
            'assigned_staff_id' => $this->rider->id,
            'pod_status'        => 'cash_collected',
            'pod_amount_collected' => 33000,
            'status'            => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.pod-cash.export'));

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'pod_cash_report_'));
    }
}

