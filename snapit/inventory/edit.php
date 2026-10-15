<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$itemId = (int)($_GET['id'] ?? 0);
if ($itemId <= 0) {
    set_flash('Invalid item.', 'danger');
    redirect(site_url('inventory/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT * FROM inventory_items WHERE item_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $itemId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$item = mysqli_fetch_assoc($result);

if (!$item) {
    set_flash('Item not found.', 'danger');
    redirect(site_url('inventory/index.php'));
}

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Inventory Item</h2>
        <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= e(site_url('inventory/update.php')) ?>">
                <input type="hidden" name="item_id" value="<?= e($item['item_id']) ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku" maxlength="50" readonly
                            value="<?= e($item['sku'] ?? '') ?>" style="background-color: #e9ecef;">
                        <small class="text-muted">SKU cannot be changed.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="150" required
                            value="<?= e($old['name'] ?? $item['name']) ?>">
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                            maxlength="255"><?= e($old['description'] ?? $item['description']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">-- Select --</option>
                            <?php $cat = $old['category'] ?? $item['category']; ?>
                            <option value="paper" <?= $cat === 'paper' ? 'selected' : '' ?>>Paper</option>
                            <option value="ink" <?= $cat === 'ink' ? 'selected' : '' ?>>Ink</option>
                            <option value="other" <?= $cat === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="qty_info" class="form-label">Current Qty on Hand</label>
                        <input type="text" class="form-control" id="qty_info" readonly
                            value="<?= e(number_format($item['quantity_on_hand'])) . ' ' . e($item['unit_measure']) ?>"
                            style="background-color: #e9ecef;">
                        <small class="text-muted">Use Restock button on list to add quantity.</small>
                    </div>
                    <div class="col-md-4">
                        <label for="reorder_level" class="form-label">Reorder Level</label>
                        <input type="number" class="form-control" id="reorder_level" name="reorder_level" min="0"
                            value="<?= e($old['reorder_level'] ?? $item['reorder_level']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="unit_measure" class="form-label">Unit of Measure</label>
                        <input type="text" class="form-control" id="unit_measure" name="unit_measure" maxlength="30"
                            value="<?= e($old['unit_measure'] ?? $item['unit_measure']) ?>">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Update Item
                    </button>
                    <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
