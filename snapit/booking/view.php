<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id <= 0) {
    set_flash('Invalid booking reference.', 'danger');
    redirect(site_url('booking/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT b.*, p.name AS package_name, p.description AS package_description,
    p.duration_hours AS package_duration, p.softcopy_count AS pkg_softcopy_count,
    p.hardcopy_count AS pkg_hardcopy_count, p.has_softcopy_addon, p.softcopy_addon_price,
    u.name AS customer_name, u.email AS customer_email
    FROM bookings b
    INNER JOIN packages p ON b.package_id = p.package_id
    INNER JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    set_flash('Booking not found.', 'danger');
    redirect(site_url('booking/index.php'));
}

if (!is_staff() && (int)$booking['user_id'] !== (int)$_SESSION['user_id']) {
    set_flash('You are not authorized to view this booking.', 'danger');
    redirect(site_url('booking/index.php'));
}

$is_staff = is_staff();

$sess_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM guest_sessions WHERE booking_id = ?");
mysqli_stmt_bind_param($sess_stmt, 'i', $booking_id);
mysqli_stmt_execute($sess_stmt);
$sess_result = mysqli_stmt_get_result($sess_stmt);
$sess_row = mysqli_fetch_assoc($sess_result);
$session_count = (int)$sess_row['cnt'];

$pay_stmt = mysqli_prepare($conn, "SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date DESC");
mysqli_stmt_bind_param($pay_stmt, 'i', $booking_id);
mysqli_stmt_execute($pay_stmt);
$pay_result = mysqli_stmt_get_result($pay_stmt);

$total_paid = 0;
$payments = [];
while ($p = mysqli_fetch_assoc($pay_result)) {
    $payments[] = $p;
    $total_paid += (float)$p['amount_paid'];
}
$balance = (float)$booking['total_amount'] - $total_paid;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-regular fa-calendar-check me-2"></i>Booking #<?= (int)$booking['booking_id'] ?>
            </h2>
            <div class="text-muted small">
                Created on <?= e(format_date($booking['created_at'], true)) ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(site_url('booking/index.php')) ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
            <?php if ($is_staff): ?>
                <?php if ($booking['status'] === 'pending'): ?>
                    <form method="POST" action="<?= e(site_url('booking/confirm.php')) ?>" class="d-inline">
                        <input type="hidden" name="id" value="<?= (int)$booking['booking_id'] ?>">
                        <button type="submit" class="btn btn-info text-white"
                                onclick="return confirm('Confirm this booking?')">
                            <i class="fa-solid fa-check me-1"></i> Confirm
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (!in_array($booking['status'], ['paid', 'completed', 'cancelled'])): ?>
                    <form method="POST" action="<?= e(site_url('booking/cancel.php')) ?>" class="d-inline">
                        <input type="hidden" name="id" value="<?= (int)$booking['booking_id'] ?>">
                        <button type="submit" class="btn btn-danger"
                                onclick="return confirm('Cancel this booking? This cannot be undone.')">
                            <i class="fa-solid fa-xmark me-1"></i> Cancel
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($booking['status'] === 'paid'): ?>
                    <form method="POST" action="<?= e(site_url('booking/complete.php')) ?>" class="d-inline">
                        <input type="hidden" name="id" value="<?= (int)$booking['booking_id'] ?>">
                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('Mark booking as completed?')">
                            <i class="fa-solid fa-flag-checkered me-1"></i> Mark Complete
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= e(site_url('payment/record.php?booking_id=' . (int)$booking['booking_id'])) ?>" class="btn btn-snapit">
                    <i class="fa-solid fa-money-bill-wave me-1"></i> Record Payment
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fa-regular fa-calendar me-2"></i>Event Details</h5>
                    <span class="badge <?= status_badge_class($booking['status']) ?> fs-6 px-3 py-2">
                        <?= e(ucfirst($booking['status'])) ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="small text-muted text-uppercase fw-semibold">Event Name</label>
                            <div class="fs-5 fw-bold"><?= e($booking['event_name']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted text-uppercase fw-semibold">Date</label>
                            <div class="fw-semibold"><?= e(format_date($booking['event_date'])) ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted text-uppercase fw-semibold">Start Time</label>
                            <div class="fw-semibold"><?= e(date('h:i A', strtotime($booking['start_time']))) ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted text-uppercase fw-semibold">Duration</label>
                            <div class="fw-semibold"><?= (int)$booking['duration_hours'] ?> hours</div>
                        </div>
                        <div class="col-12">
                            <label class="small text-muted text-uppercase fw-semibold">Venue</label>
                            <div class="fw-semibold"><?= e($booking['venue']) ?></div>
                        </div>
                    </div>
                    <?php if (!empty($booking['notes'])): ?>
                        <hr>
                        <label class="small text-muted text-uppercase fw-semibold">Notes / Special Requests</label>
                        <div class="mt-1 p-3 bg-light rounded"><?= nl2br(e($booking['notes'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-box me-2"></i>Package</h5>
                </div>
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1"><?= e($booking['package_name']) ?></h5>
                    <p class="text-muted small mb-3"><?= e($booking['package_description'] ?? '') ?></p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="small text-muted text-uppercase fw-semibold">Package Duration</label>
                            <div class="fw-semibold"><?= (int)$booking['package_duration'] ?> hours (min)</div>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted text-uppercase fw-semibold">Included Soft Copies</label>
                            <div class="fw-semibold"><?= (int)$booking['pkg_softcopy_count'] ?> copies</div>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted text-uppercase fw-semibold">Included Hard Copies</label>
                            <div class="fw-semibold"><?= (int)$booking['pkg_hardcopy_count'] ?> copies</div>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted text-uppercase fw-semibold">Softcopy Add-on</label>
                            <div class="fw-semibold">
                                <?php if ((int)$booking['softcopy_addon'] === 1): ?>
                                    <span class="text-success"><i class="fa-solid fa-check me-1"></i> Enabled</span>
                                <?php else: ?>
                                    <span class="text-secondary"><i class="fa-solid fa-xmark me-1"></i> Not included</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="small text-muted text-uppercase fw-semibold">Estimated Soft Copies</label>
                            <div class="fw-semibold"><?= (int)$booking['estimated_softcopies'] ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted text-uppercase fw-semibold">Estimated Hard Copies</label>
                            <div class="fw-semibold"><?= (int)$booking['estimated_hardcopies'] ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted text-uppercase fw-semibold">Guest Sessions</label>
                            <div class="fw-semibold">
                                <?= $session_count ?> session<?= $session_count !== 1 ? 's' : '' ?>
                                <?php if ($is_staff): ?>
                                    <a href="<?= e(site_url('booth/index.php?booking_id=' . (int)$booking['booking_id'])) ?>"
                                       class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="fa-solid fa-camera-retro me-1"></i> Attach Booth
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($booking['hardcopy_delivery_name'] || $booking['hardcopy_delivery_address'] || $booking['hardcopy_delivery_contact']): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-truck me-2"></i>Hardcopy Delivery</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small text-muted text-uppercase fw-semibold">Recipient</label>
                                <div class="fw-semibold"><?= e($booking['hardcopy_delivery_name'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted text-uppercase fw-semibold">Contact</label>
                                <div class="fw-semibold"><?= e($booking['hardcopy_delivery_contact'] ?? '-') ?></div>
                            </div>
                            <div class="col-12">
                                <label class="small text-muted text-uppercase fw-semibold">Delivery Address</label>
                                <div class="fw-semibold"><?= e($booking['hardcopy_delivery_address'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-money-bill me-2"></i>Payment History</h5>
                    <?php if ($is_staff): ?>
                        <a href="<?= e(site_url('payment/record.php?booking_id=' . (int)$booking['booking_id'])) ?>"
                           class="btn btn-sm btn-snapit">
                            <i class="fa-solid fa-plus me-1"></i> Record Payment
                        </a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>OR Number</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Collected By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        No payments recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?= e(format_date($p['payment_date'], true)) ?></td>
                                        <td class="fw-semibold"><?= e($p['or_number'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= e(ucfirst($p['payment_method'] ?? 'cash')) ?></span>
                                        </td>
                                        <td class="fw-semibold text-success"><?= e(format_money($p['amount_paid'])) ?></td>
                                        <td class="small text-muted">
                                            <?php
                                            if (!empty($p['collected_by'])) {
                                                $stmt2 = mysqli_prepare($conn, "SELECT name FROM users WHERE user_id = ? LIMIT 1");
                                                mysqli_stmt_bind_param($stmt2, 'i', $p['collected_by']);
                                                mysqli_stmt_execute($stmt2);
                                                $r2 = mysqli_stmt_get_result($stmt2);
                                                $staff = mysqli_fetch_assoc($r2);
                                                echo e($staff['name'] ?? '-');
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4 sticky-top" style="top:1rem;">
                <div class="card-header py-3 text-white" style="background: linear-gradient(135deg,#3a2a1c,#1d140d);">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-receipt me-2"></i>Payment Summary</h5>
                </div>
                <div class="card-body p-4">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Package Price</td>
                                <td class="text-end fw-semibold"><?= e(format_money($booking['package_price'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Add-ons</td>
                                <td class="text-end fw-semibold"><?= e(format_money($booking['addon_price'])) ?></td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold">Total Amount</td>
                                <td class="text-end fw-bold" style="color:var(--snapit-gold)"><?= e(format_money($booking['total_amount'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Amount Paid</td>
                                <td class="text-end fw-semibold text-success"><?= e(format_money($total_paid)) ?></td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold">
                                    <?= $balance > 0 ? 'Balance Due' : 'Status' ?>
                                </td>
                                <td class="text-end fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?php if ($balance > 0): ?>
                                        <?= e(format_money($balance)) ?>
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-check me-1"></i> Fully Paid
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($is_staff): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-user me-2"></i>Customer</h5>
                    </div>
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-1"><?= e($booking['customer_name']) ?></h6>
                        <div class="small text-muted mb-2"><?= e($booking['customer_email']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
