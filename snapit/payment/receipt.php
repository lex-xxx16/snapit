<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$paymentId = $_GET['id'] ?? '';
if (empty($paymentId)) {
    set_flash('Payment ID is required.', 'danger');
    redirect(site_url('payment/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT p.payment_id, p.or_number, p.amount_paid, p.payment_method, p.payment_date, p.notes,
                                      b.booking_id, b.event_name, b.event_date, b.package_price, b.addon_price, b.total_amount,
                                      u.name AS customer_name, u.email,
                                      s.name AS collected_by_name
                               FROM payments p
                               INNER JOIN bookings b ON p.booking_id = b.booking_id
                               INNER JOIN users u ON b.user_id = u.user_id
                               LEFT JOIN users s ON p.collected_by = s.user_id
                               WHERE p.payment_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $paymentId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);

if (!$payment) {
    set_flash('Payment not found.', 'danger');
    redirect(site_url('payment/index.php'));
}

$total = (float)$payment['total_amount'];
$paid = (float)$payment['amount_paid'];
$change = $paid > $total ? $paid - $total : 0;

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="no-print d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">
            <i class="fa-solid fa-file-invoice me-2"></i>Payment Receipt
        </h2>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-snapit">
                <i class="fa-solid fa-print me-1"></i>Print Receipt
            </button>
            <a href="<?= e(site_url('payment/index.php')) ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Payments
            </a>
        </div>
    </div>

    <div class="receipt">
        <div class="text-center mb-3">
            <div style="font-size: 1.5rem; font-weight: bold; color: #1a120c;">
                <svg viewBox="0 0 32 26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:1.5em;height:1.2em;vertical-align:-.2em;margin-right:8px"><path d="M10.6 5 12.3 2.4h7.4L21.4 5h5.1A3.5 3.5 0 0 1 30 8.5v12a3.5 3.5 0 0 1-3.5 3.5h-21A3.5 3.5 0 0 1 2 20.5v-12A3.5 3.5 0 0 1 5.5 5h5.1Z"/><circle cx="16" cy="14.2" r="5.4"/><circle cx="16" cy="14.2" r="2.1" fill="currentColor" stroke="none"/><circle cx="25.4" cy="9.4" r="1" fill="currentColor" stroke="none"/></svg>Snap It
            </div>
            <div style="font-size: 0.8rem; color: #555;">Photo Booth & Rental System</div>
            <div style="font-size: 0.85rem; font-weight: bold; margin-top: 8px; border-top: 1px dashed #444; border-bottom: 1px dashed #444; padding: 4px 0;">
                OFFICIAL RECEIPT
            </div>
        </div>

        <div class="mb-3" style="font-size: 0.85rem;">
            <div class="d-flex justify-content-between">
                <span><strong>OR #:</strong></span>
                <span><?= e($payment['or_number'] ?? '-') ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span><strong>Payment ID:</strong></span>
                <span>#<?= e($payment['payment_id']) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span><strong>Date / Time:</strong></span>
                <span><?= e(format_date($payment['payment_date'], true)) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span><strong>Method:</strong></span>
                <span><?= e(ucfirst($payment['payment_method'])) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span><strong>Collected by:</strong></span>
                <span><?= e($payment['collected_by_name'] ?? '-') ?></span>
            </div>
        </div>

        <div style="border-top: 1px dashed #444; margin: 10px 0;"></div>

        <div class="mb-3" style="font-size: 0.85rem;">
            <div><strong>Booking #:</strong> #<?= e($payment['booking_id']) ?></div>
            <div><strong>Event:</strong> <?= e($payment['event_name']) ?></div>
            <div><strong>Event Date:</strong> <?= e(format_date($payment['event_date'])) ?></div>
        </div>

        <div style="border-top: 1px dashed #444; margin: 10px 0;"></div>

        <div class="mb-3" style="font-size: 0.85rem;">
            <div><strong>Customer:</strong> <?= e($payment['customer_name']) ?></div>
            <?php if (!empty($payment['email'])): ?>
                <div style="margin-left: 0; padding-left: 0;"><strong>Email:</strong> <?= e($payment['email']) ?></div>
            <?php endif; ?>
        </div>

        <div style="border-top: 1px dashed #444; margin: 10px 0;"></div>

        <div class="mb-3" style="font-size: 0.85rem;">
            <div class="d-flex justify-content-between">
                <span>Package Price:</span>
                <span><?= e(format_money($payment['package_price'])) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Addon Price:</span>
                <span><?= e(format_money($payment['addon_price'])) ?></span>
            </div>
            <div style="border-top: 1px dashed #444; margin: 6px 0;"></div>
            <div class="d-flex justify-content-between" style="font-weight: bold;">
                <span>Total Amount:</span>
                <span><?= e(format_money($payment['total_amount'])) ?></span>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <span>Amount Paid:</span>
                <span><?= e(format_money($payment['amount_paid'])) ?></span>
            </div>
            <?php if ($change > 0): ?>
                <div class="d-flex justify-content-between" style="font-weight: bold; color: #198754;">
                    <span>Change:</span>
                    <span><?= e(format_money($change)) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($payment['notes'])): ?>
            <div style="border-top: 1px dashed #444; margin: 10px 0;"></div>
            <div class="mb-3" style="font-size: 0.8rem;">
                <strong>Notes:</strong><br>
                <?= nl2br(e($payment['notes'])) ?>
            </div>
        <?php endif; ?>

        <div style="border-top: 1px dashed #444; margin: 20px 0 10px;"></div>

        <div class="mt-4" style="font-size: 0.85rem;">
            <div style="margin-bottom: 50px;">
                <div style="border-bottom: 1px solid #333; width: 180px; margin-bottom: 4px;"></div>
                <div>Customer Signature / Date</div>
            </div>
            <div>
                <div style="border-bottom: 1px solid #333; width: 180px; margin-bottom: 4px;"></div>
                <div>Authorized Signature / Date</div>
            </div>
        </div>

        <div class="text-center mt-4" style="font-size: 0.75rem; color: #666;">
            <div style="border-top: 1px dashed #444; padding-top: 8px;">
                <em>Thank you for choosing Snap It!</em>
            </div>
            <div>This is a system-generated receipt.</div>
        </div>
    </div>
</div>
<?php
require __DIR__ . '/../includes/footer.php';
