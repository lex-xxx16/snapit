# Snap It — Sitewide Polish, Admin Booth Fatal Error Fix, and Compatibility Pass
## Implementation Plan (tasks.md)

Execution strategy: **Bottom-up dependency order** — critical fatal fixes first (T1, T2, T3), then security/CSRF (T6, T7, T8), then user-facing logic correctness (T4, preset cache T5), then polish pass (T9), then email pipeline (T10), then browser compat + testing (T11, T12).

---

## Task 1: Critical Fatal Fix — payments.status missing column + booth/index.php crash
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: None
- **Description**:
  1. Add an idempotent migration script `snapit/migrate_payments_status.php` (alongside existing `migrate_strips.php`):
     - Run `SHOW COLUMNS FROM payments LIKE 'status'`; if missing → `ALTER TABLE payments ADD COLUMN status ENUM('pending','completed','captured','failed','refunded_pending') NOT NULL DEFAULT 'completed'` AFTER `payment_date`.
     - Default `'completed'` ensures all EXISTING cash payments recorded before this fix retroactively count as completed → fixes the booking_is_fully_paid() check returning 0 paid for all past bookings.
  2. **Verify inventory untouched**: Do NOT edit any inventory/* file at any point.
  3. Safety hardening of booth/index.php (NOT inventory): Wrap the booking-loop + payment lookups in try/catch Throwable so that a single bad row never fatal-blankets the whole page; if exception caught, flash a staff-visible warning "One or more bookings could not be loaded; please contact the administrator." and continue rendering all valid rows.
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-8 (integration)
- **Test Requirements**:
  - `rule` TR-1.1: After running `migrate_payments_status.php` once in browser, `SHOW COLUMNS FROM payments` shows `status` column with ENUM(...), DEFAULT='completed', and no existing rows were modified except status=completed. Evidence: SQL query output or post-migration SELECT * FROM payments LIMIT 2 shows status=completed.
  - `rule` TR-1.2: `booth/index.php` loads as admin user via integrated browser without "Fatal error"/"Uncaught" text in snapshot. Evidence: browser_snapshot output.
  - `rule` TR-1.3: Grep for `FROM payments WHERE booking_id = ? AND status IN` returns 2 matches still present (functions.php lines 302, 325), correctly referencing the now-existing column. Evidence: grep output.
  - `rubric` TR-1.4: Error-handling robustness for booth/index; scale 1-3; 1=still blanket die/fatal, 2=try/catch added but no flash, 3=full try/catch + flash + graceful continue; threshold >= 3. Evidence: static code snippet of the try/catch block.
- **Notes**:

---

## Task 2: Global Runtime Error Shielding (config.php + functions.php)
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: None
- **Description**:
  1. In `includes/config.php`: Before `mysqli_connect`, add:
     ```php
     error_reporting(E_ALL);
     ini_set('display_errors', 0);
     ini_set('log_errors', 1);
     ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
     @mkdir(__DIR__ . '/../logs', 0755, true);
     mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
     ```
     Change `die("Connection failed: ...")` to a generic `set_flash("Site is unavailable right now. Please try again in a minute.", 'danger'); redirect(site_url('users/login.php'));` — never echo raw mysqli error to user.
  2. In `includes/functions.php`, replace the relative `require_login/require_staff/require_admin` redirects:
     - `redirect('../users/login.php')` → `redirect(site_url('users/login.php'))`
     - `redirect('../index.php')` → `redirect(site_url('index.php'))`
     This ensures they work from ANY depth (e.g., `inventory/subdir/` hypothetical).
  3. Add a bootstrap `set_exception_handler` in `functions.php` (after session start) that catches any otherwise-uncaught Throwable, logs it to `logs/php_errors.log` via `error_log()`, and redirects the user to a safe `site_url('index.php')` with a flash "Something went wrong. Our team has been notified." instead of a raw stack trace.
- **Acceptance Criteria Addressed**: AC-7
- **Test Requirements**:
  - `rule` TR-2.1: Grep for `ini_set\('display_errors'` returns ≥1 match in includes/*.php. Evidence: grep output.
  - `rule` TR-2.2: `require_login` redirect uses `site_url('users/login.php')` (NOT string literal `'../users/login.php'`). Evidence: grep output.
  - `rule` TR-2.3: NO `die("Connection failed` string exists anywhere in includes/config.php post-fix. Evidence: grep output 0 matches.
- **Notes**: Ensure `logs/` dir gets created if missing; add `.htaccess` to logs/ to deny all web access (`Require all denied`).

---

## Task 3: Hardening Softcopy Email Access Gate (gallery/send_emails.php Bug)
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: None
- **Description**:
  1. In `gallery/send_emails.php`, REPLACE lines 37-62 (the session query + softcopy gate):
     - Keep the existing LEFT JOIN `packages` on bookings so we have package data available, OR more simply: **call the `booking_has_softcopy_access($conn, $booking_id)` helper after extracting booking_id from the session query** (which already joins to `bookings` anyway).
     - Replace the buggy guard `if ((int)$session['softcopy_addon'] !== 1)` at line 59 with `if (!booking_has_softcopy_access($conn, $booking_id))` so it respects BOTH add-on flag AND package softcopy_count.
     - Update the danger message to "Softcopy email delivery is disabled for this booking. Purchase the softcopy add-on or upgrade to a package with softcopy access to enable delivery." (Matches the wording in gallery/download.php so CTA copy is consistent).
  2. Apply the same helper-based check to any OTHER non-inventory place that directly checks `$row['softcopy_addon']` instead of `booking_has_softcopy_access()`.
- **Acceptance Criteria Addressed**: AC-3
- **Test Requirements**:
  - `rule` TR-3.1: In `gallery/send_emails.php`, the guard `booking_has_softcopy_access($conn, $booking_id)` appears exactly once as the authorization check, and direct `softcopy_addon !== 1` check no longer appears. Evidence: grep output.
  - `rule` TR-3.2: A booking with softcopy_count > 0 in its package and softcopy_addon=0 passes the gate (formulate a manual test plan: select a booking with pkg softcopy_count>0, POST 2 valid emails via send_emails.php, confirm rows appear in delivery_recipients table AND danger flash NOT set). Evidence: SQL SELECT of delivery_recipients after POST, or browser test showing success flash.
  - `rule` TR-3.3: A booking with BOTH softcopy_addon=0 AND package softcopy_count=0 is STILL correctly rejected (danger flash). Evidence: test with a hypothetical add-on-only scenario.
- **Notes**: This bug affects EVERY paid package except the add-on purchases. Fix alone is a huge win.

---

## Task 4: Admin Gallery Segregation Audit + Minor Hardening (profile.php verification)
- **Status**: `pending`
- **Priority**: **medium**
- **Depends On**: None
- **Description**:
  1. Verify (NO CHANGES IF ALREADY CORRECT) `users/profile.php` line 51-56 bookings query has `WHERE b.user_id = ?` bound to `$user_id`. It does — confirmed in diagnostic read. If no bug → NO edits, document "Verified correct; no changes needed."
  2. Add an ownership check to `current_booth_session_id($conn)`: return null ALSO if the session found by `active_session_id` belongs to a DIFFERENT user_id than `$_SESSION['user_id']` (prevent cross-user booth session leaks from walk-in kiosks after login).
  3. Verify `gallery/index.php` My Gallery view defaults correctly (admin user sees their own bookings unless `?staff=1` is set). Already correct per diagnostic — document verification only.
  4. Make NO changes to inventory/.
- **Acceptance Criteria Addressed**: AC-8, AC-9
- **Test Requirements**:
  - `rule` TR-4.1: `current_booth_session_id()` adds a `booking.user_id <> $_SESSION['user_id']` ownership check; the SELECT query joins bookings and excludes rows from other user ids, OR verifies post-fetch in PHP. Evidence: grep + code snippet.
  - `rule` TR-4.2: 9 inventory files byte-identical to pre-edit (no edits allowed). Evidence: manual re-read vs known-good content from diagnostic phase (or documented "no changes" statement with checksums verified if feasible).
- **Notes**: This is primarily a verification-only task with a tiny cross-user guard.

---

## Task 5: Preset Cache Client-Side JS Wiring (capture.php — __presetOnLoad / __presetOnError)
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: None
- **Description**:
  1. In `booth/capture.php` — inside the existing `<script>` IIFE near the bottom of the file (DO NOT create new JS files), ADD definitions for:
     - `window.__presetOnLoad = function (imgEl)`: On image success → draw image to 240px offscreen canvas → `toDataURL('image/jpeg', 0.85)` → save to `localStorage.setItem('preset_' + imgEl.dataset.preset, dataUrl)`.
     - `window.__presetOnError = function (imgEl)`: On load failure → read `localStorage.getItem('preset_' + imgEl.dataset.preset)` → if exists, set `imgEl.src = dataUrl` → if not, replace the `<img>` with a FontAwesome icon card.
     - Additionally, on `DOMContentLoaded` (inside the existing IIFE), iterate every preset card image with `data-preset` attribute, and issue an idle-deferred `fetch('ajax_cache_preset.php', {method: 'POST', body: JSON.stringify({preset: name, src: currentSrc})})` with `Content-Type: application/json` header — AFTER the main page is interactive (use `setTimeout(idle, 200)` to not block).
  2. `ajax_cache_preset.php` is already correct — NO CHANGES TO SERVER-SIDE (the endpoint works).
  3. Ensure no inventory/.
- **Acceptance Criteria Addressed**: AC-5, AC-11 (browser compat)
- **Test Requirements**:
  - `rule` TR-5.1: `booth/capture.php` has TWO `window.__presetOn(Load|Error)` assignments (grep returns 2 matches). Evidence: grep output.
  - `rule` TR-5.2: A `fetch('ajax_cache_preset.php'` POST call exists in capture.php that triggers on idle. Evidence: grep output.
  - `rule` TR-5.3: Every new browser API used (`localStorage`, `fetch`, `OffscreenCanvas` fallback `HTMLCanvasElement`, `toDataURL`) has no IE11-era-only usage AND has the expected Safari/Firefox/Chrome support (documented). Evidence: MDN cross-check in task notes.
  - `rubric` TR-5.4: Graceful offline fallback behavior; scale 1-3; 1=blank broken images on offline, 2=localStorage wiring exists but images still flicker, 3=images switch cleanly to localStorage dataUrl fallback on onerror with zero broken image icons; threshold >= 3. Evidence: DevTools offline simulation (if accessible via browser tools) OR documented static code analysis of the onerror path.
- **Notes**: The code is pure JS addition only. No new files. No inventory edits.

---

## Task 6: CSRF Coverage — Booth/Walk-in/Booking/Payment POST Endpoints
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: None
- **Description**:
  1. Add CSRF token hidden input + server `verify_csrf()` check to the following NON-INVENTORY POST endpoints that are currently missing it:
     - `booth/start_session.php` (if POST, add verify_csrf + hidden token on start_session form)
     - `booth/store_selection.php` (POST with preset/filter/layout/countdown/guestname → add token check)
     - `booth/store_recipients.php` (POST email list → add token check)
     - `booth/end_session.php` (POST to end session → add token check)
     - Any `booth/ajax_*.php` that accepts POST: if GET only, mark CSRF-verified via same-site cookie + referer check.
     - `payment/store.php` (already has verify_csrf; confirm double-check only).
     - `payment/record.php`: if has POST block, add CSRF; verify.
  2. On the HTML side, add `<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">` hidden inputs into the corresponding forms.
- **Acceptance Criteria Addressed**: AC-6
- **Test Requirements**:
  - `rule` TR-6.1: Every POST endpoint in the NON-inventory set above contains an actual `verify_csrf(` call BEFORE any DB insert/update. Evidence: grep output (file list + line numbers).
  - `rule` TR-6.2: If CSRF token missing/incorrect on any of those endpoints, the endpoint redirects with danger flash and runs zero INSERT/UPDATE queries. Evidence: submit a test request via browser without token, observe danger flash + no DB writes.
  - `rule` TR-6.3: NO inventory file was edited in the process of this task. Evidence: manual readback of the 9 inventory files (no line diffs).
- **Notes**: `booth/ajax_heartbeat.php` is GET-only + same-session ownership check; skip CSRF (acceptable).

---

## Task 7: Admin Booth Module Additional Polish (Resolve the Prior "Admin Booth Module Error" Root Causes)
- **Status**: `pending`
- **Priority**: **medium**
- **Depends On**: T1 (fatal fix prerequisite)
- **Description**:
  1. In `booth/index.php` (Studio home), after T1 fatal fix, polish the UI so that:
     - Paid bookings show a green `✅ Paid` badge with checkmark.
     - Bookings with unpaid balance show a disabled option AND the warning line already exists in index.php code (keep that). Add a prominent top-of-card button → "Go to Payments (balance PHP X)" as a sticky button on mobile.
     - `booth/index.php` add a `<div class="gutter-anchor">` wrapper to keep the cards from collapsing in narrow mobile widths.
     - Walk-in link from nav for `is_customer()` only — currently correct. Confirm no admin can create a walk-in booking accidentally (if admin has walk-in link somehow, hide it).
  2. Make no inventory edits.
- **Acceptance Criteria Addressed**: AC-12 (polish consistency)
- **Test Requirements**:
  - `rule` TR-7.1: When viewing booth/index.php as staff, unpaid bookings in the dropdown do not contain a "Select" button or are disabled. Evidence: browser snapshot.
  - `rubric` TR-7.2: Booth/index page polish visual quality; scale 1-5; 1=clipped text / uneven padding, 3=basic Bootstrap cards, 5=consistent rounded-3/shadow-sm/var accents, no horizontal overflows at 375px viewport; threshold >= 4. Evidence: browser screenshot + responsive snapshot.
  - `rule` TR-7.3: `nav walkin/index.php` link (in header.php line 56) is only wrapped in `if (is_customer())` — verify no changes needed.
- **Notes**:

---

## Task 8: Payment Method ENUM Guard + Receipt Deep-Linking
- **Status**: `pending`
- **Priority**: **medium**
- **Depends On**: None
- **Description**:
  1. In `payment/store.php` and any `payment/record.php` or `walkin/store.php` if they insert payments:
     - Validate `$payment_method` against `in_array($payment_method, ['cash'], true)` BEFORE bind_param (to prevent ENUM strict-mode errors if a future non-cash method string arrives via POST that doesn't match the ENUM). If invalid method → set flash + redirect, NO DB write.
  2. After payment `record.php` or `store.php` completes successfully → redirect user to `payment/receipt.php?id=X` instead of plain index.php; add a deep-link back to booking view from receipt page. `payment/receipt.php` already exists; just enhance the redirect destination.
- **Acceptance Criteria Addressed**: AC-8 (integration via payments), AC-12 (usability polish)
- **Test Requirements**:
  - `rule` TR-8.1: payment/store.php walkin/store.php each have `in_array($payment_method, ['cash'], true)` BEFORE any DB INSERT statement. Evidence: grep output.
  - `rule` TR-8.2: After successful payment record, redirect target contains `receipt.php?id=` (not plain `payment/index.php`). Evidence: grep + redirect trace.
  - `rule` TR-8.3: No inventory edits. Evidence: inventory files identical.
- **Notes**:

---

## Task 9: Commercial UI Polish Pass — All Non-Inventory Pages
- **Status**: `pending`
- **Priority**: **medium**
- **Depends On**: T1 (needed to test booth/index visually)
- **Description**:
  1. Go page-by-page on every non-inventory PHP page listed in NFR-1 list (14 pages total), apply polish:
     - Replace any direct hardcoded `style="color:#XXXXXX"` with a `style="color:var(--snapit-gold)"` (or `--champagne` / `--snapit-cream` / `--espresso`) using the CSS variables defined in `style.css`.
     - Normalize all cards to `card shadow-sm border-0 rounded-3 p-4`; if a page has cards with `class="card shadow border"` instead, unify them. DO NOT add or remove padding from inventory pages (those are untouched).
     - Ensure tables use `table table-hover align-middle` + `thead-light` + `px-4` on first-last `th`/`td` (no horizontal cramped columns).
     - Buttons use `btn btn-snapit fw-semibold px-4 py-2 rounded-3` for primary calls; `btn btn-outline-light` / `btn-ghost` for secondary.
  2. Target pages (must visit and apply exactly): users/profile.php (currently open in IDE), users/login.php, users/register.php, booking/create.php, booking/index.php, booking/view.php, gallery/index.php, gallery/view_booking.php, payment/index.php, payment/receipt.php, walkin/index.php, booth/index.php, booth/capture.php, booth/done.php, booth/session.php, admin/index.php, admin/customers.php, admin/staff.php.
  3. Skip ALL `inventory/` 9 files completely.
- **Acceptance Criteria Addressed**: AC-12
- **Test Requirements**:
  - `rule` TR-9.1: Grep for pattern `style=".*#[0-9a-fA-F]{3,6}"` outside of `inventory/` folder + css variables + inline CSS value lines returns ≤ 3 matches total (excluding `style="--custom-var: value"` which are variable sets). Evidence: grep output.
  - `rule` TR-9.2: Every page has no horizontal scrollbar at 375px mobile width (no overflow:hidden hacks). Evidence: responsive browser tests via DevTools device emulation (if available) OR documented code review of container widths <100% anywhere in card layouts.
  - `rubric` TR-9.3: Commercial polish visual unity; scale 1-5; anchors as in AC-12; threshold >= 4. Evidence: screenshots of 6+ representative pages.
- **Notes**: Focus on consistency > novelty; don't change any existing DOM class names that JS depends on (e.g., keep `id="logoutModal"`, keep forms with existing method/action, etc).

---

## Task 10: Email Delivery Pipeline — mailer.php wrapper + dispatch worker + observability columns
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: T3 (softcopy gate prerequisite because sending email must only go to authorized recipients)
- **Description**:
  1. Create idempotent `migrate_observability_notifications.php` (at `snapit/` root, sibling to `migrate_strips.php`):
     - `SHOW COLUMNS FROM notifications LIKE 'status'` → if missing:
       - ADD `status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued'`
       - ADD `message_id VARCHAR(255) NULL`
       - ADD `error_detail TEXT NULL`
       - ADD INDEX idx_notifications_status_created(status, created_at)
     - Add observability columns to `delivery_recipients` too IF missing: `delivery_status`, `error_detail`, `smtp_message_id` (optional, soft-fail if no migrate possible).
  2. Create `includes/mailer.php` that exposes:
     ```php
     /**
      * Send an email via PHPMailer 6.9 (Mailtrap sandbox by default, Google SMTP optional).
      * Returns: ['success' => bool, 'message_id' => string|null, 'error' => string|null]
      * Never throws; always logs on failure.
      */
     function snapit_send_email($to, $subject, $bodyHTML, $attachments = [], $booking_id = null) { ... }
     ```
     - PHPMailer inclusion: First check `includes/vendor/phpmailer/phpmailer/src/PHPMailer.php` exists; if yes use it. If PHPMailer files NOT present → download the 3 required PHPMailer 6.9 files (Exception.php, PHPMailer.php, SMTP.php) DIRECTLY from GitHub raw URLs via PHP `file_get_contents` + write into `includes/vendor/phpmailer/phpmailer/src/` at runtime ONCE, OR fall back gracefully to PHP `mail()` + document "Mailer fallback: native PHP mail() enabled because PHPMailer source was missing".
     - SMTP configuration: Use constants from `includes/config.php` — add new `$smtp_host`, `$smtp_port`, `$smtp_user`, `$smtp_pass`, `$smtp_secure`, `$mail_from_email`, `$mail_from_name` with Mailtrap defaults + `getenv()` override for Google.
     - TLS enforced; never cleartext; log ALL results (success/fail + message_id + error) into `notifications` table via UPDATE AFTER SMTP attempt.
  3. Create cron-schedulable worker `notifications_dispatch_pending.php` at project root:
     - Finds up to 20 rows from `delivery_recipients` where `sent_at IS NULL` AND `delivery_status <> 'sent'` OR status 'queued' in notifications, grabs a booking+session, then calls `snapit_send_email()` per recipient; attaches all kept session_photos as ZIP or individual PNGs; updates sent_at + status + message_id + error_detail.
     - Worker itself is idempotent (lock table rows with FOR UPDATE SKIP LOCKED if MySQL 8+); rate limits to 10/min.
  4. Integrate calls: On `gallery/send_emails.php` successful insert → call immediate dispatch for newly inserted recipients; on `booth/store_recipients.php` (booth flow) → queue for dispatch.
- **Acceptance Criteria Addressed**: AC-4
- **Test Requirements**:
  - `rule` TR-10.1: File `includes/mailer.php` exists AND defines function `snapit_send_email`. Evidence: Glob + grep.
  - `rule` TR-10.2: The function has try/catch that never throws to callers; always returns array with 'success' bool key. Evidence: static code analysis of function body.
  - `rule` TR-10.3: Migrate script runs idempotently (second run SHOW COLUMNS = same state; no MySQL error thrown). Evidence: browser run twice, no flash danger on second run.
  - `rubric` TR-10.4: Observability completeness; scale 1-3; 1 = only 'sent'/'failed' status flag, 2 = + message_id or error_detail present but not BOTH, 3 = status + message_id + error_detail + created/sent timestamps all queryable; threshold >= 3. Evidence: DESC notifications table output + grep of UPDATE statement inside snapit_send_email success/fail branches.
  - `rule` TR-10.5: Zero inventory files touched. Evidence: 9 inventory files read back identical.
- **Notes**: Heavy task — do after everything else works. If PHPMailer download blocked in env, fall back to `mail()` cleanly with a documented note in functions.php and still update notifications table observability columns for the attempt.

---

## Task 11: Browser Compatibility Static Shims (Firefox / Safari / Edge)
- **Status**: `pending`
- **Priority**: **medium**
- **Depends On**: T5 (preset JS) + T9 (polish pass affecting CSS)
- **Description**:
  1. `includes/header.php` — pageshow handler (lines 27-35):
     - Guard `performance.getEntriesByType('navigation')[0]` → assign to var, check exists before `.type`; older Safari returns undefined for entries.
     - Alternative fallback: just check `ev.persisted` alone (already exists) + wrap `performance` in `typeof performance !== 'undefined' && performance.getEntriesByType`.
  2. `booth/session.php` + `capture.php` — getUserMedia:
     - Before calling `navigator.mediaDevices.getUserMedia(constraints)`, add the standard legacy shim:
       ```js
       if (!navigator.mediaDevices) navigator.mediaDevices = {};
       if (!navigator.mediaDevices.getUserMedia) {
         navigator.mediaDevices.getUserMedia = function(constraints) {
           var gum = navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
           if (!gum) return Promise.reject(new Error('getUserMedia not supported'));
           return new Promise(function(resolve, reject) { gum.call(navigator, constraints, resolve, reject); });
         };
       }
       ```
  3. `booth/capture.php` ResizeObserver (existing) — add `'ResizeObserver' in window` guard before new.
  4. `header.php` auth-switch ResizeObserver (line 161) — same guard.
  5. CSS: Ensure Bootstrap 5 classes cover prefixes; no direct `-webkit-backdrop-filter` added without `backdrop-filter` standard property too (Bootstrap already does this).
- **Acceptance Criteria Addressed**: AC-11
- **Test Requirements**:
  - `rule` TR-11.1: All uses of `getUserMedia` in JS are reached through the shim path with `Promise.reject` fallback. Evidence: grep of getUserMedia calls + shim code block in same file.
  - `rule` TR-11.2: Zero lines of JS will throw ReferenceError on missing APIs in older Safari/Firefox ESR. Evidence: line-by-line verification code snippets showing `typeof` guards for each of: performance.getEntriesByType, navigator.mediaDevices, ResizeObserver, fetch (or XMLHttpRequest fallback if fetch used — fetch is fine for all modern).
  - `rubric` TR-11.3: Compatibility rigor quality; scale 1-5; 1=3+ features missing guards, 3=1-2 missing, 5=all features used have documented compatibility and all required shims/guards in place; threshold >= 4. Evidence: static code table listing API → guard used → browser support %.
- **Notes**: No inventory files edited during shim placement.

---

## Task 12: End-to-End Smoke Test Suite (14 Pages + 3 User Roles)
- **Status**: `pending`
- **Priority**: **high**
- **Depends On**: ALL prior tasks (T1-T11 complete; done LAST)
- **Description**:
  1. Use the integrated browser (Chromium engine, validates Chrome + Edge) to visit 14 pages in role-separated sessions:
     - **Guest**: home `/`, `/users/login.php`, `/users/register.php` → all 3.
     - **Admin**: login → `/admin/index.php`, `/admin/customers.php`, `/admin/staff.php`, `/booth/index.php`, `/booth/capture.php`, `/gallery/index.php`, `/gallery/index.php?staff=1`, `/booking/index.php`, `/booking/create.php`, `/payment/index.php`, `/users/profile.php` → 12.
     - **Customer**: logout then login as `customer@snapit.ph : cust123` → `/walkin/index.php`, `/booking/view.php?id=X` (existing), `/gallery/view_booking.php?id=X` → 3.
     Total unique coverage ~ 16 pages (overlaps deduplicated).
  2. Capture `browser_console_messages` per page — must be ZERO errors (warnings allowed but count them).
  3. Capture `browser_network_requests` per page — Document requests must be HTTP 2xx/3xx (0 4xx/5xx). Record `@vite/client` noise 404 as "non-blocking, IDE-injected" and accept (not app fault).
  4. Exercise the critical Admin Booth module path end-to-end:
     - Admin → Studio/Booth index → select a paid booking → Start Session → redirects to capture.php → capture.php loads correctly with presets visible → click a preset → live preview updates (check via browser_snapshot) → go back → cancel session cleanly.
     - Document this path as evidence of the "Admin Booth Module Fix".
  5. For Firefox and Safari, since the environment may not have them installed:
     - Document the static compatibility rationale file in the spec folder: `FIREFOX_SAFARI_COMPAT_RATIONALE.md` listing every ES API used + its browser compatibility version + shim present.
- **Acceptance Criteria Addressed**: AC-10, AC-11, AC-12
- **Test Requirements**:
  - `rule` TR-12.1: Every page in the smoke-test set returns 2xx/3xx Document status. Evidence: per-page browser_network_requests Document entry status code.
  - `rule` TR-12.2: Zero JS console errors (Severity=error) across ALL pages tested (warnings acceptable, IDE-injected @vite/client not counted). Evidence: concatenated browser_console_messages output.
  - `rule` TR-12.3: Admin Booth flow (booth/index.php → select booking → capture.php load) completes successfully with no HTTP 500. Evidence: snapshots of each stage.
  - `rubric` TR-12.4: Depth of compatibility documentation for Firefox/Safari; scale 1-3; 1=no doc, 2=partial bullet list, 3=structured table with API names + min versions + guards used; threshold >= 3. Evidence: FIREFOX_SAFARI_COMPAT_RATIONALE.md file in spec folder.
  - `rule` TR-12.5: Integration test — `booth/end_session.php` after this task creates print_jobs rows that the UNTOUCHED `inventory/mark_printed.php` can still find via `print_jobs.status='pending'` and deduct inventory correctly (verify one session end job creates pending rows correctly). Evidence: `SELECT job_id, status, copies, paper_used, ink_used_ml FROM print_jobs ORDER BY job_id DESC LIMIT 3;` after one session end.
- **Notes**: This is the final evidence-collecting task. No inventory edits.

---

## Task 13: Changelog Documentation + Verification Log (Review Prep)
- **Status**: `pending`
- **Priority**: **medium**
- **Depends On**: T1-T12 complete
- **Description**:
  1. Add a structured `CHANGES_SITE_POLISH.md` file inside the spec folder with sections:
     - Section A: Admin Booth Module Fixes (file list + before/after error states + resolution details for the fatal, plus any additional hardening from T1 T7)
     - Section B: Gallery Softcopy Gate Fixes (send_emails.php bug, the helper switch)
     - Section C: Email Pipeline (mailer.php + migrations + worker)
     - Section D: Preset Cache Client-Side (the __presetOnLoad/Error functions + localStorage + background fetch)
     - Section E: Security Hardening (CSRF additions, display_errors off, relative-redirect fix, generic-exception handler, cross-user booth session ownership guard)
     - Section F: UI Commercial Polish Pass (pages visited + hardcoded colors replaced + card consistency changes)
     - Section G: Browser Compatibility Shims Applied (getUserMedia, ResizeObserver, performance navigation type)
     - Section H: Integrations Seamless with Untouched Inventory (the end_session contract verification SQL output + print_jobs column list unchanged)
  2. Write a `REVIEW_CHECKLIST.md` listing every AC with links back to the TR evidence (to make Review phase fast).
- **Acceptance Criteria Addressed**: AC-13
- **Test Requirements**:
  - `rule` TR-13.1: 8 sections above all present in CHANGES_SITE_POLISH.md with 1+ bullet per section. Evidence: file read of the CHANGES doc.
  - `rule` TR-13.2: The Admin Booth section explicitly names the original "Unknown column 'status'" fatal error and its fix. Evidence: the CHANGES Section A paragraph mentioning the bug text verbatim.
- **Notes**: Zero inventory file edits required. This task is documentation only.
