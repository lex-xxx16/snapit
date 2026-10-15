<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('inventory/index.php'));
}

$itemId = (int)($_POST['item_id'] ?? 0);
$addQty = (int)($_POST['add_qty'] ?? 0);
$reason = trim($_POST['reason'] ?? '') ?: 'Restock';

if ($itemId <= 0 || $addQty <= 0) {
    set_flash('Invalid item or quantity.', 'danger');
    redirect(site_url('inventory/index.php'));
}

$doneBy = $_SESSION['user_id'] ?? null;

mysqli_begin_transaction($conn);

try {
    $updateStmt = mysqli_prepare($conn,
        "UPDATE inventory_items SET quantity_on_hand = quantity_on_hand + ?, last_restocked_at = NOW() WHERE item_id = ?");
    mysqli_stmt_bind_param($updateStmt, 'ii', $addQty, $itemId);
    mysqli_stmt_execute($updateStmt);

    if (mysqli_stmt_affected_rows($updateStmt) === 0) {
        throw new Exception('Item not found.');
    }

    $logStmt = mysqli_prepare($conn,
        "INSERT INTO inventory_logs (item_id, quantity_delta, reason, done_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($logStmt, 'iisi', $itemId, $addQty, $reason, $doneBy);
    mysqli_stmt_execute($logStmt);

    mysqli_commit($conn);
    set_flash("Restocked {$addQty} units successfully.", 'success');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    set_flash('Failed to restock: ' . $e->getMessage(), 'danger');
}

redirect(site_url('inventory/index.php'));
