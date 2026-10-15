<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('booking/create.php'));
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid form submission. Please try again.', 'danger');
    redirect(site_url('booking/create.php'));
}

$package_id = (int)($_POST['package_id'] ?? 0);
$event_name = trim($_POST['event_name'] ?? '');
$event_date = trim($_POST['event_date'] ?? '');
$start_time = trim($_POST['start_time'] ?? '');
$duration_hours = (int)($_POST['duration_hours'] ?? 0);

// Defensive AC-5: Duration is locked at the moment of initial booking creation.
// If a client craftily passes an existing booking_id/id in the attempt to update
// an existing record via this create-only handler, IGNORE the provided duration.
if (!empty($_POST['booking_id']) || !empty($_POST['id'])) {
    $existing_id = (int)($_POST['booking_id'] ?? $_POST['id'] ?? 0);
    if ($existing_id > 0) {
        $durLockStmt = mysqli_prepare($conn, "SELECT duration_hours FROM bookings WHERE booking_id = ? LIMIT 1");
        mysqli_stmt_bind_param($durLockStmt, 'i', $existing_id);
        mysqli_stmt_execute($durLockStmt);
        $durLockRes = mysqli_stmt_get_result($durLockStmt);
        $durLockRow = mysqli_fetch_assoc($durLockRes);
        mysqli_stmt_close($durLockStmt);
        if ($durLockRow) {
            $duration_hours = (int)$durLockRow['duration_hours'];
        }
    }
}

$venue = trim($_POST['venue'] ?? '');
$estimated_softcopies = (int)($_POST['estimated_softcopies'] ?? 0);
$estimated_hardcopies = (int)($_POST['estimated_hardcopies'] ?? 0);
$softcopy_addon = isset($_POST['softcopy_addon']) && (int)$_POST['softcopy_addon'] === 1 ? 1 : 0;
$hardcopy_delivery_name = trim($_POST['hardcopy_delivery_name'] ?? '') ?: null;
$hardcopy_delivery_address = trim($_POST['hardcopy_delivery_address'] ?? '') ?: null;
$hardcopy_delivery_contact = trim($_POST['hardcopy_delivery_contact'] ?? '') ?: null;
$notes = trim($_POST['notes'] ?? '') ?: null;

$valid_duration = [6, 7, 8, 9, 10, 11, 12];
$errors = [];

if ($package_id <= 0) {
    $errors[] = 'Please select a valid package.';
}
if (strlen($event_name) < 2 || strlen($event_name) > 200) {
    $errors[] = 'Event name must be between 2 and 200 characters.';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
    $errors[] = 'Invalid event date format.';
}
if (is_date_past($event_date)) {
    $errors[] = 'Event date cannot be in the past.';
}
$tomorrow = date('Y-m-d', strtotime('+1 day'));
if ($event_date < $tomorrow) {
    $errors[] = 'Bookings must be made at least 1 day in advance.';
}
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) {
    $errors[] = 'Invalid start time format.';
}
if (strlen($venue) < 5) {
    $errors[] = 'Please provide a valid venue/address (at least 5 characters).';
}
if ($estimated_softcopies < 0) {
    $errors[] = 'Estimated soft copies cannot be negative.';
}
if ($estimated_hardcopies < 0) {
    $errors[] = 'Estimated hard copies cannot be negative.';
}

if (!empty($errors)) {
    set_flash(implode(' ', $errors), 'danger');
    redirect(site_url('booking/create.php'));
}

$pkg_stmt = mysqli_prepare($conn, "SELECT * FROM packages WHERE package_id = ? AND is_active = 1 AND package_type = 'event' LIMIT 1");
mysqli_stmt_bind_param($pkg_stmt, 'i', $package_id);
mysqli_stmt_execute($pkg_stmt);
$pkg_result = mysqli_stmt_get_result($pkg_stmt);
$pkg = mysqli_fetch_assoc($pkg_result);

if (!$pkg) {
    set_flash('The selected package is no longer available.', 'danger');
    redirect(site_url('booking/create.php'));
}

// Duration is fixed by the package (no free choice).
$duration_hours = (int)$pkg['duration_hours'];
if ($softcopy_addon && (!(int)$pkg['has_softcopy_addon'] || (int)$pkg['softcopy_count'] > 0)) {
    $softcopy_addon = 0;
}
if (!in_business_hours($start_time, $duration_hours)) {
    $errors[] = 'Booking is outside business hours (8:00 AM to 10:00 PM). Please adjust the time or duration.';
}
if (booking_has_conflict($conn, $event_date, $start_time, $duration_hours)) {
    $errors[] = 'This time slot is already booked. Please choose a different date or time.';
}

if (!empty($errors)) {
    set_flash(implode(' ', $errors), 'danger');
    redirect(site_url('booking/create.php'));
}

$package_price = (float)$pkg['base_price'];
$addon_price = $softcopy_addon ? (float)$pkg['softcopy_addon_price'] : 0.00;
$total_amount = $package_price + $addon_price;
$status = 'pending';
$user_id = $_SESSION['user_id'];

$ins_stmt = mysqli_prepare($conn, "INSERT INTO bookings
    (user_id, package_id, event_name, event_date, start_time, duration_hours, venue,
     estimated_softcopies, estimated_hardcopies, softcopy_addon,
     hardcopy_delivery_name, hardcopy_delivery_address, hardcopy_delivery_contact,
     package_price, addon_price, total_amount, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($ins_stmt, 'iisssisssisssddsss',
    $user_id, $package_id, $event_name, $event_date, $start_time, $duration_hours, $venue,
    $estimated_softcopies, $estimated_hardcopies, $softcopy_addon,
    $hardcopy_delivery_name, $hardcopy_delivery_address, $hardcopy_delivery_contact,
    $package_price, $addon_price, $total_amount, $status, $notes);

if (!mysqli_stmt_execute($ins_stmt)) {
    set_flash('Failed to create booking. Please try again.', 'danger');
    redirect(site_url('booking/create.php'));
}

$new_booking_id = mysqli_insert_id($conn);

$user_stmt = mysqli_prepare($conn, "SELECT email, name FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

$notif_type = 'booking_created';
$recipient_email = $user['email'] ?? null;
$notif_subject = 'Booking #' . $new_booking_id . ' Created - ' . $event_name;
$notif_body = "Hi " . ($user['name'] ?? '') . ",\n\n"
    . "Your booking has been created successfully.\n\n"
    . "Booking ID: #" . $new_booking_id . "\n"
    . "Event: " . $event_name . "\n"
    . "Date: " . format_date($event_date) . "\n"
    . "Time: " . date('h:i A', strtotime($start_time)) . "\n"
    . "Duration: " . $duration_hours . " hours\n"
    . "Total: " . format_money($total_amount) . "\n\n"
    . "Status: Pending confirmation. We'll contact you shortly.";

$notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications
    (booking_id, user_id, type, recipient_email, subject, message_body, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())");
$notif_user_id = $user_id;
mysqli_stmt_bind_param($notif_stmt, 'iissss',
    $new_booking_id, $notif_user_id, $notif_type, $recipient_email, $notif_subject, $notif_body);
mysqli_stmt_execute($notif_stmt);

set_flash('Booking #' . $new_booking_id . ' created successfully. Awaiting confirmation.', 'success');
redirect(site_url('booking/view.php?id=' . $new_booking_id));
