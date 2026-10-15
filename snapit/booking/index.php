<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$is_staff = is_staff();
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_clauses = [];
$params = [];
$types = '';

if (!$is_staff) {
    $where_clauses[] = "b.user_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

if ($search !== '' && $is_staff) {
    $where_clauses[] = "(b.event_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($status_filter !== '') {
    $where_clauses[] = "b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql = "SELECT b.*, p.name AS package_name, u.name AS customer_name, u.email AS customer_email
        FROM bookings b
        INNER JOIN packages p ON b.package_id = p.package_id
        INNER JOIN users u ON b.user_id = u.user_id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$statuses = ['pending', 'confirmed', 'paid', 'completed', 'cancelled'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <i class="fa-regular fa-calendar me-2"></i>
            <?= $is_staff ? 'All Bookings' : 'My Bookings' ?>
        </h2>
        <a href="<?= e(site_url('booking/create.php')) ?>" class="btn btn-snapit">
            <i class="fa-regular fa-calendar-plus me-1"></i> New Booking
        </a>
    </div>

    <?php if ($is_staff): ?>
    <form method="GET" class="card card-body mb-4 shadow-sm border-0">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Event name or customer email..."
                           value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>>
                            <?= e(ucfirst($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-snapit flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="<?= e(site_url('booking/index.php')) ?>" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Event Name</th>
                        <?php if ($is_staff): ?>
                            <th>Customer</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Start</th>
                        <th>Duration</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="<?= $is_staff ? 10 : 9 ?>" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-calendar-xmark fa-3x mb-3 d-block"></i>
                                No bookings found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($b = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int)$b['booking_id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($b['event_name']) ?></div>
                                    <div class="small text-muted"><?= e($b['venue']) ?></div>
                                </td>
                                <?php if ($is_staff): ?>
                                    <td>
                                        <div class="fw-semibold"><?= e($b['customer_name']) ?></div>
                                        <div class="small text-muted"><?= e($b['customer_email']) ?></div>
                                    </td>
                                <?php endif; ?>
                                <td><?= e(format_date($b['event_date'])) ?></td>
                                <td><?= e(date('h:i A', strtotime($b['start_time']))) ?></td>
                                <td><?= (int)$b['duration_hours'] ?>h</td>
                                <td><?= e($b['package_name']) ?></td>
                                <td>
                                    <span class="badge <?= status_badge_class($b['status']) ?>">
                                        <?= e(ucfirst($b['status'])) ?>
                                    </span>
                                </td>
                                <td class="fw-semibold"><?= e(format_money($b['total_amount'])) ?></td>
                                <td class="text-end">
                                    <a href="<?= e(site_url('booking/view.php?id=' . (int)$b['booking_id'])) ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-eye me-1"></i>View
                                    </a>
                                    <?php if ($is_staff): ?>
                                        <?php if ($b['status'] === 'pending'): ?>
                                            <form method="POST" action="<?= e(site_url('booking/confirm.php')) ?>" class="d-inline">
                                                <input type="hidden" name="id" value="<?= (int)$b['booking_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-info"
                                                        onclick="return confirm('Confirm booking #<?= (int)$b['booking_id'] ?>?')">
                                                    <i class="fa-solid fa-check me-1"></i>Confirm
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!in_array($b['status'], ['paid', 'completed', 'cancelled'])): ?>
                                            <form method="POST" action="<?= e(site_url('booking/cancel.php')) ?>" class="d-inline">
                                                <input type="hidden" name="id" value="<?= (int)$b['booking_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Cancel booking #<?= (int)$b['booking_id'] ?>?')">
                                                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($b['status'] === 'paid'): ?>
                                            <form method="POST" action="<?= e(site_url('booking/complete.php')) ?>" class="d-inline">
                                                <input type="hidden" name="id" value="<?= (int)$b['booking_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success"
                                                        onclick="return confirm('Mark booking #<?= (int)$b['booking_id'] ?> as completed?')">
                                                    <i class="fa-solid fa-flag-checkered me-1"></i>Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
