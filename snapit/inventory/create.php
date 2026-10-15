<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fa-solid fa-plus me-2"></i>Add Inventory Item</h2>
        <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= e(site_url('inventory/store.php')) ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku" maxlength="50"
                            value="<?= e($_SESSION['old']['sku'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="150" required
                            value="<?= e($_SESSION['old']['name'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                            maxlength="255"><?= e($_SESSION['old']['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">-- Select --</option>
                            <option value="paper" <?= (($_SESSION['old']['category'] ?? '') === 'paper') ? 'selected' : '' ?>>Paper</option>
                            <option value="ink" <?= (($_SESSION['old']['category'] ?? '') === 'ink') ? 'selected' : '' ?>>Ink</option>
                            <option value="other" <?= (($_SESSION['old']['category'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="quantity_on_hand" class="form-label">Qty on Hand</label>
                        <input type="number" class="form-control" id="quantity_on_hand" name="quantity_on_hand" min="0"
                            value="<?= e($_SESSION['old']['quantity_on_hand'] ?? '0') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="reorder_level" class="form-label">Reorder Level</label>
                        <input type="number" class="form-control" id="reorder_level" name="reorder_level" min="0"
                            value="<?= e($_SESSION['old']['reorder_level'] ?? '10') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="unit_measure" class="form-label">Unit of Measure</label>
                        <input type="text" class="form-control" id="unit_measure" name="unit_measure" maxlength="30"
                            value="<?= e($_SESSION['old']['unit_measure'] ?? 'pcs') ?>">
                    </div>
                </div>
                <?php unset($_SESSION['old']); ?>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Save Item
                    </button>
                    <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
