# Snap It — SMTP Email Configuration & Maintenance Guide

## 0. Full functionality assessment (before fix)

**Verdict — NOT WORKING.** Before implementing PHPMailer:

| Flow | File | What it was doing | Delivered real email? |
|------|------|-------------------|-----------------------|
| Booking created | `booking/store.php:143` | INSERT into `notifications` (sent_at=NULL) | ❌ |
| Booking confirmed + reminders | `booking/confirm.php:56` | INSERT row, no delivery code | ❌ + no event_reminder rows |
| Booking cancelled | `booking/cancel.php:54` | INSERT row only | ❌ |
| Payment received (customer + admin) | `payment/store.php:87, 116` | Two INSERTs, no send | ❌ |
| Customer gallery softcopy send | `gallery/send_emails.php:94` | INSERT `delivery_recipients` with `sent_at=NOW()` (FAKE — no email was actually sent) | ❌ + lied about success |
| Booth done-screen recipients | `booth/store_recipients.php:49` | INSERT with sent_at=NOW(), and had a **bind_param bug** (5 args into 'iiss') | ❌ + broken recipient save |
| User registration welcome | `users/store.php` | No code at all | ❌ |
| Event reminders 1-2 days before | — | `type='event_reminder'` declared in schema ENUM but nothing ever INSERTed / scheduled / dispatched | ❌ |
| Cron retries for failed sends | — | None | ❌ |

**Schema gaps found** (now added by `migrate_mailer_schema.php`):

- `notifications` table missing `status`, `error_detail`, `message_id`, `scheduled_at`
- `notifications` missing composite index `(scheduled_at, type, status)` for cron performance
- `delivery_recipients` missing `error_detail`, `message_id`
- `delivery_recipients` missing UNIQUE on `(booking_id, session_id, email)` to prevent duplicate softcopy sends

All of the above are now wired end-to-end using **PHPMailer** via a shared wrapper that supports Mailtrap (dev/staging) and Google SMTP (production) with the same interface and full error observability.

---

## 1. Install PHPMailer

Two paths — either works because `vendor/autoload.php` auto-chains:

### Path A — Composer (recommended)
```bash
cd snapit
composer install
```
Composer reads `snapit/composer.json` and installs `phpmailer/phpmailer:^6.9` into `vendor/phpmailer/`.

### Path B — Manual (no composer)
1. Download latest PHPMailer 6.x **release zip** from https://github.com/PHPMailer/PHPMailer/releases
2. Extract and copy these 4 files into `snapit/vendor/phpmailer/src/`:
   - `Exception.php`
   - `SMTP.php`
   - `PHPMailer.php`
   - `OAuth.php` (optional, only needed for XOAUTH2; not required for either Mailtrap or Google App-Password flow)

After install, log in as staff and open **`snapit/test_email.php`** — the header card will show:
✅ PHPMailer availability → **LOADED** — `PHPMailer` class ready.

---

## 2. Configure mailer credentials

All mailer settings live in the `$MAILER` array in `snapit/includes/config.php`, with `getenv()` overrides so secrets can come from the webserver/Nginx/Apache environment (never commit real passwords to git).

```php
$MAILER = [
    'service'     => 'mailtrap' | 'google',        # toggle which SMTP backend is used globally
    'from_email'  => 'no-reply@snapit.ph',
    'from_name'   => 'Snap It Photobooth',
    'reply_to'    => 'support@snapit.ph',
    'debug_level' => 0,                             # set to 1 or higher in test_email.php via the "Show SMTP trace" checkbox
    'mailtrap' => [ ... ],                          # SMTP credentials for staging
    'google'   => [ ... ],                          # SMTP credentials for production
];
```

If you're re-running `install.php` on a fresh machine, these defaults are auto-written. To upgrade an existing installed DB, open `migrate_mailer_schema.php` (staff-only) and click **Run Migrations** — all steps are idempotent (safe to re-run).

### 2a. Environment variables (recommended for secrets)

Set these in your Apache/Nginx/PHP-FPM pool `.conf`, `.htaccess`, Windows `System Properties → Environment`, or shell profile before starting the web server:

| Var | Purpose |
|-----|---------|
| `SNAPIT_MAILER_SERVICE` | `mailtrap` or `google` — global switch |
| `SNAPIT_FROM_EMAIL` | SMTP MAIL FROM envelope (e.g. `no-reply@yourdomain.com`) |
| `SNAPIT_FROM_NAME` | Display name in the inbox |
| `SNAPIT_REPLY_TO` | Reply-To header (for customer support replies) |
| `SNAPIT_MAILTRAP_HOST` | Default `smtp.mailtrap.io` |
| `SNAPIT_MAILTRAP_PORT` | Default `2525` |
| `SNAPIT_MAILTRAP_USER` | 16-hex-char username from Mailtrap inbox → SMTP Settings tab |
| `SNAPIT_MAILTRAP_PASS` | 16-hex-char password from same tab |
| `SNAPIT_GOOGLE_USER` | Gmail address used to send |
| `SNAPIT_GOOGLE_PASS` | **Google App Password** (16 chars, space-free); *see §3b* |
| `SNAPIT_CRON_TOKEN` | Shared secret for HTTP-based cron trigger of `notifications_dispatch_pending.php` (§6) |

---

## 3. Mailtrap & Google SMTP setup step-by-step

### 3a. Mailtrap (development / staging / UAT)

Use this for all local testing — it *never* delivers real mail to recipients, just shows it in your Mailtrap sandbox inbox so you can inspect headers, HTML, and spam score.

1. Sign up free at https://mailtrap.io → create an Inbox (default Email Testing project).
2. Open **Inbox → SMTP Settings**. Integrations dropdown → pick **PHPMailer**.
3. Copy the **Username** (hex chars) into `$MAILER['mailtrap']['username']` (or `SNAPIT_MAILTRAP_USER` env var).
4. Copy the **Password** into the matching password field.
5. Defaults for the rest are already correct:
   - Host: `smtp.mailtrap.io` / Port: `2525` / Secure: `STARTTLS` / Auth: `true`.
6. In config.php set `'service' => 'mailtrap'`.
7. Go to **`test_email.php`** (staff login) → pick Mailtrap service → click **Send Test Email**.
   Expected result: success card shows `✅ Email accepted by mailtrap` and the message appears in your Mailtrap Inbox preview within 2s.

### 3b. Google SMTP / Gmail (production — real inboxes)

You **cannot** use your plain Google account password after May 2022 — Google blocks `less-secure-app` sign-ins entirely. You **must** create an App Password (requires 2-Step Verification enabled on the Google account):

1. Sign in to the Gmail account that will be your sender (e.g. `your.photobooth@gmail.com`).
2. Open https://myaccount.google.com/security → enable **2-Step Verification** (if it's off — the App Password option does not exist until this is on).
3. Immediately after 2SV is on, return to Security → scroll or search: **App passwords**.
4. Click **Select app** dropdown → pick **Other (Custom name)** → type `Snap It Photobooth` → click **Generate**.
5. Copy the generated **16-character space-free password** (e.g. `abcd efgh ijkl mnop` → paste without spaces: `abcdefghijklmnop`).
6. In config.php:
   ```php
   $MAILER['service'] = 'google';
   $MAILER['google']['username'] = 'your.photobooth@gmail.com';
   $MAILER['google']['password'] = 'abcdefghijklmnop';   // no spaces
   ```
   (Or set via `SNAPIT_GOOGLE_USER` / `SNAPIT_GOOGLE_PASS` env vars.)
7. The remaining Google defaults are already correct:
   - Host `smtp.gmail.com`, Port `587`, Secure `STARTTLS` (TLS in dropdown), SMTPAuth true, timeout 30s.
8. Go to **`test_email.php`** → pick **Google SMTP** → send a test to a 2nd Gmail inbox you own.
   Expected result: `✅ Email accepted by google` within 5s and the message lands in the Gmail **Primary** tab (not Spam). Headers show `Received: from smtp.gmail.com` with DKIM / SPF passes automatically applied by Google.

**Google SMTP quotas:** Consumer Gmail accounts are capped at ~500 external recipients / rolling 24h, and 2000 recipients/day for Google Workspace. For very-high-volume production consider switching to SES/Postmark/Mailgun by adding a third service key to the wrapper — the wrapper `snapit_configure_smtp()` is designed so adding a key in `$MAILER` plus a new case in test_email.php is enough.

---

## 4. End-to-end email list & expected subjects

All flows that send email, and what to check in Mailtrap inbox / Gmail after running each scenario:

| # | When it fires | Subject line (exact or prefix) | Recipient | Code file |
|---|---------------|--------------------------------|-----------|-----------|
| 1 | Customer creates account | `Welcome to Snap It Photobooth, {Name}!` | New user email | `users/store.php` |
| 2 | Booking submitted (pending confirmation) | `Booking #{ID} Created - {Event Name}` | Booking customer | `booking/store.php` |
| 3 | Staff confirms booking | `Booking #{ID} Confirmed - {Event Name}` | Booking customer | `booking/confirm.php` |
| 4 | Staff confirms booking — schedules 2 reminders | Two rows INSERTed `type=event_reminder` with `scheduled_at = {event_date}-1d` and `{event_date}-2d` | Booking customer | `booking/confirm.php` (stored; fired via cron §6) |
| 5 | Staff cancels booking | `Booking #{ID} Cancelled - {Event Name}` | Booking customer | `booking/cancel.php` |
| 6 | Staff records cash payment | `Payment Received - Booking #{ID}` | Booking customer | `payment/store.php` |
| 7 | (on #6) Same time — admin copy | `New Payment Recorded - Booking #{ID}` | First user with `role='admin'` | `payment/store.php` |
| 8 | Softcopy sent from customer gallery page | `Your Snap It Photos from {Event Name} (Session #{N})` | Up to 10 emails input in gallery form | `gallery/send_emails.php` |
| 9 | Softcopy recipients entered in booth Done screen | Same softcopy subject | Up to 10 emails input in booth Done form | `booth/store_recipients.php` |
| 10 | Cron runs 1-2 days before event | `Reminder: {Event Name} in {N} day(s) (Booking #{ID})` | Booking customer | `notifications_dispatch_pending.php` |
| 11 | Cron retries last 7 days' `status=failed` rows | Original subject of failed notification | Whoever was the original recipient | Same file as above |

Each of these now:
1. Attempts real PHPMailer SMTP send immediately after DB INSERT.
2. Writes `status=sent` / `status=failed` and `message_id` (SMTP server Message-Id) back to `notifications.*` or `delivery_recipients.*`.
3. On failure, captures up to 1000 chars of PHPMailer exception detail + SMTP last error into `error_detail` for forensics.

### Notification `status` column meaning

| status | Meaning |
|--------|---------|
| `pending` | INSERTed but not yet dispatched (normal for scheduled event_reminders until cron fires) |
| `sent` | PHPMailer `->send()` returned true |
| `partial` | gallery softcopy send when some recipients OK and some fail |
| `failed` | SMTP rejected / exception / missing credentials |

---

## 5. Security & transport protocols

| Concern | Mitigation |
|---------|-----------|
| In-transit encryption | Mailtrap: **STARTTLS on 2525**. Google SMTP: **STARTTLS on 587**. Both set `PHPMailer::ENCRYPTION_STARTTLS` when PHP≥5.5, fallback to `SMTPSecure='tls'`. If you add a legacy relay expecting SSL-wrapped connect on 465, set `smtp_secure='ssl'` and `Port=465` → wrapper uses `ENCRYPTION_SMTPS`. |
| Credentials on disk | All secrets have `getenv()` first. If you set env vars and commit a config.php with placeholder values (like install.php does), no real credentials ever land in git. |
| SMTP authentication | Both services explicitly enable `SMTPAuth=true` (never open-relay). |
| Attachment hardening | Softcopy emails reference photos via inline `<img>` + download links instead of attaching every raw 5MB PNG to every recipient. The `test_email.php` attachment is only ever a ~100 byte text written to `sys_get_temp_dir()` and unlinked after sending. |
| Output encoding | `CharSet='UTF-8'` globally — Filipino names with diacritics, emojis in subject (🎉), and multi-language event titles will round-trip correctly. |
| Header injection | All addresses pass `filter_var(… FILTER_VALIDATE_EMAIL)` and bodies are `htmlspecialchars` before HTML wrap. No user-supplied strings go into raw email headers without sanitization. |
| Timeout cap | `Timeout=30s` per SMTP session so a hung remote server never blocks the customer HTTP request indefinitely. |

---

## 6. Cron setup for event reminders + failed-send retries

The `notifications_dispatch_pending.php` script does two jobs every run:

1. Fires any `type=event_reminder AND scheduled_at <= NOW() AND DATE(scheduled_at)=CURDATE()` rows (so exactly on 1-day-before and 2-days-before).
2. Retries any `status=failed` notification younger than 7 days (e.g. a transient SMTP outage, or an App Password you replaced post-setup).

You can invoke it **two ways** — pick one:

### Via system cron (Linux/Mac — production recommended)
```cron
*/15 * * * * /usr/bin/php /var/www/html/snapit/notifications_dispatch_pending.php >> /var/log/snapit_cron.log 2>&1
```
Runs every 15 minutes. Reminder window is `DATE(scheduled_at)=CURDATE()`, so any run during that day will fire the reminder.

### Via URL (Windows Task Scheduler, or for shared hosting without cron)
First set the secret token in env:
```
set SNAPIT_CRON_TOKEN=s0meL0ngRand0mStr1ng
```
Then make a Scheduled Task / cron entry that hits:
```
https://your-domain.com/snapit/notifications_dispatch_pending.php?token=s0meL0ngRand0mStr1ng
```
If the token doesn't match, the page returns HTTP 403 with a short message. Never commit the token to the codebase.

Expected cron log output for a clean run:
```
[SNAP-IT NOTIFICATION DISPATCHER] started at 2026-10-02T08:00:01+08:00
  [OK]   event_reminder #412 to john@example.com (1d before)
  [OK]   event_reminder #405 to jane@example.com (2d before)
  [RETRY OK]  #398 booking_confirmed admin@snapit.ph
Done. Processed=3 sent=3 failed=0 retriesOK=1 retriesFail=0 at 2026-10-02T08:00:03+08:00
```

---

## 7. SMTP_CONFIGURATION test results matrix

Fill this after running `test_email.php` twice (Mailtrap, then Google) and walking through scenarios 1–11 from §4. Copy this template into the same document after your first production config.

### 7a. Test 1 — Mailtrap (staging) — environment: _______________________ date run: _______

| Scenario (from §4) | Sender | Inbox expected? | MSG-ID captured in DB? | notes |
|---|---|---|---|---|
| 1 Registration welcome | Mailtrap SMTP | ☐ Yes / ☐ No | ☐ / ☐ | |
| 2 Booking created | " | ☐ | ☐ / ☐ | |
| 3 Booking confirmed | " | ☐ | ☐ / ☐ | |
| 4 Reminder INSERT (manually bump scheduled_at to NOW and run cron) | " | ☐ | ☐ / ☐ | |
| 5 Booking cancelled | " | ☐ | ☐ / ☐ | |
| 6/7 Payment received (customer + admin) | " | ☐ x2 | ☐ / ☐ | |
| 8 Gallery softcopy send (10 emails, 2 fake invalid) | " | ☐ 8 received | ☐ / ☐ | |
| 9 Booth done softcopy send | " | ☐ | ☐ / ☐ | |

SMTP trace (from test_email.php debug toggle) observed:
- `SERVER -> CLIENT: 220 mailtrap.io ESMTP ready.` ☐ seen
- `250 2.0.0 Ok: queued as …` ☐ seen at end of DATA
- Any TLS/STARTTLS warnings: _________________________________________________

### 7b. Test 2 — Google SMTP (production) — environment: _______________________ date run: _______

| Scenario | Sender | Gmail Primary tab / not Spam? | DKIM/SPF PASS in `Show original`? | MSG-ID in DB `message_id` column? |
|---|---|---|---|---|
| test_email.php single shot | smtp.gmail.com:587 STARTTLS | ☐ Yes / ☐ No | header `dkim=pass` ☐ ; `spf=pass` ☐ | ☐ / ☐ |
| test_email.php **With attachment** on | same | ☐ attachment decoded correctly | same | ☐ |
| Scenario 3 confirm | same | ☐ | same | ☐ |
| Scenario 8 gallery softcopy | same | ☐ | same | ☐ |
| Scenario 10 cron reminder | same | ☐ | same | ☐ |

Observed App Password login stage (debug toggle on):
- `AUTH CRAM-MD5` succeeded / ☐ `AUTH LOGIN` succeeded / ☐ both worked
- Any errors around `SMTP ERROR: Password command failed`: ____ — resolve by regenerating a fresh Google App Password.

### 7c. Failure path smoke tests — date run: _______

| Test | Config change applied | Expected behaviour | Pass / Fail |
|---|---|---|---|
| F1 | Wrong Mailtrap password | notification.status=failed, error_detail contains `SMTP connect() failed` / `535 5.7.0 Invalid login or password` | ☐ |
| F2 | Google App Password is plain Google password (not App Password, 2SV on) | status=failed, detail mentions `535-5.7.8 Username and Password not accepted` | ☐ |
| F3 | 2SV turned off in Google (App Passwords vanish, App Password login used) | detail mentions `Application-specific password required` | ☐ |
| F4 | PHPMailer src files removed (wrapper missing) | test_email.php banner shows ❌ MISSING, every dispatch returns `phpmailer_missing` error WITHOUT crashing the booking page (non-blocking) | ☐ |
| F5 | Recipient `@example.com` fake | delivery_recipients.status for that email row = failed, error_detail has recipient-server bounce reason, remaining recipients still deliver | ☐ |
| F6 | Token-less cron URL hit | HTTP 403 page | ☐ |

---

## 8. Troubleshooting checklist (most common issues, ordered by frequency)

1. **PHPMailer class not found** → open `vendor/README.md` → install via Path A (composer) or Path B (copy 4 src files).
2. **Mailtrap: 535 Invalid login or password** → you copied username into password or vice versa; open Mailtrap SMTP Settings and re-copy fresh pair (they regenerate per-click sometimes).
3. **Google: 535 Username and Password not accepted** → either:
   - 2SV is OFF on the Google account (turn it on then regenerate App Password), or
   - you pasted the App Password **with spaces** from the Google UI, or
   - user changed their Google password → invalidates all App Passwords → make a new one.
4. **Google: `SMTP connect() failed` / `stream_socket_client(): unable to connect to tcp://smtp.gmail.com:587`** → host firewall / antivirus (Windows Defender / Bitdefender) blocks outbound 587; try switching to port 465 + `secure=SSL` (ENCRYPTION_SMTPS). If on a Philippine residential ISP, some ISPs block port 25/465/587 to combat spam — tunnel through a VPS or switch to Mailtrap/Mailgun for staging.
5. **Softcopy says "sent" but email never arrives (and DB shows status=sent)** → check SPF/DKIM/DMARC on the From domain. If `from_email` is `yourname@gmail.com`, Google handles this automatically; if `from_email` is a custom domain like `hello@my-photobooth.ph`, you need MX + SPF TXT records in DNS, and ideally enable Google Workspace DKIM for that domain. Otherwise Gmail silently files it in Spam.
6. **Event reminders never fire** → check `scheduled_at` column on the notification row (should be 1 or 2 days before event date at midnight). Then run cron manually once, OR set scheduled_at to NOW() and test. Check cron log for `Processed=0 sent=0` which means the WHERE clause didn't match.
7. **Duplicate softcopy rows (same email appears multiple times)** → run `migrate_mailer_schema.php` (idempotent) so the UNIQUE key on `(booking_id, session_id, email)` is added. Any existing duplicates must be cleaned manually first.
8. **Booking create page hangs after submit for exactly 30s then works** → PHPMailer timeout was reached trying to reach SMTP host. Usually means: (a) no internet on server, (b) firewall blocked outbound 2525/587, (c) host typo in config. Set `'timeout' => 10` during debugging so the user doesn't wait 30s per attempt.
9. **HTML shows raw tags or missing styling in Gmail** → all HTML is inline-styled in `snapit_email_wrap_html()` (Gmail strips `<style>` blocks). If you edit the template, **do not add `<style>` blocks** — keep everything inline or use `style="…"` attributes on every element you add.
10. **Emoji show as ?? in subject** → wrapper sets `CharSet='UTF-8'` so this should be fine. If you add custom subjects, don't use `substr()` / naive string functions on them — use `mb_substr()` when present.
11. **Gallery softcopy send: 2 of 4 recipients succeeded and 2 failed; partial=true** → inspect each delivery_recipients row:
    - `error_detail=softcopy addon not purchased for booking #N` → expected if booking.softcopy_addon=0; UI already hints at this in booth/done.php flash.
    - `error_detail=phpmailer_exception — SMTP Error: The following recipients failed: …` → recipient's mail server actively rejected the address (bad email format, mailbox full, MX down, etc.) — safe to retry once via cron or ask customer for corrected address.

For deeper debugging, flip the `Show SMTP trace` toggle on `test_email.php` before sending — you'll get a full `CLIENT → SERVER` transcript with every EHLO/STARTTLS/AUTH/DATA/RSET command so you can pinpoint the exact stage of failure.

---

## 9. Files changed summary (for future maintainers)

### Added / New
| Path | Purpose |
|------|---------|
| `composer.json` | Declares `phpmailer/phpmailer:^6.9` + PHP >=7.4 |
| `vendor/autoload.php` | PSR-4 loader; chains to composer autoload if present, else scans `phpmailer/src/*.php` |
| `vendor/README.md` | Install PHPMailer instructions (composer vs manual) |
| `includes/mailer.php` | Shared wrapper: load, config, TLS/SSL setup, error capture, helpers |
| `test_email.php` | Staff tool — dual-service SMTP smoke test with SMTP trace + attachments |
| `migrate_mailer_schema.php` | One-click idempotent DB migration of mailer columns (§0 schema gaps) |
| `notifications_dispatch_pending.php` | Cron script for event reminders + 7-day retry backlog |
| `SMTP_CONFIGURATION.md` | **This document** |

### Wired into existing flows
| Path | What changed |
|------|--------------|
| `includes/config.php` | Added `$MAILER` block with mailtrap/google keys, env overrides |
| `users/store.php` | Welcome email after successful insert (non-blocking, @silent on fail) |
| `booking/store.php` | INSERT booking_created notification → immediately dispatches, marks `sent_at`/`status` |
| `booking/confirm.php` | Dispatches confirmed email on confirm, INSERTs event_reminder rows for 1/2 days before |
| `booking/cancel.php` | Dispatches cancelled email |
| `payment/store.php` | Dispatches payment_received email for customer + 1-row admin copy |
| `gallery/send_emails.php` | Actually SENDS softcopy emails now, per recipient, with per-row `sent_at`/`error_detail`/`message_id`; fakes sent_at=NOW removed; 10 cap, dedupe, validation unchanged |
| `booth/store_recipients.php` | bind_param count bug (5 args → 4) fixed, same real softcopy send wired in, softcopy_addon gating explicit with error_detail message if addon off |
| `install.php` | Writes full `$MAILER` placeholder block into generated config.php, runs 7-column + 2-index migration steps after seed, re-bcrypts default accounts with current PHP cost so repair_accounts rarely needed |
