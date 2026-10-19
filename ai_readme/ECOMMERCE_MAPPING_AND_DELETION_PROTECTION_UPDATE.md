# POS & Ecommerce Integration Update: Reverse Product Mapping & Deletion Protection

**Date:** October 19, 2026  
**Status:** Completed & Deployed  
**Module:** E-Commerce Integration / POS Inventory Management  
**Target Audience:** Frontend Team, Backend Team, Ecommerce Platform Integrators  

---

## 1. Executive Summary

This update establishes **reverse product mapping** and **deletion protection** between the Skynet Digital POS system and connected online e-commerce stores (WooCommerce, Shopify, custom web storefronts).

### The Problem It Solves
Previously, if a product was imported or synchronized into an online e-commerce store, a POS user could accidentally delete the parent item from the POS inventory. This resulted in orphaned online product listings, broken inventory webhooks, failed stock synchronization, and phantom online orders that could not be fulfilled.

### The Solution
We have implemented:
1. **A Reverse Mapping Registry (`ecommerce_product_links`)**: Tracks every active connection between a POS item and its online product counterpart.
2. **Strict Multi-Layer Deletion Protection**: Blocks deletion at the database lifecycle hook, the single-item delete endpoints, and the bulk delete workflows.
3. **Automated Synchronization API**: Three new endpoints allowing the e-commerce store to register links, unregister deleted/unlinked items, and reconcile entire catalogs to resolve drift.
4. **Visual UI Indicators**: Distinct **"Online Store"** badges with padlocked delete buttons and tooltips inside POS item tables.

---

## 2. API Reference for Ecommerce Integrators

All endpoints support Bearer Token authentication via the integration API token or Sanctum, as well as the standard `X-Branch-Id` header (if not already bound to the integration token).

### Base URLs
- `/api/pos-integration/mappings/*`
- `/api/commerce/v1/mappings/*`
- `/mappings/*`

---

### Endpoint 1: Register Mappings (Lock POS Items)

Call this endpoint whenever items from the POS are imported or mapped to the online store. This immediately locks the items from deletion in the POS.

- **Method:** `POST`
- **Route:** `/api/pos-integration/mappings/register` (or `/mappings/register`)
- **Headers:**
  ```http
  Authorization: Bearer <COMMERCE_API_TOKEN>
  X-Branch-Id: 2807
  Content-Type: application/json
  Accept: application/json
  ```
- **Payload:**
  ```json
  {
    "mappings": [
      {
        "pos_item_id": 667,
        "ecommerce_product_id": "wc_prod_991",
        "type": "simple"
      },
      {
        "pos_item_id": 668,
        "ecommerce_product_id": "shopify_prod_1024",
        "type": "variant"
      }
    ]
  }
  ```
  *(Note: Accepts both `pos_item_id` and `item_id`, as well as `ecommerce_product_id` and `external_product_id`).*

- **Response (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Successfully registered 2 mappings with POS.",
    "registered": 2
  }
  ```

---

### Endpoint 2: Unregister Mappings (Release Deletion Lock)

Call this endpoint when an item is deleted from the online store, unpublished, or unlinked. This safely unlocks the item so POS operators can delete or archive it if needed.

- **Method:** `POST`
- **Route:** `/api/pos-integration/mappings/unregister` (or `/mappings/unregister`)
- **Payload:**
  ```json
  {
    "pos_item_ids": [667, 668]
  }
  ```
- **Response (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Unregistered 2 item mappings.",
    "unregistered": 2
  }
  ```

---

### Endpoint 3: Reconcile Mappings (Drift Repair)

Call this endpoint on a scheduled sync (e.g., nightly) or when manual re-synchronization is triggered in the e-commerce dashboard. It sets the active items to exactly what is provided, automatically removing locks for items no longer in the e-commerce store.

- **Method:** `POST`
- **Route:** `/api/pos-integration/mappings/reconcile` (or `/mappings/reconcile`)
- **Payload:**
  ```json
  {
    "mappings": [
      {
        "pos_item_id": 667,
        "ecommerce_product_id": "wc_prod_991",
        "type": "simple"
      }
    ]
  }
  ```
- **Response (`200 OK`):**
  ```json
  {
    "success": true,
    "reconciled": 1
  }
  ```

---

## 3. POS Protection Behavior & Error Handling

### Single Item Delete
If an operator tries to delete a linked item via the POS interface or API:
- **HTTP Status:** `422 Unprocessable Content`
- **JSON Response:**
  ```json
  {
    "status": false,
    "message": "Cannot delete item 'Organic Honey'. It is currently linked to an active product in your online Ecommerce store. Please unlink it from Ecommerce first."
  }
  ```

### Bulk Deletion Guard
If an operator selects multiple items and triggers **"Delete Selected"**:
- **Unlinked Items:** Deleted normally.
- **Linked Items:** Automatically preserved and skipped.
- **Notification:** Returns a warning notification indicating how many items were deleted and how many were protected from deletion to prevent online store drift.

### Model Lifecycle Guard (`deleting` hook)
Even if deletion is attempted directly through tinker, console scripts, or custom services, `Item::deleting` triggers and throws a `\RuntimeException`, preventing silent database cascades.

---

## 4. UI Changes in POS

1. **Items Catalog (`resources/js/Pages/Items/Index.vue`)**:
   - Linked items display an emerald **"Online Store"** badge with a lock icon next to their name.
   - The red delete button is replaced by a padlock icon with a tooltip: `"Cannot delete: Linked to active Online Ecommerce product"`.
   - The bulk delete modal informs the user if any of the selected items are linked to the store and will be preserved.

2. **Super Admin Branch Catalog (`resources/js/Pages/SuperAdmin/Branches/Items.vue`)**:
   - Added the **"Online Store"** badge to the item name column.
   - Replaced the "Remove" button with a locked badge for active online items.

3. **REST API Quick Reference Card (`resources/js/Pages/SuperAdmin/Branches/Partials/EcommerceTab.vue`)**:
   - Added `/mappings/register`, `/mappings/unregister`, and `/mappings/reconcile` to the developer cheatsheet in the branch settings.

---

## 5. Database Schema & Architecture

### New Migrations (Production-Safe)
- `database/migrations/2026_10_19_213500_create_ecommerce_product_links_table.php`
- `database/migrations/2026_10_19_214600_change_ecommerce_product_id_to_string_in_ecommerce_product_links_table.php`

### Table: `ecommerce_product_links`
| Column | Type | Index | Description |
|---|---|---|---|
| `id` | `bigint unsigned` | PK | Primary Key |
| `branch_id` | `bigint unsigned` | Index, Nullable | POS Branch ID |
| `pos_item_id` | `bigint unsigned` | Index | Foreign Key to `items.id` |
| `ecommerce_product_id` | `string(191)` | Index | External product ID / UID |
| `type` | `string` | — | `simple` or `variant` |
| `is_active` | `boolean` | — | Active status flag (default `true`) |
| `last_synced_at` | `timestamp` | — | Timestamp of last sync |
| `created_at` / `updated_at` | `timestamp` | — | Standard timestamps |

- **Unique Composite Key:** `['pos_item_id', 'ecommerce_product_id']` prevents duplicate mapping records.

---

## 6. Verification & Test Results

### Automated Feature Tests
Added full test coverage in `tests/Feature/EcommerceMappingAndDeletionProtectionTest.php`:
- `test_can_register_ecommerce_product_links`: Verified link registration and status accessors.
- `test_deletion_of_linked_item_is_blocked_by_model_hook`: Verified `\RuntimeException` prevention.
- `test_item_controller_destroy_rejects_linked_item`: Verified 422 JSON response.
- `test_bulk_delete_skips_linked_items`: Verified preservation of linked items during batch delete.
- `test_can_unregister_ecommerce_product_links`: Verified unregistering unlocks deletion.
- `test_can_reconcile_ecommerce_product_links`: Verified drift cleanup.
- `test_superadmin_branch_item_controller_destroy_rejects_linked_item`: Verified Super Admin deletion guard.

**Test Execution:**
```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/EcommerceMappingAndDeletionProtectionTest.php
# Result: OK (7 tests, 25 assertions) - 100% Passing
```

### Full Regression Suite
- `BranchEcommerceActivityTest` & `CommerceIntegrationTest`: **26 tests, 134 assertions passing**.
- Frontend Assets: `npm run build` compiled cleanly with 0 errors.
