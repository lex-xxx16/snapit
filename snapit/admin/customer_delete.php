<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('Your session expired. Please try again.', 'danger');
    redirect(site_url('admin/customers.php'));
}

$id = (int)($_POST['user_id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT user_id, name, avatar FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$c) {
    set_flash('Customer not found.', 'danger');
    redirect(site_url('admin/customers.php'));
}

// Bookings cascade-delete with the user (payments, photos, print jobs included),
// so refuse when the customer has any and point the admin to Deactivate instead.
$bk = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM bookings WHERE user_id = ?");
mysqli_stmt_bind_param($bk, 'i', $id);
mysqli_stmt_execute($bk);
$n = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($bk))['n'] ?? 0);
if ($n > 0) {
    set_flash("{$c['name']} has {$n} booking(s) on record, so the account can't be deleted without erasing payment and photo history. Deactivate it instead.", 'warning');
    redirect(site_url('admin/customers.php'));
}

try {
    $del = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role = 'customer'");
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    if (!empty($c['avatar'])) avatar_delete_file($c['avatar']);
    set_flash('Customer account deleted.', 'success');
} catch (Throwable $ex) {
    @error_log('[admin/customer_delete] ' . $ex->getMessage());
    set_flash('Could not delete the customer. Please try again.', 'danger');
}
redirect(site_url('admin/customers.php'));
