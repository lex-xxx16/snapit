<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$bookingId = $_GET['booking_id'] ?? '';
$booking = null;

if (!empty($bookingId)) {
    $stmt = mysqli_prepare($conn, "SELECT b.booking_id, b.event_name, b.event_date, b.total_amount, b.package_price, b.addon_price, b.status,
                                          u.user_id, u.name, u.email
                                   FROM bookings b INNER JOIN users u ON b.user_id = u.user_id
                                   WHERE b.booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">
            <i class="fa-solid fa-cash-register me-2"></i>Record Payment
        </h2>
        <a href="<?= e(site_url('payment/index.php')) ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Payments
        </a>
    </div>

    <?php if (!$booking): ?>
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Select Booking</label>
                        <select name="booking_id" class="form-select" required>
                            <option value="">-- Choose a booking --</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT b.booking_id, b.event_name, b.event_date, b.total_amount, b.status, u.name
                                                        FROM bookings b INNER JOIN users u ON b.user_id = u.user_id
                                                        WHERE b.status IN ('pending','confirmed')
                                                        ORDER BY b.event_date DESC, b.booking_id DESC");
                            while ($row = mysqli_fetch_assoc($res)):
                            ?>
                                <option value="<?= e($row['booking_id']) ?>">
                                    #<?= e($row['booking_id']) ?> - <?= e($row['event_name']) ?> (<?= e($row['name']) ?>) - <?= e(format_money($row['total_amount'])) ?> [<?= e(ucfirst($row['status'])) ?>]
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-snapit">
                            <i class="fa-solid fa-check me-1"></i>Load Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fa-solid fa-receipt me-2"></i>Booking Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Booking ID</dt>
                            <dd class="col-sm-8">#<?= e($booking['booking_id']) ?></dd>

                            <dt class="col-sm-4">Event Name</dt>
                            <dd class="col-sm-8"><?= e($booking['event_name']) ?></dd>

                            <dt class="col-sm-4">Event Date</dt>
                            <dd class="col-sm-8"><?= e(format_date($booking['event_date'])) ?></dd>

                            <dt class="col-sm-4">Customer</dt>
                            <dd class="col-sm-8"><?= e($booking['name']) ?></dd>

                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?= e($booking['email']) ?></dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?= e(status_badge_class($booking['status'])) ?>">
                                    <?= e(ucfirst($booking['status'])) ?>
                                </span>
                            </dd>

                            <hr class="my-3">

                            <dt class="col-sm-4">Package Price</dt>
                            <dd class="col-sm-8"><?= e(format_money($booking['package_price'])) ?></dd>

                            <dt class="col-sm-4">Addons</dt>
                            <dd class="col-sm-8"><?= e(format_money($booking['addon_price'])) ?></dd>

                            <dt class="col-sm-4 fw-bold">Total Amount</dt>
                            <dd class="col-sm-8 fw-bold"><?= e(format_money($booking['total_amount'])) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fa-solid fa-pen-to-square me-2"></i>Payment Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= e(site_url('payment/store.php')) ?>" method="POST" class="row g-3">
                            <input type="hidden" name="booking_id" value="<?= e($booking['booking_id']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">PHP</span>
                                    <input type="number" name="amount_paid" step="0.01" min="0.01" required
                                           class="form-control"
                                           value="<?= e(number_format((float)$booking['total_amount'], 2, '.', '')) ?>">
                                </div>
                                <div class="form-text">Enter the exact cash collected at the counter.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" value="Cash" readonly>
                                <input type="hidden" name="payment_method" value="cash">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Official Receipt (OR) Number <span class="text-danger">*</span></label>
                                <input type="text" name="or_number" required maxlength="50"
                                       class="form-control" placeholder="e.g. OR-2026-000123">
                                <div class="form-text">Unique receipt number from the official receipt book.</div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" rows="3" class="form-control"
                                          placeholder="Optional notes about this payment..."></textarea>
                            </div>

                            <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-end pt-2">
                                <a href="<?= e(site_url('payment/record.php')) ?>" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-snapit">
                                    <i class="fa-solid fa-floppy-disk me-1"></i>Record Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
require __DIR__ . '/../includes/footer.php';
