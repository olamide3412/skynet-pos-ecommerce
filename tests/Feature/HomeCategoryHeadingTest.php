<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeCategoryHeadingTest extends TestCase
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

    public function test_home_page_includes_default_or_custom_category_headings_in_store_settings(): void
    {
        StoreSetting::set('home_categories_title', 'Browse Popular Departments');
        StoreSetting::set('home_categories_subtitle', 'Find everything you love organized neatly by category');

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('store_settings.home_categories_title', 'Browse Popular Departments')
            ->where('store_settings.home_categories_subtitle', 'Find everything you love organized neatly by category')
        );
    }

    public function test_admin_can_update_home_categories_headings(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.sliders.settings.update'), [
                'home_categories_title'    => 'Explore Top Collections',
                'home_categories_subtitle' => 'Hand-picked products curated just for your lifestyle',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame('Explore Top Collections', StoreSetting::get('home_categories_title'));
        $this->assertSame('Hand-picked products curated just for your lifestyle', StoreSetting::get('home_categories_subtitle'));
    }
}

