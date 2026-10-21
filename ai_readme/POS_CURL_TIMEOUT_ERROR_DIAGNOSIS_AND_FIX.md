# POS cURL Error 28 Timeout Diagnosis & Complete Fix Guide

## 1. Problem Summary
When the Ecommerce store attempts to confirm a reservation:
```text
POST http://127.0.0.1:8000/api/pos-integration/reservations/{uuid}/confirm
```
The request fails after exactly 10 seconds with:
```text
Connection / Authentication Error
Could not reach POS API: cURL error 28: Operation timed out after 10006 milliseconds with 0 bytes received for http://127.0.0.1:8000/api/pos-integration/reservations/597f5721-fc78-438d-906c-13f8ea318233/confirm
```

---

## 2. Root Cause Analysis

### Root Cause A: Local Multi-App Deadlock (Synchronous Webhook Callback) — **Most Common**
When Ecommerce confirms a reservation:
1. **Ecommerce (`:8002`)** sends a synchronous HTTP request to **POS (`:8000`)** `/api/pos-integration/reservations/{id}/confirm`.
2. **POS (`:8000`)** updates physical item stock in its database.
3. Because item stock changed, **POS (`CommerceWebhookDispatcher.php` or `ItemObserver`)** immediately fires a webhook back to Ecommerce (`http://127.0.0.1:8002/api/webhooks/pos-inventory`).
4. **The Deadlock:**
   - Both Ecommerce and POS are running on local dev using `php artisan serve`.
   - By default on Windows/PHP, `php artisan serve` uses a **single-threaded worker**.
   - Ecommerce's worker on port 8002 is **busy waiting** for POS on port 8000 to finish the `/confirm` call.
   - When POS tries to make an HTTP request back to Ecommerce on port 8002, port 8002 cannot respond because it is waiting for POS!
   - POS hangs waiting for Ecommerce to accept the webhook. Ecommerce hangs waiting for POS to finish `/confirm`.
   - After 10 seconds, Ecommerce's cURL client gives up: `cURL error 28: Operation timed out after 10006 milliseconds`.

---

### Root Cause B: Single-Threaded `php artisan serve` Worker Starvation
If multiple requests are hitting POS (like stock polling or browser requests) and `PHP_CLI_SERVER_WORKERS` is not set, any subsequent request queues up and times out.

---

### Root Cause C: Database Transaction Wrapping an Outbound HTTP Request in POS
If POS has:
```php
DB::transaction(function () {
    // 1. Lock items & reservation
    // 2. Outbound HTTP call / slow query / webhook
});
```
Holding database locks while executing network I/O or waiting for locks creates table contention, causing the request to stall.

---

## 3. Step-by-Step Fixes on the POS Project (`skynet_digital_pos`)

### Fix 1: Make Webhook Dispatch Non-Blocking / Queued in POS
In `app/Modules/CommerceIntegration/Services/CommerceWebhookDispatcher.php` (or wherever webhooks are dispatched):

**Anti-Pattern (Blocks POS):**
```php
// Synchronous call blocks the confirm request!
Http::timeout(5)->post($webhookUrl, $payload);
```

**Recommended Fix (Queued or `dispatchAfterResponse`):**
Dispatch the webhook in the background or after the HTTP response has already been sent to Ecommerce:
```php
// Option A: If using Queues
dispatch(new \App\Jobs\SendCommerceWebhookJob($branch, $payload))->onQueue('webhooks');

// Option B: If running synchronously without queue worker, use dispatchAfterResponse()
dispatch(function () use ($webhookUrl, $payload, $headers) {
    try {
        Http::timeout(3)->withHeaders($headers)->post($webhookUrl, $payload);
    } catch (\Throwable $e) {
        \Log::warning("Webhook dispatch failed: " . $e->getMessage());
    }
})->afterResponse();
```
> **Key Benefit:** `afterResponse()` sends HTTP 200 back to Ecommerce immediately. Port 8002 finishes its request and becomes free *before* POS sends the inventory webhook. No deadlock!

---

### Fix 2: Skip Webhook Dispatch if the Change Originated from Ecommerce
When confirming a reservation, Ecommerce already knows that this reservation confirmed its items.
In POS Reservation Confirm logic:
```php
// Flag the current process to suppress redundant webhooks back to ecommerce
CommerceWebhookDispatcher::suppressForCurrentRequest();

$reservation->update(['status' => 'confirmed']);
// deduct physical stock...

CommerceWebhookDispatcher::resume();
```

---

### Fix 3: Enable Multi-Threaded Workers for `php artisan serve`
On local development, PHP CLI Server can handle concurrent requests if workers are enabled.

**In PowerShell (for both POS and Ecommerce):**
```powershell
# Set 4 concurrent worker processes
$env:PHP_CLI_SERVER_WORKERS=4
php artisan serve --port=8000
```
And in Ecommerce:
```powershell
$env:PHP_CLI_SERVER_WORKERS=4
php artisan serve --port=8002
```

---

### Fix 4: Check POS Reservation Confirm Controller & Route
Verify that:
1. Route exists in POS `routes/api.php`:
   ```php
   Route::post('/pos-integration/reservations/{externalReference}/confirm', [ReservationController::class, 'confirm']);
   ```
2. The controller does not throw an uncaught exception or enter an infinite loop.
3. Test the route directly using Postman, curl, or tinker to ensure it returns within 100-300ms:
   ```bash
   curl -X POST http://127.0.0.1:8000/api/pos-integration/reservations/597f5721-fc78-438d-906c-13f8ea318233/confirm \
     -H "Accept: application/json" \
     -H "X-Client-Id: your-client-id" \
     -H "Authorization: Bearer your-token"
   ```

---

## 4. Prompt to Send to the POS Project Agent

Copy and paste the prompt below to your agent working on the POS project:

```text
Please investigate and fix the following timeout error occurring on the Ecommerce integration:

Error:
Could not reach POS API: cURL error 28: Operation timed out after 10006 milliseconds with 0 bytes received for http://127.0.0.1:8000/api/pos-integration/reservations/{uuid}/confirm

Tasks to perform in skynet_digital_pos:
1. Check the controller and service handling:
   POST /api/pos-integration/reservations/{externalReference}/confirm
2. Check CommerceWebhookDispatcher.php and Item model observers:
   - Is confirming a reservation or updating item stock triggering a synchronous Http::post() webhook back to Ecommerce (http://127.0.0.1:8002/api/webhooks/pos-inventory)?
   - If yes, this creates a circular deadlock on single-threaded `php artisan serve` where Ecommerce waits for POS and POS waits for Ecommerce.
3. Fix the webhook dispatcher so it is non-blocking:
   - Use `dispatch(...)->afterResponse()` or a queued Job so POS sends HTTP 200 to Ecommerce BEFORE sending the outbound webhook.
   - Alternatively, suppress outbound webhooks for stock changes that originated directly from Ecommerce reservation confirmations.
4. Verify that database transactions do not wrap external HTTP calls.
5. Test confirming a reservation to ensure it responds in under 500ms without timing out.
```
