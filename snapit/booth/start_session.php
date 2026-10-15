<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('booth/index.php'));
}

if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    set_flash('Invalid security token. Please refresh the page and try again.', 'danger');
    redirect(site_url('booth/index.php'));
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$guest_name = trim($_POST['guest_name'] ?? '');

if ($booking_id <= 0) {
    set_flash('Please select a valid booking.', 'warning');
    redirect(site_url('booth/index.php'));
}

$where = "WHERE booking_id = ? AND status IN ('confirmed','paid')";
$params = [$booking_id];
$types = 'i';
if (is_customer()) {
    $where .= " AND user_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$stmt = mysqli_prepare($conn, "SELECT booking_id, booking_type, total_amount FROM bookings $where LIMIT 1");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    set_flash('Booking not found or not eligible for booth sessions.', 'danger');
    redirect(site_url('booth/index.php'));
}

$bk_row = mysqli_fetch_assoc($result);

if (!booking_is_fully_paid($conn, (int)$bk_row['booking_id'])) {
    $bal = booking_outstanding_balance($conn, (int)$bk_row['booking_id']);
    set_flash('This booking has an outstanding balance of ₱' . number_format((float)$bal, 2) . '. Please complete payment before starting a booth session.', 'danger');
    redirect(site_url('payment/index.php'));
}

if ($bk_row['booking_type'] === 'walkin') {
    $chk = mysqli_prepare($conn, "SELECT 1 FROM guest_sessions WHERE booking_id = ? LIMIT 1");
    $bid = (int)$bk_row['booking_id'];
    mysqli_stmt_bind_param($chk, 'i', $bid);
    mysqli_stmt_execute($chk);
    $chkres = mysqli_stmt_get_result($chk);
    if (mysqli_num_rows($chkres) > 0) {
        mysqli_stmt_close($chk);
        set_flash('This walk-in package already has its photo session. Choose a new walk-in package to take more photos.', 'warning');
        redirect(site_url('walkin/index.php'));
    }
    mysqli_stmt_close($chk);
}

$session_owner = is_loggedin() ? (int)$_SESSION['user_id'] : null;
$stmt = mysqli_prepare($conn, "INSERT INTO guest_sessions (booking_id, guest_name, status, idle_timeout_at, started_at, session_owner_user_id)
                               VALUES (?, ?, 'active', NOW() + INTERVAL 3 MINUTE, NOW(), ?)");
$gn = empty($guest_name) ? null : $guest_name;
mysqli_stmt_bind_param($stmt, 'isi', $booking_id, $gn, $session_owner);
mysqli_stmt_execute($stmt);

$session_id = mysqli_insert_id($conn);
$_SESSION['active_session_id'] = $session_id;
unset($_SESSION['capture_index'], $_SESSION['captured']);

set_flash('Session started! Customize your booth experience.', 'success');
redirect(site_url('booth/capture.php'));
