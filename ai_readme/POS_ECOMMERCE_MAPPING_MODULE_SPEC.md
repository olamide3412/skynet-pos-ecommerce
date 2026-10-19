# POS Module Specification: Ecommerce Mapping & Deletion Protection Registry

## 1. Overview & Purpose

When items from the **POS system** are imported into the **Ecommerce store**, the Ecommerce store maintains the link (`pos_item_id`). 

This specification adds the **reverse mapping registry** to the POS Ecommerce Module. It gives the POS two crucial capabilities:
1. **Deletion Protection:** Prevents cashiers, inventory managers, or branch admins from permanently deleting POS inventory items that are currently active in the online store.
2. **Product Link Traceability:** Allows POS admins to see which POS items are live on Ecommerce, their linked Ecommerce Product IDs, and sync status.

---

## 2. Database Migration

Run on the **POS project**:

```bash
php artisan make:migration create_ecommerce_product_links_table
```

### Migration Code:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_product_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('pos_item_id')->index();
            $table->unsignedBigInteger('ecommerce_product_id')->index();
            $table->string('type')->default('simple'); // 'simple' or 'variant'
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Unique constraint: prevent duplicate link per branch & item
            $table->unique(['pos_item_id', 'ecommerce_product_id'], 'unique_pos_ecom_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_product_links');
    }
};
```

---

## 3. Eloquent Model

Create `app/Models/EcommerceProductLink.php` (or inside your POS Ecommerce module directory):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommerceProductLink extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_product_links';

    protected $fillable = [
        'branch_id',
        'pos_item_id',
        'ecommerce_product_id',
        'type',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'pos_item_id');
    }
}
```

---

## 4. Deletion Protection (POS Item Model / Observer)

In your POS `Item` model (e.g. `app/Models/Item.php`) or `ItemObserver`:

```php
// Add relation to Item model
public function ecommerceLinks()
{
    return $this->hasMany(EcommerceProductLink::class, 'pos_item_id');
}

public function isLinkedToEcommerce(): bool
{
    return $this->ecommerceLinks()->where('is_active', true)->exists();
}
```

In the `deleting` hook or `ItemController::destroy`:
```php
// Prevent accidental deletion if mapped to active online product
if ($item->isLinkedToEcommerce()) {
    return response()->json([
        'status'  => false,
        'message' => "Cannot delete item '{$item->name}'. It is currently linked to an active product in your online Ecommerce store. Please unlink it from Ecommerce first."
    ], 422);
}
```

---

## 5. API Controller

Create `EcommerceMappingController.php` in the POS Ecommerce module:

```php
<?php

namespace App\Http\Controllers\Api\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProductLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceMappingController extends Controller
{
    /**
     * POST /api/pos-integration/mappings/register
     * Also accessible via: POST /mappings/register
     *
     * Registers item-to-ecommerce links for deletion protection.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mappings'                          => 'required|array|min:1',
            'mappings.*.pos_item_id'            => 'required|integer',
            'mappings.*.ecommerce_product_id'   => 'required',
            'mappings.*.type'                   => 'nullable|string',
        ]);

        $branchId = $request->header('X-Branch-Id') ?: auth()->user()?->branch_id;
        $registered = 0;

        foreach ($validated['mappings'] as $mapping) {
            EcommerceProductLink::updateOrCreate(
                [
                    'pos_item_id'          => $mapping['pos_item_id'],
                    'ecommerce_product_id' => $mapping['ecommerce_product_id'],
                ],
                [
                    'branch_id'      => $branchId,
                    'type'           => $mapping['type'] ?? 'simple',
                    'is_active'      => true,
                    'last_synced_at' => now(),
                ]
            );
            $registered++;
        }

        return response()->json([
            'success'    => true,
            'message'    => "Successfully registered {$registered} mappings with POS.",
            'registered' => $registered,
        ]);
    }

    /**
     * POST /api/pos-integration/mappings/unregister
     * Also accessible via: POST /mappings/unregister
     *
     * Unregisters mappings when an item is deleted or unlinked on Ecommerce.
     */
    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pos_item_ids'   => 'required|array|min:1',
            'pos_item_ids.*' => 'integer',
        ]);

        $deleted = EcommerceProductLink::whereIn('pos_item_id', $validated['pos_item_ids'])->delete();

        return response()->json([
            'success'      => true,
            'message'      => "Unregistered {$deleted} item mappings.",
            'unregistered' => $deleted,
        ]);
    }

    /**
     * POST /api/pos-integration/mappings/reconcile
     *
     * Reconciles all active mappings to repair any drift.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mappings'                        => 'present|array',
            'mappings.*.pos_item_id'          => 'required|integer',
            'mappings.*.ecommerce_product_id' => 'required',
            'mappings.*.type'                 => 'nullable|string',
        ]);

        $branchId = $request->header('X-Branch-Id') ?: auth()->user()?->branch_id;
        $activeItemIds = collect($validated['mappings'])->pluck('pos_item_id')->toArray();

        // Deactivate links not in the active list
        EcommerceProductLink::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('pos_item_id', $activeItemIds)
            ->delete();

        // Upsert active ones
        foreach ($validated['mappings'] as $mapping) {
            EcommerceProductLink::updateOrCreate(
                [
                    'pos_item_id'          => $mapping['pos_item_id'],
                    'ecommerce_product_id' => $mapping['ecommerce_product_id'],
                ],
                [
                    'branch_id'      => $branchId,
                    'type'           => $mapping['type'] ?? 'simple',
                    'is_active'      => true,
                    'last_synced_at' => now(),
                ]
            );
        }

        return response()->json([
            'success'    => true,
            'reconciled' => count($validated['mappings']),
        ]);
    }
}
```

---

## 6. Route Registration (POS API Routes)

In your POS module routes file (e.g. `routes/api.php` or `Modules/Ecommerce/routes/api.php`), register the mapping endpoints inside the authenticated commerce group:

```php
use App\Http\Controllers\Api\Ecommerce\EcommerceMappingController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Existing POS Commerce routes:
    // GET  /ping
    // GET  /catalog
    // GET  /catalog/{id}/stock
    // POST /reservations
    // POST /reservations/confirm
    // POST /reservations/release

    // Mapping Registry Endpoints:
    Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
    Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
    Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);

    // Alias routes for /api/pos-integration/mappings/*
    Route::prefix('pos-integration')->group(function () {
        Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
        Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
        Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);
    });
});
```

---

## 7. Update REST API Endpoints Quick Reference Card

In the POS UI Quick Reference blade/Vue view, add the new endpoints:

```
POST /mappings/register      Register deletion protection links
POST /mappings/unregister    Remove deletion protection
POST /mappings/reconcile     Sync active mappings
```

---

## 8. Summary of Flow

```
+------------------+                    +---------------------+
| Ecommerce Store  |                    |     POS System      |
+------------------+                    +---------------------+
        |                                          |
        | 1. GET /catalog                          |
        |----------------------------------------->|
        |    (Fetches items for import)            |
        |<-----------------------------------------|
        |                                          |
        | 2. Import product to ecommerce DB        |
        |                                          |
        | 3. POST /mappings/register               |
        |----------------------------------------->|
        |    payload: [{pos_item_id: 667, ...}]    | 4. Saves to `ecommerce_product_links`
        |                                          | 5. Locks item #667 from deletion
        |<-----------------------------------------|
        |    200 OK (Registered)                   |
```
