<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('inventory/index.php'));
}

$_SESSION['old'] = $_POST;

$sku = trim($_POST['sku'] ?? '');
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category = $_POST['category'] ?? '';
$qty = (int)($_POST['quantity_on_hand'] ?? 0);
$reorder = (int)($_POST['reorder_level'] ?? 10);
$unit = trim($_POST['unit_measure'] ?? 'pcs');

if ($name === '' || !in_array($category, ['paper', 'ink', 'other'], true)) {
    set_flash('Please fill in required fields.', 'danger');
    redirect(site_url('inventory/create.php'));
}

$skuValue = $sku === '' ? null : $sku;

mysqli_begin_transaction($conn);

try {
    $sql = "INSERT INTO inventory_items (sku, name, description, category, quantity_on_hand, reorder_level, unit_measure, last_restocked_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, " . ($qty > 0 ? "NOW()" : "NULL") . ", NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    $skuType = $skuValue === null ? 's' : 's';
    mysqli_stmt_bind_param($stmt, 'ssssiis', $skuValue, $name, $description, $category, $qty, $reorder, $unit);
    mysqli_stmt_execute($stmt);

    $itemId = mysqli_insert_id($conn);

    if ($qty > 0) {
        $reason = 'Initial stock';
        $doneBy = $_SESSION['user_id'] ?? null;
        $logStmt = mysqli_prepare($conn,
            "INSERT INTO inventory_logs (item_id, quantity_delta, reason, done_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($logStmt, 'iisi', $itemId, $qty, $reason, $doneBy);
        mysqli_stmt_execute($logStmt);
    }

    mysqli_commit($conn);
    unset($_SESSION['old']);
    set_flash('Inventory item created successfully.', 'success');
    redirect(site_url('inventory/index.php'));
} catch (Throwable $e) {
    mysqli_rollback($conn);
    set_flash('Failed to create item: ' . $e->getMessage(), 'danger');
    redirect(site_url('inventory/create.php'));
}
