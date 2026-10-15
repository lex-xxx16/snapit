<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$active_session_id = current_booth_session_id($conn);

$where = "WHERE status IN ('confirmed','paid')";
$params = [];
$types = '';
if (is_customer()) {
    $where .= " AND user_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$all_candidates = [];
try {
    $sql = "SELECT b.*, p.name AS package_name FROM bookings b 
            LEFT JOIN packages p ON b.package_id = p.package_id 
            $where 
            ORDER BY b.event_date DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $_allCandidates = mysqli_stmt_get_result($stmt);
    while ($rw = mysqli_fetch_assoc($_allCandidates)) $all_candidates[] = $rw;
} catch (Throwable $e) {
    @error_log('[booth/index] bookings query failed: ' . $e->getMessage());
    set_flash('One or more bookings could not be loaded; please contact the administrator.', 'warning');
}

$bookings_list = [];
$unpaid_hidden_count = 0;
$unpaid_samples = [];
foreach ($all_candidates as $b) {
    try {
        $bid = (int)$b['booking_id'];
        $paid = booking_is_fully_paid($conn, $bid);
        if (!$paid) {
            $unpaid_hidden_count++;
            if (count($unpaid_samples) < 2) {
                $bal = booking_outstanding_balance($conn, $bid);
                $unpaid_samples[] = ['name' => $b['event_name'], 'balance' => $bal];
            }
        } else {
            $bookings_list[] = $b;
        }
    } catch (Throwable $e) {
        @error_log('[booth/index] booking check failed for id ' . ($b['booking_id'] ?? '?') . ': ' . $e->getMessage());
        $unpaid_hidden_count++;
        if (count($unpaid_samples) < 2) {
            $unpaid_samples[] = ['name' => ($b['event_name'] ?? 'Unknown') . ' (load error)', 'balance' => 0];
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 p-4">
                <div class="text-center mb-4">
                    <span class="eyebrow mb-3">The Studio</span>
                    <h2 class="fw-bold">Photo Booth</h2>
                    <p class="text-muted mb-0">Attach to a confirmed booking to start capturing moments.</p>
                </div>

                <?php if ($active_session_id): ?>
                    <div class="alert alert-primary mb-4" role="alert">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <i class="fa-solid fa-circle-play me-2"></i>
                                <strong>Active session in progress</strong>
                            </div>
                            <a href="<?= e(site_url('booth/session.php')) ?>" class="btn btn-primary btn-sm">
                                Resume Session <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= e(site_url('booth/start_session.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="mb-3">
                        <label for="booking_id" class="form-label fw-semibold">
                            <i class="fa-regular fa-calendar-check me-1"></i>Attach to a Confirmed Booking
                        </label>
                        <select class="form-select form-select-lg" id="booking_id" name="booking_id" required>
                            <option value="">-- Select a booking --</option>
                            <?php foreach ($bookings_list as $b): ?>
                                <option value="<?= (int)$b['booking_id'] ?>">
                                    <?= e($b['event_name']) ?>
                                    &mdash; <?= e(format_date($b['event_date'])) ?>
                                    [<?= e($b['package_name']) ?>]
                                    <span class="badge <?= e(status_badge_class($b['status'])) ?> ms-1">
                                        <?= e(ucfirst($b['status'])) ?>
                                    </span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($unpaid_hidden_count > 0): ?>
                            <div class="alert alert-warning mt-3 mb-0 py-2 small" role="alert">
                                <i class="fa-solid fa-sack-dollar me-1"></i>
                                <strong><?= (int)$unpaid_hidden_count ?> booking<?= $unpaid_hidden_count !== 1 ? 's' : '' ?> hidden:</strong> requires full payment before booth access.
                                <?php if (!empty($unpaid_samples)): ?>
                                    <ul class="mb-0 mt-1">
                                        <?php foreach ($unpaid_samples as $s): ?>
                                            <li><?= e($s['name']) ?> &mdash; ₱<?= e(number_format((float)$s['balance'], 2)) ?> remaining</li>
                                        <?php endforeach; ?>
                                        <?php if ($unpaid_hidden_count > count($unpaid_samples)): ?><li class="text-muted">…and <?= (int)($unpaid_hidden_count - count($unpaid_samples)) ?> more.</li><?php endif; ?>
                                    </ul>
                                <?php endif; ?>
                                <a class="alert-link d-block mt-1" href="<?= e(site_url('payment/index.php')) ?>">Go to Payments to settle balance &rarr;</a>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($bookings_list)): ?>
                            <div class="form-text text-danger">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                No fully-paid confirmed bookings available. Please complete payment before starting a booth session.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="guest_name" class="form-label fw-semibold">
                            <i class="fa-regular fa-user me-1"></i>Guest Name
                            <span class="text-muted fw-normal small">(optional)</span>
                        </label>
                        <input type="text" class="form-control form-control-lg" id="guest_name" name="guest_name"
                               placeholder="Enter guest name or leave blank">
                    </div>

                    <button type="submit" class="btn btn-snapit btn-lg w-100 fw-semibold">
                        <i class="fa-solid fa-bolt me-2"></i>Start Photo Booth Session
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
