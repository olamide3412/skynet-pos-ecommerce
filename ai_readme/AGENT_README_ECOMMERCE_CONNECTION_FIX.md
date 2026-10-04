# Skynet Digital Ecommerce — Fix: POS Connection Pairing Flow (Agent README)

**Scope of this document:** This is a **narrow, targeted fix** on top of the already-executed `AGENT_README_POS_INTEGRATION_ECOMMERCE_COMPLETE.md`. It does **not** revisit catalog sync, pricing synchronization, reservations, checkout, or anything else already built from that document — all of that stays exactly as implemented. This fixes **one specific gap**: the connection between this ecommerce installation and its POS branch was never actually established, because the original document only described the pairing/handshake mechanism in one vague paragraph (its §33), with no concrete fields, no defined screen behavior, and no "Test Connection" logic to actually implement. That gap is why the current Store Config screen shows "No Connected POS Branch — Disconnected" and "Endpoint: Not configured."

---

## 1. Diagnosis — Why This Is Disconnected

- §31 of the original document already defines a `pos_connections` table with the right fields (`pos_base_url`, `pos_integration_id`, `pos_branch_id`, `pos_branch_name`, `client_id`, `encrypted_api_secret`, `is_connected`, `connected_at`, `last_successful_request_at`, `last_catalog_sync_at`, `last_inventory_sync_at`, `last_error_at`, `last_error_message`) — **this table and its fields are correct and should not be rebuilt.**
- §32 correctly establishes that the ecommerce admin must never be able to pick/switch a POS branch, and must never be able to generate or rotate credentials themselves — credential **generation** stays exclusively a POS Super Admin action (per the corrected POS-side README's §36/§37).
- **What's missing:** a concrete, buildable spec for how the values POS Super Admin generates actually get **into** this ecommerce installation's `pos_connections` row in the first place, and what the Store Config screen should actually let an ecommerce admin do with them. §33 left this as "may use a dedicated protected pairing or connection callback endpoint" — too vague to implement correctly, so it appears to have been left as a stub.

---

## 2. The Fix — A Concrete One-Time Pairing Flow

Since these are two separately deployed, separately hosted applications with no shared database (per the original document's own architecture), the realistic and secure approach is a **manual, one-time credential handover** — not an automatic cross-server callback, which would require the POS to know this specific ecommerce installation's URL in advance and reach it over the network, adding unnecessary complexity and failure points.

### 2.1 On the POS Side (already covered by the corrected POS README — reference only, do not rebuild here)
- POS Super Admin creates the ecommerce integration for a branch (Branch Directory → Open Branch → Ecommerce Integration, per that document's §36).
- POS generates: a **Client ID**, an **API Secret** (shown once, in full, at generation time — standard "copy this now, it won't be shown again" pattern), and the **POS API base URL** this ecommerce installation should talk to.
- These three values are what POS Super Admin hands over to whoever is setting up this ecommerce installation (outside the system — e.g., securely shared during setup).

### 2.2 On the Ecommerce Side — Store Config Screen (this is what needs building/fixing)

Build a proper pairing form under **Settings → Store Config** (matching where the attached screenshot already shows this screen living), with these three fields:

| Field | Maps to `pos_connections` column |
|---|---|
| **POS API Base URL** | `pos_base_url` |
| **Client ID** | `client_id` |
| **API Secret** | `encrypted_api_secret` (encrypt immediately on save — never store or display it in plain text after initial entry) |

**On submit ("Save & Test Connection"):**
1. Save the three values to the `pos_connections` record (create it if none exists — there should only ever be one row, per §31's "one active POS connection record").
2. Immediately make an authenticated test request to the POS API using these credentials (a lightweight POS-side health/identity endpoint is sufficient — confirm with the POS README's API surface, or request one be added if it doesn't exist).
3. **On success:** set `is_connected = true`, `connected_at = now()`, `last_successful_request_at = now()`, clear any `last_error_message`. Update the dashboard status badge from "Disconnected" to "Connected," and populate the branch name (`pos_branch_name`) and endpoint display from the now-confirmed connection.
4. **On failure:** do **not** set `is_connected = true`. Store `last_error_at` and a clear `last_error_message` (e.g., "Could not authenticate with the provided credentials" or "Could not reach the POS API at this URL"), and show that error clearly on the Store Config screen so whoever is setting this up knows exactly what went wrong — not just a generic failure.

### 2.3 After a Successful Connection
- The dashboard summary bar (already visible in the attached screenshot — Last Request / Last Catalog Sync / Last Stock Sync) should now reflect real values instead of "Never" as sync actions occur.
- The **"Sync Catalog"** and **"Import All Eligible"** buttons (already visible in the screenshot, presumably already functional per the original document's extensive catalog-sync sections) should only be enabled/usable once `is_connected = true` — gate them on connection status, since there's nothing to sync against while disconnected. Do not rebuild their underlying sync logic here; only add this gate if it's missing.

### 2.4 Re-pairing / Credential Rotation
- If POS Super Admin ever rotates the credentials (per the POS README's existing rotate capability), the old `client_id`/`encrypted_api_secret` on the ecommerce side will stop authenticating. The Store Config screen must handle this gracefully: a failed request using stored credentials should set `is_connected = false` and populate `last_error_message` clearly (e.g., "Authentication failed — credentials may have been rotated. Re-enter the new credentials below."), prompting the same pairing form again with the fields cleared, not stuck showing stale "Connected" status while actually failing.
- This re-pairing is the same form/flow as §2.2 — no separate "rotation" screen needed.

---

## 3. What This Does NOT Change

- No change to the `pos_connections` table schema — it's already correctly defined in §31 of the original document.
- No change to catalog sync logic, inventory reservation logic, pricing synchronization, checkout flow, or any of the extensive behavior already specified and executed from the original document.
- No change to the POS side — this is purely the ecommerce application's connection-setup screen.
- Admin permissions/visibility rules from §32 (no branch switching, no credential generation on the ecommerce side) are preserved exactly — this fix only makes the *entry of already-generated credentials* and the *test/confirm* step concrete and functional, it doesn't grant the ecommerce admin any new authority.

---

## 4. Notes for the Agent

- Reuse the existing `pos_connections` model and table exactly as already defined — do not create a second/competing connection table.
- The "test connection" request should be a real authenticated call to the POS API, not just a format-check on the entered values — a correctly-formatted but wrong/expired secret must still fail clearly.
- Store the API secret encrypted at rest (Laravel's built-in encryption), consistent with how credentials are already handled elsewhere in this project's broader POS system.
- If the POS side doesn't yet expose a lightweight endpoint suitable for this connection test (distinct from a full catalog-sync call), flag this back — the POS README may need one small addition (a simple authenticated "ping"/identity endpoint) to support this cleanly.

---

## 5. Open Questions

1. **Credential delivery mechanism:** Is manual copy-paste of Client ID + API Secret (as described in §2.1/§2.2) the intended handover method, or would a more modern **single-use pairing token/link** be preferred instead (POS generates one short-lived setup link, ecommerce admin visits/pastes just that one token, and the full credential exchange happens automatically behind it)? The token approach is slightly more secure (shorter-lived exposure, no long secret to mistype) but is more work to build — manual entry is simpler and matches what §31's schema already assumes. Recommend sticking with manual entry for now given the schema already in place, but worth confirming.
2. **POS-side test endpoint:** Per §4's note — does a suitable lightweight "ping"/identity endpoint already exist on the POS side for this test-connection step, or does this fix need to request one be added there first?
