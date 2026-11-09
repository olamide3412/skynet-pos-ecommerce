<?php

namespace App\Services\PosIntegration;

use App\Models\PosConnection;
use App\Models\PosSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PosApiClient
{
    protected ?PosConnection $connection;
    protected string $baseUrl;
    protected ?string $apiSecret;
    protected string $clientId;
    protected int $timeout;

    public function __construct(?PosConnection $connection = null, int $timeout = 45)
    {
        $this->connection = $connection ?: PosConnection::active();
        $this->timeout = $timeout;

        if ($this->connection) {
            $this->baseUrl = rtrim($this->connection->pos_base_url, '/');
            $this->clientId = (string) $this->connection->client_id;
            $this->apiSecret = $this->connection->getDecryptedApiSecret();
        } else {
            $this->baseUrl = '';
            $this->clientId = '';
            $this->apiSecret = null;
        }
    }

    /**
     * Check if a valid connection record exists.
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->clientId) && !empty($this->apiSecret);
    }

    /**
     * Test the API connection and branch credential validity.
     */
    public function testConnection(): array
    {
        $this->ensureConfigured();

        // Test versioned ping or catalog endpoint
        $candidatePaths = str_contains($this->baseUrl, '/api/commerce/v1')
            ? ['/ping', '/catalog?per_page=1']
            : ['/api/commerce/v1/ping', '/api/commerce/v1/catalog?per_page=1'];

        $lastError = 'Connection failed';
        foreach ($candidatePaths as $path) {
            try {
                $response = $this->sendRequest('GET', $path);

                if ($response->successful()) {
                    $data = $response->json();
                    if ($this->connection) {
                        $this->connection->update([
                            'is_connected'               => true,
                            'last_successful_request_at' => now(),
                            'last_error_at'              => null,
                            'last_error_message'         => null,
                        ]);
                    }
                    PosSyncLog::log('connection_test', 'success', 'Connection test successful', null, $data);
                    return ['status' => true, 'data' => $data];
                }

                if ($response->status() === 401) {
                    $errorMsg = 'Could not authenticate with the provided credentials. The API secret/token is invalid or expired.';
                    if ($this->connection) {
                        $this->connection->update([
                            'is_connected'       => false,
                            'last_error_at'      => now(),
                            'last_error_message' => $errorMsg,
                        ]);
                    }
                    PosSyncLog::log('connection_test', 'failed', $errorMsg, null, ['status' => 401]);
                    return ['status' => false, 'message' => $errorMsg];
                }

                if ($response->status() === 403) {
                    $errCode = $response->json('error_code');
                    $msg = $response->json('message') ?? 'Access forbidden by POS API.';
                    if ($errCode === 'PLAN_FEATURE_DISABLED') {
                        $msg = 'Ecommerce integration feature is not enabled for your organization plan on the POS.';
                    } elseif ($errCode === 'COMMERCE_INTEGRATION_INACTIVE') {
                        $msg = 'The POS ecommerce integration is currently deactivated in the POS branch settings.';
                    }
                    if ($this->connection) {
                        $this->connection->update([
                            'is_connected'       => false,
                            'last_error_at'      => now(),
                            'last_error_message' => $msg,
                        ]);
                    }
                    PosSyncLog::log('connection_test', 'failed', $msg, null, ['status' => 403]);
                    return ['status' => false, 'message' => $msg];
                }

                if ($response->status() === 404) {
                    // Endpoint route not found, attempt next candidate
                    $lastError = "Endpoint '{$path}' not found on POS server (HTTP 404).";
                    continue;
                }

                $errorMsg = $response->json('message') ?? ('HTTP ' . $response->status() . ': ' . $response->body());
                $lastError = $errorMsg;
            } catch (\Throwable $e) {
                $lastError = "Could not reach the POS API at '{$this->baseUrl}': " . $e->getMessage();
                break;
            }
        }

        if ($this->connection) {
            $this->connection->update([
                'is_connected'       => false,
                'last_error_at'      => now(),
                'last_error_message' => $lastError,
            ]);
        }
        PosSyncLog::log('connection_test', 'failed', $lastError);
        return ['status' => false, 'message' => $lastError];
    }

    /**
     * List branch catalog items with optional incremental timestamp.
     */
    public function listItems(?string $since = null, int $page = 1, int $perPage = 100): array
    {
        $this->ensureConfigured();

        $params = [
            'page'     => $page,
            'per_page' => $perPage,
        ];
        if ($since) {
            $params['since'] = $since;
        }

        $response = $this->sendRequest('GET', '/catalog', $params);

        if (!$response->successful()) {
            throw new PosIntegrationException(
                "Failed to fetch POS catalog: " . ($response->json('message') ?? $response->body()),
                "Unable to retrieve catalog from POS at this time."
            );
        }

        $this->recordSuccess();
        return $response->json();
    }

    /**
     * List branch brands from POS (optional endpoint; returns empty array with unsupported=true on 404).
     */
    public function listBrands(?string $since = null, int $page = 1, int $perPage = 100): array
    {
        $this->ensureConfigured();

        $params = [
            'page'     => $page,
            'per_page' => $perPage,
        ];
        if ($since) {
            $params['since'] = $since;
        }

        try {
            $response = $this->sendRequest('GET', '/brands', $params);

            if ($response->status() === 404) {
                return [
                    'data'        => [],
                    'unsupported' => true,
                ];
            }

            if (!$response->successful()) {
                return [
                    'data'        => [],
                    'unsupported' => true,
                ];
            }

            $this->recordSuccess();
            return $response->json() ?? ['data' => []];
        } catch (\Throwable) {
            return [
                'data'        => [],
                'unsupported' => true,
            ];
        }
    }

    /**
     * Get a single POS item with all selling units.
     */
    public function getItem(int|string $posItemId): array
    {
        $this->ensureConfigured();

        $response = $this->sendRequest('GET', "/catalog/{$posItemId}");

        if (!$response->successful()) {
            throw new PosIntegrationException(
                "Failed to fetch POS item #{$posItemId}: " . ($response->json('message') ?? $response->body()),
                "Unable to retrieve product details from POS."
            );
        }

        $this->recordSuccess();
        return $response->json();
    }

    /**
     * Get real-time effective stock availability for mapped POS items.
     * Automatically chunks large ID lists into batches of 100 to prevent POS API timeouts.
     */
    public function getAvailability(array $posItemIds = []): array
    {
        $this->ensureConfigured();

        $uniqueIds = array_values(array_unique($posItemIds));
        if (empty($uniqueIds)) {
            return ['status' => true, 'data' => []];
        }

        // If more than 100 items, process in chunks of 100 and merge results
        if (count($uniqueIds) > 100) {
            $mergedData = [];
            foreach (array_chunk($uniqueIds, 100) as $chunkIds) {
                $chunkResp = $this->getAvailability($chunkIds);
                $chunkData = $chunkResp['data'] ?? [];
                foreach ($chunkData as $k => $v) {
                    if (is_array($v) && isset($v['pos_item_id'])) {
                        $mergedData[$v['pos_item_id']] = $v;
                    } elseif (is_array($v) && isset($v['id'])) {
                        $mergedData[$v['id']] = $v;
                    } else {
                        $mergedData[$k] = $v;
                    }
                }
            }
            return ['status' => true, 'data' => $mergedData];
        }

        $response = $this->sendRequest('POST', '/availability', [
            'item_ids' => $uniqueIds,
        ]);

        if ($response->successful()) {
            $this->recordSuccess();
            return $response->json();
        }

        // Fallback: If bulk availability endpoint is not available (HTTP 404), query GET /catalog/{id}/stock
        if ($response->status() === 404) {
            $data = [];
            foreach ($posItemIds as $id) {
                try {
                    $stockResp = $this->sendRequest('GET', "/catalog/{$id}/stock");
                    if ($stockResp->successful()) {
                        $stockJson = $stockResp->json();
                        $qty = null;

                        if (isset($stockJson['available_quantity']) && is_numeric($stockJson['available_quantity'])) {
                            $qty = (float) $stockJson['available_quantity'];
                        } elseif (isset($stockJson['quantity']) && is_numeric($stockJson['quantity'])) {
                            $qty = (float) $stockJson['quantity'];
                        } elseif (isset($stockJson['stock']) && is_numeric($stockJson['stock'])) {
                            $qty = (float) $stockJson['stock'];
                        } elseif (isset($stockJson['data'])) {
                            if (is_numeric($stockJson['data'])) {
                                $qty = (float) $stockJson['data'];
                            } elseif (is_array($stockJson['data'])) {
                                $d = $stockJson['data'];
                                if (isset($d['available_quantity']) && is_numeric($d['available_quantity'])) {
                                    $qty = (float) $d['available_quantity'];
                                } elseif (isset($d['quantity']) && is_numeric($d['quantity'])) {
                                    $qty = (float) $d['quantity'];
                                } elseif (isset($d['stock']) && is_numeric($d['stock'])) {
                                    $qty = (float) $d['stock'];
                                }
                            }
                        }

                        $data[$id] = $qty !== null ? $qty : 0.0;
                    }
                } catch (\Throwable $e) {
                    // skip individual item error
                }
            }
            $this->recordSuccess();
            return ['status' => true, 'data' => $data];
        }

        throw new PosIntegrationException(
            "Failed to fetch availability from POS: " . ($response->json('message') ?? $response->body()),
            "Unable to verify live stock availability from POS."
        );
    }

    /**
     * Create an authoritative POS reservation before payment.
     * Items format: [ ['pos_item_id' => 123, 'pos_item_unit_id' => null, 'quantity' => 2], ... ]
     */
    public function createReservation(string $externalReference, array $items, int $expiryMinutes = 30): array
    {
        $this->ensureConfigured();

        $payload = [
            'external_reference' => $externalReference,
            'expiry_minutes'     => $expiryMinutes,
            'items'              => $items,
        ];

        $response = $this->sendRequest('POST', '/reservations', $payload);

        if ($response->successful()) {
            $data = $response->json();
            $this->recordSuccess();
            PosSyncLog::log('reservation_create', 'success', "Reserved stock for reference {$externalReference}", $externalReference, $data);
            return ['status' => true, 'data' => $data];
        }

        $errorMsg = $response->json('message') ?? 'Stock reservation rejected by POS';
        PosSyncLog::log('reservation_create', 'failed', $errorMsg, $externalReference, ['payload' => $payload, 'response' => $response->json()]);

        return [
            'status'  => false,
            'message' => $errorMsg,
            'code'    => $response->status(),
            'errors'  => $response->json('errors') ?? [],
        ];
    }

    /**
     * Authoritatively confirm an existing POS reservation after verified payment or POD acceptance.
     */
    public function confirmReservation(string $externalReference): array
    {
        $this->ensureConfigured();

        $response = $this->sendRequest('POST', "/reservations/{$externalReference}/confirm");

        if ($response->successful()) {
            $data = $response->json();
            $this->recordSuccess();
            PosSyncLog::log('reservation_confirm', 'success', "Confirmed reservation {$externalReference}", $externalReference, $data);
            return ['status' => true, 'data' => $data];
        }

        $errorMsg = $response->json('message') ?? 'Failed to confirm POS reservation';
        PosSyncLog::log('reservation_confirm', 'failed', $errorMsg, $externalReference, ['status' => $response->status(), 'response' => $response->json()]);

        return ['status' => false, 'message' => $errorMsg];
    }

    /**
     * Explicitly release an existing POS reservation (payment cancelled, abandoned, order rejected).
     */
    public function releaseReservation(string $externalReference): array
    {
        $this->ensureConfigured();

        $response = $this->sendRequest('POST', "/reservations/{$externalReference}/release");

        if ($response->successful()) {
            $data = $response->json();
            $this->recordSuccess();
            PosSyncLog::log('reservation_release', 'success', "Released reservation {$externalReference}", $externalReference, $data);
            return ['status' => true, 'data' => $data];
        }

        $errorMsg = $response->json('message') ?? 'Failed to release POS reservation';
        PosSyncLog::log('reservation_release', 'failed', $errorMsg, $externalReference, ['status' => $response->status()]);

        return ['status' => false, 'message' => $errorMsg];
    }

    /**
     * Retrieve status of a reservation.
     */
    public function getReservation(string $externalReference): ?array
    {
        $this->ensureConfigured();

        $response = $this->sendRequest('GET', "/reservations/{$externalReference}");

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Register product and variant mappings with the POS deletion-protection registry.
     */
    public function registerMappings(array $mappings): bool
    {
        if (!$this->isConfigured() || empty($mappings)) {
            return false;
        }

        try {
            $response = $this->sendRequest('POST', '/mappings/register', [
                'mappings' => $mappings,
            ]);

            if ($response->successful()) {
                $this->recordSuccess();
                $count = count($mappings);
                PosSyncLog::log('mapping_register', 'success', "Successfully registered {$count} product mapping(s) with POS deletion protection.");
                return true;
            }

            // If the POS API does not have this endpoint (HTTP 404), silently skip
            if ($response->status() === 404) {
                return true;
            }

            $errDetail = $response->json('message')
                ?? ('HTTP ' . $response->status() . ($response->body() ? ': ' . \Illuminate\Support\Str::limit(strip_tags($response->body()), 120) : ''));

            PosSyncLog::log('mapping_register', 'failed', "Failed to register mappings with POS ({$errDetail})", null, [
                'status'   => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            // Silently ignore if POS cannot be reached
            return true;
        }
    }

    /**
     * Unregister mapping when a product is unlinked, deleted, or converted to Ecommerce Only.
     */
    public function unregisterMappings(array $posItemIds): bool
    {
        if (!$this->isConfigured() || empty($posItemIds)) {
            return false;
        }

        try {
            $response = $this->sendRequest('POST', '/mappings/unregister', [
                'pos_item_ids' => array_values(array_unique($posItemIds)),
            ]);

            if ($response->successful()) {
                $this->recordSuccess();
                $count = count($posItemIds);
                PosSyncLog::log('mapping_unregister', 'success', "Unregistered {$count} mapping(s) from POS deletion protection.");
                return true;
            }

            return $response->status() === 404;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Full mapping reconciliation to repair registry drift.
     * Chunks large mapping sets to avoid POS request timeouts.
     */
    public function reconcileMappings(array $activeMappings): array
    {
        $this->ensureConfigured();

        try {
            $chunks = array_chunk($activeMappings, 200);
            $firstChunk = $chunks[0] ?? [];

            $response = $this->sendRequest('POST', '/mappings/reconcile', [
                'mappings' => $firstChunk,
            ]);

            if ($response->successful()) {
                // Register any remaining chunks beyond the first 200
                for ($i = 1; $i < count($chunks); $i++) {
                    $this->registerMappings($chunks[$i]);
                }

                $this->recordSuccess();
                $data = $response->json();
                $count = count($activeMappings);
                PosSyncLog::log('mapping_reconcile', 'success', "Successfully reconciled {$count} product mapping(s) with POS deletion protection.", null, $data);
                return [
                    'status'     => true,
                    'success'    => true,
                    'reconciled' => $count,
                    'data'       => $data,
                ];
            }

            $errDetail = $response->json('message') ?? ('HTTP ' . $response->status() . ': ' . \Illuminate\Support\Str::limit(strip_tags($response->body()), 120));
            PosSyncLog::log('mapping_reconcile', 'failed', "Failed to reconcile mappings with POS: {$errDetail}", null, [
                'status'   => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            return ['status' => false, 'success' => false, 'message' => $errDetail];
        } catch (\Throwable $e) {
            return ['status' => false, 'success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send authenticated HTTP request to POS API with support for versioned API endpoints.
     */
    protected function sendRequest(string $method, string $path, array $data = [])
    {
        $baseUrl = rtrim($this->baseUrl, '/');

        // Check if baseUrl specifies an API version root (e.g. http://127.0.0.1:8000/api/commerce/v1 or /api/v1)
        if (preg_match('#/api/(?:commerce/v\d+|v\d+(?:/pos-integration)?|pos-integration)#i', $baseUrl)) {
            // Strip redundant leading /api/pos-integration or /api/commerce/v1 from relative path
            $cleanPath = preg_replace('#^/api/(?:pos-integration|commerce/v\d+|v\d+)#i', '', $path);
            $url = $baseUrl . '/' . ltrim($cleanPath, '/');
        } elseif (str_starts_with($path, '/api/')) {
            if (preg_match('#^(https?://[^/]+)#i', $baseUrl, $matches)) {
                $url = $matches[1] . $path;
            } else {
                $url = $baseUrl . $path;
            }
        } else {
            $url = $baseUrl . '/' . ltrim($path, '/');
        }

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$this->clientId}:{$timestamp}", $this->apiSecret ?? '');

        $headers = [
            'Accept'           => 'application/json',
            'X-Client-Id'      => $this->clientId,
            'X-Timestamp'      => $timestamp,
            'X-Signature'      => $signature,
            'X-Integration-Id' => $this->connection->pos_integration_id ?? '',
            'X-Branch-Id'      => (string) ($this->connection->pos_branch_id ?? ''),
        ];

        if (!empty($this->apiSecret)) {
            $headers['Authorization']  = 'Bearer ' . $this->apiSecret;
            $headers['X-Commerce-Key'] = $this->apiSecret;
        }

        $client = Http::timeout($this->timeout)->withHeaders($headers);

        try {
            $response = (strtoupper($method) === 'GET')
                ? $client->get($url, $data)
                : $client->post($url, $data);

            if ($response->status() === 401) {
                if ($this->connection) {
                    $this->connection->update([
                        'is_connected'       => false,
                        'last_error_at'      => now(),
                        'last_error_message' => 'Authentication failed — credentials may have been rotated. Re-enter the new credentials below.',
                    ]);
                }
            }

            return $response;
        } catch (\Throwable $e) {
            if ($this->connection) {
                $isTimeout = str_contains($e->getMessage(), 'cURL error 28') || str_contains(strtolower($e->getMessage()), 'timed out');
                $this->connection->update([
                    'is_connected'       => $isTimeout ? $this->connection->is_connected : false,
                    'last_error_at'      => now(),
                    'last_error_message' => 'Could not reach POS API: ' . $e->getMessage(),
                ]);
            }
            Log::error("POS API request failed [{$method} {$url}]: " . $e->getMessage());
            throw new PosIntegrationException(
                "Communication with POS API failed: " . $e->getMessage(),
                "Inventory service is currently unavailable. Please try again shortly.",
                0,
                ['method' => $method, 'url' => $url],
                $e
            );
        }
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new PosIntegrationException(
                "POS API is not configured or connection credentials are missing.",
                "The store is currently not linked to a POS branch inventory authority."
            );
        }
    }

    protected function recordSuccess(): void
    {
        if ($this->connection) {
            $this->connection->update([
                'is_connected'               => true,
                'last_successful_request_at' => now(),
                'last_error_at'              => null,
                'last_error_message'         => null,
            ]);
        }
    }
}
