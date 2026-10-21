<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_theme_settings_page(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.themes.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Themes/Index')
            ->has('theme_settings')
        );
    }

    public function test_admin_can_update_theme_palette_and_dark_mode(): void
    {
        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $payload = [
            'theme_preset'               => 'emerald',
            'theme_primary_color'        => '#10b981',
            'theme_secondary_color'      => '#f59e0b',
            'theme_dark_mode_preset'     => 'tint',
            'theme_dark_mode_text_color' => '#C084FC',
            'theme_dark_mode_tint_color' => '#C084FC',
        ];

        $response = $this->actingAs($admin, 'web')->put(route('admin.themes.update'), $payload);

        $response->assertRedirect();
        $this->assertEquals('emerald', StoreSetting::get('theme_preset'));
        $this->assertEquals('#10b981', StoreSetting::get('theme_primary_color'));
        $this->assertEquals('#f59e0b', StoreSetting::get('theme_secondary_color'));
        $this->assertEquals('tint', StoreSetting::get('theme_dark_mode_preset'));
        $this->assertEquals('#C084FC', StoreSetting::get('theme_dark_mode_text_color'));
    }
}
