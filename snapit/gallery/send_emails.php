<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$redirect_url = site_url('gallery/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Invalid request method.', 'danger');
    redirect($redirect_url);
}

if (isset($_POST['csrf_token']) && !verify_csrf($_POST['csrf_token'])) {
    set_flash('Invalid security token. Please refresh and try again.', 'danger');
    $sid_post = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    if ($sid_post > 0) {
        $bstmt = mysqli_prepare($conn, "SELECT booking_id FROM guest_sessions WHERE session_id = ? LIMIT 1");
        mysqli_stmt_bind_param($bstmt, 'i', $sid_post);
        mysqli_stmt_execute($bstmt);
        $bres = mysqli_stmt_get_result($bstmt);
        $brow = mysqli_fetch_assoc($bres);
        if ($brow) {
            redirect(site_url('gallery/view_booking.php?id=' . (int)$brow['booking_id']));
        }
    }
    redirect($redirect_url);
}

$session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

if ($session_id <= 0) {
    set_flash('Invalid session reference.', 'danger');
    redirect($redirect_url);
}

$sstmt = mysqli_prepare($conn, "SELECT gs.booking_id, b.user_id AS booking_user_id, b.softcopy_addon
    FROM guest_sessions gs
    INNER JOIN bookings b ON gs.booking_id = b.booking_id
    WHERE gs.session_id = ? LIMIT 1");
mysqli_stmt_bind_param($sstmt, 'i', $session_id);
mysqli_stmt_execute($sstmt);
$sres = mysqli_stmt_get_result($sstmt);
$session = mysqli_fetch_assoc($sres);

if (!$session) {
    set_flash('Photo session not found.', 'danger');
    redirect($redirect_url);
}

$booking_id = (int)$session['booking_id'];
$redirect_url = site_url('gallery/view_booking.php?id=' . $booking_id);

if ((int)$session['booking_user_id'] !== $current_user_id) {
    set_flash('You are not authorized to send emails for this session.', 'danger');
    redirect($redirect_url);
}

if (!booking_has_softcopy_access($conn, $booking_id)) {
    set_flash('Softcopy email delivery is not available for this booking. It is included with Solo, Classic, Premium, and Grand Wedding packages, or may be purchased separately as a softcopy add-on.', 'danger');
    redirect($redirect_url);
}

$raw_emails = $_POST['emails'] ?? [];
if (!is_array($raw_emails)) {
    $raw_emails = [];
}

$valid_emails = [];
$seen = [];
$invalid_count = 0;
$total_submitted = 0;

foreach ($raw_emails as $raw) {
    $raw = trim((string)$raw);
    if ($raw === '') continue;
    $total_submitted++;
    $norm = strtolower($raw);
    if (isset($seen[$norm])) continue;
    if (valid_email($raw)) {
        $seen[$norm] = true;
        $valid_emails[] = $raw;
    } else {
        $invalid_count++;
    }
}

if (count($valid_emails) > 10) {
    $valid_emails = array_slice($valid_emails, 0, 10);
}

$inserted = 0;
if (!empty($valid_emails)) {
    $istmt = mysqli_prepare($conn, "INSERT INTO delivery_recipients
        (booking_id, session_id, email, sent_at, created_at)
        VALUES (?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE sent_at = IF(sent_at IS NULL, NOW(), sent_at)");

    $existing_keys_stmt = mysqli_prepare($conn, "SELECT email FROM delivery_recipients WHERE booking_id = ? AND session_id = ?");
    mysqli_stmt_bind_param($existing_keys_stmt, 'ii', $booking_id, $session_id);
    mysqli_stmt_execute($existing_keys_stmt);
    $existing_res = mysqli_stmt_get_result($existing_keys_stmt);
    $existing_emails = [];
    while ($row = mysqli_fetch_assoc($existing_res)) {
        $existing_emails[strtolower($row['email'])] = true;
    }
    mysqli_stmt_close($existing_keys_stmt);

    foreach ($valid_emails as $email) {
        $norm = strtolower($email);
        if (!isset($existing_emails[$norm])) {
            mysqli_stmt_bind_param($istmt, 'iis', $booking_id, $session_id, $email);
            if (mysqli_stmt_execute($istmt)) {
                $inserted++;
            }
        }
    }
    mysqli_stmt_close($istmt);
}

if ($inserted > 0) {
    $nstmt = mysqli_prepare($conn, "INSERT INTO notifications
        (booking_id, type, subject, message_body, created_at)
        VALUES (?, 'softcopy_sent', ?, ?, NOW())");
    $subject = 'Softcopy delivery recorded for session #' . $session_id;
    $body = $inserted . ' recipient(s) have been marked as sent for Session #' . $session_id . ' (Booking #' . $booking_id . ') by user #' . $current_user_id . '.';
    mysqli_stmt_bind_param($nstmt, 'iss', $booking_id, $subject, $body);
    mysqli_stmt_execute($nstmt);
    mysqli_stmt_close($nstmt);
}

if ($total_submitted === 0) {
    set_flash('No email addresses were provided.', 'warning');
} else {
    if ($inserted > 0) {
        $msg = 'Emails recorded: ' . $inserted . ' recipient(s) added for delivery.';
        if ($invalid_count > 0) {
            $msg .= ' (Skipped ' . $invalid_count . ' invalid address' . ($invalid_count !== 1 ? 'es' : '') . '.)';
        }
        set_flash($msg, 'success');
    } else {
        if ($invalid_count > 0) {
            set_flash('No new recipients added. ' . $invalid_count . ' invalid email address(es) were skipped.', 'warning');
        } else {
            set_flash('No new recipients added. All provided emails were already recorded.', 'info');
        }
    }
}

redirect($redirect_url);
