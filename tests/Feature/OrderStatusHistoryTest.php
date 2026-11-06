<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
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

    public function test_order_status_update_records_lifecycle_history()
    {
        $order = Order::create([
            'guest_email'    => 'history@example.com',
            'total_amount'   => 5000,
            'payment_method' => 'pod',
            'status'         => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.orders.status', $order->id), [
            'status' => 'shipped',
        ]);

        $response->assertSessionHas('success');
        $order->refresh();
        $this->assertEquals('shipped', $order->status);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id'    => $order->id,
            'user_id'     => $this->admin->id,
            'event'       => 'status_change',
            'from_status' => 'processing',
            'to_status'   => 'shipped',
        ]);
    }

    public function test_assigning_staff_records_history()
    {
        $order = Order::create([
            'guest_email'    => 'assign@example.com',
            'total_amount'   => 7000,
            'payment_method' => 'pod',
            'status'         => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.orders.assign-staff', $order->id), [
            'assigned_staff_id' => $this->staff->id,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'event'    => 'staff_assigned',
        ]);
    }

    public function test_collecting_cash_and_reconciling_records_history()
    {
        $order = Order::create([
            'guest_email'          => 'collect@example.com',
            'total_amount'         => 12000,
            'payment_method'       => 'pod',
            'assigned_staff_id'    => $this->staff->id,
            'pod_status'           => 'awaiting_collection',
            'status'               => 'processing',
        ]);

        // 1. Staff collects
        $this->actingAs($this->staff)->post(route('admin.orders.mark-cash-collected', $order->id), [
            'amount'  => 12000,
            'channel' => 'cash',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'user_id'  => $this->staff->id,
            'event'    => 'payment_collected',
        ]);

        // 2. Admin receives and reconciles
        $this->actingAs($this->admin)->post(route('admin.orders.mark-cash-remitted', $order->id));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id'    => $order->id,
            'user_id'     => $this->admin->id,
            'event'       => 'reconciled',
            'from_status' => 'processing',
            'to_status'   => 'completed',
        ]);
    }

    public function test_updating_to_same_status_records_confirmation_history()
    {
        $order = Order::create([
            'guest_email'    => 'confirm@example.com',
            'total_amount'   => 5000,
            'payment_method' => 'pod',
            'status'         => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.orders.status', $order->id), [
            'status' => 'processing',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id'    => $order->id,
            'user_id'     => $this->admin->id,
            'event'       => 'status_change',
            'from_status' => 'processing',
            'to_status'   => 'processing',
            'comment'     => "Order status confirmed as Processing by {$this->admin->name}.",
        ]);
    }

    public function test_completed_and_cancelled_orders_cannot_be_modified()
    {
        $completedOrder = Order::create([
            'guest_email'    => 'locked1@example.com',
            'total_amount'   => 5000,
            'payment_method' => 'pod',
            'status'         => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.orders.status', $completedOrder->id), [
            'status' => 'processing',
        ]);
        $response->assertSessionHas('error');
        $this->assertEquals('completed', $completedOrder->fresh()->status);

        $cancelledOrder = Order::create([
            'guest_email'    => 'locked2@example.com',
            'total_amount'   => 5000,
            'payment_method' => 'pod',
            'status'         => 'cancelled',
        ]);

        $response2 = $this->actingAs($this->admin)->put(route('admin.orders.status', $cancelledOrder->id), [
            'status' => 'pending',
        ]);
        $response2->assertSessionHas('error');
        $this->assertEquals('cancelled', $cancelledOrder->fresh()->status);
    }

    public function test_admin_can_add_audit_note_to_order_timeline()
    {
        $order = Order::create([
            'guest_email'    => 'note@example.com',
            'total_amount'   => 4500,
            'payment_method' => 'pod',
            'status'         => 'processing',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.orders.add-note', $order->id), [
            'note' => 'Customer called to request delivery after 5 PM.',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'user_id'  => $this->admin->id,
            'event'    => 'note_added',
            'comment'  => 'Customer called to request delivery after 5 PM.',
        ]);
    }

    public function test_order_show_page_loads_with_status_histories()
    {
        $order = Order::create([
            'guest_email'    => 'view@example.com',
            'total_amount'   => 6000,
            'payment_method' => 'pod',
            'status'         => 'processing',
        ]);

        $order->recordStatusHistory(
            toStatus: 'processing',
            fromStatus: null,
            event: 'order_placed',
            comment: 'Order placed by customer.'
        );

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Show')
            ->has('order.status_histories', 1)
            ->where('order.status_histories.0.event', 'order_placed')
        );
    }

    public function test_manage_orders_page_provides_status_counts(): void
    {
        Order::create([
            'guest_email'  => 'order1@example.com',
            'total_amount' => 5000,
            'status'       => 'pending',
        ]);
        Order::create([
            'guest_email'  => 'order2@example.com',
            'total_amount' => 6000,
            'status'       => 'processing',
        ]);
        Order::create([
            'guest_email'  => 'order3@example.com',
            'total_amount' => 7000,
            'status'       => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Orders/Index')
            ->has('statusCounts')
            ->where('statusCounts.all', 3)
            ->where('statusCounts.pending', 1)
            ->where('statusCounts.processing', 1)
            ->where('statusCounts.completed', 1)
            ->where('statusCounts.cancelled', 0)
        );
    }
}

