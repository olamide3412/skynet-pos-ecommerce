<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAiProductDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);
    }

    public function test_guests_cannot_access_ai_endpoints(): void
    {
        $this->postJson(route('admin.ai.generate-product-description'), [
            'type' => 'short',
            'product_name' => 'iPhone 15 Pro',
        ])->assertStatus(401);

        $this->postJson(route('admin.ai.test-connection'), [
            'provider' => 'gemini',
        ])->assertStatus(401);
    }

    public function test_it_validates_required_fields_for_ai_generation(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_name']);
    }

    public function test_it_fails_when_ai_is_globally_disabled(): void
    {
        StoreSetting::set('ai_enabled', '0');

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'short',
                'product_name' => 'iPhone 15',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('disabled', strtolower($response->json('message')));
    }

    public function test_it_generates_short_description_with_gemini_provider(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'The ultimate flagship smartphone crafted with titanium and ultra-fast performance.']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'short',
                'product_name' => 'iPhone 15 Pro Max',
                'category_name' => 'Smartphones',
                'price' => 1200000,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
                'type' => 'short',
                'content' => 'The ultimate flagship smartphone crafted with titanium and ultra-fast performance.',
            ]);
    }

    public function test_it_passes_optional_keywords_to_ai_generator(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Peak Full Cream Cup with 28 servings and fortified with vitamins.']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'full',
                'product_name' => 'Peak Full Cream Cup 400g',
                'category_name' => 'Groceries',
                'keywords' => '28 servings, fortified with vitamin A & D, resealable lid',
            ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            $body = $request->body();
            return str_contains($body, '28 servings') && str_contains($body, 'resealable lid');
        });
    }

    public function test_it_generates_full_description_with_sanitized_semantic_html(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'openai');
        StoreSetting::set('ai_openai_key', 'test-openai-key');
        StoreSetting::set('ai_openai_model', 'gpt-4o-mini');

        $rawAiHtml = "```html\n<h3>Overview</h3>\n<p>Experience unmatched productivity with this device.</p>\n<h3>Key Highlights</h3>\n<ul><li>Long battery life</li><li>High refresh rate</li></ul>\n```";

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $rawAiHtml,
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'full',
                'product_name' => 'MacBook Pro 16',
                'category_name' => 'Laptops',
            ]);

        $response->assertStatus(200);
        $content = $response->json('content');

        // Markdown code blocks must be stripped
        $this->assertStringNotContainsString('```html', $content);
        $this->assertStringNotContainsString('```', $content);

        // Raw semantic HTML tags must be preserved for Quill editor
        $this->assertStringContainsString('<h3>Overview</h3>', $content);
        $this->assertStringContainsString('<ul><li>Long battery life</li>', $content);
    }

    public function test_it_strips_blank_headings_and_headings_without_writeup(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');

        $rawWithBlankHeadings = "<h3>Overview</h3>\n<p>Peak Full Cream Cup 400g is a rich milk powder.</p>\n\n<h3>Key Features & Benefits</h3>\n\n<h3>Specifications</h3>\n\n<h3>Why Choose Peak Full Cream Cup 400g?</h3>\n<p>Peak has earned its place in kitchens.</p>\n\n<h3>Trailing Orphan Heading</h3>";

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $rawWithBlankHeadings]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'full',
                'product_name' => 'Peak Full Cream Cup 400g',
                'category_name' => 'Groceries',
            ]);

        $response->assertStatus(200);
        $content = $response->json('content');

        // Headings WITH writeup must remain
        $this->assertStringContainsString('<h3>Overview</h3>', $content);
        $this->assertStringContainsString('<h3>Why Choose Peak Full Cream Cup 400g?</h3>', $content);

        // Headings WITHOUT writeup must be stripped
        $this->assertStringNotContainsString('Key Features & Benefits', $content);
        $this->assertStringNotContainsString('Specifications', $content);
        $this->assertStringNotContainsString('Trailing Orphan Heading', $content);
    }

    public function test_it_tests_connection_successfully_for_provider(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Antigravity AI connection established.']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.test-connection'), [
                'provider' => 'gemini',
                'api_key' => 'custom-runtime-key',
                'model' => 'gemini-2.5-flash',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'gemini',
            ]);

        $this->assertArrayHasKey('latency_ms', $response->json());
        $this->assertArrayHasKey('message', $response->json());
    }

    public function test_it_handles_connection_test_failure_gracefully(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided: sk-invalid',
                ]
            ], 401),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.test-connection'), [
                'provider' => 'openai',
                'api_key' => 'sk-invalid',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'provider' => 'openai',
            ]);

        $this->assertStringContainsString('Incorrect API key', $response->json('message'));
    }

    public function test_admin_can_save_ai_configurations_in_store_settings(): void
    {
        $payload = [
            'company_name'       => 'Skynet Store',
            'ai_enabled'         => true,
            'ai_active_provider' => 'claude',
            'ai_claude_key'      => 'sk-ant-test-key-12345',
            'ai_claude_model'    => 'claude-3-5-sonnet-20241022',
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.settings.update'), $payload);

        $response->assertRedirect();
        $this->assertEquals('1', StoreSetting::get('ai_enabled'));
        $this->assertEquals('claude', StoreSetting::get('ai_active_provider'));
        $this->assertEquals('sk-ant-test-key-12345', StoreSetting::get('ai_claude_key'));
        $this->assertEquals('claude-3-5-sonnet-20241022', StoreSetting::get('ai_claude_model'));
    }

    public function test_admin_can_view_ai_settings_sub_menu_page(): void
    {
        $this->withoutVite();

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.ai-settings.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AiSettings/Index')
            ->has('ai_settings')
        );
    }

    public function test_admin_can_update_ai_settings_via_dedicated_endpoint(): void
    {
        $payload = [
            'ai_enabled'         => true,
            'ai_active_provider' => 'deepseek',
            'ai_deepseek_key'    => 'sk-deepseek-live-key',
            'ai_deepseek_model'  => 'deepseek-chat',
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.ai-settings.update'), $payload);

        $response->assertRedirect();
        $this->assertEquals('1', StoreSetting::get('ai_enabled'));
        $this->assertEquals('deepseek', StoreSetting::get('ai_active_provider'));
        $this->assertEquals('sk-deepseek-live-key', StoreSetting::get('ai_deepseek_key'));
        $this->assertEquals('deepseek-chat', StoreSetting::get('ai_deepseek_model'));
    }
}

