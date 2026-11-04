<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EndOfDayReportTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);
    }

    public function test_guest_cannot_access_end_of_day_report()
    {
        $response = $this->get(route('admin.reports.end-of-day'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_access_end_of_day_report_with_today_as_default()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.end-of-day'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/EndOfDay')
            ->has('date')
            ->has('formatted_date')
            ->has('metrics')
            ->has('source_breakdown')
            ->has('payment_methods')
            ->has('hourly_stats')
            ->has('products_sold')
            ->has('orders')
            ->has('store_info')
            ->where('is_today', true)
        );
    }

    public function test_end_of_day_report_accurately_aggregates_sales_and_payment_methods()
    {
        $category = Category::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);

        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Espresso Roast',
            'slug'           => 'espresso-roast',
            'url_key'        => 'espresso-roast',
            'product_number' => 'ESP-001',
            'price'          => 2500,
            'cost_price'     => 1500,
            'stock'          => 50,
            'status'         => true,
        ]);

        $today = Carbon::today();

        // Create a paid order today
        $order = Order::create([
            'total_amount'    => 5000,
            'discount_amount' => 500,
            'status'          => 'completed',
            'payment_method'  => 'paystack',
            'billing_name'    => 'John Doe',
            'billing_email'   => 'john@example.com',
            'created_at'      => $today->copy()->setTime(10, 30),
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $product->id,
            'quantity'         => 2,
            'price'            => 2500,
            'cost_price'       => 1500,
            'inventory_source' => 'pos',
        ]);

        // Create an order on a different date (yesterday)
        $yesterday = $today->copy()->subDay();
        $pastOrder = Order::create([
            'total_amount'    => 10000,
            'discount_amount' => 0,
            'status'          => 'completed',
            'payment_method'  => 'direct_bank_transfer',
            'billing_name'    => 'Jane Smith',
            'billing_email'   => 'jane@example.com',
            'created_at'      => $yesterday->copy()->setTime(14, 0),
        ]);

        OrderItem::create([
            'order_id'         => $pastOrder->id,
            'product_id'       => $product->id,
            'quantity'         => 4,
            'price'            => 2500,
            'cost_price'       => 1500,
            'inventory_source' => 'ecommerce',
        ]);

        // Query today's EOD
        $response = $this->actingAs($this->admin)->get(route('admin.reports.end-of-day', [
            'date' => $today->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/EndOfDay')
            ->where('date', $today->format('Y-m-d'))
            ->where('metrics.paid_orders_count', 1)
            ->where('metrics.total_orders_count', 1)
            ->where('metrics.items_sold', 2)
            ->where('metrics.total_revenue', 5000)
            ->where('metrics.total_cost', 3000)
            ->where('metrics.total_profit', 2000)
            ->where('source_breakdown.pos.units_sold', 2)
            ->where('source_breakdown.pos.revenue', 5000)
            ->has('orders', 1)
        );

        // Query yesterday's EOD
        $pastResponse = $this->actingAs($this->admin)->get(route('admin.reports.end-of-day', [
            'date' => $yesterday->format('Y-m-d'),
        ]));

        $pastResponse->assertStatus(200);
        $pastResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/EndOfDay')
            ->where('date', $yesterday->format('Y-m-d'))
            ->where('metrics.paid_orders_count', 1)
            ->where('metrics.items_sold', 4)
            ->where('metrics.total_revenue', 10000)
            ->where('source_breakdown.ecommerce.units_sold', 4)
            ->has('orders', 1)
        );
    }

    public function test_end_of_day_includes_pod_order_collected_today_even_if_created_yesterday()
    {
        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Chips',
            'slug'           => 'chips',
            'url_key'        => 'chips',
            'product_number' => 'CHP-001',
            'price'          => 2000,
            'cost_price'     => 1200,
            'stock'          => 30,
            'status'         => true,
        ]);

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        // Order created yesterday, but POD collected & reconciled today
        $podOrder = Order::create([
            'total_amount'      => 3500, // Includes 1500 delivery fee
            'status'            => 'completed',
            'payment_method'    => 'pod',
            'pod_status'        => 'reconciled',
            'created_at'        => $yesterday->copy()->setTime(22, 0),
            'pod_collected_at'  => $today->copy()->setTime(11, 0),
            'pod_reconciled_at' => $today->copy()->setTime(11, 30),
        ]);

        OrderItem::create([
            'order_id'         => $podOrder->id,
            'product_id'       => $product->id,
            'quantity'         => 1,
            'price'            => 2000,
            'cost_price'       => 1200,
            'inventory_source' => 'ecommerce',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.end-of-day', [
            'date' => $today->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/EndOfDay')
            ->where('date', $today->format('Y-m-d'))
            ->where('metrics.paid_orders_count', 1)
            ->where('metrics.total_revenue', 3500)
            ->has('orders', 1)
        );
    }
}
