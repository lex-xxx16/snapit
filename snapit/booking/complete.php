<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$booking_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = (int)($_POST['id'] ?? 0);
} else {
    $booking_id = (int)($_GET['id'] ?? 0);
}

if ($booking_id <= 0) {
    set_flash('Invalid booking reference.', 'danger');
    redirect(site_url('booking/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT b.*, u.email AS customer_email, u.name AS customer_name
    FROM bookings b INNER JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    set_flash('Booking not found.', 'danger');
    redirect(site_url('booking/index.php'));
}

if ($booking['status'] !== 'paid') {
    set_flash('Only fully paid bookings can be marked as completed.', 'warning');
    redirect(site_url('booking/view.php?id=' . $booking_id));
}

$new_status = 'completed';
$upd = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE booking_id = ?");
mysqli_stmt_bind_param($upd, 'si', $new_status, $booking_id);
mysqli_stmt_execute($upd);

set_flash('Booking #' . $booking_id . ' has been marked as completed.', 'success');
redirect(site_url('booking/view.php?id=' . $booking_id));
