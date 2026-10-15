<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$search = $_GET['q'] ?? '';
$methodFilter = $_GET['method'] ?? '';

$sql = "SELECT p.payment_id, p.or_number, p.booking_id, p.amount_paid, p.payment_method, p.payment_date,
               b.event_name, b.total_amount,
               u.name AS customer_name,
               s.name AS collected_by_name
        FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.booking_id
        INNER JOIN users u ON b.user_id = u.user_id
        LEFT JOIN users s ON p.collected_by = s.user_id
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (p.booking_id LIKE ? OR p.or_number LIKE ? OR b.event_name LIKE ? OR u.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

if (!empty($methodFilter)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $methodFilter;
    $types .= 's';
}

$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $payments[] = $row;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">
            <i class="fa-solid fa-money-bill-wave me-2"></i>Payments
        </h2>
        <a href="<?= e(site_url('payment/record.php')) ?>" class="btn btn-snapit">
            <i class="fa-solid fa-plus me-1"></i>Record Payment
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="q" class="form-control"
                           value="<?= e($search) ?>"
                           placeholder="Booking ID, OR#, Event name, or Customer name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Method</label>
                    <select name="method" class="form-select">
                        <option value="">All Methods</option>
                        <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-snapit">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Payment ID / OR#</th>
                            <th>Booking</th>
                            <th>Event Name</th>
                            <th>Customer</th>
                            <th>Amount Paid</th>
                            <th>Method</th>
                            <th>Payment Date</th>
                            <th>Collected By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox me-2"></i>No payments found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">#<?= e($p['payment_id']) ?></div>
                                        <small class="text-muted">OR: <?= e($p['or_number'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-snapit">#<?= e($p['booking_id']) ?></span>
                                    </td>
                                    <td><?= e($p['event_name']) ?></td>
                                    <td><?= e($p['customer_name']) ?></td>
                                    <td class="fw-semibold"><?= e(format_money($p['amount_paid'])) ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= e(ucfirst($p['payment_method'])) ?></span>
                                    </td>
                                    <td><?= e(format_date($p['payment_date'], true)) ?></td>
                                    <td><?= e($p['collected_by_name'] ?? '-') ?></td>
                                    <td>
                                        <a href="<?= e(site_url('payment/receipt.php?id=' . $p['payment_id'])) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-receipt me-1"></i>Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
require __DIR__ . '/../includes/footer.php';
