<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$itemId = (int)($_GET['id'] ?? 0);
if ($itemId <= 0) {
    set_flash('Invalid item.', 'danger');
    redirect(site_url('inventory/index.php'));
}

$checkStmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS cnt FROM inventory_usage WHERE item_id = ? UNION ALL SELECT COUNT(*) FROM inventory_logs WHERE item_id = ?");
mysqli_stmt_bind_param($checkStmt, 'ii', $itemId, $itemId);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$hasUsage = false;
while ($row = mysqli_fetch_assoc($checkResult)) {
    if ($row['cnt'] > 0) {
        $hasUsage = true;
        break;
    }
}

if ($hasUsage) {
    set_flash('Cannot delete: item has existing usage or log references.', 'danger');
    redirect(site_url('inventory/index.php'));
}

$stmt = mysqli_prepare($conn, "DELETE FROM inventory_items WHERE item_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $itemId);

if (mysqli_stmt_execute($stmt)) {
    set_flash('Inventory item deleted successfully.', 'success');
} else {
    set_flash('Failed to delete item: ' . mysqli_error($conn), 'danger');
}

redirect(site_url('inventory/index.php'));
