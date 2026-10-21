# Strict Versioned POS Commerce API (v1) Specification & Verification Guide

> **Note**: Backward compatibility has been completely eliminated. Both the POS (`skynet_digital_pos`) and Ecommerce (`skynet_pos_ecommerce`) systems communicate strictly via the versioned API route prefix:
> ```
> /api/commerce/v1/
> ```
> No `/api/pos-integration/` routes or fallback aliases are used or required.

---

## 1. Overview & Connection Architecture

The Ecommerce application (`skynet_pos_ecommerce`) connects to the POS application (`skynet_digital_pos`) with the configured Base URL:
```text
http://127.0.0.1:8000/api/commerce/v1
```

All API requests are sent with strict relative paths against `/api/commerce/v1/` and include security & tenant identification headers on every call.

---

## 2. Authentication & Request Headers

Every request from Ecommerce to POS includes the following headers:

| Header Name | Type | Description |
|---|---|---|
| `Accept` | `string` | `application/json` |
| `Authorization` | `string` | `Bearer <api_secret>` |
| `X-Commerce-Key` | `string` | `<api_secret>` (Used by custom POS API token middleware) |
| `X-Client-Id` | `string` | Client / Pairing Identifier (e.g. `ECOMM-01`) |
| `X-Branch-Id` | `string` | Target POS Branch ID (e.g. `1`) |
| `X-Integration-Id` | `string` | POS Integration UUID / ID |
| `X-Timestamp` | `string` | Current Unix timestamp (e.g. `1792495373`) |
| `X-Signature` | `string` | HMAC-SHA256 signature generated with `api_secret` over `client_id:timestamp` |

---

## 3. Strict Versioned Endpoints Checklist (POS Side)

The POS project (`skynet_digital_pos`) must register and confirm these **12 versioned endpoints** under prefix `/api/commerce/v1`:

### A. Health & Handshake
1. **`GET /api/commerce/v1/ping`**
   - **Purpose**: Verify network connectivity, branch availability, and credentials.
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "message": "POS Commerce API v1 is active",
       "branch_id": 1,
       "branch_name": "Main Store",
       "version": "1.0.0"
     }
     ```

---

### B. Catalog & Product Inventory
2. **`GET /api/commerce/v1/catalog`**
   - **Query Parameters**:
     - `page` (integer, default `1`)
     - `per_page` (integer, default `100`, max `250`)
     - `since` (string, ISO 8601 or `Y-m-d H:i:s`, optional incremental filter)
   - **Response (200 OK)**:
     ```json
     {
       "data": [
         {
           "id": 101,
           "name": "Men Casual T-Shirt",
           "sku": "TSHIRT-001",
           "barcode": "123456789012",
           "description": "100% cotton crew neck",
           "category": "Apparel",
           "price": 25.00,
           "cost_price": 12.00,
           "available_quantity": 45,
           "allow_ecommerce_sale": true,
           "has_units": false,
           "units": []
         }
       ],
       "current_page": 1,
       "last_page": 15,
       "per_page": 100,
       "total": 1432
     }
     ```

3. **`GET /api/commerce/v1/catalog/{id}`**
   - **Purpose**: Retrieve single POS item detail including multi-selling units.
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "data": {
         "id": 101,
         "name": "Men Casual T-Shirt",
         "sku": "TSHIRT-001",
         "available_quantity": 45,
         "units": [
           {
             "id": 201,
             "unit_name": "Pack of 3",
             "multiplier": 3,
             "price": 65.00,
             "barcode": "123456789099"
           }
         ]
       }
     }
     ```

4. **`GET /api/commerce/v1/catalog/{id}/stock`**
   - **Purpose**: Fast fallback stock check for a single item.
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "pos_item_id": 101,
       "available_quantity": 45
     }
     ```

---

### C. Live Stock Availability
5. **`POST /api/commerce/v1/availability`**
   - **Payload**:
     ```json
     {
       "item_ids": [101, 102, 103, 104]
     }
     ```
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "data": {
         "101": 45,
         "102": 12,
         "103": 0,
         "104": 8.5
       }
     }
     ```

---

### D. Stock Reservations (Cart & Checkout)
6. **`POST /api/commerce/v1/reservations`**
   - **Purpose**: Authoritatively hold stock during checkout before payment.
   - **Payload**:
     ```json
     {
       "external_reference": "ECOM-ORD-20261020-00123",
       "expiry_minutes": 30,
       "items": [
         {
           "pos_item_id": 101,
           "pos_item_unit_id": null,
           "quantity": 2
         }
       ]
     }
     ```
   - **Response (200 OK or 201 Created)**:
     ```json
     {
       "status": true,
       "reservation_id": "res_84920492",
       "reference": "ECOM-ORD-20261020-00123",
       "expires_at": "2026-10-20T17:56:16Z",
       "items": [
         { "pos_item_id": 101, "reserved_quantity": 2 }
       ]
     }
     ```
   - **Error (422 Unprocessable Entity - Insufficient Stock)**:
     ```json
     {
       "status": false,
       "message": "Insufficient stock for item #101. Available: 1, Requested: 2",
       "errors": {
         "101": ["Available stock (1) is less than requested quantity (2)"]
       }
     }
     ```

7. **`GET /api/commerce/v1/reservations/{reference}`**
   - **Purpose**: Query reservation status (`active`, `confirmed`, `expired`, `released`).
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "reference": "ECOM-ORD-20261020-00123",
       "reservation_status": "active",
       "expires_at": "2026-10-20T17:56:16Z"
     }
     ```

8. **`POST /api/commerce/v1/reservations/{reference}/confirm`**
   - **Purpose**: Authoritatively deduct stock upon successful payment or confirmed COD order.
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "message": "Reservation confirmed and POS inventory deducted",
       "reference": "ECOM-ORD-20261020-00123"
     }
     ```

9. **`POST /api/commerce/v1/reservations/{reference}/release`**
   - **Purpose**: Release held stock back to available inventory (payment cancelled, abandoned checkout).
   - **Response (200 OK)**:
     ```json
     {
       "status": true,
       "message": "Reservation released successfully",
       "reference": "ECOM-ORD-20261020-00123"
     }
     ```

---

### E. Product Mapping Registry & POS Deletion Protection
10. **`POST /api/commerce/v1/mappings/register`**
    - **Purpose**: Inform POS that specific items/units are actively mapped to online products. POS prevents cashier or branch managers from deleting these items.
    - **Payload**:
      ```json
      {
        "mappings": [
          {
            "pos_item_id": 101,
            "pos_item_unit_id": null,
            "ecommerce_product_id": 45,
            "ecommerce_variant_id": null,
            "sync_pricing": true,
            "sync_stock": true
          }
        ]
      }
      ```
    - **Response (200 OK)**:
      ```json
      {
        "status": true,
        "registered": 1,
        "message": "1 mapping(s) registered for deletion protection."
      }
      ```

11. **`POST /api/commerce/v1/mappings/unregister`**
    - **Purpose**: Unregister protection when an online product is deleted, unmapped, or changed to "Ecommerce Only".
    - **Payload**:
      ```json
      {
        "pos_item_ids": [101]
      }
      ```
    - **Response (200 OK)**:
      ```json
      {
        "status": true,
        "unregistered": 1,
        "message": "1 mapping(s) unregistered."
      }
      ```

12. **`POST /api/commerce/v1/mappings/reconcile`**
    - **Purpose**: Sync the entire active mapping table to heal any registry drift between POS and Ecommerce.
    - **Payload**:
      ```json
      {
        "mappings": [
          {
            "pos_item_id": 101,
            "pos_item_unit_id": null,
            "ecommerce_product_id": 45,
            "ecommerce_variant_id": null,
            "sync_pricing": true,
            "sync_stock": true
          }
        ]
      }
      ```
    - **Response (200 OK)**:
      ```json
      {
        "status": true,
        "reconciled": 1,
        "message": "Mapping registry reconciled successfully."
      }
      ```

---

## 4. Verification Prompt for the POS AI Agent

Copy and paste the prompt below into the chat with the **POS AI Agent** working on `skynet_digital_pos`:

```text
### Task: Verify and Confirm Versioned Commerce API (v1) in skynet_digital_pos

#### 1. Context:
We are strictly using versioned API routing under prefix `/api/commerce/v1/`. All backward compatibility routes (/api/pos-integration/*) have been completely removed from both POS and Ecommerce.

The Ecommerce store (`skynet_pos_ecommerce`) connects with:
- Base URL: http://127.0.0.1:8000/api/commerce/v1
- Auth: Bearer Token / X-Commerce-Key, X-Client-Id, X-Signature, X-Branch-Id

#### 2. Please confirm and verify the following 12 endpoints in `skynet_digital_pos` (routes/api.php or Modules/CommerceIntegration/routes/api.php):

Route prefix: `commerce/v1` (with middleware: auth:sanctum or custom commerce auth)

1. GET  /api/commerce/v1/ping
2. GET  /api/commerce/v1/catalog (accepts: page, per_page, since)
3. GET  /api/commerce/v1/catalog/{id}
4. GET  /api/commerce/v1/catalog/{id}/stock
5. POST /api/commerce/v1/availability (accepts: item_ids: [...])
6. POST /api/commerce/v1/reservations (accepts: external_reference, expiry_minutes, items: [...])
7. GET  /api/commerce/v1/reservations/{reference}
8. POST /api/commerce/v1/reservations/{reference}/confirm
9. POST /api/commerce/v1/reservations/{reference}/release
10. POST /api/commerce/v1/mappings/register (accepts: mappings: [...])
11. POST /api/commerce/v1/mappings/unregister (accepts: pos_item_ids: [...])
12. POST /api/commerce/v1/mappings/reconcile (accepts: mappings: [...])

#### 3. Verification Commands to run on POS terminal:
Run:
php artisan route:list --path=commerce/v1

Ensure all 12 routes return HTTP 200/201 when called with valid auth headers, and return proper JSON error payloads if validation or authentication fails.
```
