<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAffiliateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'role' => 'administrator',
        ]);
        $this->admin->assignRole('Administrator');

        $this->staff = User::factory()->create([
            'role' => 'staff',
        ]);
        $this->staff->assignRole('Sales / Cashier');
    }

    public function test_admin_can_view_affiliates_list(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.affiliates.index'));
        $response->assertOk();
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        // Sales/Cashier does not have 'affiliates.view'
        $response = $this->actingAs($this->staff)->get(route('admin.affiliates.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_approve_pending_affiliate(): void
    {
        $customer = Customer::create([
            'name'     => 'Applicant Customer',
            'email'    => 'applicant@example.com',
            'password' => bcrypt('password'),
        ]);

        $affiliate = Affiliate::create([
            'customer_id'   => $customer->id,
            'referral_code' => 'APPL1234',
            'status'        => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.affiliates.update', $affiliate->id), [
            'status'          => 'approved',
            'commission_type' => 'percentage',
            'commission_rate' => 12.5,
        ]);

        $response->assertSessionHasNoErrors();

        $affiliate->refresh();
        $this->assertEquals('approved', $affiliate->status);
        $this->assertEquals(12.5, (float)$affiliate->commission_rate);
        $this->assertNotNull($affiliate->approved_at);
        $this->assertEquals($this->admin->id, $affiliate->approved_by);
    }

    public function test_admin_can_enroll_customer_directly(): void
    {
        $customer = Customer::create([
            'name'     => 'Direct Partner',
            'email'    => 'direct@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.affiliates.enroll'), [
            'customer_id'     => $customer->id,
            'commission_type' => 'flat',
            'commission_rate' => 2000,
            'bank_name'       => 'Access Bank',
            'account_number'  => '9876543210',
            'account_name'    => 'Direct Partner Ent',
        ]);

        $response->assertSessionHasNoErrors();

        $affiliate = Affiliate::where('customer_id', $customer->id)->first();
        $this->assertNotNull($affiliate);
        $this->assertEquals('approved', $affiliate->status);
        $this->assertEquals('flat', $affiliate->commission_type);
        $this->assertEquals(2000, (float)$affiliate->commission_rate);
    }
}

