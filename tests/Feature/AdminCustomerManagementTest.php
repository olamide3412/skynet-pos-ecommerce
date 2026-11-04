<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_is_forbidden_from_customer_management()
    {
        $staff = User::factory()->create([
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staff, 'web')->get(route('admin.customers.index'));
        $response->assertStatus(403);
    }

    public function test_administrator_can_view_customers_index()
    {
        $this->withoutVite();

        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        Customer::factory()->create([
            'name' => 'Alice Test',
            'email' => 'alice@example.com',
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.customers.index'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Index')
                ->has('customers')
                ->has('stats')
            );
    }

    public function test_super_administrator_can_view_customers_index()
    {
        $this->withoutVite();

        $superAdmin = User::factory()->create([
            'role' => 'super administrator',
        ]);

        $response = $this->actingAs($superAdmin, 'web')->get(route('admin.customers.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_view_customer_show_page()
    {
        $this->withoutVite();

        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'total_amount' => 5000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.customers.show', $customer->id));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Show')
                ->where('customer.id', $customer->id)
                ->has('orders')
                ->has('addresses')
            );
    }

    public function test_admin_can_update_customer_record()
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '08012345678',
        ]);

        $response = $this->actingAs($admin, 'web')->put(route('admin.customers.update', $customer->id), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '08099999999',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '08099999999',
        ]);
    }

    public function test_admin_can_reset_customer_password()
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $customer = Customer::factory()->create([
            'password' => bcrypt('oldpassword123'),
        ]);

        $response = $this->actingAs($admin, 'web')->put(route('admin.customers.reset-password', $customer->id), [
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewSecurePassword123!', $customer->password));
    }

    public function test_admin_can_delete_customer()
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin, 'web')->delete(route('admin.customers.destroy', $customer->id));

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_admin_can_export_customers_to_csv()
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        Customer::factory()->create([
            'name' => 'Export User',
            'email' => 'export@example.com',
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.customers.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}

