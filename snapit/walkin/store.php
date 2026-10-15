<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid form submission. Please try again.', 'danger');
    redirect(site_url('walkin/index.php'));
}
if (!is_customer()) {
    set_flash('Walk-in orders are made from a customer account.', 'warning');
    redirect(site_url('walkin/index.php'));
}

$package_id = (int)($_POST['package_id'] ?? 0);

// Defensive: never trust any price-related field coming from the client.
// The only source of truth for package price is the packages table.
unset($_POST['base_price'], $_POST['price'], $_POST['package_price'], $_POST['addon_price'], $_POST['total_amount']);

$stmt = mysqli_prepare($conn, "SELECT * FROM packages WHERE package_id = ? AND package_type = 'walkin' AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $package_id);
mysqli_stmt_execute($stmt);
$pkg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$pkg) {
    set_flash('That walk-in package is no longer available.', 'danger');
    redirect(site_url('walkin/index.php'));
}

$price    = (float)$pkg['base_price'];
$tendered = $price; // price is fixed by the package; any posted amount is ignored
if ($tendered < $price) {
    set_flash('Please enter at least ' . format_money($price) . ' for this package.', 'warning');
    redirect(site_url('walkin/index.php'));
}
$change = round($tendered - $price, 2);

$user_id = (int)$_SESSION['user_id'];
$name    = $pkg['name'];
$venue   = 'Walk-in · Photo booth counter';
$strips  = (int)$pkg['hardcopy_count'];
$notes   = 'Walk-in order. Amount entered by the customer at the booth.';

mysqli_begin_transaction($conn);
try {
    // 1) the order, already paid
    $ins = mysqli_prepare($conn, "INSERT INTO bookings
        (user_id, package_id, booking_type, event_name, event_date, start_time, duration_hours, venue,
         estimated_softcopies, estimated_hardcopies, softcopy_addon, package_price, addon_price, total_amount, status, notes)
        VALUES (?, ?, 'walkin', ?, CURDATE(), CURTIME(), 1, ?, 1, ?, 1, ?, 0, ?, 'paid', ?)");
    mysqli_stmt_bind_param($ins, 'iissidds', $user_id, $package_id, $name, $venue, $strips, $price, $price, $notes);
    mysqli_stmt_execute($ins);
    $booking_id = mysqli_insert_id($conn);

    // 2) the cash payment (no staff member involved)
    $or  = 'WI-' . date('Ymd') . '-' . $booking_id;
    $pn  = 'Self-reported at walk-in booth. Cash entered: ' . number_format($tendered, 2) . ', change due: ' . number_format($change, 2) . '. Staff to verify against the cash drawer.';
    $pay = mysqli_prepare($conn, "INSERT INTO payments (booking_id, amount_paid, payment_method, collected_by, or_number, notes)
                                  VALUES (?, ?, 'cash', NULL, ?, ?)");
    mysqli_stmt_bind_param($pay, 'idss', $booking_id, $price, $or, $pn);
    mysqli_stmt_execute($pay);

    // 3) open the booth session right away
    $ses = mysqli_prepare($conn, "INSERT INTO guest_sessions (booking_id, status, idle_timeout_at, started_at)
                                  VALUES (?, 'active', NOW() + INTERVAL 3 MINUTE, NOW())");
    mysqli_stmt_bind_param($ses, 'i', $booking_id);
    mysqli_stmt_execute($ses);
    $session_id = mysqli_insert_id($conn);

    mysqli_commit($conn);
} catch (Throwable $ex) {
    mysqli_rollback($conn);
    set_flash('Could not record your payment. Please try again or ask the staff for help.', 'danger');
    redirect(site_url('walkin/index.php'));
}

$_SESSION['active_session_id'] = $session_id;
unset($_SESSION['capture_index'], $_SESSION['captured']);
$msg = 'Payment of ' . format_money($price) . ' recorded (order #' . $booking_id . ').';
if ($change > 0) $msg .= ' Change due: ' . format_money($change) . '.';
set_flash($msg . ' Customize your booth and start taking photos!', 'success');
redirect(site_url('booth/capture.php'));
