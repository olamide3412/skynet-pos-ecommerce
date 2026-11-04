<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminP2ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);
    }

    public function test_admin_can_view_and_manage_coupons()
    {
        // 1. Visit coupon index
        $response = $this->actingAs($this->admin)->get(route('admin.coupons.index'));
        $response->assertStatus(200);

        // 2. Store a coupon
        $storeResponse = $this->actingAs($this->admin)->post(route('admin.coupons.store'), [
            'code'             => 'PROMO15',
            'type'             => 'percent',
            'value'            => 15,
            'min_order_amount' => 5000,
            'max_uses'         => 100,
            'is_active'        => true,
        ]);
        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'PROMO15', 'value' => 15]);

        $coupon = Coupon::where('code', 'PROMO15')->first();

        // 3. Update coupon
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.coupons.update', $coupon), [
            'code'             => 'PROMO20',
            'type'             => 'percent',
            'value'            => 20,
            'min_order_amount' => 10000,
            'is_active'        => true,
        ]);
        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'PROMO20', 'value' => 20]);

        // 4. Toggle status
        $toggleResponse = $this->actingAs($this->admin)->patch(route('admin.coupons.toggle-status', $coupon));
        $toggleResponse->assertRedirect();
        $this->assertFalse((bool) $coupon->fresh()->is_active);

        // 5. Delete coupon
        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.coupons.destroy', $coupon));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_can_moderate_product_reviews()
    {
        $customer = Customer::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Test Gadget',
            'slug'           => 'test-gadget',
            'url_key'        => 'test-gadget',
            'product_number' => 'GAD-001',
            'price'          => 5000,
            'status'         => true,
            'stock'          => 10,
        ]);

        $review = ProductReview::create([
            'customer_id' => $customer->id,
            'product_id'  => $product->id,
            'rating'      => 5,
            'comment'     => 'Outstanding quality and super fast shipping!',
            'is_approved' => false,
        ]);

        // 1. Admin views reviews list
        $response = $this->actingAs($this->admin)->get(route('admin.reviews.index'));
        $response->assertStatus(200);

        // 2. Toggle approval
        $toggleResponse = $this->actingAs($this->admin)->patch(route('admin.reviews.toggle-approval', $review));
        $toggleResponse->assertRedirect();
        $this->assertTrue((bool) $review->fresh()->is_approved);

        // 3. Delete review
        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.reviews.destroy', $review));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);
    }

    public function test_admin_can_manage_shipping_zones()
    {
        // 1. Visit shipping zones index
        $response = $this->actingAs($this->admin)->get(route('admin.shipping-zones.index'));
        $response->assertStatus(200);

        // 2. Store new zone
        $storeResponse = $this->actingAs($this->admin)->post(route('admin.shipping-zones.store'), [
            'state'          => 'Lagos',
            'city'           => 'Ikeja / Island',
            'fee'            => 2500,
            'estimated_days' => '1-2 Days',
            'is_active'      => true,
        ]);
        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('shipping_zones', ['state' => 'Lagos', 'fee' => 2500]);

        $zone = ShippingZone::where('state', 'Lagos')->first();

        // 3. Update zone
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.shipping-zones.update', $zone), [
            'state'          => 'Lagos',
            'city'           => 'All Lagos Regions',
            'fee'            => 3000,
            'estimated_days' => '24-48 Hours',
            'is_active'      => true,
        ]);
        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('shipping_zones', ['state' => 'Lagos', 'fee' => 3000]);

        // 4. Toggle active status
        $toggleResponse = $this->actingAs($this->admin)->patch(route('admin.shipping-zones.toggle-status', $zone));
        $toggleResponse->assertRedirect();
        $this->assertFalse((bool) $zone->fresh()->is_active);

        // 5. Delete zone
        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.shipping-zones.destroy', $zone));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('shipping_zones', ['id' => $zone->id]);
    }

    public function test_admin_can_create_multiple_zones_for_same_state_and_set_default()
    {
        // 1. Create first town under Delta (Abraka) with Free delivery
        $response1 = $this->actingAs($this->admin)->post(route('admin.shipping-zones.store'), [
            'state'          => 'Delta',
            'city'           => 'Abraka',
            'fee'            => 0,
            'estimated_days' => 'Same Day',
            'is_active'      => true,
            'is_default'     => true,
        ]);
        $response1->assertRedirect();
        $this->assertDatabaseHas('shipping_zones', [
            'state'      => 'Delta',
            'city'       => 'Abraka',
            'fee'        => 0,
            'is_default' => true,
        ]);

        // 2. Create second town under Delta (Warri) with 1500 fee
        $response2 = $this->actingAs($this->admin)->post(route('admin.shipping-zones.store'), [
            'state'          => 'Delta',
            'city'           => 'Warri',
            'fee'            => 1500,
            'estimated_days' => '1-2 Days',
            'is_active'      => true,
            'is_default'     => false,
        ]);
        $response2->assertRedirect();
        $this->assertDatabaseHas('shipping_zones', [
            'state'      => 'Delta',
            'city'       => 'Warri',
            'fee'        => 1500,
            'is_default' => false,
        ]);

        $abrakaZone = ShippingZone::where('state', 'Delta')->where('city', 'Abraka')->first();
        $warriZone = ShippingZone::where('state', 'Delta')->where('city', 'Warri')->first();

        // 3. Switch default zone to Warri
        $setDefaultResponse = $this->actingAs($this->admin)->patch(route('admin.shipping-zones.set-default', $warriZone));
        $setDefaultResponse->assertRedirect();

        $this->assertFalse((bool) $abrakaZone->fresh()->is_default);
        $this->assertTrue((bool) $warriZone->fresh()->is_default);
    }
}
