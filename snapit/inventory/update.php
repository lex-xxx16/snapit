<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('inventory/index.php'));
}

$itemId = (int)($_POST['item_id'] ?? 0);
if ($itemId <= 0) {
    set_flash('Invalid item.', 'danger');
    redirect(site_url('inventory/index.php'));
}

$_SESSION['old'] = $_POST;

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category = $_POST['category'] ?? '';
$reorder = (int)($_POST['reorder_level'] ?? 10);
$unit = trim($_POST['unit_measure'] ?? 'pcs');

if ($name === '' || !in_array($category, ['paper', 'ink', 'other'], true)) {
    set_flash('Please fill in required fields.', 'danger');
    redirect(site_url('inventory/edit.php?id=' . $itemId));
}

$stmt = mysqli_prepare($conn,
    "UPDATE inventory_items SET name = ?, description = ?, category = ?, reorder_level = ?, unit_measure = ? WHERE item_id = ?");
mysqli_stmt_bind_param($stmt, 'sssisi', $name, $description, $category, $reorder, $unit, $itemId);

if (mysqli_stmt_execute($stmt)) {
    unset($_SESSION['old']);
    set_flash('Inventory item updated successfully.', 'success');
} else {
    set_flash('Failed to update item: ' . mysqli_error($conn), 'danger');
    redirect(site_url('inventory/edit.php?id=' . $itemId));
}

redirect(site_url('inventory/index.php'));
