# Skynet Digital Ecommerce POS Integration Agent README

## 1. Purpose

This document defines the ecommerce side modification required to connect a freshly cloned Skynet Digital Ecommerce installation to one branch of the existing Skynet POS platform.

This document applies only to the ecommerce codebase.

Do not use this README to modify the POS application.

The POS has its own separate ecommerce integration README that defines the POS API, reservation service, inventory authority, branch scoping, Super Admin connection management, and POS side inventory behavior.

The ecommerce clone can be heavily modified because this installation is being prepared specifically for the new client and is not the live POS system.

The goal is to preserve the ecommerce application as a complete standalone online shopping system while replacing its independent physical inventory ownership with controlled synchronization and reservation calls to the selected POS branch.


## 2. Confirmed Existing Ecommerce Stack

The current ecommerce application is already a substantial standalone platform.

Existing technology includes:

* Laravel 12
* PHP 8.2 or later
* Inertia.js 2
* Vue 3 using Composition API and script setup
* Tailwind CSS 4
* Pinia
* Vite
* MySQL or MariaDB
* Dual authentication guards
* Laravel Socialite
* Paystack
* Flutterwave
* Cash on Delivery
* Intervention Image
* Vue Quill
* HTML2PDF
* Database queues
* Database sessions
* Dynamic store settings

The existing Inertia architecture should remain.

Do not convert the ecommerce application into a separate frontend API architecture merely because it now talks to the POS API.

The ecommerce application itself can continue using normal Laravel controllers and Inertia pages internally.


## 3. Core Architecture

There are two independent applications.

The POS is a multi branch business system.

The ecommerce application is a standalone single store system.

One ecommerce installation connects to exactly one POS branch.

The ecommerce application must never behave as a multi branch client.

Conceptually:

```text
Skynet POS
    Branch A
    Branch B
    Branch C

Standalone Ecommerce Store
    connects only to Branch B
```

The ecommerce installation must never:

* Browse the POS branch directory
* Switch between branches
* Combine stock from several branches
* Choose a branch during checkout
* Query stock from another branch
* Send a different branch identifier to gain access to another branch
* Move an online order between POS branches

The connected branch is fixed by the POS integration credential issued for that ecommerce installation.


## 4. Responsibility Boundary

The POS and ecommerce application remain separate sales systems.

The POS owns physical inventory.

The ecommerce application owns online commerce.

The ecommerce application must not attempt to become another POS cashier terminal.


## 5. POS Responsibilities

The POS remains authoritative for:

* POS item identity
* Branch ownership
* Base unit
* Additional POS item units
* Standard selling price
* Wholesale price where exposed
* Front store physical quantity
* Back store physical quantity
* Effective sellable quantity after active online reservations
* IMEI inventory
* Batch inventory behavior
* Inventory movement history
* External ecommerce reservations
* Reservation confirmation
* Reservation release
* Reservation expiry
* Physical POS sales
* POS discounts
* POS reporting
* POS offline sales


## 6. Ecommerce Responsibilities

The ecommerce application remains authoritative for:

* Online product name
* Online slug
* Product descriptions
* Product images
* Product galleries
* Online category structure
* Attributes used for storefront filtering
* SEO information
* Featured products
* Related products
* Online publishing status
* Homepage merchandising
* Sliders
* Customer accounts
* Customer addresses
* Guest checkout
* Shopping carts
* Wishlists
* Product comparison
* Online checkout
* Paystack
* Flutterwave
* Cash on Delivery
* Ecommerce tax
* Service fees
* Shipping fees
* Pickup
* Order tracking
* Ecommerce invoices
* Online order management
* Ecommerce revenue reports
* Ecommerce discounts
* Future coupon features
* Future referral features
* Future loyalty features
* Future online promotions

The POS must not take over these responsibilities.


## 7. Existing Ecommerce Features That Must Continue Working

The current ecommerce application already contains useful functionality that should be preserved unless a later requirement explicitly changes it.

Preserve:

* Storefront homepage
* Hierarchical categories
* Live search suggestions
* Product pages
* Product descriptions
* Product images
* Dynamic attributes
* Related products
* Guest cart
* Customer cart
* Customer registration
* Customer login
* Google login if enabled
* Facebook login if enabled
* Customer order history
* Public order tracking
* Wishlists
* Product comparison
* Paystack
* Flutterwave
* Cash on Delivery
* Tax settings
* Service fee settings
* Shipping settings
* Pickup settings
* Free shipping threshold
* Admin order management
* Revenue analytics
* Store settings
* Store branding
* Homepage sliders
* Existing ecommerce discount price behavior


## 8. Current Ecommerce Inventory Assumption Must Change

The current ecommerce product model stores its own stock quantity.

The existing product field:

```text
stock
```

currently behaves as if the ecommerce database owns the real inventory.

That assumption must change.

The POS becomes the physical inventory authority.

The ecommerce `stock` field may remain for performance and display compatibility, but its meaning changes.

It becomes:

```text
Last synchronized effective online availability
```

It is a cache.

It is not the final source of truth.

The ecommerce application must never permanently deduct physical inventory only by changing its local `products.stock` value.


## 9. POS Quantity Used Online

Online sales use only the connected POS branch front store quantity.

The authoritative POS quantity is:

```text
front_store_qty
```

Do not use:

```text
back_store_qty
```

for online availability.

Example:

```text
front_store_qty = 0
back_store_qty = 100
```

The ecommerce site must treat the product as unavailable.

The ecommerce application must never automatically move quantity from back store to front store.

The normal POS stock movement process controls that.


## 10. Effective Online Availability

The POS calculates effective availability as:

```text
front_store_qty
minus
active ecommerce reservation quantity
```

The ecommerce application receives the calculated availability from the POS API.

Example:

```text
front_store_qty = 20
active reserved quantity = 4
effective availability = 16
```

The ecommerce site may cache:

```text
products.stock = 16
```

for browsing and display.

The final checkout decision must still use a live POS reservation request.


## 11. Do Not Create a Second Inventory System

The ecommerce clone must not create independent physical inventory tables that compete with POS inventory.

Do not build:

```text
warehouse_stock
online_physical_stock
ecommerce_real_stock
branch_stock
```

as competing sources of truth.

The ecommerce database may contain synchronized cache fields and order snapshots, but physical inventory belongs to POS.


## 12. Product Mapping

Every online product that is sellable through the POS integration must be linked to one POS item.

The primary integration key is:

```text
pos_item_id
```

Do not use:

* Product name
* Online slug
* Barcode
* SKU
* Ecommerce product id

as the authoritative cross system identity.

Names can change.

Slugs can change.

Barcodes can be replaced.

The POS item id is the stable mapping reference.


## 13. Recommended Product Fields

The ecommerce `products` table should be adapted to include POS mapping and synchronization information.

Recommended fields include:

```text
id
category_id
pos_item_id
name
slug
url_key
product_number
sku
short_description
description
price
discount_price
special_price
special_price_from
special_price_to
stock
image
images
attributes
related_products
status
is_new
is_featured
visible_individually
show_stock_level
pos_item_name_snapshot
pos_base_unit_name
pos_sync_status
pos_item_updated_at
last_pos_sync_at
created_at
updated_at
```

Important meanings:

```text
pos_item_id
```

is the external POS item mapping.

```text
price
```

is the latest synchronized POS standard selling price for the base unit.

```text
stock
```

is the latest synchronized effective online availability cache.

```text
pos_item_name_snapshot
```

is only an administrative reference showing the latest POS item name.

It does not control the online customer facing product name.


## 14. Cost Price

The ecommerce application should not automatically fetch or expose POS cost price.

The current ecommerce schema contains:

```text
cost_price
```

but the current documented revenue reports do not require it.

For this integration, do not make ecommerce depend on POS cost price unless a later requirement explicitly asks for online profit reporting.

If the clone is restructured before deployment and no ecommerce feature requires `cost_price`, it may be removed from the online product management flow.

Do not expose sensitive POS cost information through the integration without a business requirement.


## 15. Online Product Name Is Independent

The ecommerce administrator can rename the online product freely.

Example POS name:

```text
Samsung Galaxy A56 256GB
```

Example ecommerce name:

```text
Samsung Galaxy A56 5G 256GB Smartphone
```

Both still represent the same inventory item through:

```text
pos_item_id
```

A future POS item name change must not automatically overwrite carefully written online marketing copy.

The ecommerce system may refresh `pos_item_name_snapshot` for administrative reference while leaving `products.name` unchanged.


## 16. Online Categories Are Independent

The existing ecommerce hierarchical category system should remain independent from POS categories.

Example POS category:

```text
Phones
```

Example ecommerce category path:

```text
Electronics
Mobile Phones
Android Phones
```

The ecommerce administrator chooses the online category.

POS category changes must not automatically reorganize the online storefront.


## 17. Images Remain Ecommerce Owned

The current ecommerce application already supports:

* Primary product image
* Additional product images
* Category images
* Store logo
* Slider images

Keep this behavior.

Do not push ecommerce product galleries into POS.

Do not require POS items to contain online marketing images.


## 18. Attributes Remain Ecommerce Owned

The existing ecommerce `attributes` system remains useful for:

* Color
* Storage
* Screen size
* Size
* Material
* Brand specifications
* Filtering
* Comparison

These attributes remain ecommerce data unless a specific attribute needs inventory identity.

Do not automatically turn ecommerce attributes into POS inventory records.


## 19. Existing Ecommerce Variants Need Careful Separation

The current ecommerce application stores variants in JSON and may allow a variant to provide a price override.

That concept must not be confused with POS flexible item units.

POS additional item units represent real inventory conversion levels such as:

```text
Piece
Roll
Pack
Half Carton
Carton
```

They determine how many base units are deducted.

Ecommerce attributes or display variants may represent:

```text
Blue
Black
128GB
256GB
Small
Medium
Large
```

These are not automatically POS unit conversions.


## 20. Rule for Inventory Significant Variants

If an ecommerce choice represents a genuinely different physical stock pool, it must map to a separate POS inventory identity.

For example, if:

```text
Samsung Phone 128GB
Samsung Phone 256GB
```

are stocked separately in POS, they should normally be separate POS items and separate online product mappings.

Do not let a local ecommerce JSON variant create its own unmanaged physical stock.


## 21. POS Flexible Unit Support

The ecommerce application should support POS base unit and optional additional POS units without reusing the old ecommerce variant JSON as the inventory mechanism.

Create a dedicated table for POS selling unit mappings.

Recommended table:

```text
product_pos_units
```

Suggested fields:

```text
id
product_id
pos_item_unit_id
unit_kind
unit_name
quantity_in_base_units
synced_standard_price
online_label
is_enabled_online
sort_order
last_pos_sync_at
created_at
updated_at
```

Rules:

```text
unit_kind = base
```

means the POS base unit.

For the base unit:

```text
pos_item_unit_id = null
quantity_in_base_units = 1
```

For an additional unit:

```text
unit_kind = additional
pos_item_unit_id = POS item_units id
```

The ecommerce administrator can choose which synchronized POS unit levels are visible online.


## 22. POS Unit Price

Each POS unit level has its own independent selling price.

Never calculate:

```text
additional unit price
equals
base price multiplied by conversion quantity
```

Use the actual synchronized POS unit price.

Example:

```text
Base unit price = 250
Roll quantity = 10
Roll POS price = 2250
```

The online normal Roll price is:

```text
2250
```

not:

```text
2500
```


## 23. Online Unit Label

The ecommerce application may optionally present a friendlier label without changing the POS unit identity.

Example POS unit:

```text
Carton
```

Online label:

```text
Full Carton
```

The ecommerce display label may change.

The POS item unit id remains the integration key.


## 24. Cart Changes

The existing cart system should continue supporting guests and authenticated customers.

Each cart item must retain enough POS mapping information to reserve the correct physical inventory.

Recommended cart item additions:

```text
product_pos_unit_id nullable
```

The cart item already has:

```text
product_id
quantity
price
variant
```

The new inventory related selection should be separate from the old `variant` field.

The `variant` field must not become the POS unit identifier.


## 25. Order Item Snapshot Changes

Online order history must remain understandable even if the POS product or unit configuration changes later.

Extend `order_items` with inventory snapshots such as:

```text
pos_item_id
pos_item_unit_id nullable
unit_name
quantity_in_base_units
standard_pos_price_snapshot
online_unit_price
```

The actual order `price` continues storing what the customer was charged per ordered unit.

This distinction is important because ecommerce discounts can make:

```text
online_unit_price
```

different from the standard POS price.


## 26. Standard Price Ownership

The POS owns the standard selling price.

The ecommerce application synchronizes the standard price.

For the base unit:

```text
products.price
```

stores the latest synchronized POS base selling price.

For additional POS units:

```text
product_pos_units.synced_standard_price
```

stores the latest synchronized POS unit price.

The ecommerce admin should not directly edit these synchronized standard prices.


## 27. Ecommerce Discounts Remain Independent

The existing ecommerce application already has basic promotional price fields:

```text
discount_price
special_price
special_price_from
special_price_to
```

These remain ecommerce controlled.

The final customer price can therefore differ from the synchronized POS standard price.

Example:

```text
POS standard price = 10000
Ecommerce promotional price = 8500
Customer pays = 8500
```

The POS does not need to calculate or store that promotion.


## 28. Coupon and Referral Clarification

Coupon codes and referral systems are not currently documented as implemented features in this ecommerce clone.

Do not accidentally build a full coupon system, referral system, loyalty system, affiliate system, or complex promotion engine as part of the POS integration unless separately requested.

The architecture must allow those ecommerce features to be added later.

When they are eventually added, they remain ecommerce only and must not use the POS discount engine.


## 29. POS Discounts Must Stay Separate

Do not call or duplicate:

* POS Item Discount
* POS Group Discount
* POS Overall Discount

for online checkout.

The ecommerce application calculates its own final customer price.

The POS integration controls inventory only and provides synchronized standard price information.


## 30. Recommended POS Integration Module

Keep external POS communication centralized.

Recommended structure:

```text
app/
    Services/
        PosIntegration/
            PosApiClient.php
            PosCatalogService.php
            PosSyncService.php
            PosReservationService.php
            PosConnectionService.php
            PosIntegrationException.php
```

Additional models may include:

```text
PosConnection
PosSyncLog
```

Do not scatter raw HTTP calls throughout:

* ProductController
* CartController
* CheckoutController
* OrderController
* Vue pages

Controllers should call the integration service.


## 31. POS Connection Record

Because the ecommerce store connects to only one POS branch, use one active POS connection record.

Recommended table:

```text
pos_connections
```

Suggested fields:

```text
id
pos_base_url
pos_integration_id
pos_branch_id
pos_branch_name
client_id
encrypted_api_secret
is_connected
connected_at
last_successful_request_at
last_catalog_sync_at
last_inventory_sync_at
last_error_at
last_error_message
created_at
updated_at
```

Do not store the API secret in plain text.


## 32. Connection Ownership

Only POS Super Admin is allowed to decide which POS branch is connected to this ecommerce installation.

The ecommerce admin must not receive:

* POS branch selector
* Connect another branch button
* Change branch control
* POS credential generation
* POS credential rotation
* POS integration enable or disable authority

The ecommerce admin interface may show read only information such as:

```text
Connected POS branch
Connection status
Last successful synchronization
Last synchronization error
```

but it must not allow the ecommerce administrator to switch the branch.


## 33. Connection Handshake

The exact handshake should match the POS side implementation.

The ecommerce application must support receiving and securely storing the branch scoped credentials issued by the POS integration.

A safe implementation may use a dedicated protected pairing or connection callback endpoint.

The important rules are:

* POS Super Admin initiates the connection
* The credential is already bound to one POS branch
* Ecommerce does not choose the branch
* Ecommerce stores the POS API base URL and credential securely
* Ecommerce cannot alter `pos_branch_id` through normal admin settings
* Disconnecting or rotating credentials is controlled from POS Super Admin


## 34. Server Communication

POS communication is server to server.

Do not expose the POS API secret to Vue.

Do not store it in:

* Browser localStorage
* Browser sessionStorage
* JavaScript bundles
* Inertia props
* HTML source
* Public configuration files

Only Laravel backend services should send authenticated requests to POS.


## 35. API Client Requirements

The POS API client should provide centralized methods conceptually similar to:

```text
testConnection()
listItems()
getItem()
getAvailability()
createReservation()
confirmReservation()
releaseReservation()
getReservation()
```

The exact endpoint paths must match the POS integration implementation.


## 36. API Timeout and Retry Behavior

Use reasonable HTTP timeouts.

Do not let an unavailable POS API freeze the ecommerce request indefinitely.

Read operations may retry carefully.

Inventory mutation operations must rely on POS idempotency and ecommerce idempotency before retries.

Never retry a reservation confirmation blindly without using the same external reference.


## 37. Catalog Synchronization

The ecommerce application should synchronize selected POS catalog data.

Synchronize:

* POS item id
* POS administrative item name
* POS item active state
* Base unit name
* Standard base selling price
* Additional item units
* Additional unit conversion quantity
* Additional unit standard prices
* Effective availability
* IMEI tracking flag where provided
* POS item updated timestamp

Do not synchronize ecommerce presentation fields back into POS.


## 38. Initial POS Item Selection Workflow

The online product creation experience should change.

Instead of manually creating an entirely independent inventory product, ecommerce admin should be able to select a POS item from the connected branch.

Recommended flow:

```text
Admin
Choose Add Online Product
Choose a POS item
System links pos_item_id
System loads standard price and availability
Admin enters online presentation information
Admin chooses ecommerce category
Admin uploads images
Admin writes description
Admin sets SEO information if supported
Admin chooses which POS unit levels are enabled online
Admin publishes product
```

The admin must never manually type a POS item id.


## 39. Prevent Duplicate Mapping

A POS item should normally map to one online product in the same standalone ecommerce installation.

Enforce a uniqueness rule on:

```text
pos_item_id
```

unless a future requirement explicitly allows several online listings to share one physical POS item.

For the current implementation, one POS item equals one online product mapping.


## 40. Unpublished POS Items

Selecting a POS item for ecommerce does not automatically mean it must immediately appear online.

The ecommerce administrator controls:

```text
status
visible_individually
is_featured
```

The item can remain linked but unpublished while online descriptions and images are prepared.


## 41. POS Item Deactivation

If the POS item becomes inactive or unavailable, synchronization should disable new online purchasing.

Do not automatically delete the ecommerce product.

Keep:

* Product page content
* Images
* SEO history
* Order relationships
* Wishlists
* Reporting history

Mark it unavailable for purchase until the mapping becomes valid again.


## 42. POS Item Missing

If synchronization reports that a mapped POS item no longer exists:

* Do not silently map another item
* Do not delete the ecommerce product
* Disable purchasing
* Show an admin integration warning
* Preserve order history
* Preserve online content




## 42A. Missing POS Item Lifecycle

A mapped POS item may become unavailable because it was disabled, archived, deleted outside the protected POS workflow, lost during a data restoration problem, or otherwise no longer returned by the POS API.

The ecommerce application must distinguish between:

```text
POS item inactive
```

and:

```text
POS item missing
```

Inactive means the POS record still exists but is not currently sellable.

Missing means the previously mapped `pos_item_id` can no longer be found by POS.


## 42B. Do Not Delete the Ecommerce Product

When a mapped POS item becomes missing, do not automatically delete:

* Ecommerce product
* Online name
* Description
* Images
* Category
* SEO information
* Related products
* Wishlist relationships
* Existing cart history
* Order history
* Reporting history

The ecommerce product remains a valid online content and historical record even when its physical POS mapping is broken.


## 42C. Preserve the Missing POS Item Identifier

When POS reports that a mapped item no longer exists, do not immediately set:

```text
pos_item_id = null
```

Keep the previous POS item identifier for troubleshooting and audit.

Set an integration state such as:

```text
pos_sync_status = missing_pos_item
```

The admin can then see which POS item identity was previously linked.


## 42D. Disable New Purchase

A missing POS mapping must make the affected inventory unavailable for new purchase.

For a simple POS linked product:

* Disable Add to Cart
* Disable Buy Now
* Prevent checkout
* Keep the product page if ecommerce visibility settings allow it

For a true ecommerce variant:

* Disable only the affected variant combination
* Keep other valid variants sellable

Do not mark the entire parent product unavailable when other independently mapped variants still work.


## 42E. Existing Cart Revalidation

A customer may already have the affected product in the cart when its POS mapping becomes missing.

Do not silently remove the item without explanation.

At cart review or checkout validation:

* Detect the missing mapping
* Mark the line unavailable
* Prevent payment initialization
* Ask the customer to remove or replace the unavailable line

Example customer message:

```text
One or more items in your cart are currently unavailable.
Please review your cart before continuing.
```


## 42F. Never Automatically Remap

The ecommerce application must never guess a replacement POS item based on:

```text
product name
barcode
SKU
price
category
similar description
```

A newly created POS item with the same name is still a different POS item identity.

Only an explicit admin remapping action may replace the missing mapping.


## 42G. Admin Missing Mapping Warning

The ecommerce admin area must clearly show missing mapping problems.

Recommended information:

```text
Online Product
Previous POS Item ID
Previous POS Item Name Snapshot
Mapping Status
Last Successful POS Sync
Last Mapping Error
```

Recommended actions:

```text
Remap POS Item

Convert to Ecommerce Only
```

The second action is only available when business and inventory rules permit it.


## 42H. Remap to Another POS Item

The admin may explicitly remap a missing product or variant to another POS item from the already connected POS branch.

Recommended workflow:

```text
Open affected online product

Choose Remap POS Item

Search connected POS branch

Select replacement POS item

Validate replacement

Register new mapping with POS

Synchronize standard price

Synchronize availability

Synchronize POS selling units

Save mapping

Revalidate storefront availability
```

Do not allow free text POS item ids.


## 42I. Safe Remapping Rules

Before completing remapping:

* Replacement item must belong to the connected POS branch
* Replacement item must exist
* Replacement item must be active where required
* Replacement mapping must not violate duplicate mapping rules
* Any unresolved reservation tied to the old mapping must be reviewed
* Historical order snapshots must not be rewritten

Remapping affects future sales only.


## 42J. Convert Missing POS Product to Ecommerce Only

A previously POS linked product may be intentionally converted to:

```text
inventory_source = ecommerce
```

but this must never happen automatically.

The admin must explicitly choose the conversion.

Before conversion:

* Confirm there are no unresolved POS reservations for the product
* Confirm no pending POS inventory confirmation exists
* Require a local ecommerce stock quantity
* Require a local ecommerce standard price
* Remove or unregister the active POS mapping
* Preserve the previous POS item snapshot for history where useful
* Preserve all completed order snapshots

After conversion, ecommerce becomes the inventory and standard price authority for future sales.


## 42K. Do Not Use Cached POS Stock After Mapping Is Missing

A POS linked product may still have a previously synchronized value in:

```text
products.stock
```

When:

```text
pos_sync_status = missing_pos_item
```

that cached quantity must not be treated as sellable inventory.

The missing mapping state overrides cached stock.


## 42L. Do Not Use Cached POS Price for New Checkout After Mapping Is Missing

A previously synchronized:

```text
products.price
```

may remain for display or administrative history.

It must not authorize a new POS linked checkout when the mapping is missing.

A valid inventory identity is required before new purchase.


## 42M. Existing Orders Remain Untouched

Deleting, disabling, or remapping a POS item must never rewrite old ecommerce orders.

Historical `order_items` must keep:

* Online product name snapshot
* Variant snapshot
* POS item id snapshot where stored
* Selected POS selling unit snapshot
* Quantity
* Base quantity snapshot where relevant
* Standard price snapshot
* Actual customer price

A customer who purchased an item before the POS mapping disappeared must still see a complete order history.


## 42N. Existing Payment and Fulfilment Records Remain Untouched

If an order was already paid and its POS reservation was successfully confirmed, a later missing item mapping must not invalidate the old payment.

The ecommerce application should continue using historical order snapshots for fulfilment and support records.

Do not attempt to recreate a deleted POS item automatically.


## 42O. Variant Missing Mapping Behavior

Example:

```text
Nike Polo

Blue Small
to
POS Item 501

Blue Medium
to
POS Item 502

Black Small
to
POS Item 503
```

If POS Item 502 becomes missing:

```text
Blue Medium
```

becomes unavailable.

The other valid combinations remain sellable.

The parent product can remain published.


## 42P. POS Selling Unit Missing Behavior

If the POS item still exists but an additional POS selling unit disappears:

* Keep the parent POS item mapping
* Disable only the missing purchase unit
* Keep valid POS selling units available
* Show an admin synchronization warning
* Revalidate carts that selected the missing unit

Do not mark the whole product missing merely because one additional selling unit no longer exists.


## 42Q. Mapping Registry Synchronization

Whenever ecommerce:

* Creates a POS linked product
* Creates a POS mapped inventory variant
* Remaps a product
* Remaps a variant
* Converts a POS linked product to Ecommerce Only
* Deletes or retires an online mapping

it must synchronize the mapping state with the POS mapping registry.

This registry allows POS to protect actively mapped items from unsafe hard deletion.


## 42R. Mapping Registration Failure

If ecommerce saves a local mapping but POS mapping registration fails, do not treat the mapping as fully healthy.

Use a status such as:

```text
mapping_pending
```

or:

```text
mapping_sync_failed
```

Retry safely.

Do not allow checkout that depends on a mapping whose POS side registration has never been confirmed if deletion protection could therefore be bypassed.


## 42S. Mapping Unregister Failure

If ecommerce removes a mapping locally but POS unregister fails:

* Record the synchronization failure
* Retry the unregister operation
* Keep enough previous mapping information to complete the retry
* Warn the ecommerce admin

The POS may continue blocking deletion until the unregister operation is successfully reconciled.

This is safer than silently assuming the POS mapping registry updated.


## 42T. Full Mapping Reconciliation

Provide a controlled reconciliation operation that can send the currently active ecommerce POS mappings to the connected POS integration.

This allows both systems to repair mapping registry drift.

Reconciliation must never automatically choose a different POS item.


## 42U. Missing Mapping Notifications

Missing mappings and mapping synchronization failures should appear in the ecommerce admin integration status area.

Useful indicators include:

```text
Mapped Products

Missing POS Items

Inactive POS Items

Mapping Sync Failures

Last Mapping Reconciliation
```


## 42V. Missing Item Tests

Test all of the following:

* POS item inactive disables new purchase but preserves mapping
* Missing POS item preserves ecommerce product
* Missing POS item preserves previous `pos_item_id`
* Missing POS item overrides cached stock
* Missing POS item blocks checkout
* Existing cart line becomes unavailable during revalidation
* Customer payment is not initialized for missing mapping
* Admin receives missing mapping warning
* Admin can explicitly remap to another POS item
* Remap does not change historical orders
* Admin can explicitly convert to Ecommerce Only when safe
* Conversion requires local stock and price
* System never remaps by matching name or barcode automatically
* Missing one variant disables only that variant
* Missing one POS selling unit disables only that selling unit
* Existing completed orders remain readable
* Mapping registration and unregister failures are retryable
* Full mapping reconciliation can repair registry drift




## 42W. Initial POS Catalog Synchronization After Connection

Immediately after a POS branch connection is successfully established, the ecommerce application should perform an initial branch catalog synchronization.

The ecommerce administrator must not manually recreate every POS item.

Recommended flow:

```text
POS Super Admin connects branch
to
connection verified
to
ecommerce requests branch POS catalog
to
ecommerce stores synchronized POS catalog
to
admin chooses which POS items become online products
```

The initial catalog synchronization is an administrative inventory discovery step.

It does not automatically publish all POS items online.


## 42X. Separate POS Catalog From Ecommerce Products

Maintain a clear distinction between:

```text
Synchronized POS Catalog
```

and:

```text
Ecommerce Products
```

The synchronized POS catalog represents items that exist in the connected POS branch.

The ecommerce `products` table represents products that the online store has actually prepared and chosen to sell.

This allows a POS branch to contain many items while only a selected subset appears online.


## 42Y. Recommended Synced POS Catalog Table

A dedicated local cache table is recommended.

Example:

```text
synced_pos_items
```

Suggested fields:

```text
id
pos_item_id
pos_item_name
barcode nullable
item_reference nullable
sku nullable
is_active
base_unit_name
standard_price
effective_availability
imei_tracking_enabled
pos_updated_at
last_synced_at
mapping_status
created_at
updated_at
```

Additional selling units may be stored in a related table or another normalized local cache structure.

This table is a synchronized POS catalog cache.

It is not an ecommerce product table and is not a second physical inventory authority.


## 42Z. Initial Catalog Actions

For each synchronized POS catalog item, ecommerce admin should be able to choose one of the following:

```text
Import as Simple Product

Use for Variant Mapping

Ignore
```

Ignore means the POS item remains available in the synchronized catalog but is not sold online.

Ignoring an item does not disable or modify it in POS.


## 42AA. Import as Simple Product

When admin selects:

```text
Import as Simple Product
```

the system should automatically:

* Create or prepare an ecommerce product draft
* Set `inventory_source = pos`
* Store the POS item mapping
* Register that mapping with POS
* Synchronize current standard price
* Synchronize current availability
* Synchronize the POS base unit
* Synchronize all available POS selling units
* Preserve ecommerce control over online name, description, category, images, attributes, SEO, visibility, and promotional pricing

The administrator should not need to select the same POS item again after importing it from the synchronized catalog.


## 42AB. Draft First Import

Bulk or individual POS item import should preferably create ecommerce products as:

```text
draft
```

or unpublished records.

This allows ecommerce admin to complete:

```text
online name
description
images
category
attributes
SEO
online selling unit selection
```

before the product becomes visible to customers.


## 42AC. Bulk POS Product Import

The ecommerce admin should be able to select several synchronized POS items and import them together as draft simple products.

Example:

```text
select Milo
select Peak Milk
select Indomie
select Coca Cola

Import Selected
```

The system should create one ecommerce product mapping for each selected POS item.

Do not require one by one manual mapping for normal simple products.


## 42AD. Import All Eligible Items

An optional:

```text
Import All Eligible POS Items
```

action may be provided.

Imported products should still default to draft or unpublished unless the business explicitly requests automatic publication.

This prevents incomplete products from appearing publicly without images or online descriptions.


## 42AE. Automatic POS Selling Unit Import

When a simple POS item is imported, its POS selling units should synchronize automatically.

Example:

```text
Milo

Base Unit
Single

Additional Unit
Roll

Additional Unit
Carton
```

The ecommerce administrator should not manually map Single, Roll, or Carton.

They already belong to the selected POS item.

The ecommerce admin may decide which synchronized selling units are enabled online.


## 42AF. Selling Unit Online Visibility

For a linked POS item with several selling units, ecommerce admin may choose:

```text
Single
enabled

Roll
enabled

Carton
disabled
```

Disabling a POS selling unit online does not delete or disable it in POS.

It only controls whether ecommerce customers can select that selling unit.


## 42AG. Use for Variant Mapping

When admin selects:

```text
Use for Variant Mapping
```

the POS item remains in the synchronized catalog and becomes selectable inside an ecommerce variant mapping workflow.

The POS item should not automatically create its own separate public product page unless admin also imports it as a simple product.


## 42AH. Variant Mapping Uses Synced POS Catalog

True ecommerce variant mapping must use the already synchronized POS catalog.

Example:

```text
Nike Polo

Blue plus Small
Select POS Item
Nike Polo Blue Small

Blue plus Medium
Select POS Item
Nike Polo Blue Medium
```

The selector should search the local synchronized POS catalog and may refresh against POS when needed.

Admin must not type POS item ids manually.


## 42AI. New POS Item Discovery After Initial Connection

After the initial connection, future catalog synchronization should identify newly created POS items.

The ecommerce admin should be able to view a section such as:

```text
New POS Items Available
```

These items remain unpublished until admin chooses to import or map them.


## 42AJ. Remapping a Simple POS Product

A simple POS linked ecommerce product must support explicit remapping to another POS item.

Recommended action:

```text
Remap POS Item
```

Safe flow:

```text
open product
to
choose Remap POS Item
to
search synchronized POS catalog
to
select replacement POS item
to
validate replacement
to
register new mapping with POS
to
synchronize price
to
synchronize availability
to
synchronize selling units
to
retire old mapping
```

Historical orders remain unchanged.


## 42AK. Convert Simple POS Product to Variant Product

An existing simple POS linked ecommerce product must be able to become a true variant product later without deleting and recreating the ecommerce product.

Example initial state:

```text
Nike Polo
to
POS Item 501
```

Later:

```text
Nike Polo

Blue plus Small
to
POS Item 501

Blue plus Medium
to
POS Item 502

Black plus Small
to
POS Item 503

Black plus Medium
to
POS Item 504
```

The ecommerce product itself remains the same online product record.


## 42AL. Preserve Ecommerce Product Identity During Conversion

Converting a simple POS linked product into a variant product must preserve:

```text
ecommerce product id
online name
slug
url key
description
images
category
attributes
SEO information
featured state
wishlist relationships
review relationships where present
related products
historical order relationships
```

Only the inventory mapping structure changes.


## 42AM. Transactional Simple to Variant Conversion

The conversion must be transactional from an integration perspective.

Recommended flow:

```text
admin defines variant structure
to
admin maps each required variant
to
system validates every mapped POS item
to
system registers new variant mappings with POS
to
system confirms all required mappings
to
system switches product inventory structure
to
old simple mapping is retired only if no longer directly used
```

If any required mapping fails, keep the existing simple product mapping active and do not partially convert the product.


## 42AN. Active Reservations Block Conversion

Do not allow:

```text
Simple POS Product
to
Variant POS Product
```

while unresolved reservations exist against the current mapping.

The admin should see a message explaining that active ecommerce reservations must first be confirmed, released, or expired.

This prevents future inventory identity from changing while an existing customer order still relies on the old mapping.


## 42AO. Existing Cart Revalidation After Conversion

A customer may already have the old simple product in a cart when the product is converted to a variant product.

The ecommerce application must not guess which new variant the customer intended.

At the next cart review or checkout:

* Detect that the product inventory structure changed
* Require the customer to select a valid variant
* Revalidate price
* Revalidate selling unit where applicable
* Revalidate availability
* Prevent payment until the selection is valid

Example customer message:

```text
This product has been updated.
Please select your preferred option before continuing.
```


## 42AP. Existing Orders Are Not Rewritten After Conversion

A historical order created while the product was simple remains a simple historical order snapshot.

Do not rewrite it to a new variant merely because its old POS item later becomes one of the variant mappings.

Mapping changes affect future purchases only.


## 42AQ. Individual Variant Remapping

Each inventory significant variant mapping should have an explicit:

```text
Remap POS Item
```

action.

Example:

```text
Blue plus Medium
currently maps to POS Item 502
```

Admin may change it to:

```text
Blue plus Medium
maps to POS Item 602
```

Safe flow:

```text
validate POS Item 602
to
register new mapping
to
synchronize price and availability
to
activate new mapping
to
retire old mapping to Item 502
```

Historical order snapshots remain unchanged.


## 42AR. Variant Remapping Reservation Protection

Do not remap a variant while unresolved reservations depend on the old POS item mapping.

Resolve those reservations first.

A reservation created against POS Item 502 must remain associated with POS Item 502 even if future sales of the variant later use POS Item 602.


## 42AS. Optional Conversion Back to Simple Product

A future admin action may support:

```text
Convert to Simple Product
```

but this is a guarded inventory restructuring operation.

It should require:

* No unresolved reservations on variant mappings
* Selection of the POS item that will become the simple mapping
* Successful POS mapping registration
* Revalidation of carts
* Preservation of historical variant order snapshots

For the first release, this conversion may remain disabled if it adds unnecessary complexity.


## 42AT. Mapping Status in Synced POS Catalog

The synchronized POS catalog should show whether each POS item is:

```text
Unmapped

Mapped as Simple Product

Mapped to Variant

Ignored

Inactive

Missing
```

This gives ecommerce admin a clear picture of how the branch catalog is being used online.


## 42AU. Do Not Auto Group POS Items Into Variants

The ecommerce application must never automatically decide that several POS items are variants of one online product merely because their names are similar.

Example:

```text
Nike Polo Blue Small
Nike Polo Blue Medium
Nike Polo Black Small
```

The system may offer search and selection assistance, but admin must explicitly create the online variant structure and choose the corresponding POS item for each combination.


## 42AV. Initial Catalog and Remapping Tests

Test all of the following:

* First connection automatically triggers or offers initial POS catalog synchronization
* Synced POS catalog remains separate from ecommerce products
* POS catalog items do not automatically publish online
* Simple product can be imported directly from catalog
* Bulk import creates draft products
* POS selling units synchronize automatically
* Admin can disable selected selling units online without changing POS
* POS item can be used for a variant mapping
* New POS items appear after later synchronization
* Simple product can be remapped to another POS item
* Simple product can be converted to variant structure without recreating ecommerce product
* Product id, images, description, SEO, wishlist, and history survive conversion
* Conversion fails safely if any required mapping fails
* Active reservations block unsafe conversion
* Old carts require new variant selection after conversion
* Historical orders remain unchanged
* Individual variant can be remapped
* Variant remapping preserves old reservation identity
* Similar POS names are never auto grouped into variants


## 43. Price Synchronization

Price changes in POS should synchronize to ecommerce.

When the POS standard price changes:

```text
products.price
```

must update for the base unit.

Relevant:

```text
product_pos_units.synced_standard_price
```

records must update for additional units.

Existing completed orders must not change because `order_items.price` is already a historical snapshot.


## 44. Customer Cart Price Changes

A customer may add an item to cart and remain there while the POS standard price changes.

Before order creation, ecommerce must refresh or validate the latest synchronized standard price.

If the current price differs from the cart price, the cart should update to the latest valid ecommerce effective price and clearly inform the customer before payment.

Do not charge using indefinitely stale synchronized prices.


## 45. Ecommerce Effective Price

The ecommerce effective price remains calculated by ecommerce.

Conceptually:

```text
Synchronized POS standard price
then
Ecommerce discount rules
then
Ecommerce special price window
then
Future coupon or referral logic if later implemented
```

POS is not asked to approve the final online marketing price.


## 46. Shopping Cart Does Not Reserve POS Stock

Adding a product to cart must not create a POS reservation.

Customers abandon carts frequently.

The cart may perform local availability checks for user experience, but this is not a stock guarantee.


## 47. Meaningful Reservation Stage

POS reservation should happen when the customer reaches a meaningful checkout commitment.

For the current application, the recommended point is during order processing immediately before payment initialization or final Cash on Delivery order acceptance.

The reservation API is the authoritative final stock check.


## 48. External Reservation Reference

Every ecommerce order attempt that needs POS reservation must use a stable unique external reference.

Do not depend solely on browser session id.

Recommended:

```text
inventory_reference
```

on the `orders` table.

Use a generated UUID or another collision safe identifier.

The same reference must be reused for:

* Reservation retry
* Reservation lookup
* Reservation confirmation
* Reservation release


## 49. Recommended Order Fields

Extend `orders` with integration information such as:

```text
inventory_reference
pos_reservation_status
pos_reservation_expires_at
pos_reservation_confirmed_at
pos_reservation_released_at
pos_sync_error
```

Do not store raw POS API secrets on orders.


## 50. Reservation Before Payment

For Paystack and Flutterwave, recommended flow:

```text
Customer submits checkout
Validate ecommerce cart
Refresh current mapped product information where required
Generate inventory reference
Request POS reservation
If reservation fails stop checkout
If reservation succeeds create ecommerce order
Initialize payment gateway
Redirect customer to payment
```

If local order creation fails after POS reservation succeeds, ecommerce must immediately attempt to release the reservation using the same reference.


## 51. Why Reservation Happens Before Gateway Redirect

Do not send a customer to Paystack or Flutterwave for an item that the POS could not reserve.

The reservation must succeed before payment initialization.

This prevents receiving successful payment for stock that was already unavailable when checkout began.


## 52. Paystack Successful Payment

The current Paystack webhook verifies:

* Gateway signature
* Successful event
* Paid amount

After the payment is securely verified, ecommerce should confirm the existing POS reservation.

Do not create another reservation.

Use the same:

```text
inventory_reference
```

If POS confirmation succeeds, continue the normal ecommerce order workflow.

If POS confirmation temporarily fails because of network or POS service availability, record a synchronization error and queue a controlled retry.

Do not charge the customer again.


## 53. Flutterwave Successful Payment

Apply the same principle.

After the existing Flutterwave verification succeeds:

* Find the order
* Use the existing inventory reference
* Confirm the POS reservation
* Update the ecommerce order according to the canonical status workflow
* Record any synchronization failure for retry

Never create a second reservation after successful payment.


## 54. Failed or Abandoned Online Payment

If payment fails or the customer never completes payment:

* The order must not consume physical stock permanently
* The reservation may be explicitly released when failure is known
* Otherwise POS reservation expiry provides the safety mechanism

Ecommerce should also reconcile its local reservation status during scheduled jobs.


## 55. Cash on Delivery

Cash on Delivery requires a different inventory decision because payment is not verified online.

Recommended behavior:

* Create POS reservation when the COD order is formally placed
* Keep the ecommerce order pending
* Confirm the POS reservation when the order is accepted for fulfilment or moved into the configured fulfilment state
* Release the reservation if the admin rejects or cancels the order before confirmation

Do not permanently deduct stock merely because a visitor selected Cash on Delivery and abandoned the flow.


## 56. Pickup Orders

Pickup is an ecommerce delivery method.

It does not change the inventory authority.

The same reservation rules apply.

The ecommerce application remains responsible for:

* Pickup address
* Customer instructions
* Pickup status
* Customer communication


## 57. Canonical Order Status Workflow

The existing documentation contains inconsistent status descriptions.

One section describes:

```text
pending
processing
completed
cancelled
```

while the admin workflow also references:

```text
pending
paid
shipped
completed
cancelled
```

This must be normalized in the clone.

Recommended canonical statuses:

```text
pending
paid
processing
shipped
completed
cancelled
```

If refund and return workflows are added later, introduce explicit states instead of overloading `cancelled`.


## 58. Inventory Meaning of Order Status

The ecommerce order status and POS reservation status are related but not identical.

Recommended interpretation:

```text
pending
```

may have an active reservation.

```text
paid
```

should have a confirmed POS reservation for online paid orders.

```text
processing
```

means ecommerce fulfilment has started.

```text
shipped
```

remains ecommerce shipping state.

```text
completed
```

remains ecommerce completion state.

```text
cancelled
```

must release an active reservation if stock has not yet been confirmed.


## 59. Do Not Infer POS Inventory From Shipping

POS should not wait for:

```text
shipped
```

or:

```text
completed
```

unless the business explicitly chooses that later.

Inventory confirmation should happen at a clearly defined ecommerce commitment point such as verified payment or accepted COD fulfilment.

Shipping remains an ecommerce concern.


## 60. Cancellation Before Confirmation

If an ecommerce order is cancelled while POS reservation status is still active:

```text
release reservation
```

Do not increase `front_store_qty`.

POS never deducted it yet.


## 61. Cancellation After Confirmation

If stock has already been confirmed and deducted, cancellation alone must not automatically restore physical inventory.

Payment refund does not prove that the product has physically returned.

Use a proper return and restock workflow.


## 62. Return and Restock

A future or existing return workflow should distinguish:

* Financial refund
* Physical return received
* Physical return inspected
* Return accepted back into sellable stock

Only after the physical item is accepted back into inventory should ecommerce call the POS restock operation if the POS integration exposes one.

Do not restore stock merely because money was refunded.


## 63. Partial Cancellation

If future ecommerce behavior supports cancelling only part of an order, the integration must support line level release or return behavior before enabling that feature.

Do not fake partial inventory changes by editing local `products.stock`.


## 64. Reservation Expiry

The POS reservation system supports expiry.

The ecommerce store should know the reservation expiry timestamp.

Add a configurable ecommerce setting such as:

```text
pos_reservation_minutes
```

only if the POS contract allows the ecommerce client to propose the expiry duration.

Otherwise use the expiry returned by POS.

Do not independently assume that a reservation remains valid forever.


## 65. Local Stock Display

For normal browsing, product cards and product pages should use the synchronized stock cache for speed.

The current `show_stock_level` feature can continue.

Possible storefront states include:

```text
In Stock
Low Stock
Out of Stock
```

or exact quantity when configured.

The displayed value is informational until the POS reservation succeeds.


## 66. Add to Cart Validation

The cart should prevent obviously impossible quantities using the synchronized availability cache.

Example:

```text
cached stock = 5
customer requests 20
```

The UI can reject the request immediately.

However the final authoritative check remains the POS reservation API during checkout.


## 67. Live Reservation Failure

If the synchronized cache says stock is available but POS reservation fails because another customer or physical cashier used the stock:

* Do not create a paid order
* Do not initialize online payment
* Refresh the affected product availability
* Return the customer to a clear stock adjustment state
* Explain which item quantity is no longer available
* Allow cart correction and retry


## 68. Ecommerce Reports Remain Ecommerce Reports

Preserve ecommerce reporting as an independent system.

Current ecommerce reports include revenue metrics such as:

* Total revenue
* Average order value
* Daily earnings
* Payment method breakdown

Those remain based on ecommerce orders.

Do not query POS sales tables for ecommerce revenue reporting.


## 69. Inventory Audit Relationship

The POS only needs enough ecommerce information to explain why physical inventory changed.

Ecommerce keeps the detailed order.

Example POS inventory reference:

```text
Source = Ecommerce
Reference = ecommerce inventory reference
```

The ecommerce system can use that reference to find the full order.


## 70. Tax Remains Ecommerce Owned

The existing ecommerce application calculates its own tax using store settings.

Keep this behavior.

Do not call POS tax settings during ecommerce checkout.

POS tax and ecommerce tax are separate configurations unless a future requirement explicitly decides to synchronize them.


## 71. Shipping Remains Ecommerce Owned

Keep:

* Shipping enabled setting
* Waybill fee
* Pickup
* Pickup address
* Free shipping threshold
* Delivery method
* Customer delivery address

Do not send shipping calculations into POS inventory API.


## 72. Service Fee Remains Ecommerce Owned

The existing service fee logic remains ecommerce only.

POS does not need to know the service fee.


## 73. Payments Remain Ecommerce Owned

Keep:

* Paystack
* Flutterwave
* Cash on Delivery
* Payment references
* Payment audit records
* Gateway response logs
* Payment webhooks

Do not send the customer through POS payment logic.


## 74. Customers Remain Ecommerce Owned

The customer guard remains separate from POS users.

Do not merge:

```text
customers
```

with POS staff or POS users.

Customer authentication remains ecommerce specific.


## 75. Admin Users Remain Ecommerce Owned

The ecommerce `web` guard continues serving ecommerce admin and staff.

Do not authenticate ecommerce administrators against POS.

The only shared relationship is the server integration credential.


## 76. Product Search

Storefront search should continue querying ecommerce products locally.

Do not call POS search API for every customer search.

This protects performance and keeps online naming independent.

POS synchronization updates the ecommerce cache in the background.


## 77. Related Products

Keep ecommerce related product behavior.

Related products are online merchandising information.

Do not derive related products from POS unless later requested.


## 78. Wishlist

Wishlist remains completely local to ecommerce.

No POS call is required when a customer adds or removes a wishlist item.


## 79. Product Comparison

Comparison remains ecommerce only.

No POS call is required except the normal synchronized price and availability already stored on the product.


## 80. Reviews

If reviews are enabled later, they remain ecommerce data.

Do not send reviews into POS.


## 81. Synchronization Strategy

Use a combination of:

* Initial POS catalog fetch
* Periodic synchronization
* Manual admin refresh for troubleshooting
* Live POS reservation at checkout

The first implementation does not require real time webhooks if periodic synchronization plus final live reservation validation is sufficient.


## 82. Incremental Synchronization

Where POS API supports timestamps, request only items changed since the last successful sync.

Store:

```text
last_catalog_sync_at
```

and:

```text
pos_item_updated_at
```

This reduces unnecessary full catalog downloads.


## 83. Full Synchronization

Provide a controlled full synchronization operation for:

* First setup
* Recovery
* Troubleshooting
* Major mapping changes

Do not run a complete catalog refresh on every storefront page request.


## 84. Synchronization Frequency

Use a sensible scheduled frequency for price and availability refresh.

The exact interval can remain configurable.

Checkout does not rely solely on the schedule because reservation provides the final live validation.


## 85. Queue Usage

The ecommerce application already has database queue infrastructure.

Use queued jobs where appropriate for:

* Large catalog synchronization
* Retry of temporary POS confirmation failures
* Availability refresh
* Connection health checks
* Synchronization logs

Do not queue the initial customer facing reservation so far into the background that the customer proceeds without knowing whether stock was secured.


## 86. Recommended Jobs

Suggested jobs:

```text
SyncPosCatalog
SyncPosAvailability
RetryPosReservationConfirmation
ReconcilePosReservationStatuses
CheckPosConnectionHealth
```

Names may be adjusted to existing project conventions.


## 87. Scheduler

Use Laravel scheduler for periodic tasks such as:

* Catalog synchronization
* Availability synchronization
* Reservation reconciliation
* Connection health checks

Do not require manual admin action for routine synchronization.


## 88. Integration Logging

Create useful logs for:

* Connection test
* Catalog sync started
* Catalog sync completed
* Item sync failure
* Availability sync failure
* Reservation created
* Reservation rejected
* Reservation confirmed
* Reservation released
* POS timeout
* Authentication failure
* Mapping error

Do not log secrets.


## 89. Recommended Sync Log Table

Optional dedicated table:

```text
pos_sync_logs
```

Suggested fields:

```text
id
type
status
reference
message
context
started_at
completed_at
created_at
updated_at
```

Sanitize `context` before storage so credentials are never recorded.


## 90. Admin Integration Status

Add a read only ecommerce admin integration status area.

It may show:

* Connected POS branch name
* POS connection status
* Last successful connection
* Last catalog sync
* Last inventory sync
* Number of mapped products
* Products with mapping errors
* Recent synchronization errors

It must not allow changing the POS branch.


## 91. Product Admin Changes

The ecommerce Product Admin should clearly separate:

POS controlled fields:

```text
POS item mapping
Standard POS price
POS availability
POS unit conversion
POS unit standard price
```

from ecommerce controlled fields:

```text
Online name
Descriptions
Images
Online category
Attributes
Featured state
New arrival state
Visibility
Related products
Discount price
Special price
Special price dates
```

POS controlled fields should appear read only.


## 92. Product Creation Without POS Mapping

For this integrated client deployment, a product intended for sale should normally require a POS item mapping.

If the business needs non inventory online products later, such as digital services, define that separately.

Do not silently allow manually created stock products to bypass POS inventory authority.


## 93. Existing Products in the Fresh Clone

Because this is a fresh clone for a new client, old sample products and old inventory values should not be treated as production truth.

The project may be reset and seeded appropriately.

Do not migrate fake or template inventory into the new client store as if it were POS synchronized inventory.


## 94. Fresh Clone Migration Strategy

This ecommerce instance is being prepared from a fresh clone specifically for this integration.

Unlike the live POS, the ecommerce clone is not constrained by existing live production transaction history.

Therefore the agent may cleanly modify ecommerce migrations and schema before deployment if the database can be safely recreated.

Recommended approach during development:

```text
modify ecommerce schema cleanly
reset local database
run migrations
seed only required configuration
perform initial POS connection
synchronize products
```

Once this ecommerce instance goes live with real customer orders, future schema changes must use normal additive migrations.


## 95. Do Not Modify POS Database From Ecommerce

The ecommerce application must never connect directly to the POS MySQL database.

Do not use a second Laravel database connection to write into POS tables.

All shared operations go through the authenticated POS integration API.


## 96. No POS Cashier Checkout Calls

The ecommerce application must never call:

* POS cashier sale controller
* POS cart controller
* POS receipt controller
* POS discount service
* POS cashier authentication
* POS offline sale queue

The dedicated POS commerce API is the only integration surface.


## 97. Idempotency

Network retries and gateway webhook retries are normal.

The ecommerce application must reuse the same external inventory reference.

Do not generate a new reservation reference each time a timeout occurs.

Repeated calls must be safe against:

* Duplicate reservation
* Duplicate confirmation
* Duplicate release


## 98. Concurrency

Never trust only local ecommerce stock cache during checkout.

Two online customers can attempt the final unit simultaneously.

The POS reservation endpoint decides who successfully reserves the quantity.

Ecommerce handles rejection gracefully.


## 99. POS Offline Cashier Limitation

The POS supports offline cashier sales.

A completely disconnected POS device cannot know about a new ecommerce reservation created on the POS server after that device went offline.

This is an accepted offline limitation.

Do not attempt to solve it from ecommerce.

The POS synchronization and conflict system handles reconciliation after the till reconnects.


## 100. IMEI Tracked Items

The ecommerce storefront normally must not expose IMEI numbers to customers.

For an IMEI tracked POS item:

* Display online availability from the POS integration
* Reserve quantity through the normal ecommerce reservation call
* Let POS allocate or validate serialized units according to POS rules
* Do not duplicate IMEI records in ecommerce as a competing inventory system

The online order may store returned allocation references later if POS provides them for fulfilment or audit.


## 101. Batch Tracked Items

Do not create an independent ecommerce batch system merely because the POS item uses batch tracking.

Ecommerce sells the mapped product.

POS remains responsible for its batch side effects and future FEFO behavior.

Do not make customers select a POS batch during normal online checkout unless a future business requirement explicitly asks for it.


## 102. Barcode

Barcode is not the cross system primary key.

The ecommerce application may synchronize barcode for administration or search if useful.

Mapping remains:

```text
pos_item_id
```

Do not remap automatically because a barcode happens to match.


## 103. SKU

SKU may remain useful for online display or administration.

Do not use SKU as the only authoritative POS mapping key.


## 104. Product Delete Behavior

Deleting an online product must not delete the POS item.

Prefer archiving or unpublishing online products when historical orders exist.

The ecommerce system owns only its online product presentation record.


## 105. POS Disconnect Behavior

If the POS connection becomes disabled or revoked:

* Existing ecommerce orders remain intact
* Existing customer accounts remain intact
* Existing online product content remains intact
* Product purchase should fail safely when current stock cannot be reserved
* Admin should see a clear integration warning
* Do not delete synchronized products automatically


## 106. POS API Temporary Failure

Browsing can continue using the most recently synchronized catalog cache.

Final checkout reservation cannot be assumed successful while POS is unreachable.

If POS inventory verification is unavailable:

* Do not initialize payment for unreserved stock
* Show a temporary availability message
* Allow customer to retry
* Preserve cart


## 107. Payment Succeeds but POS Confirmation Is Temporarily Unavailable

This is a critical recovery case.

The customer may already have paid.

Do not mark the payment as failed.

Record:

```text
payment verified
inventory confirmation pending
```

Queue controlled retries using the same inventory reference.

Surface the issue to ecommerce admin.

The operation remains idempotent.


## 108. Reservation Succeeds but Order Creation Fails

Attempt immediate POS reservation release using the same inventory reference.

Log the failure.

If release also fails, reconciliation must find and release the orphan reservation later.


## 109. Order Cancellation and Reservation State

The order cancellation code must inspect local POS reservation state.

If still reserved:

```text
release
```

If already confirmed:

```text
do not restore automatically
```

Use return workflow for confirmed physical stock.


## 110. Store Settings

Continue using the existing `store_settings` pattern for normal ecommerce business configuration.

Possible new non secret settings include:

```text
pos_integration_enabled_display
pos_sync_interval_minutes
pos_show_connection_status
```

Secrets should live in the dedicated encrypted connection record rather than ordinary plain setting values.


## 111. Existing Store Settings Stay Independent

Do not synchronize POS settings into:

* company_name
* company_tagline
* company_email
* company_phone
* company_address
* company_logo
* tax_enabled
* tax_rate
* service_fee_enabled
* service_fee_amount
* shipping_enabled
* waybill_fee
* pickup_enabled
* pickup_address
* free_shipping_threshold
* customer_registration_enabled
* guest_checkout_enabled
* wishlist_enabled
* reviews_enabled

These are ecommerce settings.


## 112. Store Branding

The ecommerce storefront is independently branded.

The connected POS branch name does not have to overwrite:

```text
company_name
```

unless the client explicitly wants them identical.

Connection identity and storefront branding are separate concepts.


## 113. Search Suggestions

The existing live search endpoint should continue using ecommerce product data.

Use synchronized:

```text
price
stock
```

cache values locally.

Do not make each keystroke call POS.


## 114. Homepage Products

Featured products and new arrivals continue being selected by ecommerce.

The POS does not decide homepage merchandising.


## 115. Admin Dashboard

Keep ecommerce KPIs such as:

* Total Orders
* Total Revenue
* Total Products
* Registered Customers
* Recent Orders

Optionally add integration health cards such as:

```text
POS Connected
Mapped Products
Last Sync
Sync Errors
```

Do not replace ecommerce dashboard analytics with POS reports.


## 116. Revenue Report

Revenue reports must use ecommerce order data and ecommerce customer prices.

Do not substitute POS standard prices for historical online order revenue.

If a customer paid a discounted online price, the ecommerce report must use the actual amount paid.


## 117. Invoice

The ecommerce invoice remains based on the online order.

It may show:

* Customer details
* Ecommerce product name
* Selected online unit
* Ecommerce price
* Ecommerce discount
* Ecommerce tax
* Shipping
* Service fee
* Total

It does not need to look like a POS receipt.


## 118. Order Tracking

The existing ecommerce order tracking system remains.

The customer tracks the ecommerce order.

Do not expose POS reservation ids to customers unless there is a clear support reason.


## 119. Security

Requirements:

* POS API secrets encrypted at rest
* POS secret never exposed to frontend
* TLS in production
* Strict validation of POS responses
* Connection scoped to one branch
* Request timeout
* Rate limit awareness
* Safe retry rules
* No credential logging
* No arbitrary POS URL changes by normal ecommerce admin
* No direct POS database access


## 120. Validation of POS Responses

Never trust remote response shape blindly.

Validate:

* Required item id
* Price numeric format
* Availability numeric format
* Unit identifiers
* Conversion quantities
* Reservation status
* External reference match

Fail safely if the response is malformed.


## 121. Timeouts

A POS timeout during simple browsing synchronization may be retried later.

A POS timeout during reservation must not be interpreted as success.

Query the reservation by the same external reference before deciding whether to retry creation.


## 122. Manual Synchronization

Provide an ecommerce admin button such as:

```text
Refresh POS Data
```

This may trigger a safe synchronization job.

This is not a branch connection control.

It only refreshes data from the already connected POS branch.


## 123. Mapping Errors

Surface clear admin errors such as:

* POS item missing
* POS item inactive
* POS unit missing
* Authentication failure
* POS unavailable
* Duplicate POS mapping
* Price synchronization failure
* Availability synchronization failure

Do not hide these behind generic server errors.


## 124. Checkout Error Messages

Customer facing errors should be simple.

Examples:

```text
One or more items are no longer available in the requested quantity.
```

```text
We could not confirm product availability right now. Please try again.
```

Do not expose internal POS API details to customers.


## 125. Integration Testing

Test all of the following.


## 126. Connection Tests

Verify:

* Correct POS branch is connected
* Ecommerce cannot select another branch
* Revoked credential fails
* Rotated credential works
* Secret is not visible in frontend
* Connection status updates correctly


## 127. Catalog Tests

Verify:

* POS item can be linked once
* Duplicate mapping is rejected
* Online name can differ from POS name
* Online category can differ from POS category
* Images remain ecommerce only
* POS name change does not overwrite online name
* POS inactive item becomes unavailable
* Missing POS item produces admin warning


## 128. Price Tests

Verify:

* Base POS price synchronizes
* Additional POS unit price synchronizes
* POS price change updates ecommerce standard price
* Existing completed order price does not change
* Ecommerce discount can reduce customer price independently
* POS discounts are never called


## 129. Availability Tests

Verify:

* Ecommerce uses front store quantity only
* Back store quantity never increases online availability
* Active reservations reduce effective availability
* Cached stock refreshes
* Final reservation catches stale cached stock
* Two simultaneous customers cannot both reserve unavailable final stock


## 130. Cart Tests

Verify:

* Add to cart does not reserve
* Guest cart still works
* Customer cart still works
* Cart quantity validation uses cache
* Checkout uses live reservation
* POS unit selection is stored separately from old ecommerce variant field


## 131. Paystack Tests

Verify:

* Reservation succeeds before gateway initialization
* Failed reservation blocks payment initialization
* Successful verified webhook confirms same reservation once
* Duplicate webhook does not deduct twice
* Temporary confirmation failure retries safely


## 132. Flutterwave Tests

Verify the same guarantees as Paystack.


## 133. Cash on Delivery Tests

Verify:

* COD order creates reservation
* Pending COD does not deduct permanently until accepted fulfilment stage
* Cancelled COD releases reservation
* Confirmed COD cannot be released as if stock was never deducted


## 134. Cancellation Tests

Verify:

* Cancel before confirmation releases reservation
* Cancel after confirmation does not automatically restore stock
* Return and restock follows explicit physical return logic


## 135. Synchronization Failure Tests

Verify:

* Storefront browsing can use last synchronized cache
* Checkout fails safely if reservation cannot be verified
* Cart remains available for retry
* Sync errors are visible to admin
* No secret is logged


## 136. Reporting Tests

Verify:

* Ecommerce revenue remains based on ecommerce orders
* Ecommerce reports do not use POS sales
* Online discounted price is preserved in order history
* Shipping and tax remain in ecommerce totals


## 137. IMEI Tests

Verify:

* Customer never sees IMEI list by default
* Availability reflects POS serialized stock rules
* Reservation works through POS
* Ecommerce does not maintain a competing IMEI inventory


## 138. Unit Tests

Verify:

* Base unit mapping
* Additional unit mapping
* Conversion quantity synchronization
* Independent unit price synchronization
* Disabled online unit cannot be ordered
* Removed POS unit disables affected online option safely


## 139. Fresh Deployment Tests

Verify:

* Fresh database migration succeeds
* Required store settings seed correctly
* POS connection can be established
* First catalog sync succeeds
* Online products can be built from POS items
* Checkout works after initial synchronization


## 140. Explicit Non Goals

Do not build these as part of this integration unless separately requested:

Ecommerce only products are now explicitly in scope. The items below remain outside scope:

* Multi branch ecommerce
* One ecommerce store combining several POS branches
* POS customer account synchronization
* POS staff authentication for ecommerce
* Direct POS database connection
* POS cashier checkout reuse
* Full coupon engine
* Referral engine
* Loyalty engine
* Affiliate engine
* New ecommerce shipping provider integration
* New payment gateway unrelated to the integration
* Automatic back store to front store transfer
* Customer IMEI selection
* Customer batch selection
* Full FEFO redesign
* Shopify connector
* WooCommerce connector




## 140A. Dual Inventory Source Support

The ecommerce application must support two valid product inventory sources.

Every sellable product must explicitly identify its inventory source.

Recommended field on `products`:

```text
inventory_source
```

Allowed values:

```text
pos
ecommerce
```

Meaning:

```text
pos
```

means the product is linked to the connected POS branch and its physical stock is controlled by POS.

```text
ecommerce
```

means the product exists only inside the ecommerce application and its physical stock is controlled locally by ecommerce.

This distinction must drive stock, pricing, checkout, cancellation, return, and reporting behavior.


## 140B. POS Linked Product

A POS linked product uses:

```text
inventory_source = pos
pos_item_id = valid POS item id
```

For this product type:

* POS is the inventory authority
* `front_store_qty` is the physical stock source
* Active POS ecommerce reservations reduce available quantity
* Standard price synchronizes from POS
* POS unit mappings may be synchronized
* Ecommerce does not permanently deduct local physical stock by itself
* Checkout must create a POS reservation
* Successful fulfilment must confirm that reservation
* Cancellation before confirmation must release it
* Confirmed inventory must only return through an explicit physical return workflow

The ecommerce application still owns all online presentation fields.


## 140C. Ecommerce Only Product

An ecommerce only product uses:

```text
inventory_source = ecommerce
pos_item_id = null
```

For this product type, ecommerce owns:

* Product name
* Price
* Stock quantity
* Categories
* Images
* Descriptions
* Attributes
* Discounts
* Cart behavior
* Inventory deduction
* Cancellation stock restoration where appropriate
* Return and restock behavior
* Reporting

No POS API inventory call is required for this product type.

The ecommerce application must preserve its normal local inventory behavior for these products.


## 140D. Product Creation Source Selection

The ecommerce product creation flow should begin by asking the administrator to choose the inventory source.

Conceptually:

```text
Inventory Source

POS Item

Ecommerce Item
```

If POS Item is selected:

```text
Select item from connected POS branch
```

Then the ecommerce system:

* Stores `inventory_source = pos`
* Stores `pos_item_id`
* Synchronizes standard price
* Synchronizes availability
* Loads POS unit information where applicable
* Makes POS controlled fields read only
* Allows ecommerce presentation fields to be edited

If Ecommerce Item is selected:

```text
Create Ecommerce Item
```

Then the ecommerce system:

* Stores `inventory_source = ecommerce`
* Keeps `pos_item_id = null`
* Allows local price entry
* Allows local stock quantity entry
* Uses normal ecommerce inventory logic
* Never calls POS for this product


## 140E. Validation Rules

For:

```text
inventory_source = pos
```

Require:

```text
pos_item_id
```

For:

```text
inventory_source = ecommerce
```

Require:

```text
pos_item_id = null
```

Do not allow a product to be both sources at the same time.


## 140F. Local Stock Meaning by Source

For POS linked products:

```text
products.stock
```

is only a synchronized availability cache.

For ecommerce only products:

```text
products.stock
```

is the real ecommerce controlled physical quantity.

All inventory services must inspect:

```text
inventory_source
```

before interpreting `stock`.


## 140G. Pricing Meaning by Source

For POS linked products:

```text
products.price
```

is the latest synchronized standard POS base selling price.

For ecommerce only products:

```text
products.price
```

is ecommerce owned and directly editable.

Ecommerce promotional pricing remains ecommerce controlled for both sources.


## 140H. Mixed Cart Support

A single ecommerce cart may contain both POS linked and ecommerce only products.

Example:

```text
Product A
inventory_source = pos

Product B
inventory_source = ecommerce
```

The customer still sees one normal cart.

The system handles the inventory sources internally.


## 140I. Mixed Checkout Validation

Before order creation, split cart lines logically into:

```text
POS controlled lines

Ecommerce controlled lines
```

For POS controlled lines:

* Validate mapping
* Validate current POS availability
* Reserve through the POS API

For ecommerce controlled lines:

* Validate local ecommerce stock
* Use database transaction and row locking where appropriate
* Do not call POS

The full order should proceed only when every line can be safely fulfilled.


## 140J. Atomic Mixed Checkout Principle

A mixed order must not leave one inventory source committed while the other failed silently.

Recommended sequence:

```text
Validate ecommerce only stock

Request POS reservation for all POS linked lines

If POS reservation fails
stop checkout
do not deduct ecommerce only stock

If POS reservation succeeds
create ecommerce order

Only after ecommerce order creation succeeds
continue payment or COD flow
```

If order creation fails after POS reservation, attempt to release that reservation immediately.


## 140K. POS Reservation Payload for Mixed Carts

Only POS linked lines should be sent to POS.

Never send ecommerce only products to the POS reservation API.

Example:

```text
Line 1
inventory_source = pos
pos_item_id = 125
quantity = 2

Line 2
inventory_source = ecommerce
product_id = 88
quantity = 1
```

POS receives only Line 1.

The ecommerce order still contains both lines.


## 140L. Ecommerce Only Stock Deduction

For ecommerce only products:

* Add to cart does not permanently deduct stock
* Checkout validates local stock
* Stock deduction happens only at the configured ecommerce commitment stage
* Local stock changes use database transactions
* Local stock cannot fall below zero
* Concurrent checkout requests must not oversell the final quantity


## 140M. Mixed Cancellation and Return

When cancelling or returning a mixed order, process each line by its inventory source.

For POS linked lines:

* Release an active reservation if not yet confirmed
* Do not automatically restore confirmed physical stock
* Use POS return or restock integration for physically returned goods

For ecommerce only lines:

* Use ecommerce local cancellation and return logic
* Restore local stock only when the business rules confirm the stock should return

Do not perform one blanket stock reversal for the entire order.


## 140N. Reporting With Dual Sources

Ecommerce revenue reports include both POS linked and ecommerce only products because both are ecommerce sales.

Inventory views should distinguish:

```text
POS Linked Inventory

Ecommerce Owned Inventory
```

For POS linked products, quantity is synchronized.

For ecommerce only products, quantity is locally authoritative.


## 140O. Product Admin Presentation

The product list should visibly show:

```text
POS Linked

Ecommerce Only
```

For POS linked products:

* Standard price is read only
* Availability is read only
* POS mapping is read only unless an explicit remapping workflow exists

For ecommerce only products:

* Price is editable
* Stock is editable
* No POS mapping controls are required


## 140P. Prevent Duplicate Physical Inventory

Do not create an ecommerce only product for the exact same physical stock already represented by a POS linked item.

Administrator guidance should state:

```text
If the physical product exists in POS, link it to POS.

Use Ecommerce Only only for stock that genuinely does not belong to POS inventory.
```


## 140Q. Inventory Service Abstraction

Create an internal inventory abstraction.

Suggested structure:

```text
app
Services
Inventory

InventoryManager.php

Sources
PosInventorySource.php
EcommerceInventorySource.php
```

Common operations may include:

```text
getAvailability()
validateQuantity()
reserve()
confirm()
release()
restock()
```

For POS linked products, delegate to POS integration services.

For ecommerce only products, operate locally.


## 140R. Seamless Customer Experience

The dual source architecture must be invisible to customers.

Customers should still experience:

* One storefront
* One search
* One cart
* One checkout
* One payment
* One order
* One invoice
* One tracking flow
* One order history


## 140S. Payment Behavior for Mixed Orders

Paystack, Flutterwave, and Cash on Delivery continue to process one ecommerce order total.

On verified payment:

For POS linked lines:

* Confirm existing POS reservation

For ecommerce only lines:

* Commit local stock according to ecommerce inventory rules

Repeated gateway webhooks must not deduct either source twice.


## 140T. POS Outage Behavior

If POS API is unavailable:

A cart containing only ecommerce only products may continue through checkout using local ecommerce inventory.

A cart containing one or more POS linked products cannot complete until POS inventory can be verified and reserved.

Do not disable the entire ecommerce store because POS is temporarily unavailable.


## 140U. Product Source Conversion

Do not silently switch a product between:

```text
pos
```

and:

```text
ecommerce
```

For the first release, source conversion may be disabled after product creation.

If conversion is added later, it must be an explicit guarded workflow that preserves historical order snapshots and resolves any active reservations first.


## 140V. Additional Dual Source Tests

Test:

* Create POS linked product
* Create ecommerce only product
* POS linked product requires `pos_item_id`
* Ecommerce only product requires `pos_item_id = null`
* Mixed cart works
* Mixed Paystack order works
* Mixed Flutterwave order works
* Mixed COD order works
* POS reservation failure blocks the whole mixed checkout
* Local ecommerce stock shortage blocks the whole mixed checkout
* Ecommerce only lines are never sent to POS
* POS linked lines are never permanently deducted only through local stock
* Duplicate payment webhook cannot deduct either source twice
* Mixed cancellation handles both sources correctly
* Mixed return handles both sources correctly
* Reports include both product types
* Search and wishlist work with both product types
* POS outage does not block ecommerce only checkout
* Customer experience remains one seamless order flow


## 140W. Updated Dual Source Guardrails

Before implementation is complete, verify:

* Every sellable product has an explicit inventory source
* POS linked products use POS inventory authority
* Ecommerce only products use local ecommerce inventory authority
* Mixed carts are supported
* Only POS linked lines are sent to POS
* Ecommerce only products never require POS availability
* POS outages do not block ecommerce only checkout
* One order can contain both product types
* Payment remains one ecommerce transaction
* Cancellation and returns branch correctly by source
* Reporting includes both sources
* The same physical stock is never represented by two independent authorities




## 140X. POS Selling Units and Ecommerce Variants Are Different Concepts

The integration must keep POS selling units and ecommerce product variants separate.

POS selling units represent different selling quantities of the same physical stock.

Examples:

```text
Single Milo
1 base unit

Roll of Milo
10 base units

Carton of Milo
100 base units
```

The POS additional unit has:

```text
unit name
quantity_in_base_units
independent selling price
```

Selling one Roll may deduct 10 from the item base quantity.

This is not an ecommerce product variant.

Do not rename or remodel POS selling units into color, size, storage, flavor, model, or other advanced ecommerce variant concepts.


## 140Y. True Ecommerce Variant Meaning

A true ecommerce variant represents a different version or configuration of a product.

Examples include:

```text
Color
Blue
Black

Size
Small
Medium
Large

Storage
128GB
256GB

Model
WiFi
WiFi plus Cellular
```

If the selected option represents separate physical stock, the variant must have its own inventory identity.

The existing POS selling unit system must not be used to fake this behavior.


## 140Z. Simple POS Linked Product

A simple POS linked ecommerce product maps to one POS item.

Example:

```text
Online Product
Milo Chocolate Drink

Linked POS Item
Milo
```

Once the POS item is selected, ecommerce can automatically synchronize:

```text
Base Unit

Additional POS Selling Units

Conversion Quantities

Independent POS Unit Prices

Availability
```

The ecommerce administrator should not manually map each POS selling unit.

They already belong to the selected POS item.


## 140AA. Example of Automatic POS Selling Unit Synchronization

Example POS configuration:

```text
POS Item
Milo

Base Unit
Single
Price 250

Additional Unit
Roll
quantity_in_base_units 10
Price 2300

Additional Unit
Carton
quantity_in_base_units 100
Price 21500
```

The ecommerce product may display:

```text
Purchase As

Single
250

Roll of 10
2300

Carton
21500
```

If the customer chooses:

```text
Roll
Quantity 2
```

the ecommerce system sends the POS item and selected POS unit identity.

POS interprets that as:

```text
2 Roll
10 base units per Roll
20 base units required
```

The reservation therefore protects 20 base units.

Do not calculate POS unit price from base price multiplied by conversion quantity.

Always use the independent POS unit price returned by POS.


## 140AB. Ecommerce Variant With Separate POS Stock

When a true ecommerce variant represents separate physical stock in POS, each inventory significant variant combination should map to its corresponding POS item.

Example online product:

```text
Nike Polo Shirt
```

Variants:

```text
Blue plus Small
Blue plus Medium
Black plus Small
Black plus Medium
```

POS may contain:

```text
POS Item 501
Nike Polo Blue Small

POS Item 502
Nike Polo Blue Medium

POS Item 503
Nike Polo Black Small

POS Item 504
Nike Polo Black Medium
```

The ecommerce application may present those as one online product while mapping each inventory variant combination to the correct POS item.


## 140AC. Variant Mapping Table

Do not force variant mapping into the old `variants` JSON alone.

Create a dedicated relational mapping structure.

Recommended table:

```text
product_variant_mappings
```

Suggested fields:

```text
id
product_id
variant_key
variant_label
option_values
pos_item_id nullable
inventory_source
is_active
sort_order
last_pos_sync_at
created_at
updated_at
```

Recommended rules:

```text
inventory_source = pos
```

requires:

```text
pos_item_id
```

For a variant that is locally owned by ecommerce:

```text
inventory_source = ecommerce
pos_item_id = null
```

If the project later adopts a more normalized ecommerce variant model, this table may reference a variant id instead of storing `variant_key` directly.


## 140AD. Variant Key

Every inventory significant ecommerce variant combination should have a stable internal key.

Example:

```text
color=blue|size=small
```

or a generated internal variant UUID.

Do not use the display label as the only identity.

Display labels may change.

The internal mapping must remain stable.


## 140AE. Admin Variant Mapping Workflow

The ecommerce administrator should map variants using the interface.

Never require manual typing of POS item ids.

Recommended flow:

```text
Admin creates or edits online product

Admin defines ecommerce variant options

System generates inventory variant combinations

For each combination
Admin clicks Select POS Item

System searches only the already connected POS branch

Admin selects the correct POS item

System stores the POS item id internally
```

Example interface concept:

```text
Blue plus Small
Linked POS Item
Nike Polo Blue Small

Blue plus Medium
Linked POS Item
Nike Polo Blue Medium

Black plus Small
Linked POS Item
Nike Polo Black Small

Black plus Medium
Linked POS Item
Nike Polo Black Medium
```


## 140AF. POS Item Selector

The POS item selector should support search using administrative information such as:

```text
POS item name
Barcode
SKU or item reference where available
```

The result may show:

```text
POS item name
Current standard price
Current effective availability
Barcode
Base unit
```

The administrator selects the item.

The application stores the POS item id automatically.

Do not expose free text `pos_item_id` input.


## 140AG. Mapping Validation

For inventory significant POS variants:

* Every active sellable combination must have a valid POS item mapping
* The mapped POS item must belong to the connected POS branch
* The mapped POS item must be active
* Duplicate mappings should be validated carefully
* Missing mappings must prevent publication or purchase of that variant
* A variant must never silently fall back to the parent product POS item when separate physical stock is expected


## 140AH. One POS Item Per Variant Combination

For the current design, one inventory significant variant combination maps to one POS item.

Example:

```text
Blue plus Small
to
POS Item 501
```

Do not map one variant combination to several POS items.

Do not combine stock from several POS items into one variant unless a future aggregation feature is explicitly designed.


## 140AI. Parent Product Mapping for Variant Products

A true variant product does not need to use one parent `pos_item_id` as the inventory source for every combination.

For a product where every physical variant maps separately:

```text
products.pos_item_id
```

may remain null or serve only as an optional administrative parent reference.

Inventory must come from the selected variant mapping.

Do not reserve against the parent product mapping when the chosen combination has its own POS item mapping.


## 140AJ. Variant Availability

For a POS mapped variant, availability comes from the mapped POS item.

Example:

```text
Blue plus Small
POS Item 501
Available 4

Blue plus Medium
POS Item 502
Available 0
```

The ecommerce storefront can therefore disable only the unavailable combination instead of marking the entire parent product out of stock.


## 140AK. Variant Standard Price

For a POS mapped inventory variant, the standard price comes from the mapped POS item or selected POS selling unit for that mapped item.

Example:

```text
Blue plus Small
POS Item 501
Standard Price 12000

Blue plus Medium
POS Item 502
Standard Price 12500
```

Ecommerce promotional rules may still modify the final online price independently.


## 140AL. Combining Ecommerce Variant and POS Selling Unit

A true ecommerce variant may also expose POS selling units if the mapped POS item supports them.

Example:

```text
Product
Milo

Flavor
Chocolate
Vanilla
```

Inventory mapping:

```text
Chocolate
POS Item 50

Vanilla
POS Item 51
```

Each mapped item may then have:

```text
Single
Roll
Carton
```

Customer selection conceptually becomes:

```text
Variant
Chocolate

Purchase Unit
Roll

Quantity
2
```

The system first resolves:

```text
Chocolate
to
POS Item 50
```

then resolves:

```text
Roll
to
the Roll item unit belonging to POS Item 50
```

then reserves the corresponding base quantity.


## 140AM. Do Not Cross Map POS Units Between Variant Items

A POS selling unit belongs to one POS item.

Do not allow:

```text
Variant maps to POS Item 50
```

while:

```text
Selected Roll unit belongs to POS Item 51
```

The ecommerce application must validate that the selected POS unit belongs to the resolved POS item.


## 140AN. Ecommerce Only Variants

Ecommerce only products may also have true variants.

Example:

```text
Handmade Shirt

Blue Small
Blue Medium
Black Small
Black Medium
```

If:

```text
inventory_source = ecommerce
```

the ecommerce application manages local inventory for each inventory significant variant.

Do not force these variants into POS.

A normalized local variant inventory table should be used instead of one shared parent `products.stock` when combinations have independent stock.


## 140AO. Recommended Local Variant Inventory

For ecommerce only products with separate variant stock, use a dedicated table such as:

```text
product_variants
```

Suggested fields:

```text
id
product_id
variant_key
option_values
sku
price
stock
is_active
created_at
updated_at
```

The exact schema may be adjusted to the existing ecommerce architecture.

The key requirement is that independently stocked variants must have independently tracked stock.


## 140AP. Presentation Only Options

Some ecommerce options do not represent separate physical stock.

Examples:

```text
Gift Wrap
Message Card
Engraving Text
```

These may remain presentation or service options.

They do not require POS item mapping unless they represent a separately stocked sellable item.


## 140AQ. Variant Cart Snapshot

For true variant products, cart items should store enough information to reproduce the customer selection.

Recommended data includes:

```text
variant_key
variant_label
variant_option_snapshot
resolved_pos_item_id nullable
selected_pos_unit_id nullable
selected_unit_name nullable
quantity_in_base_units nullable
```

Do not rely only on the current mutable product variant JSON after the item has been added to cart.


## 140AR. Variant Order Snapshot

Order items must preserve what was actually purchased.

Recommended snapshot information:

```text
product name
variant label
variant option values
resolved POS item id if applicable
selected POS unit id if applicable
selected unit name
quantity ordered
quantity in base units
standard price snapshot
actual ecommerce selling price
```

Historical orders must remain readable after variant names, mappings, or prices change.


## 140AS. Checkout Resolution Order

For each cart line, determine inventory in this order:

```text
1
Determine product inventory source

2
Determine whether a true inventory variant is selected

3
Resolve the inventory item

4
If POS controlled
resolve mapped POS item

5
If POS selling unit is selected
resolve unit belonging to that POS item

6
Calculate required base quantity

7
Validate and reserve stock
```

Do not begin with the POS unit before resolving the actual physical POS item.


## 140AT. Mixed Variant Cart

A single cart may contain:

```text
Simple POS linked item

POS linked variant item

Ecommerce only simple item

Ecommerce only variant item
```

All four must work inside one cart, one checkout, one payment, and one order.

Inventory handling is delegated internally based on product and variant source.


## 140AU. Search and Product Page Behavior

Search should continue returning the parent ecommerce product.

The product page should present variants cleanly.

Variant mapping is an internal inventory concern.

Do not create separate public search results for every POS item unless the ecommerce administrator intentionally creates separate online products.


## 140AV. Variant Publication Rules

A variant may be:

```text
active
inactive
```

If an active POS mapped variant loses its valid POS item mapping or the POS item becomes inactive:

* Disable purchasing for that combination
* Keep the parent product online if other combinations remain valid
* Show an admin mapping warning
* Preserve historical orders


## 140AW. Variant Mapping Changes

Changing a mapping affects future sales only.

Existing carts should be revalidated before checkout.

Existing completed orders must preserve the old resolved POS item snapshot.

Do not rewrite historical order mappings when an administrator remaps a variant.


## 140AX. Variant Mapping and POS Outage

Previously synchronized variant availability may continue displaying while POS is temporarily unavailable.

Final checkout for POS mapped variants still requires successful POS reservation.

Ecommerce only variants may continue selling using local inventory during POS outage.


## 140AY. Variant Testing

Test all of the following:

* Simple POS product with base unit only
* Simple POS product with additional selling units
* POS selling unit deducts correct base quantity
* POS unit independent price is used
* True ecommerce variant maps to separate POS item
* Different variant combinations resolve different POS items
* Unmapped active POS variant cannot be purchased
* Variant availability reflects its mapped POS item
* Variant standard price reflects its mapped POS item
* Variant plus POS selling unit resolves correctly
* POS unit cannot be selected from another mapped item
* Ecommerce only simple product works
* Ecommerce only variant with separate local stock works
* Mixed cart containing all supported product types works
* Payment webhook remains idempotent
* Completed order preserves variant and mapping snapshot
* Mapping change does not rewrite historical orders


## 140AZ. Final Variant Guardrails

Before variant support is considered complete, verify:

* POS selling units are not treated as ecommerce variants
* Ecommerce variants are not forced into POS unit conversion structure
* Simple POS products require only one POS item mapping
* POS selling units synchronize automatically from the selected POS item
* True inventory variants map to their own POS items where physical stock differs
* Administrators select POS items through a search interface
* Administrators never type POS item ids manually
* Variant mapping is stored relationally
* Customer sees one clean product page
* Customer may select both a true variant and a POS selling unit where supported
* Inventory resolution always determines physical POS item before selling unit
* Ecommerce only variants remain fully supported
* Historical order snapshots remain stable




## 140BA. Refined Price Synchronization and Checkout Price Validation

The ecommerce integration must clearly separate the POS standard selling price from the final ecommerce customer price.

For POS linked inventory, POS is the authority for the standard selling price.

For ecommerce only inventory, ecommerce is the authority for the standard selling price.

Ecommerce remains responsible for its own promotional pricing.


## 140BB. Price Authority by Inventory Source

For:

```text
inventory_source = pos
```

the standard price comes from POS.

For:

```text
inventory_source = ecommerce
```

the standard price is managed locally by ecommerce.

The application must inspect the inventory source before deciding whether an administrator can edit the standard price.


## 140BC. POS Linked Base Product Price

For a simple POS linked product, the ecommerce base product price should mirror the latest POS standard base unit selling price.

Recommended field:

```text
products.price
```

For a POS linked product, this field means:

```text
latest synchronized POS standard base unit price
```

It is a synchronized local copy for storefront performance.

The ecommerce administrator must not manually edit this synchronized standard price.


## 140BD. POS Selling Unit Price Synchronization

Each POS selling unit has its own independent selling price.

Example:

```text
Milo

Single
250

Roll of 10
2300

Carton of 100
21500
```

The ecommerce application must synchronize each selling unit price exactly as returned by POS.

Never calculate:

```text
Roll price
=
Single price multiplied by 10
```

Never calculate:

```text
Carton price
=
Single price multiplied by 100
```

The conversion quantity controls stock deduction.

It does not determine the selling price.


## 140BE. Additional POS Unit Price Storage

For synchronized POS selling units, use a field such as:

```text
product_pos_units.synced_standard_price
```

This field stores the latest standard price returned by POS for that selling unit.

Example:

```text
POS Item
Milo

Base
250

Roll
quantity_in_base_units = 10
synced_standard_price = 2300
```

When the customer selects Roll, the standard online price begins from 2300, not from 250 multiplied by 10.


## 140BF. POS Mapped Ecommerce Variant Price

For a true ecommerce variant that maps to a separate POS item, the standard price comes from the mapped POS item.

Example:

```text
Blue plus Small
maps to POS Item 501
standard price 12000

Blue plus Medium
maps to POS Item 502
standard price 12500
```

The ecommerce application should store the latest synchronized standard price for each mapped inventory variant.

Do not use one parent product price for all inventory variants when their mapped POS items have different prices.


## 140BG. Variant Plus POS Selling Unit Price

If a true ecommerce variant maps to a POS item that itself has additional POS selling units, price resolution must occur in this order:

```text
1
Resolve ecommerce variant

2
Resolve mapped POS item

3
Resolve selected POS selling unit

4
Use the standard POS price belonging to that exact selling unit
```

Example:

```text
Chocolate variant
maps to POS Item 50

Selected purchase unit
Roll

POS Item 50 Roll price
2300
```

Use 2300.

Do not use the parent online product price.

Do not use another variant item unit price.


## 140BH. Ecommerce Only Product Price

For:

```text
inventory_source = ecommerce
```

the standard price is controlled entirely by ecommerce.

The ecommerce administrator may edit:

```text
products.price
```

No POS price synchronization should overwrite it.


## 140BI. Ecommerce Only Variant Price

For ecommerce only products with independently priced variants, the variant price is managed locally by ecommerce.

If local variant records have their own:

```text
price
```

that price is authoritative for the variant.

No POS price synchronization applies.


## 140BJ. Ecommerce Promotional Pricing

The existing ecommerce promotional fields remain ecommerce controlled.

These include:

```text
discount_price

special_price

special_price_from

special_price_to
```

For POS linked products, synchronization updates the standard price only.

It must not erase valid ecommerce promotional pricing.


## 140BK. Price Resolution Model

For a POS linked product, the customer facing price is resolved conceptually as:

```text
Synchronized POS standard price

then

Ecommerce promotional pricing

then

Future ecommerce coupon or referral rules if later implemented

equals

Final ecommerce customer price
```

Example:

```text
POS standard price
10000

Ecommerce special price
8500

Customer pays
8500
```

POS does not need to know why ecommerce charged 8500.


## 140BL. POS Does Not Approve Ecommerce Promotional Price

Do not call POS discount logic to approve an online promotional amount.

Do not apply:

```text
POS Item Discount

POS Group Discount

POS Overall Discount
```

to ecommerce checkout.

The POS provides the standard price.

Ecommerce determines the final online customer price.


## 140BM. Storefront Price Performance

Normal storefront browsing must use locally synchronized price data.

Do not call POS for every:

```text
Homepage product card

Shop result

Search suggestion

Wishlist view

Comparison view

Product page request
```

The synchronized ecommerce database provides fast storefront pricing.


## 140BN. Periodic Price Synchronization

POS linked standard prices should refresh through scheduled synchronization.

Synchronization should update:

```text
products.price
```

for POS linked base products.

It should update:

```text
product_pos_units.synced_standard_price
```

for additional POS selling units.

It should update the synchronized standard price attached to mapped ecommerce variants.

Ecommerce only prices must be excluded from POS price synchronization.


## 140BO. Manual Price Refresh

The ecommerce admin may use the existing integration refresh action to request fresh POS data.

This may update synchronized prices.

The refresh action must not allow editing the POS standard price from ecommerce.


## 140BP. Price Synchronization Does Not Rewrite Orders

When a POS price changes, update current ecommerce catalog data only.

Never update historical:

```text
order_items.price
```

Completed and existing orders must preserve the amount charged when the order was created.


## 140BQ. Order Price Snapshot

Every order item must preserve sufficient pricing history.

Recommended snapshot information includes:

```text
standard_price_snapshot

actual_unit_price

discount_amount where applicable

selected POS unit price snapshot where applicable

variant standard price snapshot where applicable
```

The existing:

```text
order_items.price
```

should continue representing the actual ecommerce unit price charged to the customer.

Historical orders must remain correct after future POS price changes.


## 140BR. Cart Price Is Not Permanent

The current cart item price must not be treated as permanently guaranteed merely because the customer added the product earlier.

POS standard prices may change while an item remains in the cart.

Before payment begins, the ecommerce application must validate the current standard price for every POS linked cart line.


## 140BS. Checkout Price Validation

Before creating or finalizing payment for a cart containing POS linked inventory:

```text
1
Resolve each POS linked item or mapped variant

2
Resolve the selected POS selling unit where applicable

3
Obtain or validate the latest POS standard price

4
Update the synchronized local standard price if POS has changed

5
Recalculate the ecommerce effective price

6
Compare the recalculated cart totals with the customer cart

7
If the payable amount changed, show the updated amount before payment
```

Do not initialize Paystack or Flutterwave using a materially stale cart price.


## 140BT. Customer Notification When Price Changes

If the latest POS standard price changes the ecommerce payable amount, the customer must be informed before payment.

Example message:

```text
The price of one or more items has changed.
Your cart has been updated with the latest price.
Please review your order before continuing.
```

Do not silently charge a higher amount than the amount the customer reviewed.


## 140BU. Price Decrease Before Checkout

If the POS standard price decreases and the ecommerce effective price also becomes lower, update the cart to the lower current price before payment.

Do not intentionally keep a stale higher synchronized standard price merely because it was previously stored in the cart.


## 140BV. Promotional Price Validation After POS Price Change

Ecommerce promotional pricing must be recalculated after the latest POS standard price is known.

Example:

```text
Old POS standard price
10000

Ecommerce special price
8500

New POS standard price
11000
```

If the promotion is still valid under ecommerce rules, the customer may still pay 8500.

The POS standard price synchronization must not automatically delete the promotion.


## 140BW. Invalid Promotional Price

If future ecommerce rules impose restrictions such as:

```text
promotion cannot exceed a configured discount percentage
```

those rules remain ecommerce rules.

They should be evaluated after POS standard price synchronization.

Do not place such ecommerce marketing validation inside POS.


## 140BX. Reservation and Price Validation Relationship

Price validation and stock reservation are separate concerns.

During checkout:

```text
validate current standard price

recalculate ecommerce payable amount

reserve POS inventory

then initialize payment
```

A valid price does not guarantee stock.

Available stock does not guarantee the cart still has the latest price.

Both must succeed before payment initialization.


## 140BY. Recommended Checkout Sequence With Price and Inventory

For a cart containing POS linked items:

```text
Validate ecommerce cart

Resolve product and variant mappings

Refresh or validate POS standard prices

Recalculate ecommerce discounts and totals

If customer payable amount changed
require customer review

Validate ecommerce only local stock

Create POS reservation for POS linked lines

Create ecommerce order

Initialize online payment
or
continue COD workflow
```

If reservation fails, do not initialize payment.


## 140BZ. Mixed Cart Price Handling

A mixed cart may contain:

```text
POS linked product

Ecommerce only product
```

For the POS linked line:

```text
standard price comes from POS
```

For the ecommerce only line:

```text
standard price comes from ecommerce
```

Both are then processed through ecommerce pricing rules and combined into one order total.


## 140CA. POS Price API Failure During Browsing

If POS is temporarily unavailable during normal storefront browsing, use the most recently synchronized price.

The storefront may continue displaying the cached price.


## 140CB. POS Price API Failure During Checkout

If current POS price validation is required and POS cannot be reached during checkout for a POS linked item:

```text
do not initialize payment
```

Preserve the cart and tell the customer that current product information could not be confirmed.

Do not guess the current POS price.


## 140CC. Ecommerce Only Checkout During POS Downtime

If a cart contains only:

```text
inventory_source = ecommerce
```

POS downtime must not block checkout.

Those prices are locally authoritative.


## 140CD. Mixed Cart During POS Downtime

If a cart contains at least one POS linked item and current POS price or inventory validation cannot complete, the mixed order must wait.

Do not process only part of the mixed order while presenting it as one successful checkout.


## 140CE. Price Synchronization Timestamp

Store useful synchronization timestamps.

Recommended:

```text
last_pos_sync_at
```

on the product.

For unit or variant level records, retain their own synchronization timestamp where useful.

This helps administrators understand whether displayed POS pricing is current.


## 140CF. Price Sync Status

A POS linked product may expose an administrative synchronization status such as:

```text
synced

pending

failed

missing_pos_item
```

This status is for administration.

Do not expose internal synchronization wording to normal customers.


## 140CG. Price Changes Must Not Change POS Item Mapping

A price change never causes automatic remapping.

If POS Item 501 changes from 12000 to 13000, the ecommerce mapping remains POS Item 501.

Do not search for another POS item based on price similarity.


## 140CH. Price Change and Variant Mapping

When a mapped variant POS item price changes:

* Keep the same variant mapping
* Update its synchronized standard price
* Recalculate current ecommerce effective price
* Preserve historical order snapshots


## 140CI. Price Change and POS Selling Units

When a POS additional unit price changes:

* Keep the same POS item unit mapping
* Update only the synchronized unit standard price
* Keep `quantity_in_base_units` unchanged unless POS explicitly changed that conversion
* Recalculate current ecommerce effective price for that purchase unit


## 140CJ. Do Not Derive Cost Price From Selling Price

Do not calculate ecommerce cost price from POS selling price.

Do not expose POS cost price merely because selling prices are synchronized.

Cost information remains outside this integration unless explicitly required later.


## 140CK. Price Synchronization Testing

Test all of the following:

* POS linked base price synchronizes
* Ecommerce only base price remains locally editable
* POS selling unit uses exact independent POS price
* Unit price is not calculated from conversion quantity
* True POS mapped variant uses mapped POS item price
* Variant plus POS selling unit uses the exact unit price for the resolved mapped item
* Ecommerce promotional price remains after POS standard price synchronization
* POS discount logic is never applied online
* Cart price is revalidated before payment
* Customer is informed when payable amount changes
* Price increase updates cart before payment
* Price decrease updates cart before payment
* Completed order price remains unchanged after later POS price update
* Ecommerce only checkout works during POS downtime
* POS linked checkout stops safely when required live price validation cannot complete
* Duplicate payment webhook cannot reprice an already created order
* Historical order snapshots remain stable


## 140CL. Final Price Synchronization Guardrails

Before price synchronization is considered complete, verify:

* POS is standard price authority only for POS linked inventory
* Ecommerce is standard price authority for ecommerce only inventory
* POS unit prices synchronize independently
* Conversion quantity never determines selling price
* POS mapped variants synchronize from their mapped POS items
* Ecommerce promotions remain independent
* Storefront uses local synchronized prices for performance
* Checkout validates current POS linked pricing before payment
* Customer reviews a changed payable amount before payment
* Completed orders never change when POS prices change
* POS downtime does not block ecommerce only purchases
* POS discount engine is never used for ecommerce pricing


## 141. Future Compatibility

The architecture should make future additions possible without rewriting the core connection.

Possible future ecommerce features include:

* Coupon codes
* Referral rewards
* Loyalty points
* Advanced promotions
* POS initiated webhooks
* Near real time stock push
* Return restock endpoint
* Additional marketplace connectors

These future features must still respect the same inventory authority boundary.


## 142. Final Architecture Summary

The finished system should operate as:

```text
MULTI BRANCH SKYNET POS
        |
        | one selected branch
        |
        | authenticated commerce API
        |
        v
STANDALONE ECOMMERCE STORE
```

The POS controls:

```text
Physical inventory
Front store quantity
Reservations
Standard prices
POS unit conversions
IMEI state
Inventory movements
```

The ecommerce store controls:

```text
Online catalog presentation
Online names
Images
Categories
Attributes
Customers
Cart
Checkout
Online discounts
Payments
Tax
Shipping
Pickup
Orders
Invoices
Tracking
Online reports
```

The two systems communicate through the dedicated POS commerce integration API.

They do not share a database.

They do not share a sales process.

They do not share authentication.

They do not share discount engines.

The ecommerce site does not become a POS cashier.

The POS does not become an ecommerce application.


## 143. Critical Agent Guardrails

Before completing this implementation, verify all of the following:

* Ecommerce supports both POS linked products and ecommerce only products
* Every sellable product has an explicit inventory source
* Mixed carts remain one seamless customer checkout
* Only POS linked lines are sent to POS reservation APIs
* Ecommerce only products remain locally authoritative for price and stock
* Ecommerce is connected to exactly one POS branch
* Only POS Super Admin controls branch connection
* Ecommerce admin cannot switch POS branch
* `pos_item_id` is the product mapping key
* POS `front_store_qty` is the physical quantity source
* POS `back_store_qty` is never sold online
* Local ecommerce `stock` is only a synchronized cache
* Final checkout always uses POS reservation
* Cart does not reserve inventory
* Ecommerce standard price comes from POS
* Ecommerce promotional price remains ecommerce controlled
* Existing coupon and referral systems are not assumed to exist
* POS discount engine is never used online
* Ecommerce checkout remains independent
* Paystack and Flutterwave remain ecommerce payment systems
* Shipping remains ecommerce controlled
* Ecommerce reports remain ecommerce controlled
* Product names and categories remain independently editable online
* Existing POS flexible units are not confused with ecommerce display variants
* Simple POS products map once and inherit POS selling units automatically
* True inventory variants map each physical combination to its corresponding POS item
* POS item ids are selected through the admin interface and never typed manually
* Missing POS items never cause automatic ecommerce product deletion
* Missing POS mappings block new purchase while preserving history
* Remapping is always an explicit admin action
* POS mapping registry state is synchronized when mappings are created, changed, or removed
* No direct POS database access exists
* No raw POS API secret reaches Vue
* Repeated payment webhooks cannot deduct inventory twice
* Cancellation before confirmation releases reservation
* Cancellation after confirmation does not automatically restore stock
* Existing customer and guest functionality continues working
* The fresh clone is tested from a clean database before deployment
