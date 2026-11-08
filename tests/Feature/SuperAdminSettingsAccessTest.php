<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $standardAdmin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => 'super administrator',
        ]);

        $this->standardAdmin = User::factory()->create([
            'role' => 'administrator',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
        ]);
    }

    public function test_super_admin_can_access_all_settings_pages(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.settings.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.backups.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.ai-settings.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.themes.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.pos-connection.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.shipping-zones.index'))
            ->assertOk();
    }

    public function test_standard_admin_cannot_access_settings_pages(): void
    {
        $this->actingAs($this->standardAdmin)
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->get(route('admin.backups.index'))
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->get(route('admin.ai-settings.index'))
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->get(route('admin.themes.index'))
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->get(route('admin.pos-connection.index'))
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->get(route('admin.shipping-zones.index'))
            ->assertForbidden();
    }

    public function test_standard_admin_cannot_update_settings(): void
    {
        $this->actingAs($this->standardAdmin)
            ->put(route('admin.settings.update'), ['company_name' => 'Hacked Name'])
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->post(route('admin.backups.store'))
            ->assertForbidden();

        $this->actingAs($this->standardAdmin)
            ->put(route('admin.ai-settings.update'), ['ai_enabled' => true])
            ->assertForbidden();
    }

    public function test_staff_cannot_access_settings(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->get(route('admin.backups.index'))
            ->assertForbidden();
    }
}

