<?php

namespace App\Services\PosIntegration;

use App\Models\PosConnection;
use App\Models\PosSyncLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class PosConnectionService
{
    protected PosApiClient $apiClient;

    public function __construct(?PosApiClient $apiClient = null)
    {
        $this->apiClient = $apiClient ?: new PosApiClient();
    }

    /**
     * Get read-only connection info for admin presentation.
     */
    public function getConnectionSummary(): array
    {
        $conn = PosConnection::active();

        if (!$conn) {
            return [
                'is_configured'              => false,
                'is_connected'               => false,
                'pos_branch_name'            => null,
                'pos_branch_id'              => null,
                'pos_base_url'               => null,
                'client_id'                  => null,
                'connected_at'               => null,
                'last_successful_request_at' => null,
                'last_catalog_sync_at'       => null,
                'last_inventory_sync_at'     => null,
                'last_error_at'              => null,
                'last_error_message'         => null,
                'webhook_url'                => url('/api/webhooks/pos-inventory'),
            ];
        }

        return [
            'is_configured'              => !empty($conn->pos_base_url) && !empty($conn->client_id) && !empty($conn->encrypted_api_secret),
            'is_connected'               => (bool) $conn->is_connected,
            'pos_branch_name'            => $conn->pos_branch_name,
            'pos_branch_id'              => $conn->pos_branch_id,
            'pos_base_url'               => $conn->pos_base_url,
            'client_id'                  => $conn->client_id,
            'connected_at'               => $conn->connected_at?->toIso8601String(),
            'last_successful_request_at' => $conn->last_successful_request_at?->toIso8601String(),
            'last_catalog_sync_at'       => $conn->last_catalog_sync_at?->toIso8601String(),
            'last_inventory_sync_at'     => $conn->last_inventory_sync_at?->toIso8601String(),
            'last_error_at'              => $conn->last_error_at?->toIso8601String(),
            'last_error_message'         => $conn->last_error_message,
            'webhook_url'                => url('/api/webhooks/pos-inventory'),
        ];
    }

    /**
     * Save pairing credentials and immediately perform authenticated test request to POS API.
     * Per Section 2.2 of AGENT_README_ECOMMERCE_CONNECTION_FIX.md.
     */
    public function saveAndTestConnection(string $posBaseUrl, string $clientId, string $apiSecret): array
    {
        $cleanBaseUrl = rtrim(trim($posBaseUrl), '/');
        $cleanClientId = trim($clientId);
        $cleanApiSecret = trim($apiSecret);

        if (empty($cleanBaseUrl) || empty($cleanClientId) || empty($cleanApiSecret)) {
            return [
                'status'  => false,
                'message' => 'All three fields (POS API Base URL, Client ID, API Secret) are required.',
                'data'    => $this->getConnectionSummary(),
            ];
        }

        // 1. Save values to the single pos_connections record (encrypts secret immediately)
        $conn = PosConnection::first() ?: new PosConnection();
        $conn->pos_base_url = $cleanBaseUrl;
        $conn->client_id = $cleanClientId;
        $conn->api_secret = $cleanApiSecret;
        $conn->pos_branch_id = $conn->pos_branch_id ?: 1;
        $conn->pos_branch_name = $conn->pos_branch_name ?: 'Connected POS Branch';
        $conn->is_connected = false;
        $conn->save();

        // 2. Immediately make authenticated test request to POS API
        $apiClient = new PosApiClient($conn);
        $testResult = $apiClient->testConnection();

        // 3. On success
        if ($testResult['status']) {
            $branchId = $testResult['data']['branch_id'] ?? $conn->pos_branch_id ?? 1;
            $branchName = $testResult['data']['branch_name'] ?? ($conn->pos_branch_name !== 'Connected POS Branch' ? $conn->pos_branch_name : 'Connected POS Branch');

            $conn->update([
                'is_connected'               => true,
                'connected_at'               => now(),
                'last_successful_request_at' => now(),
                'pos_branch_id'              => $branchId,
                'pos_branch_name'            => $branchName,
                'last_error_at'              => null,
                'last_error_message'         => null,
            ]);

            PosSyncLog::log('connection_handshake', 'success', "Successfully linked and authenticated with POS branch: {$conn->pos_branch_name}");

            return [
                'status'  => true,
                'message' => "Successfully connected to POS Branch: {$conn->pos_branch_name}",
                'data'    => $this->getConnectionSummary(),
            ];
        }

        // 4. On failure: do not set is_connected = true. Store last_error_at and clear message
        $errorMessage = $testResult['message'] ?? 'Could not authenticate with the provided credentials.';
        $conn->update([
            'is_connected'       => false,
            'last_error_at'      => now(),
            'last_error_message' => $errorMessage,
        ]);

        PosSyncLog::log('connection_handshake', 'failed', "POS Connection pairing failed: {$errorMessage}");

        return [
            'status'  => false,
            'message' => $errorMessage,
            'data'    => $this->getConnectionSummary(),
        ];
    }

    /**
     * Handle branch pairing / credential assignment initiated by POS Super Admin.
     */
    public function establishConnection(array $params): array
    {
        $required = ['pos_base_url', 'client_id', 'api_secret'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return ['status' => false, 'message' => "Missing required connection parameter: {$field}"];
            }
        }

        return $this->saveAndTestConnection(
            $params['pos_base_url'],
            $params['client_id'],
            $params['api_secret']
        );
    }

    /**
     * Test connection and ping the POS branch using stored active credentials.
     */
    public function testConnection(): array
    {
        $conn = PosConnection::active();
        if (!$conn || empty($conn->pos_base_url) || empty($conn->getDecryptedApiSecret())) {
            return ['status' => false, 'message' => 'No active POS connection configured. Please enter and save connection credentials first.'];
        }

        $client = new PosApiClient($conn);
        return $client->testConnection();
    }
}
