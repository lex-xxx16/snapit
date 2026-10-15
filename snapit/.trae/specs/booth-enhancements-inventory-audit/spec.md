# Specification: Snap It Booth Enhancements & Inventory System Audit

## Problem
The Snap It photo booth system currently has several functional gaps, usability issues, and security concerns across its booth capture workflow, booking/payment flow, and inventory management. Customers cannot customize countdown timers, the camera preview is not mirrored (confusing for selfie-style use), walk-in pricing is exposed to modification through dropdown controls, booth sessions can start without confirmed payment, booking duration can be modified post-creation, softcopy buttons appear redundantly for packages that already include them, visual setting changes lack a live camera preview, the done button appears before all photos are captured, admin accounts risk viewing customer photos in gallery views, camera preset images rely on external CDN URLs with no offline fallback, and the inventory module lacks several operational features (alerts, dashboards, usage reporting, integration with bookings).

## Users
- **Customer Users**: Walk-in and pre-booked customers interacting with the photo booth to capture, preview, and retrieve their photos.
- **Staff/Admin Users**: Personnel managing bookings, payments, inventory, print jobs, and customer records.

## Goals
1. Deliver a polished, intuitive, and customer-safe booth capture experience with configurable timing, mirror preview, real-time settings preview, and proper progression guards.
2. Harden booking/payment/walk-in flows so pricing cannot be tampered with, payment is enforced before booth access, and purchased entitlements (softcopy, duration) are locked down consistently.
3. Eliminate the risk of admin accounts browsing customer photos via the shared gallery route by enforcing proper data segregation.
4. Provide offline resilience by caching camera preset sample images locally on the device.
5. Make the inventory system fully operational with streamlined tracking, user-friendly UI updates, automated stock alerts, and integration with booking, printing, and walk-in sales workflows.

## Non-Goals
- Rewriting the system into a different framework or language (PHP / MySQLi / Bootstrap stack stays).
- Implementing online payment gateways (cash-only walk-in and recorded-payment event flows remain).
- Replacing the current print subsystem with driver-level printer integration (print queue + mark-printed flow stays).
- Rewriting authentication or role system (role-based access helpers remain).

---

## Functional Requirements

### FR-1: Configurable Countdown Timer (3/5/10 seconds)
- Before the first photo of a guest session is captured, the customer (or staff attaching the booth) can select a countdown duration of exactly 3s, 5s, or 10s.
- Default countdown is 3 seconds for safety.
- The chosen countdown persists for all photos within the same guest session; it can be changed per session on the capture/selections page.
- The countdown overlay displays a large numeral counting down, then flashes white before capture, matching the existing visual style.
- Sub-photo retakes use the same configured countdown.

### FR-2: Inverted (Horizontal Mirror) Camera Preview
- The live `<video>` feed shown in the booth during preview and countdown is flipped horizontally (mirror view) so waving left shows the hand on the viewer's left.
- The final captured PNG written to disk and server is **not** horizontally flipped — it keeps the real (correct) orientation.
- The mirror option is a toggle with a default of ON for the selfie/booth experience; state is remembered for the session.
- Any CSS filters, canvas overlays (grain, vignette, fade), and the live camera preview used in the customization page all respect the mirror setting for preview.

### FR-3: Fixed Non-Editable Walk-in Price Display (Removal of Price Dropdowns)
- In the walk-in landing page, the price shown for each package is a static non-editable display element — no `<select>` dropdown or editable `<input>` is present that can change package price.
- The `amount_paid` input still exists to let the customer enter the cash tendered, but the server validates in `walkin/store.php` that it is >= the package's `base_price` read from the database (never from user-submitted price fields).
- No dropdown labeled "walk-in pricing" or similar remains on any walk-in or booking page.

### FR-4: Booth Access Enforced After Confirmed Full Payment
- A booth session (`guest_sessions`) for an event booking can only start when the booking has a remaining balance of exactly 0.00 (i.e., `total_amount` minus sum of `payments.amount_paid` for that `booking_id` is <= 0).
- In `booth/index.php`, the selectable bookings list shows only bookings whose `status` is `paid` or `completed`; bookings with `confirmed` but partial or zero payment are excluded.
- `booth/start_session.php` re-validates the balance server-side before inserting the `guest_sessions` row and returns a flash error + redirect if balance > 0.
- Walk-in bookings are exempt because they are inserted with `status = 'paid'` together with the payment record in one atomic transaction (existing behavior preserved).

### FR-5: Lock Booking Duration — Remove Hour Adjustment Dropdown From Booking Details
- In the booking details (`booking/view.php`) page, there is no dropdown, input, or form control that allows changing `duration_hours` after the booking is created.
- The duration is displayed as a plain text label (hours).
- `booking/store.php` / any update path must only accept `duration_hours` at creation time; any later server-side write to `bookings.duration_hours` is prohibited.

### FR-6: Conditionally Disable/Hide "Get Softcopy" Button When Package Already Includes It
- In `gallery/index.php`, `gallery/view_booking.php`, `booth/done.php`, and any page rendering a softcopy entry point:
  - If the booking's `softcopy_addon` is 1 **or** the package's included softcopy entitlement covers the session (packages with base softcopy count > 0), the "Get Softcopy" / purchase / "Add softcopy to booking" call-to-action is not rendered or, if rendered, is disabled with a clear label "Softcopy already included".
  - The `download` and `send_emails` endpoints still allow access when softcopy is included; this requirement only concerns the redundant upsell button.
- Specifically, the softcopy-enabled badge already shown is kept; the redundant upsell button is removed.

### FR-7: Real-Time Live Camera Preview on the Customize Selections Page
- The `booth/capture.php` customization page integrates an actual live `<video>` preview element (getUserMedia feed) shown within the "Live Preview" card of the right-hand column.
- When the user clicks a different camera preset (brightness/contrast/saturation/cam_filter/fx_* values), a different CSS filter, a different layout, or a different frame design, the live video preview updates immediately (without form submission):
  - CSS filters + preset filters are applied to the `<video>` element CSS.
  - Grain / vignette / fade overlays from `CamFX.overlay()` are re-rendered inside the preview card.
  - Frame border, background, gap, and layout grid dimensions update on the surrounding preview frame container to match the chosen layout + frame.
- If camera permission is denied or unavailable, the preview falls back to the existing static picture-based preview (no error banner blocks the whole page).

### FR-8: "Done / New Guest" Button Shown Only When All Strip Slots Are Populated
- In `booth/session.php`, the primary "Done / New Guest" button is rendered in a disabled state (visually grayed out, `disabled` attribute, and click is a no-op) until `currentPhotos.filter(p => !!p).length >= targetN`.
- The button only becomes fully enabled after the customer has kept at least one photo for each required slot of the chosen layout.
- The button remains enabled if the customer later goes back and deletes/retakes, but only while the slot count condition holds; otherwise it reverts to disabled.
- An optional helper badge/caption near the button clarifies how many more photos are needed when not yet complete.

### FR-9: Admin Gallery Data Segregation — Customer Photos Never in Admin's Personal Gallery
- In `gallery/index.php` and `gallery/view_booking.php`, the admin "staff view" browse path is conceptually separate from any user's "personal gallery account":
  - Customers continue to see only their own `user_id` bookings and photos.
  - Staff/Admin see the **booking list** view with event names and thumbnails, but there is an explicit guard: admin's own user gallery (profile page, "My Gallery" conceptual view) does **not** list customer photos.
  - A new server-side helper/flag enforces that when viewing photos the query for "own" photos (used in profile/self-service views) returns only rows for which `bookings.user_id = current_user`, even if the viewer has an `admin` role.
  - The route already present (`gallery` index filtered by user for customers, unrestricted for staff to see **all booking cards**) stays, but no admin sees their own profile mixed with customer sessions.

### FR-10: Remove Walk-in Price Dropdown Button Entirely
- Confirm no `<select class="...price..."` / dropdown components exist in walk-in page(s) whose options set a package price.
- Replace any such control with the static price badge display already shown in the package card (`display-6 fw-bold` style); no form field sends a price from the browser for walk-ins.

### FR-11: Preset Image Background Download and Local Caching for Offline Access
- The `camera_photo_url()`-served preset sample images (currently hotlinked from Wikimedia) are cached locally:
  - On first render of `booth/capture.php`, for each preset card, the browser attempts to fetch and store the image via `localStorage` (as data URL) or `IndexedDB`; if offline/unavailable, the cached copy is rendered.
  - A server-side fallback routine is also provided: a small helper endpoint `includes/ajax_cache_preset.php` downloads preset images to `uploads/preset_cache/` as PNG files and serves a local URL instead; once cached on disk, HTML rendering uses that local URL.
  - Image fallback icons still work if both network + cache are empty.
  - `.htaccess` in the cache folder blocks script execution (matches `uploads` pattern).

### FR-12: Inventory System Comprehensive Audit & Improvements
- **Streamlined tracking**:
  - Inventory list includes a dashboard summary row/section (total SKUs, low-stock count, out-of-stock count, total paper/ink on hand, recent usage last 7 days).
  - Low stock / out-of-stock rows are highlighted and grouped first in a "Needs Attention" collapsible section above the standard table.
  - Restock quick input + delete flow stay; restock now additionally writes a descriptive `reason` into `inventory_logs.reason` (default "Manual restock from list").
- **User-friendly UI**:
  - Category filter dropdown and stock status filter (All / In Stock / Low Stock / Out of Stock) added to `inventory/index.php` search area.
  - SKU/Name search preserves filter state; clear button resets all.
  - "Usage History" link per item opens a small modal/linked page showing recent `inventory_logs` with delta, reason, and who performed action.
- **Automated stock alerts**:
  - When marking jobs printed (`inventory/mark_printed.php`) if after the deduct any item's `quantity_on_hand <= reorder_level`, a flash warning is raised naming those items.
  - The inventory index page shows a prominent dismissable banner when any items are at or below reorder level.
- **Integration with booking & payment systems**:
  - Walk-in `walkin/store.php` now decrements the corresponding hardcopy-consumable stock (e.g., 1 strip = 1 paper + proportional ink) when the session is created, logging it under `reason = "Walk-in package N strips"` with the `booking_id` reference.
  - Event bookings with hardcopy estimates deduct paper/ink at the time the booking is marked **completed** (via a new hook in `booking/complete.php`); estimate-based consumption is logged with reason `"Booking completed estimated hardcopy consumption"`.
  - Print Queue page shows the current paper/ink availability and a warning banner if stock is insufficient to complete pending jobs (calculated: pending copies * 1 paper each + pending copies * ink per copy vs on-hand).

---

## Non-Functional Requirements

### NFR-1: Security
- All SQL queries introduced or modified must use `mysqli_prepare` with bound parameters for any variable value (IDs, amounts, names, statuses).
- No user-supplied input is used directly in HTML without passing through `e()`; attributes are always quoted.
- CSRF tokens are verified on all POST handlers that mutate state (including new ones added for preset cache, booking complete hook, etc.).

### NFR-2: Data Integrity
- Stock decrements/increments are performed inside transactions that also write `inventory_logs` rows; partial commits never leave stock changed without a log entry.
- Payment balance check in `start_session.php` uses `FOR UPDATE` / or a serialized read of the totals to prevent race conditions if two booth start requests arrive concurrently.

### NFR-3: Performance
- Live camera preview changes apply filters via CSS + overlay HTML on the `<video>` element — expensive canvas re-draws are avoided except when actually capturing a frame.
- Preset cache endpoint uses conditional HTTP caching headers and writes atomically (write to temp file, rename) to avoid partial reads.

### NFR-4: Usability & Accessibility
- All new buttons/toggles have accessible labels (`aria-label`, visible text).
- Countdown numbers use the existing large "countdown-big" style to remain readable from a distance.
- Disabled "Done / New Guest" button clearly communicates the reason (e.g., tooltip / inline caption: "Capture all N photos first").

### NFR-5: Compatibility
- All JavaScript works in latest Chrome, Edge, and Firefox on Windows; `getUserMedia` continues to require HTTPS or localhost.
- No new build steps introduced; plain `.php` + `.js` + `.css` files deploy as-is.
- Existing schema columns remain backward compatible; new schema additions are additive and safe to run on existing installations (idempotent migrations).

---

## Constraints
- Technology stack: Plain PHP 8+, MySQLi, Bootstrap 5, session auth, PHPMailer (if touched).
- Path: project root is `C:\xampp\htdocs\Snap_It-main1\Snap_It-main\snapit\`.
- All database changes must be provided as a SQL migration file under `includes/migrate_*.sql` that can be run idempotently.
- Customer data privacy must never be degraded: any admin-facing views of customer data are clearly separated from admin-self-service profile views.

## Dependencies
- No new runtime dependencies; only existing browser APIs (`navigator.mediaDevices`, Canvas API, `localStorage`/`IndexedDB`) and the existing MySQL tables are used.
- `CamFX` and `SnapStrip` JS utilities are extended where needed rather than replaced.

## Assumptions
- "Package includes softcopy" for FR-6 is interpreted as: softcopy access is granted if `bookings.softcopy_addon = 1` OR if the layout's count <= the package's included `softcopy_count`. For simplicity, FR-6 is scoped to the boolean `softcopy_addon = 1` (purchased add-on) since packages already grant access via their included count — the redundant button we target is the "Add Softcopy to Booking" link shown in the `else` branch of `done.php` when add-on is 0 but included softcopy count already covers the session.
- Offline preset cache (FR-11) is best-effort; the app does not need to fully function offline for payment/booking. Only preset thumbnails are cached.
- Inventory booking integration (FR-12) deducts stock based on `estimated_hardcopies`; actual print marking will also deduct (double-count is avoided by checking the `bookings` status flag or using a dedicated new column `inventory_deducted` to mark booking-level dedup).

## Open Questions
1. **Package softcopy inclusion semantics**: Should "package includes softcopy" grant download/email without the addon, in effect ignoring `softcopy_addon` entirely if `packages.softcopy_count >= booking's total captured strips`? *Assumption: yes, but implementation only hides the redundant buy-more button; access control remains per current code.*
2. **Mirror default**: Should the mirror toggle default ON for all sessions? *Assumption: yes per requirement text.*
3. **Walk-in consumables deduction unit**: Is 1 walk-in strip = 1 paper unit + 2ml ink consistent with the print queue's existing `inkUsedMl = ceil(copies * 2)`? *Assumption: yes, use same formula.*

---

## Acceptance Criteria

### rule
- **AC-1**: In `booth/capture.php`, three radio buttons (3s / 5s / 10s) labeled "Countdown Timer" select a countdown value; on submit the value is saved with the `guest_sessions` record (or session-level), and `booth/session.php` countdown uses that value instead of the hardcoded 3.
- **AC-2**: Live video in `booth/session.php` preview is horizontally flipped by default; flipping does not affect the PNG files on disk captured via canvas.
- **AC-3**: Walk-in page contains zero `<select>` dropdowns that allow package price selection; price is shown as static text; `walkin/store.php` rejects any request whose submitted tendered amount is less than DB `base_price`; form has no hidden/visible price fields other than the tendered cash amount.
- **AC-4**: Attempting `POST` to `booth/start_session.php` with a `booking_id` whose `status = 'confirmed'` but balance > 0 returns a flash "Full payment is required before starting the booth." and redirects to `booth/index.php` without inserting a `guest_sessions` row.
- **AC-5**: `booking/view.php` output HTML contains no `<select name="duration_hours">` or equivalent editable control; duration renders as a read-only text value equal to the row stored at booking creation.
- **AC-6**: In `booth/done.php` for a booking where `softcopy_addon = 1` the "Add Softcopy to Booking" upsell anchor is not rendered. Similarly in `gallery/index.php` cards the "softcopy not purchased" badge does not show a buy button when package already grants access.
- **AC-7**: On `booth/capture.php`, after granting camera permission, clicking any preset / filter / layout / frame option updates the live video preview's filter and the preview frame's layout dimensions within <500ms without a page reload.
- **AC-8**: On `booth/session.php`, with a 4-photo layout selected, clicking "Done / New Guest" before any photos are kept results in no action and the button is disabled; after the 4th photo is "kept", the button becomes enabled and submitting proceeds to `end_session.php`.
- **AC-9**: Logging in as admin and navigating to `users/profile.php` does **not** show customer bookings in the "Recent Bookings" section (admin profile only shows admin's own user bookings; admin-only booking browse continues under separate menu item).
- **AC-10**: Walk-in page HTML when searched for `<select` returns only the tendered amount label area, no price-related select.
- **AC-11**: After viewing preset cards once online, refreshing `booth/capture.php` with network disabled (browser offline) still renders preset images sourced from local cache.
- **AC-12a**: Inventory index shows a collapsible "Needs Attention" section with low-stock items when at least one item is below reorder level.
- **AC-12b**: After marking print jobs as printed that would bring a paper item below reorder level, a flash message lists those affected SKUs.
- **AC-12c**: Print queue displays a warning banner when total pending copies exceed the on-hand paper stock.
- **AC-12d**: Completing a booking via `booking/complete.php` writes the correct `inventory_logs` rows for estimated hardcopy paper+ink consumption and decrements stock.

### rubric
- **R-1 Booth UX cohesion (0-2)**: 2 = countdown + mirror + live preview + progression lock all feel polished, consistent, and no visual regressions in the existing booth frame / preset cards; 1 = features work but layout/whitespace or transitions are jarring; 0 = features break existing booth capture flow.
- **R-2 Security / tamper resistance (0-2)**: 2 = all balance, price, and duration guards are enforced server-side and no form field would let an attacker influence price/duration; 1 = server checks exist but one control leaks an implicit editable state; 0 = walk-in price or booking duration can still be altered via crafted POST.
- **R-3 Inventory operational readiness (0-2)**: 2 = staff can quickly understand stock health from the dashboard area, see usage history, respond to alerts, and deductions integrate across walk-ins, bookings, and prints without double-counting or zeroed rows; 1 = dashboard/alerts exist but integration with one flow (walk-in OR completed booking OR print) is missing or double-counts; 0 = inventory still lacks visibility and is not operationally usable.
- **R-4 Data segregation clarity (0-2)**: 2 = admin self-service profile gallery is unambiguously only their own bookings; staff all-bookings browse is a clearly separate route labeled for management; 1 = separation is enforced in code but page headings/menus could confuse; 0 = admin profile still surfaces customer bookings.
