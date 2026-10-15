<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('booth/index.php'));
}

if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    set_flash('Invalid security token. Please refresh the page and try again.', 'danger');
    $sid = isset($_POST['sid']) ? (int)$_POST['sid'] : 0;
    if ($sid > 0) redirect(site_url('booth/done.php?sid=' . $sid));
    redirect(site_url('booth/index.php'));
}

$sid = (int)($_POST['sid'] ?? 0);
if ($sid <= 0) {
    set_flash('Invalid session reference.', 'warning');
    redirect(site_url('booth/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT gs.booking_id, b.user_id AS booking_user, b.softcopy_addon
                               FROM guest_sessions gs
                               LEFT JOIN bookings b ON gs.booking_id = b.booking_id
                               WHERE gs.session_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $sid);
mysqli_stmt_execute($stmt);
$sess = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sess) {
    set_flash('Session not found.', 'warning');
    redirect(site_url('booth/index.php'));
}

if (is_customer() && (int)$sess['booking_user'] !== (int)$_SESSION['user_id']) {
    set_flash('You do not have permission to modify this booking.', 'danger');
    redirect(site_url('booth/index.php'));
}

$booking_id = (int)$sess['booking_id'];
$saved = 0;

mysqli_begin_transaction($conn);
try {
    for ($i = 1; $i <= 10; $i++) {
        $email = trim($_POST['email' . $i] ?? '');
        if ($email === '') continue;
        if (!valid_email($email)) continue;

        $check = mysqli_prepare($conn, "SELECT recipient_id FROM delivery_recipients WHERE session_id = ? AND email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, 'is', $sid, $email);
        mysqli_stmt_execute($check);
        if (mysqli_num_rows(mysqli_stmt_get_result($check)) > 0) continue;

        $ins = mysqli_prepare($conn, "INSERT INTO delivery_recipients (booking_id, session_id, email, sent_at, created_at)
                                      VALUES (?, ?, ?, NOW(), NOW())");
        mysqli_stmt_bind_param($ins, 'iis', $booking_id, $sid, $email);
        mysqli_stmt_execute($ins);
        $saved++;
    }

    if ($saved > 0) {
        $notif = mysqli_prepare($conn, "INSERT INTO notifications (booking_id, session_id, type, subject, message_body, sent_at, created_at)
                                        VALUES (?, ?, 'softcopy_sent', ?, ?, NOW(), NOW())");
        $subject = 'Softcopy photos dispatched for Session #' . $sid;
        $body = $saved . ' recipient(s) have received the softcopy set for session #' . $sid . ' (booking #' . $booking_id . ').';
        $type_val = 'softcopy_sent';
        mysqli_stmt_bind_param($notif, 'iiss', $booking_id, $sid, $subject, $body);
        mysqli_stmt_execute($notif);
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    set_flash('An error occurred while saving recipients.', 'danger');
    redirect(site_url('booth/done.php?sid=' . $sid));
}

if ($saved === 0) {
    set_flash('No valid new email addresses provided.', 'warning');
} else {
    set_flash($saved . ' recipient(s) saved and notifications queued. Softcopy links will be emailed shortly.', 'success');
}

redirect(site_url('booth/done.php?sid=' . $sid));
