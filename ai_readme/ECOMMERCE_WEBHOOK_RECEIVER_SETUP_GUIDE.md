# Ecommerce Webhook Receiver Setup Guide (Skynet POS Integration)

This guide provides everything you need to **copy and paste into your standalone Ecommerce application** to receive real-time inventory and price update notifications from Skynet Digital POS.

---

## 1. What This Does

When an item is sold, stock is adjusted, or prices change in the POS physical store, the POS sends an instant HTTP `POST` webhook to your ecommerce store. Your ecommerce application receives this payload and immediately updates its online stock count and pricing, preventing overselling without waiting for scheduled cron syncs.

---

## 2. Your Webhook URL (What to paste in the POS)

### Development (Local Machine)
If your ecommerce app is running locally (e.g., on port 8002):
- **Local direct URL:**  
  `http://127.0.0.1:8002/api/webhooks/pos-inventory`  
  *(or `http://localhost:8002/api/webhooks/pos-inventory`)*
- **If using Ngrok / Expose (tunnel):**  
  `https://your-subdomain.ngrok-free.app/api/webhooks/pos-inventory`

### Production (Live Website)
- `https://your-ecommerce-domain.com/api/webhooks/pos-inventory`

### Where to paste this link in POS:
1. Open the POS Admin -> **Branches**.
2. Select your branch and click on the **"Ecommerce"** tab.
3. Click **"Edit Settings"** (or during initial integration setup).
4. Paste the URL into the **Webhook Notification URL (Optional)** field.
5. Click **"Save Settings"**.
6. Click the **"⚡ Send Test Webhook"** button to verify the connection!

---

## 3. Implementation in Your Ecommerce App (Copy & Paste)

### Step 1: Add Route in `routes/api.php`
Open `routes/api.php` in your **Ecommerce project** and add:

```php
use App\Http\Controllers\Api\PosWebhookController;
use Illuminate\Support\Facades\Route;

// POS Real-time Inventory & Price Webhook
Route::post('/webhooks/pos-inventory', [PosWebhookController::class, 'handle']);
```

*(Note: Routes in `routes/api.php` are stateless and automatically bypass Laravel CSRF protection).*

---

### Step 2: Create Webhook Controller
Create the file `app/Http/Controllers/Api/PosWebhookController.php` in your **Ecommerce project** and paste the code below:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product; // Adjust to your product model name
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PosWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from Skynet POS.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = $request->header('X-Skynet-Event', $request->input('event'));
        $branchId = $request->header('X-Branch-Id', $request->input('branch_id'));

        Log::info("POS Webhook received: [{$event}] for branch [{$branchId}]", $request->all());

        // 1. Handle Connection Verification / Test Ping
        if ($event === 'test.ping') {
            return response()->json([
                'success' => true,
                'message' => 'Test webhook successfully received and acknowledged by Ecommerce server.',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        }

        // 2. Handle Real-time Inventory & Price Updates
        if ($event === 'inventory.updated' || $event === 'item.price_updated') {
            $data = $request->input('data', []);
            $posItemId = $data['pos_item_id'] ?? null;
            $barcode = $data['barcode_number'] ?? null;
            $availableQty = $data['available_qty'] ?? null;
            $price = $data['price'] ?? null;

            if (!$posItemId && !$barcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing pos_item_id or barcode identifier.',
                ], 422);
            }

            // Find matching product in your ecommerce database
            $product = null;

            // Strategy A: Match by pos_item_id column if present
            if ($posItemId && \Illuminate\Support\Facades\Schema::hasColumn('products', 'pos_item_id')) {
                $product = Product::where('pos_item_id', $posItemId)->first();
            }

            // Strategy B: Match by barcode or SKU
            if (!$product && $barcode) {
                $product = Product::where('barcode', $barcode)
                    ->orWhere('sku', $barcode)
                    ->first();
            }

            // Strategy C: Match by reverse mapping registry if present
            if (!$product && $posItemId && class_exists(\App\Models\EcommerceProductLink::class)) {
                $link = \App\Models\EcommerceProductLink::where('pos_item_id', $posItemId)->first();
                if ($link) {
                    $product = Product::find($link->ecommerce_product_id);
                }
            }

            if ($product) {
                // Update product inventory & status
                if ($availableQty !== null) {
                    $product->stock_quantity = (int) $availableQty;
                    if (isset($product->is_in_stock)) {
                        $product->is_in_stock = (int) $availableQty > 0;
                    }
                }

                // Optionally update price if configured
                if ($price !== null && isset($product->price)) {
                    $product->price = (float) $price;
                }

                $product->save();

                Log::info("Updated product #{$product->id} stock to {$availableQty} from POS item #{$posItemId}");

                return response()->json([
                    'success' => true,
                    'message' => "Product #{$product->id} updated successfully.",
                    'product_id' => $product->id,
                    'stock_quantity' => $product->stock_quantity,
                ], 200);
            }

            Log::warning("POS item #{$posItemId} (Barcode: {$barcode}) not found in ecommerce catalog.");

            return response()->json([
                'success' => true,
                'message' => 'Webhook received, but matching product was not found in ecommerce store.',
            ], 200);
        }

        // Acknowledge any other events
        return response()->json([
            'success' => true,
            'message' => "Event '{$event}' acknowledged.",
        ], 200);
    }
}
```

---

## 4. Sample Payload Sent by POS

When inventory changes in POS, your webhook will receive:

### Headers
```http
POST /api/webhooks/pos-inventory HTTP/1.1
Host: 127.0.0.1:8002
Content-Type: application/json
Accept: application/json
User-Agent: SkynetPOS-Webhook/1.0
X-Skynet-Event: inventory.updated
X-Branch-Id: 2807
```

### JSON Body
```json
{
  "event": "inventory.updated",
  "timestamp": "2026-10-19T23:30:00+01:00",
  "branch_id": 2807,
  "data": {
    "pos_item_id": 667,
    "item_name": "Organic Honey 500g",
    "barcode_number": "8901234567890",
    "available_qty": 45,
    "front_store_qty": 50,
    "active_holds": 5,
    "price": 4500.00,
    "buy_price": 3000.00,
    "is_active": true,
    "updated_at": "2026-10-19T23:30:00+01:00"
  }
}
```

---

## 5. Verification Checklist

1. [ ] Start your ecommerce app:
   ```bash
   php artisan serve --port=8002
   ```
2. [ ] In your POS admin (e.g. `http://localhost:8000`), open **Branches** -> **Ecommerce tab**.
3. [ ] Enter `http://127.0.0.1:8002/api/webhooks/pos-inventory` into **Webhook Notification URL**.
4. [ ] Click **Save Settings**.
5. [ ] Click **⚡ Send Test Webhook**.
6. [ ] You will see a green success alert in POS:  
   `"Webhook test successful! Your ecommerce server responded with HTTP 200."`
