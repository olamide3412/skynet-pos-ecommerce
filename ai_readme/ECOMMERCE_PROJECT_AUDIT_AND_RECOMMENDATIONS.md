# Skynet POS E-Commerce — Full Project Audit & Recommendations

**Project Path:** `c:\Projects\Laravel\skynet_pos_ecommerce`  
**Stack:** Laravel 12 · Inertia.js (Vue 3) · Tailwind CSS · MySQL/SQLite · Paystack & Flutterwave · Skynet Digital POS Bridge

---

## Executive Summary

The **Skynet POS E-Commerce** platform has a solid foundation for hybrid retail operations:
- **POS Integration & Real-Time Inventory Bridge:** Branch pairing, catalog import (with batch chunking & immediate publish toggle), 3-guard HMAC/Bearer webhook authentication, and 2-step stock reservation (`reserveStock` -> `confirmReservation`).
- **Admin Backoffice:** Dashboard analytics, Catalog Management (CRUD, attributes, galleries, POS stock protection, 25/50/100/All pagination), Categories, Orders, Customers, Inventory audit logs, Store Settings, and Admin Users.
- **Storefront:** Product catalog with filters, product detail pages, session/customer cart, wishlist, Paystack & Flutterwave checkout initiation, and order tracking.

However, a full codebase audit revealed **critical security and payment integrity vulnerabilities (P0)**, **POS & inventory lifecycle gaps (P1)**, and **missing production e-commerce features (P2 & P3)** that should be addressed before going live.

---

## Priority Matrix Overview

| Priority | Category | Item | Impact | Status |
| :--- | :--- | :--- | :--- | :--- |
| **P0 (Critical)** | Payment Security | Payment callback confirms orders without verifying with Paystack/Flutterwave | Unpaid/cancelled checkouts are marked confirmed & deduct POS stock | **RESOLVED** |
| **P0 (Critical)** | Authorization (IDOR) | `CartController::update` & `destroy` do not verify cart ownership | Any user can modify or delete another user's cart items | **RESOLVED** |
| **P0 (Critical)** | Data Exposure | Unscoped `orWhere` in `Frontend\ProductController::show` | Draft/inactive products (`status = false`) are publicly viewable via `url_key` | **RESOLVED** |
| **P1 (High)** | POS Reliability | `RetryPosReservationConfirmationJob` exists but is never dispatched | Transient POS network failure after payment leaves POS stock unconfirmed | **RESOLVED** |
| **P1 (High)** | Inventory Lifecycle | No scheduled job to expire abandoned checkouts & restore local stock | Abandoned gateway checkouts permanently lock local e-commerce inventory | **RESOLVED** |
| **P1 (High)** | Checkout UX | Cart is deleted *before* payment gateway redirect completes | Customers who cancel or fail payment lose their entire cart | **RESOLVED** |
| **P1 (High)** | Auth / Cart | Guest cart is not merged into customer cart upon login/registration | Guests lose all cart items when signing in at checkout | **RESOLVED** |
| **P1 (High)** | Store Settings | Feature flags (`shop_enabled`, `guest_checkout_enabled`, etc.) only hide UI | Backend controllers still accept requests when features are disabled | **RESOLVED** |
| **P2 (Medium)** | Notifications | Zero transactional emails (`Mail` / `Notification` classes missing) | Customers receive no order confirmation, receipt, or shipping updates | **RESOLVED** |
| **P2 (Medium)** | Customer Account | No Forgot Password flow or Customer Profile / Address Book management | Customers cannot recover accounts or manage saved shipping addresses | **RESOLVED** |
| **P2 (Medium)** | Conversion & Sales | Missing Coupons/Promo Codes, Product Reviews, and State Shipping Zones | Limited marketing capabilities and flat-rate-only delivery pricing | **RESOLVED** |
| **P3 (Low)** | SEO & Maintenance | Missing Sitemap/OpenGraph tags; unused legacy template pages/enums | Suboptimal search engine indexing and dead code in bundle | **RESOLVED** |

---

## 1. P0 — Critical Security & Payment Integrity Fixes (RESOLVED)

### 1.1 Unverified Payment Callback (`CheckoutController::callback`) — [RESOLVED]
- **Location:** `app/Http/Controllers/Frontend/CheckoutController.php`
- **Resolution:**
  - Implemented authoritative server-to-server transaction verification via `PaystackService::verifyPayment($reference)` and `FlutterwaveService::verifyPayment($transactionId ?: $reference)` before confirming POS reservations or updating order status.
  - Added currency and amount validation ensuring paid amount matches or exceeds `$order->total_amount`.
  - Created and updated records in the `payments` table (`status = 'completed'` / `'failed'` / `'cancelled'`, provider, reference, response payload).
  - Implemented automatic cleanup upon verification failure or user cancellation: `$this->reservationService->releaseReservation($order)`, `$this->inventoryService->restoreOrderInventory($order)`, and updated `$order->status` to `cancelled`.
  - Added webhook routes and handlers (`/api/webhooks/paystack`, `/api/webhooks/flutterwave`, `/webhook/paystack`, `/webhook/flutterwave`) with HMAC-SHA512 (`x-paystack-signature`) and secret-hash (`verif-hash`) checks to handle interrupted browser sessions.

### 1.2 Cart Item IDOR Vulnerability (`CartController::update` & `destroy`) — [RESOLVED]
- **Location:** `app/Http/Controllers/Frontend/CartController.php`
- **Resolution:**
  - Added strict ownership validation: `$cart = $this->getCart($request); if (!$cart || $cartItem->cart_id !== $cart->id) abort(403);`.
  - Added live stock check on quantity update: returns error if requested quantity exceeds available `$product->stock`.

### 1.3 Unscoped `orWhere` Exposes Inactive/Draft Products (`Frontend\ProductController::show`) — [RESOLVED]
- **Location:** `app/Http/Controllers/Frontend/ProductController.php`
- **Resolution:**
  - Encapsulated `orWhere` condition inside a grouped closure:
    ```php
    $product = Product::where('status', true)
        ->where(function ($q) use ($url_key) {
            $q->where('url_key', $url_key)->orWhere('slug', $url_key);
        })
        ->firstOrFail();
    ```
  - Draft, inactive, and unlinked products can no longer be accessed publicly.

---

## 2. P1 — High-Priority POS Integration & Checkout Lifecycle Gaps (RESOLVED)

### 2.1 `RetryPosReservationConfirmationJob` Is Dispatched on Transient Failures — [RESOLVED]
- **Location:** `app/Services/PosIntegration/PosReservationService.php` & `app/Jobs/RetryPosReservationConfirmationJob.php`
- **Resolution:**
  - `PosReservationService::confirmReservation(Order $order, bool $isRetry = false)` now automatically dispatches `RetryPosReservationConfirmationJob::dispatch($order)->delay(now()->addMinutes(1))` whenever a transient POS failure occurs and `$isRetry` is false.
  - The job marks retry attempts and releases back into the queue up to 5 times.

### 2.2 Abandoned Checkouts Scheduled Expiration & Stock Restoration — [RESOLVED]
- **Location:** `app/Console/Commands/ExpireAbandonedOrdersCommand.php` & `routes/console.php`
- **Resolution:**
  - Created `orders:expire-abandoned` artisan command.
  - Scans for orders with `status = 'pending'` older than 35 minutes or where POS reservation expired (`pos_reservation_expires_at < now()`).
  - Calls `$reservationService->releaseReservation($order)` and `$inventoryService->restoreOrderInventory($order)` to return all reserved local e-commerce units back to available stock.
  - Updates `$order->update(['status' => 'cancelled', 'pos_reservation_status' => 'expired'])` and cancels pending `payments` records.
  - Scheduled automatically in `routes/console.php` every 5 minutes (`Schedule::command('orders:expire-abandoned')->everyFiveMinutes()`).

### 2.3 Safe Cart Lifecycle: Preserved Until Payment Confirmation — [RESOLVED]
- **Location:** `app/Http/Controllers/Frontend/CheckoutController.php`
- **Resolution:**
  - Removed premature cart deletion before gateway redirection.
  - The cart remains intact while customers navigate to Paystack or Flutterwave. If they cancel or experience an initialization error, their cart remains intact.
  - The cart is cleanly cleared upon verified payment return (`callback()`) and inside asynchronous webhooks (`paystackWebhook` / `flutterwaveWebhook`), or immediately upon placing Cash on Delivery (`cod`) orders.

### 2.4 Guest Cart Merging on Customer Login / Registration — [RESOLVED]
- **Location:** `app/Traits/MergesGuestCart.php`, `app/Http/Controllers/Frontend/CustomerAuthController.php`, and `app/Http/Controllers/Frontend/SocialLoginController.php`
- **Resolution:**
  - Created `MergesGuestCart` trait.
  - Captures the guest's session ID prior to `session()->regenerate()`.
  - Intelligently merges all guest cart items, POS unit selections, and variant options into the authenticated customer's cart without dropping quantities or creating duplicate lines.
  - Cleans up the orphaned guest cart record.

### 2.5 Backend Enforcement of Store Settings Feature Flags — [RESOLVED]
- **Location:** `app/Models/StoreSetting.php`, `CheckoutController.php`, `CartController.php`, `CustomerAuthController.php`, `OrderTrackingController.php`, and `WishlistController.php`
- **Resolution:**
  - Added type-safe `StoreSetting::getBool(string $key, bool $default = false): bool`.
  - `CheckoutController`: rejects orders and redirect if `shop_enabled` is false, or if `guest_checkout_enabled` is false for unauthenticated users.
  - `CartController`: blocks adding items if `shop_enabled` is false.
  - `CustomerAuthController`: blocks registration views and submissions if `customer_registration_enabled` is false.
  - `OrderTrackingController`: blocks tracking endpoints if `order_tracking_enabled` is false.
  - `WishlistController`: blocks wishlist views and toggles if `wishlist_enabled` is false.

---

## 3. P2 — Core Production E-Commerce Features to Add [RESOLVED]

### 3.1 Transactional Email & Notifications [RESOLVED]
- **Implemented:**
  1. `CustomerOrderConfirmationMail`: Automated order summary with billing and delivery details.
  2. `OrderStatusUpdatedMail`: Triggered on order status updates in `Admin/OrderController`.
  3. `AdminNewOrderAlertMail`: Sent to company/admin email upon order placement.
  4. `CustomerWelcomeMail`: Dispatched on successful customer registration.
  5. `CustomerResetPasswordMail`: Secure password reset link token mail.
- **Blade Templates:** In `resources/views/emails/`: `order-confirmation.blade.php`, `order-status-updated.blade.php`, `admin-new-order.blade.php`, `welcome.blade.php`, and `customer-reset-password.blade.php`.

### 3.2 Customer Password Reset ("Forgot Password") Flow [RESOLVED]
- **Implemented:**
  - Configured `'customers'` password broker in `config/auth.php`.
  - Added `CustomerPasswordResetController` handling `showForgotPassword`, `sendResetLink`, `showResetPassword`, and `resetPassword`.
  - Vue pages: `resources/js/Pages/Frontend/Auth/ForgotPassword.vue` and `ResetPassword.vue`.
  - Linked in `resources/js/Pages/Frontend/Auth/Login.vue`.

### 3.3 Customer Account Portal (Profile, Password & Address Book) [RESOLVED]
- **Implemented:**
  - `CustomerProfileController`: Profile info update (`/my-account`), password update (`/my-account/password`), address book management (`/my-addresses`), and default address toggle (`is_default`).
  - Vue pages: `resources/js/Pages/Frontend/Account/Profile.vue` and `Addresses.vue`.
  - Navigation: Added links in `resources/js/Components/Navigations/Navbar.vue` desktop profile dropdown and mobile drawer.
  - Checkout Auto-fill: Customer saved addresses automatically populate billing and shipping on checkout.

### 3.4 Complete Payment Methods Support (`Bank Transfer` & `Cash on Delivery`) [RESOLVED]
- **Implemented:**
  - `CheckoutController::process()` supports `cod` and `bank_transfer` alongside `paystack` and `flutterwave`.
  - Admin settings for `bank_transfer_enabled`, `bank_name`, `bank_account_number`, `bank_account_name`, and `bank_instructions` in `Admin/Settings/Index.vue` and `SettingsController.php`.
  - Displays bank details preview on `Frontend/Checkout/Index.vue` and receipt alert on `Frontend/Checkout/Success.vue`.
  - Creates pending `Payment` record and dispatches order confirmation notifications.

### 3.5 Product Reviews & Ratings System [RESOLVED]
- **Implemented:**
  - Migration & Eloquent model `ProductReview` (`product_id`, `customer_id`, `order_id`, `rating`, `comment`, `is_approved`).
  - `ProductReviewController::store`: Authenticated customer rating submission with verified buyer detection.
  - `Product::average_rating` dynamic accessor and approved reviews count.
  - UI in `resources/js/Pages/Frontend/Products/Show.vue`: Star rating header, breakdown summary, customer reviews list, and interactive 5-star review form.

### 3.6 Coupons, Promo Codes & Discount Engine [RESOLVED]
- **Implemented:**
  - Migration & model `Coupon` (`code`, `type` [`percent`, `fixed`], `value`, `min_order_amount`, `max_uses`, `used_count`, `starts_at`, `expires_at`, `is_active`).
  - Added `coupon_code` and `discount_amount` columns to `orders` table.
  - `CouponController` with `apply` and `remove` endpoints, validating min order subtotal and active status.
  - Interactive coupon input boxes and savings badges on `resources/js/Pages/Frontend/Cart/Index.vue` and `resources/js/Pages/Frontend/Checkout/Index.vue`.
  - Deduction reflected in net order total and database order items.

### 3.7 Dynamic State / Zone-Based Shipping Rates [RESOLVED]
- **Implemented:**
  - Migration & model `ShippingZone` (`state`, `city`, `fee`, `estimated_days`, `is_active`).
  - Seeded default shipping zones for Nigerian states (Lagos, Abuja, Rivers, Delta, Kano, Oyo, Other States).
  - Selectable shipping zone dropdown in `Checkout/Index.vue`, dynamically recalculating shipping fee, estimated delivery days, and free shipping threshold.

---

## 4. P3 — SEO, Performance & Codebase Hygiene

### 4.1 SEO, OpenGraph & Sitemap Generation
- **Dynamic Meta Tags:** Use Inertia's `<Head>` component in `Frontend/Products/Show.vue` and `Frontend/Shop/Index.vue` to render `meta_title`, `meta_description`, and OpenGraph (`og:title`, `og:description`, `og:image`, `og:price:amount`) using the product's `meta_title` / `meta_description` columns (which already exist on `products`!).
- **Sitemap (`/sitemap.xml`):** Add a controller/command to generate an XML sitemap of all active products and categories.

### 4.2 Queue Worker & Horizon / Database Queue Monitoring
- Ensure `QUEUE_CONNECTION=database` (or `redis`) is documented as a requirement in production so `RetryPosReservationConfirmationJob` and queued transactional emails execute asynchronously without blocking HTTP requests.

### 4.3 Clean Up Legacy Template Boilerplate
Several files appear to be leftover from an earlier loan/agency starter template and are not used by the e-commerce or POS modules:
- **Unused Enums (`app/Enums/`):**
  - `OccupationEnums.php`
  - `ReligionEnums.php`
  - `RepaymentFrequencyEnum.php`
  - `StaffDepartmentEnums.php`
- **Unused Legacy Vue Pages (`resources/js/Pages/Auth/`):**
  - `Pages/Auth/Clients/*`
  - `Pages/Auth/Staff/*`
  - `Pages/Auth/Orders/Instant.vue`, `Order.vue`, `History.vue`
  - `Pages/Auth/Products/History.vue`
- **Recommendation:** Archive or remove these unused files to reduce Vite build time and prevent confusion during maintenance.

---

## 5. Recommended Implementation Roadmap

### Phase 1: Security & Payment Hardening (Immediate — 1 to 2 Days)
1. Fix `CheckoutController::callback()` to verify transactions via `PaystackService::verifyPayment()` and `FlutterwaveService::verifyPayment()`, create `Payment` records, and update `Order` status.
2. Add Paystack & Flutterwave server-to-server webhook handlers (`/api/webhooks/paystack`, `/api/webhooks/flutterwave`).
3. Fix Cart IDOR in `CartController::update()` and `destroy()` + add stock quantity check on cart update.
4. Fix unscoped `orWhere` query in `Frontend\ProductController::show()`.

### Phase 2: POS & Inventory Resilience (Days 3 to 4)
1. Dispatch `RetryPosReservationConfirmationJob` inside `PosReservationService::confirmReservation()` on failure.
2. Create and schedule `orders:expire-abandoned` in `routes/console.php` to release POS reservations and restore local inventory for unpaid checkouts older than 30 minutes.
3. Defer cart deletion in `CheckoutController` until payment succeeds (or order is confirmed for COD/Bank Transfer).
4. Merge guest session cart into customer cart on login/registration in `CustomerAuthController`.
5. Enforce `StoreSetting` feature toggles (`shop_enabled`, `guest_checkout_enabled`, etc.) in backend controllers.

### Phase 3: Customer Experience & Storefront Completeness (Days 5 to 8)
1. Implement `Cash on Delivery` and `Bank Transfer` flows in `CheckoutController` and `Frontend/Checkout/Index.vue`.
2. Build Transactional Emails (Order Confirmation, Status Change, Admin Alert, Welcome).
3. Add Customer Forgot/Reset Password flow and Customer Profile + Saved Address Book (`/my-account`, `/my-addresses`).
4. Add Coupons/Promo Codes, Product Reviews, and State-based Shipping Zones.
5. Add OpenGraph/SEO meta tags and clean up unused legacy template files.

