<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$res = mysqli_query($conn, "SELECT * FROM packages WHERE package_type = 'walkin' AND is_active = 1 ORDER BY base_price ASC");
$packages = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-4">
        <h1 class="fw-bold">Walk-in Session</h1>
        <p class="text-muted mb-0">No event booking needed. Pick a strip package, enter the cash amount you will pay at the counter, and start taking photos right away.</p>
    </div>

    <?php if (!is_customer()): ?>
        <div class="alert alert-info text-center">Walk-in orders are placed from a customer account. Staff can record counter payments from <a href="<?= e(site_url('booking/index.php')) ?>">Bookings</a>.</div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php foreach ($packages as $p): ?>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body d-flex flex-column">
                        <i class="fa-solid fa-film fa-2x mb-2" style="color:var(--champagne);"></i>
                        <h5 class="fw-bold"><?= e(str_replace('Walk-in · ', '', $p['name'])) ?></h5>
                        <div class="display-6 fw-bold my-2" style="color:var(--snapit-gold);"><?= e(format_money($p['base_price'])) ?></div>
                        <p class="small text-muted flex-grow-1"><?= e($p['description']) ?></p>
                        <?php if (is_customer()): ?>
                            <form method="POST" action="<?= e(site_url('walkin/store.php')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="package_id" value="<?= (int)$p['package_id'] ?>">
                                <input type="hidden" name="amount_paid" value="<?= e($p['base_price']) ?>">
                                <div class="small text-muted mb-2">Fixed price &mdash; pay cash at the counter</div>
                                <button type="submit" class="btn btn-snapit w-100"><i class="fa-solid fa-camera me-1"></i>Pay &amp; start taking photos</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$packages): ?><div class="col-12 text-center text-muted">No walk-in packages are available right now.</div><?php endif; ?>
    </div>

    <p class="text-center small text-muted mt-4 mb-0">
        <i class="fa-solid fa-circle-info me-1"></i>Your payment is recorded as soon as you enter the amount. Please hand the same cash to the counter. Your photos are saved to your account under <strong>Gallery</strong>.
    </p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
