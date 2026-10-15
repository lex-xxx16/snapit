<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$pending_count = 0;
$confirmed_count = 0;
$paid_count = 0;
$total_bookings = 0;
$total_revenue = 0;
$active_sessions = 0;
$low_stock_count = 0;

$stmt = mysqli_prepare($conn, "SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $total_bookings += (int)$row['cnt'];
    switch ($row['status']) {
        case 'pending':
            $pending_count = (int)$row['cnt'];
            break;
        case 'confirmed':
            $confirmed_count = (int)$row['cnt'];
            break;
        case 'paid':
        case 'completed':
            $paid_count += (int)$row['cnt'];
            break;
    }
}

$stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS total FROM payments");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $total_revenue = (float)$row['total'];
}

$today = date('Y-m-d');
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM guest_sessions WHERE status = 'active' AND DATE(started_at) = ?");
mysqli_stmt_bind_param($stmt, 's', $today);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $active_sessions = (int)$row['cnt'];
}

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM inventory_items WHERE quantity_on_hand <= reorder_level");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $low_stock_count = (int)$row['cnt'];
}

$stmt = mysqli_prepare($conn, "
    SELECT b.booking_id, b.event_name, b.event_date, b.status, u.name AS customer_name
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.user_id
    ORDER BY b.created_at DESC
    LIMIT 10
");
mysqli_stmt_execute($stmt);
$recent_bookings = mysqli_stmt_get_result($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT item_id, sku, name, category, quantity_on_hand, reorder_level, unit_measure
    FROM inventory_items
    WHERE quantity_on_hand <= reorder_level
    ORDER BY quantity_on_hand ASC
");
mysqli_stmt_execute($stmt);
$low_stock_items = mysqli_stmt_get_result($stmt);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<section class="container py-4">
    <h1 class="fw-bold mb-4">
        <i class="fa-solid fa-gauge me-2"></i>Admin Dashboard
    </h1>

    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Pending Bookings</div>
                        <div class="kpi-value"><?= e($pending_count) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-regular fa-clock fa-2xl" style="color:var(--butter)"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Confirmed</div>
                        <div class="kpi-value"><?= e($confirmed_count) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-regular fa-calendar-check fa-2xl" style="color:#0dcaf0"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Paid / Completed</div>
                        <div class="kpi-value"><?= e($paid_count) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-solid fa-circle-check fa-2xl" style="color:#198754"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Total Bookings</div>
                        <div class="kpi-value"><?= e($total_bookings) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-solid fa-calendar-days fa-2xl" style="color:var(--snapit-gold)"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Total Revenue</div>
                        <div class="kpi-value" style="font-size:1.8rem"><?= e(format_money($total_revenue)) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-solid fa-money-bill-wave fa-2xl" style="color:#198754"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Active Sessions Today</div>
                        <div class="kpi-value"><?= e($active_sessions) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-solid fa-camera fa-2xl" style="color:#0dcaf0"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="kpi-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label mb-1">Low-Stock Items</div>
                        <div class="kpi-value" style="color:<?= $low_stock_count > 0 ? '#d98c8c' : 'var(--cream)' ?>"><?= e($low_stock_count) ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(216,192,154,.10)">
                        <i class="fa-solid fa-triangle-exclamation fa-2xl" style="color:#d98c8c"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Bookings
                    </h5>
                    <a href="<?= e(site_url('booking/index.php')) ?>" class="btn btn-sm btn-snapit">
                        <i class="fa-solid fa-arrow-right me-1"></i>View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Event</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($recent_bookings) > 0): ?>
                                    <?php while ($b = mysqli_fetch_assoc($recent_bookings)): ?>
                                        <tr>
                                            <td class="fw-bold">#<?= e($b['booking_id']) ?></td>
                                            <td><?= e($b['event_name']) ?></td>
                                            <td><?= e($b['customer_name']) ?></td>
                                            <td><?= e(format_date($b['event_date'])) ?></td>
                                            <td>
                                                <span class="badge rounded-pill px-3 py-2 <?= e(status_badge_class($b['status'])) ?>">
                                                    <?= e(ucfirst($b['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= e(site_url('booking/view.php?id=' . (int)$b['booking_id'])) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No recent bookings.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-boxes-stacked me-2"></i>Low-Stock Inventory Alert
                    </h5>
                    <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-sm btn-snapit">
                        <i class="fa-solid fa-arrow-right me-1"></i>Manage
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>On Hand</th>
                                    <th>Reorder Lvl</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($low_stock_items) > 0): ?>
                                    <?php while ($item = mysqli_fetch_assoc($low_stock_items)): ?>
                                        <tr class="reorder-alert">
                                            <td>
                                                <div class="fw-bold"><?= e($item['name']) ?></div>
                                                <?php if (!empty($item['sku'])): ?>
                                                    <div class="small text-muted">SKU: <?= e($item['sku']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= e(ucfirst($item['category'])) ?></span></td>
                                            <td class="fw-bold text-danger">
                                                <?= e((int)$item['quantity_on_hand']) ?> <?= e($item['unit_measure']) ?>
                                            </td>
                                            <td><?= e((int)$item['reorder_level']) ?> <?= e($item['unit_measure']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="fa-solid fa-circle-check text-success me-2"></i>All inventory levels are good.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
