<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_newsletter_with_valid_email()
    {
        $response = $this->postJson(route('newsletter.subscribe'), [
            'email' => 'customer@example.com',
            'source' => 'footer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'already_subscribed' => false,
            ]);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'customer@example.com',
            'status' => 'subscribed',
            'source' => 'footer',
        ]);
    }

    public function test_invalid_email_returns_validation_error()
    {
        $response = $this->postJson(route('newsletter.subscribe'), [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_duplicate_subscription_returns_friendly_message_without_error()
    {
        NewsletterSubscriber::create([
            'email' => 'repeat@example.com',
            'status' => 'subscribed',
            'subscribed_at' => now(),
        ]);

        $response = $this->postJson(route('newsletter.subscribe'), [
            'email' => 'repeat@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'already_subscribed' => true,
            ]);

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_admin_can_view_newsletter_subscribers_page()
    {
        $this->withoutVite();

        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        NewsletterSubscriber::create([
            'email' => 'subscriber1@example.com',
            'status' => 'subscribed',
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.newsletter.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Newsletter/Index')
            ->has('subscribers')
            ->has('stats')
        );
    }

    public function test_admin_can_export_subscribers_to_csv()
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        NewsletterSubscriber::create([
            'email' => 'csv@example.com',
            'status' => 'subscribed',
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.newsletter.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_admin_can_toggle_subscriber_status_and_delete()
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $subscriber = NewsletterSubscriber::create([
            'email' => 'toggle@example.com',
            'status' => 'subscribed',
        ]);

        $toggleResponse = $this->actingAs($admin, 'web')->patch(route('admin.newsletter.toggle-status', $subscriber->id));
        $toggleResponse->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', [
            'id' => $subscriber->id,
            'status' => 'unsubscribed',
        ]);

        $deleteResponse = $this->actingAs($admin, 'web')->delete(route('admin.newsletter.destroy', $subscriber->id));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('newsletter_subscribers', [
            'id' => $subscriber->id,
        ]);
    }
}
