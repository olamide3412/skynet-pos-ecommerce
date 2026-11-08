<?php

namespace Tests\Feature;

use App\Mail\AdminNewOrderAlertMail;
use App\Mail\CustomerOrderConfirmationMail;
use App\Mail\CustomerResetPasswordMail;
use App\Mail\CustomerWelcomeMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomizedEmailsThemeAndLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        StoreSetting::set('company_name', 'EasyPick.NG');
        StoreSetting::set('company_logo', 'settings/custom_test_logo.png');
        StoreSetting::set('theme_primary_color', '#FF4B00');
    }

    public function test_customer_reset_password_mail_renders_theme_color_and_logo(): void
    {
        $customer = Customer::factory()->create([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $mailable = new CustomerResetPasswordMail($customer, 'dummy-reset-token-12345');
        $html = $mailable->render();

        $this->assertStringContainsString('#FF4B00', $html);
        $this->assertStringNotContainsString('#7e22ce', $html, 'Purple color must not be present');
        $this->assertStringContainsString('settings/custom_test_logo.png', $html);
        $this->assertStringContainsString('EasyPick.NG', $html);
        $this->assertStringContainsString('dummy-reset-token-12345', $html);
    }

    public function test_customer_welcome_mail_renders_theme_color_and_logo(): void
    {
        $customer = Customer::factory()->create([
            'name'  => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $mailable = new CustomerWelcomeMail($customer);
        $html = $mailable->render();

        $this->assertStringContainsString('#FF4B00', $html);
        $this->assertStringNotContainsString('#7e22ce', $html, 'Purple color must not be present');
        $this->assertStringContainsString('settings/custom_test_logo.png', $html);
        $this->assertStringContainsString('EasyPick.NG', $html);
        $this->assertStringContainsString('Jane Smith', $html);
    }

    public function test_customer_order_confirmation_mail_renders_theme_color_and_logo(): void
    {
        $category = \App\Models\Category::create(['name' => 'Solar', 'slug' => 'solar']);
        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Solar Power Inverter',
            'slug'           => 'solar-power-inverter',
            'url_key'        => 'solar-power-inverter',
            'product_number' => 'INV-001',
            'price'          => 150000,
            'status'         => true,
            'stock'          => 10,
        ]);

        $order = Order::create([
            'tracking_number' => 'EP-998877',
            'billing_name'    => 'David Mark',
            'billing_email'   => 'david@example.com',
            'total_amount'    => 150000,
            'subtotal'        => 150000,
            'status'          => 'pending',
            'payment_method'  => 'card',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 150000,
        ]);

        $mailable = new CustomerOrderConfirmationMail($order);
        $html = $mailable->render();

        $this->assertStringContainsString('#FF4B00', $html);
        $this->assertStringContainsString('settings/custom_test_logo.png', $html);
        $this->assertStringContainsString('EP-998877', $html);
        $this->assertStringContainsString('Solar Power Inverter', $html);
    }

    public function test_order_status_updated_mail_renders_theme_color_and_logo(): void
    {
        $order = Order::create([
            'tracking_number' => 'EP-112233',
            'billing_name'    => 'Sarah Connor',
            'billing_email'   => 'sarah@example.com',
            'total_amount'    => 25000,
            'subtotal'        => 25000,
            'status'          => 'shipped',
        ]);

        $mailable = new OrderStatusUpdatedMail($order, 'shipped');
        $html = $mailable->render();

        $this->assertStringContainsString('#FF4B00', $html);
        $this->assertStringContainsString('settings/custom_test_logo.png', $html);
        $this->assertStringContainsString('EP-112233', $html);
        $this->assertStringContainsString('Shipped', $html);
    }

    public function test_admin_new_order_alert_mail_renders_theme_color_and_logo(): void
    {
        $order = Order::create([
            'tracking_number' => 'EP-445566',
            'billing_name'    => 'Admin Order Watcher',
            'billing_email'   => 'customer@example.com',
            'total_amount'    => 80000,
            'subtotal'        => 80000,
            'status'          => 'pending',
        ]);

        $mailable = new AdminNewOrderAlertMail($order);
        $html = $mailable->render();

        $this->assertStringContainsString('#FF4B00', $html);
        $this->assertStringContainsString('settings/custom_test_logo.png', $html);
        $this->assertStringContainsString('EP-445566', $html);
    }
}
