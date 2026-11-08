<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterBotProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legitimate_user_can_subscribe_to_newsletter(): void
    {
        $renderedAt = (microtime(true) - 3) * 1000; // Rendered 3 seconds ago (human speed)

        $response = $this->postJson(route('newsletter.subscribe'), [
            'email'                  => 'customer@example.com',
            'b_hp_contact_secondary' => '', // Clean honeypot
            '_rendered_at'           => $renderedAt,
            'source'                 => 'footer',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'already_subscribed' => false,
        ]);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email'  => 'customer@example.com',
            'status' => 'subscribed',
        ]);
    }

    public function test_bot_filling_honeypot_is_silently_discarded(): void
    {
        $response = $this->postJson(route('newsletter.subscribe'), [
            'email'                  => 'spambot@example.com',
            'b_hp_contact_secondary' => 'I am a bot filling everything',
            'source'                 => 'footer',
        ]);

        // Returns fake success to deceive the bot
        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Database is clean — no subscriber created!
        $this->assertDatabaseMissing('newsletter_subscribers', [
            'email' => 'spambot@example.com',
        ]);
    }

    public function test_instantaneous_script_submission_is_silently_discarded(): void
    {
        $instantRendered = microtime(true) * 1000; // 0 seconds elapsed

        $response = $this->postJson(route('newsletter.subscribe'), [
            'email'                  => 'fastbot@example.com',
            'b_hp_contact_secondary' => '',
            '_rendered_at'           => $instantRendered,
            'source'                 => 'footer',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Not saved in database
        $this->assertDatabaseMissing('newsletter_subscribers', [
            'email' => 'fastbot@example.com',
        ]);
    }

    public function test_disposable_email_domain_is_rejected(): void
    {
        $renderedAt = (microtime(true) - 4) * 1000;

        $response = $this->postJson(route('newsletter.subscribe'), [
            'email'                  => 'throwaway@mailinator.com',
            'b_hp_contact_secondary' => '',
            '_rendered_at'           => $renderedAt,
            'source'                 => 'footer',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseMissing('newsletter_subscribers', [
            'email' => 'throwaway@mailinator.com',
        ]);
    }
}

