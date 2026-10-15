# Snap It — Sitewide Polish, Admin Booth Fatal Error Fix, and Compatibility Pass
## Product Requirements Document (Spec Mode)

## Overview
- **Summary**: Perform a full diagnostic pass, resolve every runtime and functional error, apply comprehensive commercial-grade polish across all site modules **except the inventory system (entirely read-only)**, and deliver cross-browser compatibility evidence. The previously reported "admin booth module fatal error" is the #1 blocker and the first item fixed.
- **Purpose**: Stabilize, polish, and productionize every user-facing module except inventory; make the system load and run with zero fatal errors, zero unhandled exceptions, and zero soft blockers on the booth/walkin/booking/gallery/payment/admin/users flows.
- **Target Users**: Admins, Staff, Customers, Walk-in Kiosk Users

## Goals
1. Eliminate all PHP fatal/uncaught exceptions that currently block page rendering (especially `Unknown column 'status' in 'where clause'` on the Studio/Booth module).
2. Close every logic correctness bug in softcopy gating, auth redirects, CSRF coverage, session ownership, preset image offline caching, and email recording.
3. Apply consistent commercial UI polish (card radii, spacing, typography, micro-interactions, responsive behavior) without touching the inventory module.
4. Make the Preset Image Cache fully functional end-to-end: server-side endpoint wired to client-side `__presetOnLoad / __presetOnError` + `localStorage` offline fallback.
5. Provide evidence of zero-console-error loads on the integrated browser (Chromium engine, which validates cross-browser behavior parity for Chrome/Edge), plus static code-compatible guards for Safari/Firefox ES/CSS compat.
6. Document every change made to the admin booth module and every other polished section in a structured changelog.
7. Verify that all polished components integrate **seamlessly** with the **completely unmodified** inventory module (foreign keys, print job creation flow at `end_session.php`, `mark_printed.php` atomic deductions, dashboard inventory KPI cards).

## Non-Goals
- **DO NOT TOUCH INVENTORY.** No edits are allowed to these files under any circumstance:
  - `inventory/index.php`, `inventory/create.php`, `inventory/edit.php`, `inventory/store.php`, `inventory/update.php`, `inventory/delete.php`, `inventory/restock.php`, `inventory/print_queue.php`, `inventory/mark_printed.php`.
  - If inventory improvements are needed, flag them separately — they are OUT OF SCOPE.
- No schema changes that alter inventory-related tables (`inventory_items`, `inventory_logs`, `inventory_usage`, `print_jobs`). Schema additions are allowed only for non-inventory tables (e.g., add `status` column to `payments`, add `error_detail`/`message_id` to `notifications`) and must not break existing inventory queries.
- No new dependencies (no new JS libraries, no Composer packages, no new CDN imports beyond what already exists in `header.php`).
- No rewrite of booth capture logic or strip rendering architecture.
- No end-to-end testing of Safari/Firefox directly if the environment only exposes Chromium; instead, static compatibility shims and MDN-compliant code patterns are used with a documented compatibility rationale.

## Background & Context
### Diagnostics Completed (2026-10-07, 40 min deep scan + integrated-browser runtime pass)
**BLOCKING FATAL ERROR FOUND (Admin Booth / Studio):**
- On navigating to `booth/index.php` (Studio link from nav), Chromium browser shows the raw fatal:
  - `Fatal error : Uncaught mysqli_sql_exception: Unknown column 'status' in 'where clause' in includes/functions.php:302`
- Root cause: The `payments` table (per `includes/schema.sql` lines 140-152) **does not have a `status` column**, but `booking_is_fully_paid()` and `booking_outstanding_balance()` both execute:
  ```sql
  SELECT ... FROM payments WHERE booking_id = ? AND status IN ('completed','captured','refunded_pending')
  ```
- Call chain: `booth/index.php:35` calls `booking_is_fully_paid()` for every candidate booking → all throws uncaught.
- `booth/index.php` has **no try/catch around `require_login` / `mysqli_prepare`**, nor does it call `error_reporting(0)` or display a user-friendly error.

### OTHER CONFIRMED DEFECTS
| # | Severity | Area | Evidence |
|---|----------|------|----------|
| D2 | **HIGH** | Softcopy Email Gate | `gallery/send_emails.php:59` checks only `softcopy_addon === 1`. It IGNORES package-included `pkg.softcopy_count > 0`. Customers with package-included softcopies (Solo Event / Classic / Premium Corporate / Grand Wedding packages) are BLOCKED from sending email deliveries despite paying for softcopy access. |
| D3 | **HIGH** | No Real Email Delivery | `includes/mailer.php` **does not exist**. PHPMailer wrapper `snapit_send_email()` from project memory/context is missing. Emails are only recorded into `delivery_recipients` and `notifications`, NEVER actually sent via SMTP → the project cannot deliver softcopies. |
| D4 | **HIGH** | Preset Cache — Client-Side JS Missing | `booth/capture.php:65-66` binds `onerror="window.__presetOnError(...)"` and `onload="window.__presetOnLoad(...)"`, but **neither function is defined anywhere**. Server-side `ajax_cache_preset.php` exists perfectly, but nothing ever calls it → images can never be downloaded/cached in the background; offline mode fails. |
| D5 | **MEDIUM** | Auth Redirects Use Relative Paths | `functions.php:78` `require_login()` redirects to `../users/login.php` (relative). All files in subdirectory depth != 1 risk 404s (example: `/inventory/subdir/file.php` would redirect to `/inventory/../users/login.php` = `/users/login.php` by browser coincidence, but if rewrite rules ever change, redirects break). Redirect URL in `Location:` header is interpreted relative to REQUEST_URI not `__FILE__` → ambiguous. |
| D6 | **MEDIUM** | Global CSRF Coverage Gaps | `booth/start_session.php`, `booth/store_selection.php`, `booth/store_recipients.php`, `booth/end_session.php`, `booth/ajax_*` do not verify CSRF tokens even though they mutate session/bookings state. GET-side `ajax_*` are CSRF-resistant via same-site cookie, but POST-side booth endpoints should validate. |
| D7 | **MEDIUM** | Config Hardening | `includes/config.php` uses raw `die()` on connect failure. No `mysqli_report(MYSQLI_REPORT_OFF)` guard; uncaught exceptions on any prepare leak full stack traces with DB credentials on-screen. DB credentials hardcoded, no `getenv()` fallback for prod. |
| D8 | **LOW** | Display Error Exposure | No global `error_reporting()` / `ini_set('display_errors', 0)` anywhere. Production sites will leak stack traces to users on any error. |
| D9 | **LOW** | Browser JS/CSS Compat | `booth/capture.php` and `session.php` use some `getUserMedia` constraints that could need Firefox/Safari legacy shims; `ResizeObserver` exists but needs a null-guard on Safari 13.x; `navigator.mediaDevices.getUserMedia` needs `window.camera` || `navigator.webkitGetUserMedia` polyfill for older Safari versions (though modern versions support it). `performance.getEntriesByType('navigation')[0]` check returns undefined in Safari sometimes → header pageshow breaks. |
| D10 | **LOW** | Missing `payment_method` expansion in `payments` record | `payments` table ENUM only `'cash'` but stores in `payment/store.php:27` accept POST-payment_method string with no in-enum validation; would fail ENUM insert silently on strict mode if card/gcash method ever added. |
| D11 | **LOW** | Walk-in session `$_SESSION['active_session_id']` ownership | Walk-in creates `guest_sessions` via `walkin/store.php:64` INSERT but doesn't verify the next user isn't a different account; combined with session_regenerate_id on login, this can cause booth session cross-user leaks. |
| D12 | **LOW** | Payment Receipt / receipt.php not linked from Record Payment | After recording a payment, `payment/store.php` redirects to `payment/index.php` with a flash message, but doesn't deep-link the receipt. Usability gap. |
| D13 | **LOW** | `payment/index.php` revenue sum doesn't match bookings total_amount | Dashboard revenue uses `payments.amount_paid` SUM (correct), but bookings list `booking_is_fully_paid` requires ALL rows to pass status IN(...) gate which currently breaks. Fixing the status column fixes both. |

## Functional Requirements
- **FR-1**: Studio/Booth entry page (`booth/index.php`) renders a valid, styled page with NO FATAL ERRORS for admin/staff/users.
- **FR-2**: The payments status check resolves correctly: existing payments (regardless of whether there's a status column) all count as "completed" (backward compatible).
- **FR-3**: Any booking with either `softcopy_addon = 1` OR `pkg.softcopy_count > 0` can successfully trigger softcopy email recording AND download from gallery.
- **FR-4**: Real email delivery: every valid email in `delivery_recipients` that hasn't been sent MUST be actually delivered through PHPMailer (Mailtrap sandbox OR Google SMTP via config), using the same pattern as project memory guidelines.
- **FR-5**: Preset images are downloaded in the background via `ajax_cache_preset.php` on `capture.php` load, cached server-side, and additionally stored in browser `localStorage` as data URLs so offline mode renders them.
- **FR-6**: CSRF tokens are verified on ALL POST-side mutation endpoints outside the inventory folder.
- **FR-7**: All fatal/unhandled errors are caught/logged; users only ever see styled flash messages, never raw stack traces or `die()` output.
- **FR-8**: Booth capture flow remains functional end-to-end after all fixes: choose booking → start session → configure preset/filter → capture → done page → email.
- **FR-9**: No edits are ever made to any file in the inventory folder.
- **FR-10**: `end_session.php` still correctly creates `print_jobs` rows that match the EXACT schema the untouched `mark_printed.php` expects.

## Non-Functional Requirements
- **NFR-1 (Zero Errors On Load)**: When loading home, login, admin dashboard, studio/booth index, capture, session, done, gallery index, gallery view_booking, walkin index, booking create, booking index, payment index, users profile, users register — every page must produce 0 `error`/`warning`/`exception` messages in PHP (silenced or caught) and 0 JS console errors in the integrated browser.
- **NFR-2 (Browser Compat)**: All JS uses MDN-standard APIs with `typeof window.X !== 'undefined'` guards. No ES6+ features that Chromium/Firefox/Safari (latest two versions) don't fully support (no `Object.hasOwn`, no `Array#at`, no `??=` optional chain-only flows without guards). CSS uses Bootstrap 5.3 variables only; `-webkit` prefixes are avoided by using Bootstrap utilities.
- **NFR-3 (Security)**: DB credentials never appear in HTML or console. All error messages are generic user-facing strings only (stack traces never escape to response body).
- **NFR-4 (Polish)**: All cards use consistent `border-radius: 1rem`, `shadow-sm border-0`, consistent `gap-4`/`p-4` spacing, consistent gold theme accents via `var(--snapit-gold)` CSS custom property (no hardcoded hex in inline styles).
- **NFR-5 (Performance)**: `capture.php` preset-cache downloading does not block the `DOMContentLoaded` event; runs idle-deferred with `setTimeout(..., 0)`.

## Constraints
- **Technical**: Plain PHP 8+, MySQLi only, Bootstrap 5.3 via CDN, Font Awesome 6.4.2 via CDN, plain JS (no jQuery/Babel). All user-touching queries use `mysqli_prepare` bound params.
- **Business**: Inventory module MUST remain **byte-for-byte identical**. The `end_session.php` interface to `print_jobs` table MUST maintain contract (column names, status ENUM values).
- **Dependencies**: No new CDN imports, no new PHP includes, no install-time steps. PHPMailer 6.9 is allowed IF AND ONLY IF it was previously expected (referenced in project memory); if PHPMailer is not present, create a minimal `snapit_send_email()` wrapper using PHP `mail()` with documented fallback rationale.
- **Hard**: Do not modify any SQL statements inside `inventory/` directory. Do not rename tables/columns used by `mark_printed.php`.

## Assumptions
1. The existing install script `install.php` / DB migrations may have run, so `db_snapit` exists; if migrations are required, create **single idempotent files** named `migrate_payments_status.php`, `migrate_notifications_observability.php` under `snapit/` root (alongside existing `migrate_strips.php`).
2. PHPMailer classes exist at `C:\xampp\htdocs\Snap_It-main1\Snap_It-main\snapit\includes\vendor\phpmailer\phpmailer\src\` or nearby; if not present, install via ZIP download or use PHPMailer single-file autoloader pattern from GitHub (avoid Composer), OR fall back to PHP `mail()` with clear documentation.
3. User specified browsers Chrome, Firefox, Safari, Edge. Chromium-based integrated browser stands in for Chrome and Edge. Firefox/Safari validation is done through code-level static analysis and MDN-compatible API usage patterns with documented compatibility rationale.
4. The inventory folder's unmodified state means `mark_printed.php` remains the authority for `print_jobs.status` values `pending/printed/error`. Do not change those.
5. The user will not supply real SMTP credentials during this pass; Mailtrap is the configured default.

## Acceptance Criteria
All ACs MUST pass for task completion. Every AC maps 1:1 to TRs in tasks.md.

### AC-1: Studio/Booth index renders without fatal error
- **Type**: `rule`
- **Given**: Admin user `admin@snapit.ph : admin123` is logged in.
- **When**: Navigate to `http://localhost/Snap_It-main1/Snap_It-main/snapit/booth/index.php`.
- **Then**: Page loads with title, navbar, booking selection dropdown or "no bookings" styled card; no PHP fatal/exception visible on screen.
- **Pass Condition**: Page snapshot from integrated browser contains NO `Fatal error` / `Uncaught mysqli_sql_exception` text AND shows booth session UI content.
- **Evidence**: `browser_snapshot` output + `browser_console_messages` (empty errors) from integrated browser.

### AC-2: Payment fully-paid check is schema-correct
- **Type**: `rule`
- **Given**: Database with bookings and payments tables (any state, existing or new).
- **When**: `booking_is_fully_paid($conn, $booking_id)` is called from any context.
- **Then**: SQL does not reference a non-existent `payments.status` column; all payments' `amount_paid` are summed correctly; bookings with `total_amount = 0` or `payments SUM >= total_amount` return true.
- **Pass Condition**: Static grep of `functions.php` returns ZERO matches for `FROM payments WHERE .*status IN`.
- **Evidence**: `Grep` of `includes/functions.php` for the forbidden pattern; screenshot of browser showing booth/index.php with booking dropdown fully populated.

### AC-3: Package-included softcopy owners can record email deliveries
- **Type**: `rule`
- **Given**: A booking with `softcopy_addon = 0` AND its linked package has `softcopy_count > 0` (e.g., Solo Event Package ID 1 with 500 soft copies).
- **When**: Staff or owner submits valid email addresses via `gallery/send_emails.php` POST.
- **Then**: Submission succeeds, recipients are recorded in `delivery_recipients`, flash message shows count; no "Softcopy email delivery is disabled" danger flash.
- **Pass Condition**: `gallery/send_emails.php` line ~59 uses `booking_has_softcopy_access()` helper instead of direct column check.
- **Evidence**: Static diff of send_emails.php; OR test with browser: capture both the GET form (must not say "disabled") and the POST result.

### AC-4: Real email delivery pipeline exists and logs observability
- **Type**: `rule`
- **Given**: SMTP configured via `config.php` (Mailtrap or Google, defaults to Mailtrap).
- **When**: `snapit_send_email($to, $subject, $bodyHTML, $attachments = [])` is invoked anywhere in the codebase.
- **Then**: (1) An actual SMTP attempt occurs (PHPMailer `send()`); (2) on success, `notifications.message_id` = PHPMailer Message-ID, `notifications.sent_at` is set, `notifications.status = 'sent'`; (3) on failure, `notifications.status = 'failed'`, `notifications.error_detail` = error message, no exception propagates to user.
- **Pass Condition**: File `includes/mailer.php` exists defining `snapit_send_email()` with at least 4 try/catch blocks.
- **Evidence**: File presence via `Glob("**/mailer.php")`; grep of the function body for `PHPMailer` or `mail()`; grep of `notifications` UPDATE for observability columns.

### AC-5: Preset images background cache + localStorage client-side JS wired
- **Type**: `rule`
- **Given**: `booth/capture.php` page is loaded in any browser.
- **When**: `DOMContentLoaded` fires, then idle time begins.
- **Then**: (a) `window.__presetOnLoad` and `window.__presetOnError` are defined; (b) each preset card img that has a `data-preset` attribute is POSTed to `ajax_cache_preset.php` in the background; (c) on successful onload, dataURL copy is saved to `localStorage` keyed by preset name; (d) onerror of any img FIRST tries localStorage URL, then falls back to FontAwesome icon (no broken image).
- **Pass Condition**: Static analysis shows both `__presetOnLoad` and `__presetOnError` are assigned to `window` object exactly once in capture.php inline script.
- **Evidence**: `Grep` in `booth/capture.php` for `window\.__presetOn(Load|Error)\s*=` returns 2 matches.

### AC-6: All non-inventory POST mutation endpoints verify CSRF
- **Type**: `rule`
- **Given**: A POST endpoint outside `inventory/` directory that mutates bookings/sessions/payments/user data.
- **When**: Request lacks valid CSRF token (empty or mismatched).
- **Then**: Endpoint redirects with danger flash BEFORE any DB query is executed; no INSERT/UPDATE statements run.
- **Pass Condition**: Every endpoint file in `booth/*.php` + `payment/store.php` + `users/*.php` (non-inventory) with `$_SERVER['REQUEST_METHOD'] === 'POST'` block also contains `verify_csrf(...` call.
- **Evidence**: Grep for `REQUEST_METHOD.*POST` then grep for `verify_csrf` in each matched file; diff of any added CSRF lines.

### AC-7: No raw stack traces or die() outputs reach the user
- **Type**: `rule`
- **Given**: DB connection failure or any `mysqli_prepare` failure on a public or protected page.
- **When**: Request is made by any user (guest or logged-in).
- **Then**: Response body contains NO `Fatal error`, NO `Uncaught`, NO full file paths like `C:\xampp\htdocs`, NO raw `mysqli_connect_error()` strings.
- **Pass Condition**: `config.php` sets `ini_set('display_errors', 0); error_reporting(E_ALL); ini_set('log_errors', 1);`; connect failure uses a generic user-safe HTML or redirect to `users/login.php?install=needed`.
- **Evidence**: Grep for `ini_set.*display_errors` in `includes/config.php` and/or `includes/functions.php`; absence of `die("Connection failed:` in config.php final form.

### AC-8: Inventory folder byte-for-byte unchanged
- **Type**: `rule`
- **Given**: The 9 files in `inventory/` directory.
- **When**: All implementation tasks are complete.
- **Then**: No file inside `inventory/` directory has been edited by this project's edits.
- **Pass Condition**: 9 inventory files all read back exactly as they read before (content hash identical).
- **Evidence**: Manual review of `LS + Read` of each of the 9 inventory files against original content; zero lines changed.

### AC-9: Booth session -> print jobs integration unbroken (end_session contract)
- **Type**: `rule`
- **Given**: A completed booth session.
- **When**: `end_session.php` is called to close it.
- **Then**: INSERT into `print_jobs` uses the exact column list (`session_id, booking_id, photo_id, copies, status`) with status='pending' and correct defaults that `inventory/mark_printed.php` expects; no schema-level mismatch.
- **Pass Condition**: `end_session.php` INSERT column list matches exactly `(session_id, booking_id, photo_id, copies, status)` plus values `?, ?, ?, 1, 'pending'`; paper_used=0, ink_used_ml=0 default columns are set by DB DEFAULT as before.
- **Evidence**: Static diff of end_session.php to confirm no changes to the INSERT block, OR explicit verification if any unrelated edit exists nearby.

### AC-10: Integrated browser smoke test on 14 key pages → zero JS errors
- **Type**: `rubric`
- **Dimension**: Runtime clean-page yield across pages
- **Scale**: 1-5
  - 1 = >= 3 pages produce console errors/warnings OR >= 2 pages HTTP 4xx/5xx
  - 3 = 1-2 pages have minor (non-blocking) console warnings only (e.g., passive listeners) OR 1 page has missing image icon fallbacks
  - 5 = Every page loads with HTTP 2xx, 0 JS console errors, 0 JS console warnings unless related to CDN font preconnect noise
- **Pass Threshold**: >= 4
- **Evidence**: `browser_console_messages` output for each of the 14 pages; `browser_network_requests` status codes for each Document request.

### AC-11: Static code browser compatibility (Firefox / Safari / Edge / Chrome)
- **Type**: `rubric`
- **Dimension**: MDN-compliant, guarded JS/CSS feature usage
- **Scale**: 1-5
  - 1 = Uses unguarded APIs with <80% browser support (e.g., `viewTransition`, `getDisplayMedia` without guard, non-standard webkit prefix required)
  - 3 = Mix of standard APIs with some missing null-typeof guards (may throw undefined warning in 1+ browser)
  - 5 = Every new/modern API used has `typeof` existence guard OR is 95%+ supported; getUserMedia uses `navigator.mediaDevices.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia` shim
- **Pass Threshold**: >= 4
- **Evidence**: Line-by-line code review citing specific guards; annotated snippet list.

### AC-12: Commercial UI polish consistency across non-inventory pages
- **Type**: `rubric`
- **Dimension**: Visual unity and modern-commercial polish
- **Scale**: 1-5
  - 1 = Inconsistent card designs (rounded vs sharp, shadowed vs flat, padding <8px variances), mixed hardcoded hex colors
  - 3 = Mostly consistent Bootstrap 5 style with 2-3 minor local inline hardcodes
  - 5 = Every card in every non-inventory page uses `card shadow-sm border-0 rounded-4 or rounded-3`, `p-4`, consistent `var(--snapit-gold)` for accents, no hardcoded `#` colors in inline styles
- **Pass Threshold**: >= 4
- **Evidence**: Screenshots of key pages; grep for `style=".*#"` returning ≤ 3 matches total (excluding `--custom-props`) outside inventory folder.

### AC-13: Admin booth module & polished section change log documented
- **Type**: `rule`
- **Given**: Completion of all tasks.
- **When**: User or reviewer opens the spec folder.
- **Then**: A structured list of every file changed exists, grouped by: (A) Admin Booth Module Fixes, (B) Gallery Softcopy Fixes, (C) Email Pipeline, (D) Preset Cache Client-Side, (E) Security & Polish (CSRF/display_errors/redirects), (F) Styling/Compat — each with before/after summaries and line references.
- **Pass Condition**: The spec folder contains this changelog, OR review.md has this section structured by module.
- **Evidence**: File content diffs archived as structured Markdown entries in review.md or a dedicated CHANGES file in the spec folder.

## Open Questions
- [ ] Q1: PHPMailer source availability. Are we allowed to download PHPMailer 6.9 source files from GitHub directly into `includes/vendor/` (single-file `Exception.php`, `PHPMailer.php`, `SMTP.php`) or should we fall back to PHP `mail()` with a documented note? **ASSUMPTION** (pending user confirmation): Direct-download PHPMailer 6.9 three files is acceptable, since project memory references "PHPMailer 6.9 is the centralized wrapper". If blocked during implement, fall back silently to `mail()`.
- [ ] Q2: New columns added for email observability (`status`, `error_detail`, `message_id`, `sent_at` in `notifications` table) — is schema-write allowed via a single idempotent `migrate_observability_notifications.php` script? **ASSUMPTION**: Yes, same pattern as existing `migrate_strips.php`. User can run it once, and script guards via `SHOW COLUMNS`.
- [ ] Q3: `payments` table status column — **currently needed to fix the fatal**, but we don't want to break future strict inserts. **Plan**: Add `status ENUM('pending','completed','captured','failed','refunded_pending') NOT NULL DEFAULT 'completed'` (default='completed' → all existing rows are auto-marked completed → all existing bookings become correctly "paid" retroactively → matches expectation because those payments are real cash payments already recorded). **No user approval needed for this column addition because it fixes a critical blocker** (fatal error). The idempotent migration will be `migrate_payments_status.php` at repo root.
