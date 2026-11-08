<?php

namespace Tests\Feature;

use App\Models\PosConnection;
use App\Models\User;
use App\Services\PosIntegration\PosApiClient;
use App\Services\PosIntegration\PosConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosPairingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::SuperAdministrator->value,
        ]);
    }

    public function test_pairing_form_saves_and_tests_connection_successfully(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/commerce/v1/ping' => Http::response([
                'success'        => true,
                'branch_id'      => 5,
                'branch_name'    => 'Victoria Island Hub',
                'integration_id' => 12,
            ], 200),
            'http://127.0.0.1:8000/*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.save-and-test'), [
            'pos_base_url' => 'http://127.0.0.1:8000',
            'client_id'    => 'client_sky_test',
            'api_secret'   => 'test_sanctum_secret_token_123',
        ]);

        $response->assertSessionHas('success');

        $conn = PosConnection::first();
        $this->assertNotNull($conn);
        $this->assertTrue($conn->is_connected);
        $this->assertEquals('http://127.0.0.1:8000', $conn->pos_base_url);
        $this->assertEquals('client_sky_test', $conn->client_id);
        $this->assertEquals('test_sanctum_secret_token_123', $conn->getDecryptedApiSecret());
        $this->assertNotNull($conn->connected_at);
        $this->assertNotNull($conn->last_successful_request_at);
        $this->assertNull($conn->last_error_message);
        $this->assertEquals('Victoria Island Hub', $conn->pos_branch_name);
        $this->assertEquals(5, $conn->pos_branch_id);
    }

    public function test_pairing_with_invalid_credentials_fails_and_stores_error_message(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/*' => Http::response([
                'success' => false,
                'message' => 'Unauthenticated commerce integration token.',
            ], 401),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.save-and-test'), [
            'pos_base_url' => 'http://127.0.0.1:8000',
            'client_id'    => 'client_bad',
            'api_secret'   => 'invalid_token_xyz',
        ]);

        $response->assertSessionHas('error');

        $conn = PosConnection::first();
        $this->assertNotNull($conn);
        $this->assertFalse($conn->is_connected);
        $this->assertNotNull($conn->last_error_at);
        $this->assertStringContainsString('Could not authenticate with the provided credentials', $conn->last_error_message);
    }

    public function test_unreachable_pos_url_fails_gracefully(): void
    {
        Http::fake([
            'http://invalid-pos-host.test/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.save-and-test'), [
            'pos_base_url' => 'http://invalid-pos-host.test',
            'client_id'    => 'client_sky',
            'api_secret'   => 'secret_123',
        ]);

        $response->assertSessionHas('error');

        $conn = PosConnection::first();
        $this->assertNotNull($conn);
        $this->assertFalse($conn->is_connected);
        $this->assertStringContainsString('Could not reach the POS API', $conn->last_error_message);
    }

    public function test_credential_rotation_on_pos_marks_connection_disconnected(): void
    {
        $conn = PosConnection::create([
            'pos_base_url'         => 'http://127.0.0.1:8000',
            'client_id'            => 'client_valid',
            'encrypted_api_secret' => Crypt::encryptString('old_rotated_secret'),
            'is_connected'         => true,
            'pos_branch_id'        => 1,
            'pos_branch_name'      => 'Felix Enterprise',
        ]);

        // POS responds with 401 because credentials were rotated
        Http::fake([
            'http://127.0.0.1:8000/*' => Http::response([
                'success' => false,
                'message' => 'Unauthenticated commerce integration token.',
            ], 401),
        ]);

        $apiClient = new PosApiClient($conn);
        $result = $apiClient->testConnection();

        $this->assertFalse($result['status']);

        $conn->refresh();
        $this->assertFalse($conn->is_connected);
        $this->assertNotNull($conn->last_error_at);
        $this->assertStringContainsString('Could not authenticate', $conn->last_error_message);
    }

    public function test_sync_actions_are_blocked_when_disconnected(): void
    {
        $conn = PosConnection::create([
            'pos_base_url'         => 'http://127.0.0.1:8000',
            'client_id'            => 'client_test',
            'encrypted_api_secret' => Crypt::encryptString('secret'),
            'is_connected'         => false,
            'pos_branch_id'        => 1,
            'pos_branch_name'      => 'Felix Enterprise',
        ]);

        // Sync catalog
        $resCatalog = $this->actingAs($this->admin)->post(route('admin.pos-integration.sync-catalog'));
        $resCatalog->assertSessionHas('error');

        // Sync stock
        $resStock = $this->actingAs($this->admin)->post(route('admin.pos-integration.sync-availability'));
        $resStock->assertSessionHas('error');

        // Import all
        $resImport = $this->actingAs($this->admin)->post(route('admin.pos-integration.import-all'));
        $resImport->assertSessionHas('error');
    }

    public function test_store_config_settings_page_receives_pos_connection_summary(): void
    {
        PosConnection::create([
            'pos_base_url'         => 'http://127.0.0.1:8000',
            'client_id'            => 'client_display_test',
            'encrypted_api_secret' => Crypt::encryptString('secret'),
            'is_connected'         => true,
            'pos_branch_id'        => 2,
            'pos_branch_name'      => 'Skynet Digital Hub',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.settings.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/Settings/Index')
                ->has('pos_connection')
                ->where('pos_connection.is_connected', true)
                ->where('pos_connection.pos_branch_name', 'Skynet Digital Hub')
                ->where('pos_connection.client_id', 'client_display_test')
                ->missing('pos_connection.encrypted_api_secret')
                ->missing('pos_connection.api_secret')
        );
    }
}
