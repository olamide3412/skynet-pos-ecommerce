<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCategorySearchTest extends TestCase
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

    public function test_it_returns_all_categories_when_no_search_term_is_provided(): void
    {
        $parent = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        Category::create(['name' => 'Laptops', 'slug' => 'laptops', 'parent_id' => $parent->id]);
        Category::create(['name' => 'Beverages', 'slug' => 'beverages']);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.categories.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data', 3)
            ->where('filters.search', '')
        );
    }

    public function test_it_searches_categories_by_parent_name_and_finds_subcategories(): void
    {
        $groceries = Category::create(['name' => 'Groceries & Foods', 'slug' => 'groceries-foods']);
        $dairy = Category::create(['name' => 'Dairy & Milk', 'slug' => 'dairy-milk', 'parent_id' => $groceries->id]);
        $electronics = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        // Searching for "Groceries" should find the parent AND the subcategory (via orWhereHas parent)
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.categories.index', ['search' => 'Groceries']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data', 2)
            ->where('filters.search', 'Groceries')
        );
    }

    public function test_it_searches_by_subcategory_name_and_surfaces_parent_category(): void
    {
        $beverages = Category::create(['name' => 'Beverages', 'slug' => 'beverages']);
        $energyDrinks = Category::create(['name' => 'Energy Drinks', 'slug' => 'energy-drinks', 'parent_id' => $beverages->id]);
        $fashion = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);

        // Searching for "Energy Drinks" should find both the subcategory and its parent "Beverages"
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.categories.index', ['search' => 'Energy Drinks']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data', 2)
            ->where('filters.search', 'Energy Drinks')
        );
    }

    public function test_it_returns_empty_when_no_category_matches_search(): void
    {
        Category::create(['name' => 'Books', 'slug' => 'books']);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.categories.index', ['search' => 'NonExistentCategoryXYZ']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data', 0)
        );
    }
}

