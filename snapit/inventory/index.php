<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
$types = '';

if ($search !== '') {
    $where = 'WHERE name LIKE ? OR sku LIKE ?';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types = 'ss';
}

$sql = "SELECT * FROM inventory_items $where ORDER BY item_id DESC";
$stmt = mysqli_prepare($conn, $sql);
if ($where) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fa-solid fa-boxes-stocked me-2"></i>Inventory</h2>
        <a href="<?= e(site_url('inventory/create.php')) ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Add Item
        </a>
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search by name or SKU..."
                value="<?= e($search) ?>">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th class="text-end">Qty on Hand</th>
                    <th class="text-end">Reorder Level</th>
                    <th>Unit</th>
                    <th>Last Restocked</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No inventory items found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php $lowStock = (int)$item['quantity_on_hand'] <= (int)$item['reorder_level']; ?>
                        <tr class="<?= $lowStock ? 'reorder-alert table-warning' : '' ?>">
                            <td><?= e($item['sku'] ?? '-') ?></td>
                            <td>
                                <?= e($item['name']) ?>
                                <?php if ($lowStock): ?>
                                    <span class="badge bg-danger ms-1">LOW STOCK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $catMap = ['paper' => 'Paper', 'ink' => 'Ink', 'other' => 'Other'];
                                $catBadge = ['paper' => 'bg-info text-dark', 'ink' => 'bg-primary', 'other' => 'bg-secondary'];
                                ?>
                                <span class="badge <?= $catBadge[$item['category']] ?? 'bg-secondary' ?>">
                                    <?= e($catMap[$item['category']] ?? $item['category']) ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold <?= $lowStock ? 'text-danger' : '' ?>">
                                <?= e(number_format($item['quantity_on_hand'])) ?>
                            </td>
                            <td class="text-end"><?= e(number_format($item['reorder_level'])) ?></td>
                            <td><?= e($item['unit_measure']) ?></td>
                            <td><?= e(format_date($item['last_restocked_at'], true)) ?></td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1 align-items-center flex-wrap justify-content-center">
                                    <a href="<?= e(site_url('inventory/edit.php?id=' . $item['item_id'])) ?>"
                                        class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <form method="POST" action="<?= e(site_url('inventory/restock.php')) ?>"
                                        class="d-inline-flex align-items-center gap-1">
                                        <input type="hidden" name="item_id" value="<?= e($item['item_id']) ?>">
                                        <input type="number" name="add_qty" min="1" value="1"
                                            class="form-control form-control-sm" style="width: 70px;" required>
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restock">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </form>
                                    <a href="<?= e(site_url('inventory/delete.php?id=' . $item['item_id'])) ?>"
                                        class="btn btn-sm btn-outline-danger" title="Delete"
                                        onclick="return confirm('Delete this inventory item?');">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
