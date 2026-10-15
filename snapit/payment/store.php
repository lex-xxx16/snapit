<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('payment/index.php'));
}

if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    set_flash('Invalid security token. Please refresh the page and try again.', 'danger');
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    if ($bookingId > 0) redirect(site_url('payment/record.php?booking_id=' . $bookingId));
    redirect(site_url('payment/record.php'));
}

$bookingId = $_POST['booking_id'] ?? '';
$amountPaid = $_POST['amount_paid'] ?? 0;
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$allowed_methods = ['cash'];
if (!is_string($paymentMethod) || !in_array(strtolower($paymentMethod), $allowed_methods, true)) {
    set_flash('Invalid payment method.', 'danger');
    $bookingIdInt = is_numeric($bookingId) ? (int)$bookingId : 0;
    if ($bookingIdInt > 0) redirect(site_url('payment/record.php?booking_id=' . $bookingIdInt));
    redirect(site_url('payment/record.php'));
}
$paymentMethod = strtolower($paymentMethod);

$orNumber = trim($_POST['or_number'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (empty($bookingId)) {
    set_flash('Booking is required.', 'danger');
    redirect(site_url('payment/record.php'));
}

if ($amountPaid <= 0) {
    set_flash('Amount paid must be greater than zero.', 'danger');
    redirect(site_url('payment/record.php?booking_id=' . (int)$bookingId));
}

if (empty($orNumber)) {
    set_flash('Official Receipt (OR) number is required.', 'danger');
    redirect(site_url('payment/record.php?booking_id=' . (int)$bookingId));
}

$dupStmt = mysqli_prepare($conn, "SELECT payment_id FROM payments WHERE or_number = ? LIMIT 1");
mysqli_stmt_bind_param($dupStmt, 's', $orNumber);
mysqli_stmt_execute($dupStmt);
$dupResult = mysqli_stmt_get_result($dupStmt);
if (mysqli_num_rows($dupResult) > 0) {
    set_flash('OR number is already used. Please enter a unique receipt number.', 'danger');
    redirect(site_url('payment/record.php?booking_id=' . (int)$bookingId));
}

$bkStmt = mysqli_prepare($conn, "SELECT booking_id, status, user_id, event_name, total_amount FROM bookings WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($bkStmt, 'i', $bookingId);
mysqli_stmt_execute($bkStmt);
$bkResult = mysqli_stmt_get_result($bkStmt);
$booking = mysqli_fetch_assoc($bkResult);

if (!$booking) {
    set_flash('Booking not found.', 'danger');
    redirect(site_url('payment/record.php'));
}

$collectedBy = $_SESSION['user_id'];

$insStmt = mysqli_prepare($conn, "INSERT INTO payments (booking_id, amount_paid, payment_method, payment_date, collected_by, or_number, notes)
                                   VALUES (?, ?, 'cash', NOW(), ?, ?, ?)");
mysqli_stmt_bind_param($insStmt, 'idiss', $bookingId, $amountPaid, $collectedBy, $orNumber, $notes);
mysqli_stmt_execute($insStmt);
$paymentId = mysqli_insert_id($conn);

$currentStatus = $booking['status'];
$newStatus = $currentStatus;
if ($currentStatus === 'pending' || $currentStatus === 'confirmed') {
    $newStatus = 'paid';
}

if ($newStatus !== $currentStatus) {
    $updStmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE booking_id = ?");
    mysqli_stmt_bind_param($updStmt, 'si', $newStatus, $bookingId);
    mysqli_stmt_execute($updStmt);
}

$customerId = $booking['user_id'];
$eventName = $booking['event_name'];
$customerStmt = mysqli_prepare($conn, "SELECT email, name FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($customerStmt, 'i', $customerId);
mysqli_stmt_execute($customerStmt);
$custResult = mysqli_stmt_get_result($customerStmt);
$customer = mysqli_fetch_assoc($custResult);

if ($customer) {
    $custSubject = "Payment Received - Booking #$bookingId";
    $custMessage = "Hi " . $customer['name'] . ",\n\n"
        . "We have received your payment of " . format_money($amountPaid) . " for \"$eventName\" (Booking #$bookingId).\n"
        . "OR Number: $orNumber\n\n"
        . "Thank you for your business!\n-- Snap It Team";

    $notifCustStmt = mysqli_prepare($conn, "INSERT INTO notifications (booking_id, user_id, type, recipient_email, subject, message_body, created_at)
                                              VALUES (?, ?, 'payment_received', ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($notifCustStmt, 'iisss', $bookingId, $customerId, $customer['email'], $custSubject, $custMessage);
    mysqli_stmt_execute($notifCustStmt);
}

$adminStmt = mysqli_prepare($conn, "SELECT user_id, email, name FROM users WHERE role = 'admin' LIMIT 1");
mysqli_stmt_execute($adminStmt);
$adminResult = mysqli_stmt_get_result($adminStmt);
$admin = mysqli_fetch_assoc($adminResult);

if ($admin) {
    $adminSubject = "New Payment Recorded - Booking #$bookingId";
    $adminMessage = "A new payment has been recorded:\n\n"
        . "Booking #$bookingId: $eventName\n"
        . "Customer: " . ($customer['name'] ?? 'N/A') . "\n"
        . "Amount: " . format_money($amountPaid) . "\n"
        . "OR Number: $orNumber\n"
        . "Collected by: " . ($_SESSION['name'] ?? 'Staff') . "\n"
        . "Date: " . date('M j, Y h:i A');

    $notifAdminStmt = mysqli_prepare($conn, "INSERT INTO notifications (booking_id, user_id, type, recipient_email, subject, message_body, created_at)
                                               VALUES (?, ?, 'payment_received', ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($notifAdminStmt, 'iisss', $bookingId, $admin['user_id'], $admin['email'], $adminSubject, $adminMessage);
    mysqli_stmt_execute($notifAdminStmt);
}

set_flash('Payment recorded successfully', 'success');
redirect(site_url('payment/receipt.php?id=' . $paymentId));
