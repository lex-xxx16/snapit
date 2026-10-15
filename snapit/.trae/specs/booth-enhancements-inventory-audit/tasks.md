# Implementation Tasks: Snap It Booth Enhancements & Inventory Audit

## Task 1: Configurable Countdown Timer (3/5/10s)
- **Priority**: high
- **Parent AC**: AC-1 (rule)
- **Scope**:
  1. Add `countdown_seconds` column to `guest_sessions` (default 3) via migration SQL.
  2. Add countdown selector UI (3 radio buttons) to `booth/capture.php` with default 3s.
  3. Persist the submitted value in `booth/store_selection.php` when saving selections.
  4. In `booth/session.php`, read the configured value from `$session` and pass it to the `startCountdown()` JS function; replace the hardcoded `n=3` and 800ms interval so it matches the chosen seconds.
  5. Ensure retake and next-photo flows also use the same configured value.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-1.1 (rule)**: `booth/capture.php` renders three labeled countdown radio buttons and the form submits the chosen value.
  - **TR-1.2 (rule)**: Starting a capture with 10s selected causes the countdown overlay to display `10,9,...,1` over roughly 10 seconds before the flash.
  - **TR-1.3 (rule)**: Database migration adds nullable `countdown_seconds TINYINT NOT NULL DEFAULT 3` to `guest_sessions`; existing rows remain valid.
  - **TR-1.4 (rubric: 0-2)**: Countdown UI fits visually inside the card layout without overflow or scroll, and the interval feels natural (not too fast/slow) for each option.

---

## Task 2: Inverted (Mirror) Camera Preview + Capture Correct Orientation
- **Priority**: high
- **Parent AC**: AC-2 (rule)
- **Scope**:
  1. Add per-session `mirror_preview` column to `guest_sessions` (default 1) via SQL (reuse the migration from Task 1 or a separate idempotent file).
  2. Add a mirror toggle to `booth/capture.php` (default ON).
  3. Save the toggle value through `store_selection.php`.
  4. In `booth/session.php`, apply `transform: scaleX(-1)` (and `-webkit-transform`) to the live `<video>` element (and live video in capture.php preview) when mirror is enabled.
  5. Ensure the canvas `drawImage` used in `captureVideoFrameToDataURL` does **not** apply the horizontal flip so saved PNGs have correct unmirrored orientation. Keep CSS and canvas filter chains intact.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-2.1 (rule)**: In `session.php`, waving left hand in front of camera moves hand to the left side of the video element with default settings; saved PNG opened from disk shows hand on correct right side as seen by camera.
  - **TR-2.2 (rule)**: Toggling mirror OFF removes the CSS transform and preview matches saved orientation.
  - **TR-2.3 (rule)**: Existing CSS filters (preset + filter cards) still apply visually on the mirrored preview.
  - **TR-2.4 (rubric: 0-2)**: Mirror toggle UX is discoverable (icon + label) and does not clutter the selections page; saved PNG matches expectation.

---

## Task 3: Remove Walk-in Price Dropdown + Fixed Display + Server Validation
- **Priority**: high
- **Parent AC**: AC-3, AC-10 (rule)
- **Scope**:
  1. Audit `walkin/index.php` for any `<select>` or editable `<input>` related to package price.
  2. Ensure the only editable numeric input is the `amount_paid` cash tendered field.
  3. Verify `walkin/store.php` loads price strictly from `packages.base_price` via prepared statement, never from any user-supplied price field.
  4. Add defensive server check that rejects `$_POST['price']` or similar if somehow submitted.
  5. Confirm static price badge styling (`display-6 fw-bold` + `--snapit-gold`) remains the sole display of each package's base price.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-3.1 (rule)**: A grep for `<select` inside `walkin/` yields no option list with PHP price values.
  - **TR-3.2 (rule)**: Submitting a crafted `POST` to `walkin/store.php` with `base_price=0` + valid `package_id` is rejected; the recorded amount equals the DB `base_price`.
  - **TR-3.3 (rule)**: Cash tendered input min attribute matches `$p['base_price']` for each card.
  - **TR-3.4 (rubric: 0-2)**: Package cards display price clearly and the cashier flow (tendered / change) is unambiguous.

---

## Task 4: Payment Enforcement Before Booth Access
- **Priority**: high
- **Parent AC**: AC-4 (rule)
- **Scope**:
  1. Add a helper function `booking_is_fully_paid($conn, $booking_id)` in `includes/functions.php` that sums payments and compares to `bookings.total_amount` (or `status='paid'` OR `status='completed'` as a fast path; balance is computed via prepared statement).
  2. Update `booth/index.php` bookings query to filter using `status IN ('paid','completed')` already present but also explicitly exclude rows with unpaid balance (for rows where status was manually set but payments partial). Show inline badge for unpaid rows.
  3. In `booth/start_session.php`, call the helper; if unpaid, flash error + redirect; do not insert `guest_sessions`.
  4. Walk-in path continues to work because the insert creates the booking with `status='paid'` in same transaction.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-4.1 (rule)**: For a booking in `confirmed` status with `total_amount=5000` and no payments, `start_session.php` redirects to booth index with flash "Full payment is required before starting the booth." and no new `guest_sessions` row exists.
  - **TR-4.2 (rule)**: For a booking where total_amount equals sum of payments, the session starts successfully.
  - **TR-4.3 (rule)**: Walk-in POST still creates the session (tested by checking new row count after submission).
  - **TR-4.4 (rubric: 0-2)**: Error messaging explains clearly what's unpaid and links to booking view/payment record page; staff can understand the next step.

---

## Task 5: Lock Booking Duration (Remove Hour Adjust Dropdown in Details)
- **Priority**: medium
- **Parent AC**: AC-5 (rule)
- **Scope**:
  1. Review `booking/view.php` and confirm no `<select name="duration_hours">` exists (it currently does not); ensure no new edit form exposes it.
  2. If `booking` module has any `update/edit.php` page that includes duration dropdown, remove the dropdown and display static text only.
  3. Hardening: In any update handler (if exists) or `booking/store.php`, for existing bookings reject any write to `duration_hours` by ignoring it in SQL and/or raising an error for POST with duration_hours on an existing booking.
  4. Display on `booking/view.php` Duration row as static formatted "N hours" text consistent with adjacent columns.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-5.1 (rule)**: HTML of `booking/view.php` when loaded contains no `<select>` element with name or id matching `duration`.
  - **TR-5.2 (rule)**: Crafted POST to any booking update endpoint with `duration_hours=99` for an existing booking does not change the stored value.
  - **TR-5.3 (rule)**: Booking creation page (`booking/create.php`) duration dropdown is preserved for new bookings only.
  - **TR-5.4 (rubric: 0-2)**: Duration display in booking details layout matches neighboring label/value style.

---

## Task 6: Conditionally Hide/Disable "Get Softcopy" Redundant Buttons
- **Priority**: medium
- **Parent AC**: AC-6 (rule)
- **Scope**:
  1. Add a helper `booking_has_softcopy_access($booking)` in `functions.php` that returns true when `softcopy_addon == 1` or when the package base softcopy count >= required strips (reasonable safe default: `$booking['pkg_softcopy_count'] > 0 || softcopy_addon`).
  2. In `booth/done.php`, the `else` branch that shows "Add Softcopy to Booking" link must not render when helper says access is granted. Instead show a note "Softcopy access included in your package."
  3. In `gallery/index.php`, badges use same helper and avoid double-badging.
  4. In `gallery/view_booking.php`, non-purchase message alert (the secondary upsell) is similarly skipped when access is already granted via package.
  5. Ensure endpoints `gallery/download.php` and `gallery/send_emails.php` still authorize based on same helper semantics (not only `softcopy_addon`).
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-6.1 (rule)**: In `done.php` with `softcopy_addon=0` and `pkg_softcopy_count>0` the upsell anchor is not present in the HTML.
  - **TR-6.2 (rule)**: When `softcopy_addon=1` the download+email section renders normally.
  - **TR-6.3 (rule)**: `download.php` allows download when package softcopy count > 0 even without the addon purchase flag.
  - **TR-6.4 (rubric: 0-2)**: Customers never see a misleading "buy softcopy" button when their package already grants it; info messaging is clear and consistent.

---

## Task 7: Live Camera Preview on Capture/Customize Selections Page
- **Priority**: high
- **Parent AC**: AC-7 (rule)
- **Scope**:
  1. In `booth/capture.php`, inside the right-hand "Live Preview" card, add a `<video id="previewVideo">` element (hidden by default) alongside the existing static `previewPhotos` strip.
  2. Write a small JS module that requests `getUserMedia({video:{facingMode:'user'}})` on page load (after a small "enable preview" user gesture button if browser policy requires it) and streams to the `<video>`.
  3. On `change` of `.preset-radio`, `.filter-radio`, `.layout-radio`, `.frame-radio`: recompute the combined CSS filter string, apply to `previewVideo.style.filter`, rebuild the `CamFX.overlay()` on a sibling overlay element, and restyle the preview frame border/bg/dimensions.
  4. Mirror preview (Task 2) setting defaults to ON for the preview video as well, respecting user toggle.
  5. If camera access fails, fall back to the current Picsum static photo preview (no blocking error page).
- **Depends On**: Task 2 optional (mirror toggle feeds state).
- **Test Requirements (TR)**:
  - **TR-7.1 (rule)**: In capture page, after granting camera permission, clicking a preset card with `cam_filter = grayscale(1)` renders the live video preview in grayscale within 500ms.
  - **TR-7.2 (rule)**: Switching from 1-photo layout to 4-photo (2x2) layout updates preview frame border and internal grid placeholder dimensions immediately.
  - **TR-7.3 (rule)**: If camera is blocked (NotAllowedError) no blocking alert is thrown; static preview remains.
  - **TR-7.4 (rubric: 0-2)**: Video preview feels responsive and renders clearly inside the sticky preview card without breaking layout on smaller screens.

---

## Task 8: Done/New Guest Button State Management
- **Priority**: high
- **Parent AC**: AC-8 (rule)
- **Scope**:
  1. In `booth/session.php`, on page render the `doneBtn` is initially `disabled` attribute set (and `opacity:.6;cursor:not-allowed` style if not inherited).
  2. Write JS function `updateDoneBtnState()` called whenever `currentPhotos` changes (in `updateProgress`, after keep, retake, back). If `photoCount >= targetN`, remove `disabled` from `doneBtn` and remove muted style; else reapply.
  3. Add a small inline caption under the button that says, e.g., "Capture 4 photos to continue" when disabled, and a success check when enabled.
  4. Guard the form submit handler of `endForm` so even if `disabled` attribute is removed via devtools the JS callback still cancels submission if slot count condition fails.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-8.1 (rule)**: On initial page load with empty photos and `targetN=4`, `doneBtn.hasAttribute('disabled') === true`.
  - **TR-8.2 (rule)**: After keeping the Nth photo (N = targetN), button becomes enabled in the same JS tick.
  - **TR-8.3 (rule)**: Going back and deleting the last photo re-disables the button.
  - **TR-8.4 (rule)**: Crafted submit call via console bypassing disabled attribute fails client-side check.
  - **TR-8.5 (rubric: 0-2)**: Caption text and button state together clearly communicate how many photos remain.

---

## Task 9: Admin Gallery Data Segregation
- **Priority**: high
- **Parent AC**: AC-9 (rule)
- **Scope**:
  1. In `users/profile.php`, the recent bookings query must not return other customers' bookings even for admin users: change query filter to strictly `WHERE b.user_id = ?` (current behavior already does this for `$user_id` session user; verify no `if(is_staff)` branch widens it).
  2. Add a helper in `functions.php`: `gallery_visible_booking_ids_query($conn, $viewer_user_id, $is_staff)` that for profile/self-service gallery views restricts bookings to `user_id = ?`, and explicitly mark the staff browse route (`gallery/index.php`) as the only wide-view route with a page heading "All Bookings (Staff View)" vs customer heading "My Gallery".
  3. In `gallery/index.php`, update page title/heading to explicitly differentiate Staff Browse vs Customer Gallery: for staff show "All Bookings — Staff Overview" and for customers "My Photo Gallery".
  4. Ensure staff cannot accidentally see customer photos via `users/profile.php` route even if role is admin.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-9.1 (rule)**: Profile page (any role) shows bookings only for the logged-in user's own `user_id`.
  - **TR-9.2 (rule)**: Gallery index for staff explicitly uses a staff-only heading label; customer view does not.
  - **TR-9.3 (rule)**: Direct GET to `gallery/view_booking.php?id=OTHER_CUSTOMER_BOK` by customer user still rejects with 403-like flash; staff can still view it.
  - **TR-9.4 (rubric: 0-2)**: Separation between "my gallery" (self) and "staff overview" (all bookings) is unambiguous in labels/menu.

---

## Task 10: Preset Images Offline Cache (Local + Browser Cache)
- **Priority**: medium
- **Parent AC**: AC-11 (rule)
- **Scope**:
  1. Create `includes/ajax_cache_preset.php` — takes preset name via GET, downloads the corresponding Wikimedia image (via the existing `camera_photo_url` mapping) using `file_get_contents` + context, validates it's PNG/JPEG bytes, writes atomically to `uploads/preset_cache/<sanitized_preset_name>.jpg`.
  2. Ensure `.htaccess` in `uploads/` covers the new subfolder (or create a small one inside `uploads/preset_cache/`) disallowing script execution.
  3. Modify `camera_photo_url()` so if a local cached file exists, return the local URL; otherwise, return the remote URL as before.
  4. Browser layer: on `booth/capture.php`, each preset card `<img>` uses lazy loading and the `onerror` handler writes the loaded data URL to `localStorage` keyed by preset name; a matching `onload` caches the image on first success. If the image fails to load, it falls back to localStorage content before finally falling back to icon.
  5. Create cache folder with index.html on first install.
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-10.1 (rule)**: After loading `capture.php` online, `uploads/preset_cache/` contains at least one file for each active preset that had a mapping in `camera_photo_url()` (after first trigger — either automatic background fetch or explicit via AJAX endpoint).
  - **TR-10.2 (rule)**: Disabling network in browser devtools and refreshing `capture.php` still shows images (either from local disk URLs or localStorage).
  - **TR-10.3 (rule)**: `.htaccess` in preset_cache forbids PHP execution (test with dummy `.php` upload attempt being blocked via configuration, or confirm `SetHandler None` + `Options -ExecCGI` pattern exists).
  - **TR-10.4 (rubric: 0-2)**: Caching doesn't slow down first-render visibly; icon fallback never blanks.

---

## Task 11: Inventory System Audit & Improvements
- **Priority**: high
- **Parent AC**: AC-12a, AC-12b, AC-12c, AC-12d (rule) + R-3 (rubric)
- **Scope** (multi-part):
  1. **Schema additions**: Create migration SQL:
     - `inventory_usage` already exists; add to `bookings` a nullable column `inventory_deducted TINYINT(1) NOT NULL DEFAULT 0` to prevent double deduction when marking complete.
  2. **Inventory dashboard area** in `inventory/index.php`:
     - Card summary row with: Total SKUs, Low Stock Items (quantity_on_hand <= reorder_level), Out of Stock (qty 0), Paper On Hand, Ink On Hand (bottles), Usage past 7 days (sum of deltas from logs by category).
     - Collapsible "Needs Attention" section before the table containing rows of low/out items.
  3. **Filtering & search UX**:
     - Category `<select>` filter and Stock Status `<select>` filter added to the search form.
     - Filter state preserved when combining with `q=` search.
  4. **Usage history page**:
     - `inventory/history.php?id=N` shows paginated inventory logs for item N.
     - Link added per row in inventory list table.
  5. **Stock alerts**:
     - `mark_printed.php` after commit: check if any touched items' stock now <= reorder level; flash warning listing SKUs.
     - Inventory index shows a dismissable Bootstrap alert banner when any items are below reorder, with counts.
  6. **Print queue sufficiency**:
     - In `inventory/print_queue.php`, compute total pending copies (sum `copies` where status=pending). Compare against available paper stock (sum of quantity_on_hand for category=paper). If copies > paper, show warning banner with exact deficit and link to restock.
  7. **Walk-in deduction**:
     - In `walkin/store.php`, inside the existing transaction, after inserting the booking, deduct `estimated_hardcopies` as: paper units = `estimated_hardcopies`, ink units (bottles) = `ceil(estimated_hardcopies * 0.5)`. Mirror the deduction pattern from `mark_printed.php` (logs + usage rows), reason string = `Walk-in package hardcopy consumption (N strips)` with `reference_id = booking_id`.
  8. **Booking completion deduction**:
     - In `booking/complete.php` (create or enhance) and its handler: before marking complete, if `inventory_deducted=0`, deduct paper and ink based on `estimated_hardcopies`; set `inventory_deducted=1`; write matching `inventory_logs` + `inventory_usage` rows (job_id nullable or 0 for booking-level consumption).
- **Depends On**: None.
- **Test Requirements (TR)**:
  - **TR-11.1 (rule)**: Inventory index page after applying no filters renders at least 4 summary cards (total SKUs, low, out, paper on hand, ink, 7-day usage counts non-null values).
  - **TR-11.2 (rule)**: Category + status filter dropdowns produce correct filtered rows; combined with text search works.
  - **TR-11.3 (rule)**: `inventory/history.php?id=1` loads and lists logs for item 1, each entry having a delta and reason.
  - **TR-11.4 (rule)**: Running mark_printed that brings paper qty below reorder_level produces a flash warning with the SKU name(s).
  - **TR-11.5 (rule)**: Print queue page with 500 pending copies and 100 paper on-hand shows banner warning about deficit.
  - **TR-11.6 (rule)**: Walk-in POST for package `estimated_hardcopies=2` creates correct negative `inventory_logs` rows for paper (-2) and ink category, with booking_id reference. No errors/rollback.
  - **TR-11.7 (rule)**: Calling `booking/complete.php` twice on the same booking deducts stock only once (second call leaves quantity unchanged because `inventory_deducted` flag is set).
  - **TR-11.8 (rubric: 0-2)**: Staff dashboard layout is readable and alerts are non-intrusive but not missed.

---

## Phase Tracking

- **Specify**: done
- **Plan**: in_progress
- **Approve**: pending
- **Implement**: pending
- **Review**: pending
