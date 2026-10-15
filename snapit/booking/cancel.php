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

if (in_array($booking['status'], ['paid', 'completed', 'cancelled'])) {
    set_flash('This booking cannot be cancelled in its current state.', 'warning');
    redirect(site_url('booking/view.php?id=' . $booking_id));
}

$new_status = 'cancelled';
$upd = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE booking_id = ?");
mysqli_stmt_bind_param($upd, 'si', $new_status, $booking_id);
mysqli_stmt_execute($upd);

$notif_type = 'booking_cancelled';
$recipient_email = $booking['customer_email'] ?? null;
$user_id = (int)$booking['user_id'];
$notif_subject = 'Booking #' . $booking_id . ' Cancelled - ' . $booking['event_name'];
$notif_body = "Hi " . ($booking['customer_name'] ?? '') . ",\n\n"
    . "We regret to inform you that your booking has been cancelled.\n\n"
    . "Booking ID: #" . $booking_id . "\n"
    . "Event: " . $booking['event_name'] . "\n"
    . "Date: " . format_date($booking['event_date']) . "\n\n"
    . "If you have any questions, please contact us immediately.\n"
    . "Thank you for understanding.";

$notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications
    (booking_id, user_id, type, recipient_email, subject, message_body, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($notif_stmt, 'iissss',
    $booking_id, $user_id, $notif_type, $recipient_email, $notif_subject, $notif_body);
mysqli_stmt_execute($notif_stmt);

set_flash('Booking #' . $booking_id . ' has been cancelled. Customer notified.', 'success');
redirect(site_url('booking/view.php?id=' . $booking_id));
