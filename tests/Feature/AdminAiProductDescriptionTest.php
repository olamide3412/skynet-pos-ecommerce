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
            'role' => RoleEnums::SuperAdministrator->value,
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
            'ai_enabled'                  => true,
            'ai_active_provider'          => 'deepseek',
            'ai_deepseek_key'             => 'sk-deepseek-live-key',
            'ai_deepseek_model'           => 'deepseek-chat',
            'ai_prompt_short_description' => 'Custom Short Instruction: Write exactly one catchy sentence.',
            'ai_prompt_full_description'  => 'Custom Full Instruction: Generate bulleted specs in HTML.',
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.ai-settings.update'), $payload);

        $response->assertRedirect();
        $this->assertEquals('1', StoreSetting::get('ai_enabled'));
        $this->assertEquals('deepseek', StoreSetting::get('ai_active_provider'));
        $this->assertEquals('sk-deepseek-live-key', StoreSetting::get('ai_deepseek_key'));
        $this->assertEquals('deepseek-chat', StoreSetting::get('ai_deepseek_model'));
        $this->assertEquals('Custom Short Instruction: Write exactly one catchy sentence.', StoreSetting::get('ai_prompt_short_description'));
        $this->assertEquals('Custom Full Instruction: Generate bulleted specs in HTML.', StoreSetting::get('ai_prompt_full_description'));
    }

    public function test_it_uses_custom_system_instruction_for_short_and_full_descriptions(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');
        StoreSetting::set('ai_prompt_short_description', 'CUSTOM_SHORT_INSTRUCTION_FOR_STORE');
        StoreSetting::set('ai_prompt_full_description', 'CUSTOM_FULL_INSTRUCTION_FOR_STORE');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '<p>Generated content</p>']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // 1. Short description test
        $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'short',
                'product_name' => 'Custom Test Product',
            ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'CUSTOM_SHORT_INSTRUCTION_FOR_STORE');
        });

        // 2. Full description test
        $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'full',
                'product_name' => 'Custom Test Product',
            ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'CUSTOM_FULL_INSTRUCTION_FOR_STORE');
        });
    }

    public function test_it_falls_back_to_default_system_instruction_when_custom_prompt_is_empty(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');
        StoreSetting::set('ai_prompt_short_description', '');
        StoreSetting::set('ai_prompt_full_description', '   ');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '<p>Generated default content</p>']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'short',
                'product_name' => 'Default Fallback Test',
            ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'You are an expert e-commerce copywriter');
        });
    }

    public function test_it_generates_seo_metadata_with_ai_provider(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');

        $seoJson = json_encode([
            'meta_title' => 'iPhone 15 Pro Max 256GB - Buy in Nigeria | Skynet Store',
            'meta_description' => 'Buy the authentic iPhone 15 Pro Max with titanium finish and A17 Pro chip. Enjoy same-day delivery and warranty.',
            'meta_keywords' => 'iphone 15 pro max, titanium iphone, buy iphone lagos, apple smartphone nigeria',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "```json\n" . $seoJson . "\n```"]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.generate-product-description'), [
                'type' => 'seo',
                'product_name' => 'iPhone 15 Pro Max',
                'category_name' => 'Smartphones',
                'price' => 1200000,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'type' => 'seo',
                'seo' => [
                    'meta_title' => 'iPhone 15 Pro Max 256GB - Buy in Nigeria | Skynet Store',
                    'meta_description' => 'Buy the authentic iPhone 15 Pro Max with titanium finish and A17 Pro chip. Enjoy same-day delivery and warranty.',
                    'meta_keywords' => 'iphone 15 pro max, titanium iphone, buy iphone lagos, apple smartphone nigeria',
                ],
            ]);
    }

    public function test_product_can_be_stored_and_updated_with_seo_metadata(): void
    {
        $category = \App\Models\Category::create([
            'name' => 'Audio & Sound',
            'slug' => 'audio-sound',
        ]);

        $createPayload = [
            'name' => 'Sony WH-1000XM5 Wireless Headphones',
            'inventory_source' => 'ecommerce',
            'url_key' => 'sony-wh-1000xm5',
            'category_id' => $category->id,
            'price' => 450000,
            'stock' => 15,
            'status' => true,
            'visible_individually' => true,
            'meta_title' => 'Sony WH-1000XM5 Noise Cancelling Headphones',
            'meta_description' => 'Best industry-leading wireless noise cancelling headphones by Sony with 30-hour battery life.',
            'meta_keywords' => 'sony headphones, wh-1000xm5, noise cancelling, bluetooth audio',
        ];

        $storeResponse = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.store'), $createPayload);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Sony WH-1000XM5 Wireless Headphones',
            'meta_title' => 'Sony WH-1000XM5 Noise Cancelling Headphones',
            'meta_description' => 'Best industry-leading wireless noise cancelling headphones by Sony with 30-hour battery life.',
            'meta_keywords' => 'sony headphones, wh-1000xm5, noise cancelling, bluetooth audio',
        ]);

        $product = \App\Models\Product::where('name', 'Sony WH-1000XM5 Wireless Headphones')->firstOrFail();

        $updatePayload = array_merge($product->toArray(), [
            'meta_title' => 'Updated Sony WH-1000XM5 SEO Title',
            'meta_description' => 'Updated SEO meta description for Sony WH-1000XM5.',
            'meta_keywords' => 'updated, sony, headphones',
        ]);

        $updateResponse = $this->actingAs($this->admin, 'web')
            ->put(route('admin.products.update', $product->id), $updatePayload);

        $updateResponse->assertSessionHasNoErrors();
        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'meta_title' => 'Updated Sony WH-1000XM5 SEO Title',
            'meta_description' => 'Updated SEO meta description for Sony WH-1000XM5.',
            'meta_keywords' => 'updated, sony, headphones',
        ]);
    }

    public function test_it_suggests_related_products_using_ai(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');
        StoreSetting::set('ai_related_strategy', 'ai_only');

        $category = \App\Models\Category::create(['name' => 'Phones & Accessories', 'slug' => 'phones-accessories']);

        $case = \App\Models\Product::create([
            'name' => 'Silicone Case for iPhone 15',
            'inventory_source' => 'ecommerce',
            'url_key' => 'silicone-case-iphone-15',
            'category_id' => $category->id,
            'price' => 15000,
            'stock' => 50,
            'status' => true,
            'slug' => 'silicone-case-iphone-15',
            'pos_sync_status' => 'synced',
        ]);

        $charger = \App\Models\Product::create([
            'name' => '20W USB-C Fast Charger',
            'inventory_source' => 'ecommerce',
            'url_key' => '20w-fast-charger',
            'category_id' => $category->id,
            'price' => 20000,
            'stock' => 30,
            'status' => true,
            'slug' => '20w-fast-charger',
            'pos_sync_status' => 'synced',
        ]);

        $mockJson = json_encode([
            ['id' => $case->id, 'reason' => 'Protective case specifically for this phone model'],
            ['id' => $charger->id, 'reason' => 'Fast charger essential for optimal battery charging'],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "```json\n" . $mockJson . "\n```"]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.suggest-related-products'), [
                'product_name' => 'iPhone 15 Pro 256GB',
                'category_id' => $category->id,
                'category_name' => 'Smartphones',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ids' => [$case->id, $charger->id],
            ]);

        $this->assertCount(2, $response->json('products'));
        $this->assertEquals('Protective case specifically for this phone model', $response->json('products.0.ai_reason'));
    }

    public function test_it_suggests_hybrid_related_products_with_category_and_ai(): void
    {
        StoreSetting::set('ai_enabled', '1');
        StoreSetting::set('ai_active_provider', 'gemini');
        StoreSetting::set('ai_gemini_key', 'test-gemini-key');
        StoreSetting::set('ai_gemini_model', 'gemini-2.5-flash');
        StoreSetting::set('ai_related_strategy', 'hybrid');

        $phoneCategory = \App\Models\Category::create(['name' => 'Phones', 'slug' => 'phones']);
        $accCategory   = \App\Models\Category::create(['name' => 'Accessories', 'slug' => 'accessories']);

        // 5 products in phoneCategory
        $catProducts = [];
        for ($i = 1; $i <= 5; $i++) {
            $catProducts[] = \App\Models\Product::create([
                'name' => "Phone Model {$i}",
                'inventory_source' => 'ecommerce',
                'url_key' => "phone-model-{$i}",
                'category_id' => $phoneCategory->id,
                'price' => 100000 + $i,
                'stock' => 10,
                'status' => true,
                'slug' => "phone-model-{$i}",
            ]);
        }

        // 5 accessory products outside phoneCategory
        $accProducts = [];
        for ($i = 1; $i <= 5; $i++) {
            $accProducts[] = \App\Models\Product::create([
                'name' => "Accessory {$i}",
                'inventory_source' => 'ecommerce',
                'url_key' => "accessory-item-{$i}",
                'category_id' => $accCategory->id,
                'price' => 5000 + $i,
                'stock' => 20,
                'status' => true,
                'slug' => "accessory-item-{$i}",
            ]);
        }

        $mockJson = json_encode(array_map(fn($p) => [
            'id' => $p->id,
            'reason' => 'Complementary accessory from AI'
        ], $accProducts));

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "```json\n" . $mockJson . "\n```"]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.ai.suggest-related-products'), [
                'product_name' => 'iPhone 15 Flagship',
                'category_id' => $phoneCategory->id,
                'category_name' => 'Phones',
                'limit' => 10,
            ]);

        $response->assertStatus(200);
        $products = $response->json('products');

        // Should return 10 products: 5 from same category + 5 from AI cross-sell
        $this->assertCount(10, $products);

        // First 5 are category companions
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals('Same category companion', $products[$i]['ai_reason']);
            $this->assertEquals($phoneCategory->id, $products[$i]['category_id']);
        }

        // Remaining 5 are from AI suggestions
        for ($i = 5; $i < 10; $i++) {
            $this->assertEquals('Complementary accessory from AI', $products[$i]['ai_reason']);
            $this->assertEquals($accCategory->id, $products[$i]['category_id']);
        }
    }
}

