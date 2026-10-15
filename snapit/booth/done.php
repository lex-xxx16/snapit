<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$sid = (int)($_GET['sid'] ?? 0);
if ($sid <= 0) {
    set_flash('Invalid session reference.', 'warning');
    redirect(site_url('booth/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT gs.*, 
    f.css_filter, l.photo_count, l.grid_cols, l.grid_rows, l.print_size, fd.border_color, fd.border_width, fd.bg_color, fd.text_color, fd.photo_gap, fd.photo_radius, fd.pattern,
    b.booking_id, b.event_name, b.user_id AS booking_user_id, b.softcopy_addon
    FROM guest_sessions gs
    LEFT JOIN filters f ON gs.filter_id = f.filter_id
    LEFT JOIN layouts l ON gs.layout_id = l.layout_id
    LEFT JOIN frame_designs fd ON gs.frame_design_id = fd.design_id
    LEFT JOIN bookings b ON gs.booking_id = b.booking_id
    WHERE gs.session_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $sid);
mysqli_stmt_execute($stmt);
$session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$session) {
    set_flash('Session not found.', 'warning');
    redirect(site_url('booth/index.php'));
}

if (is_customer() && (int)$session['booking_user_id'] !== (int)$_SESSION['user_id']) {
    set_flash('You do not have access to this session.', 'danger');
    redirect(site_url('booth/index.php'));
}

$photos_stmt = mysqli_prepare($conn, "SELECT * FROM session_photos WHERE session_id = ? ORDER BY order_index ASC");
mysqli_stmt_bind_param($photos_stmt, 'i', $sid);
mysqli_stmt_execute($photos_stmt);
$photos = mysqli_stmt_get_result($photos_stmt);
$photo_rows = [];
while ($ph = mysqli_fetch_assoc($photos)) $photo_rows[] = $ph;

$softcopy = !empty($session['booking_id']) ? booking_has_softcopy_access($conn, (int)$session['booking_id']) : (bool)($session['softcopy_addon'] ?? false);
$softcopy_via_addon = !empty($session['softcopy_addon']) && (int)$session['softcopy_addon'] === 1;
$softcopy_via_package = $softcopy && !$softcopy_via_addon;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if (isset($_GET['auto'])): ?>
                <div class="alert alert-warning mb-4" role="alert">
                    <i class="fa-regular fa-clock me-2"></i>
                    <strong>Session ended due to inactivity.</strong> You can still collect and share your photos below.
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 p-5 mb-4 text-center" style="background:linear-gradient(135deg,#4a3523,#1a120c);color:#fff;border-radius:24px;">
                <i class="fa-solid fa-circle-check fa-4x mb-3" style="opacity:0.9;"></i>
                <h2 class="fw-bold mb-1">Beautifully done.</h2>
                <p class="mb-0 opacity-95">Your photos are ready. Thank you for using Snap It Photo Booth.</p>
            </div>

            <div class="card shadow-sm border-0 p-4 mb-4">
                <h5 class="fw-bold mb-3">
                    <i class="fa-regular fa-image me-2"></i>Your Photos
                    <span class="badge badge-snapit ms-2"><?= count($photo_rows) ?> photo(s)</span>
                </h5>
                <div class="mb-3">
                    <div class="text-muted small mb-2">
                        <i class="fa-regular fa-calendar-check me-1"></i><?= e($session['event_name']) ?>
                        &bull; Guest: <strong><?= e($session['guest_name'] ?: 'Guest #' . $sid) ?></strong>
                    </div>
                </div>

                <?= strip_html(array_column($photo_rows,'photo_path'), strip_cfg($session, $session['event_name'].' · '.date('M j, Y'))) ?>
            </div>

            <?php if ($softcopy): ?>
            <div class="card shadow-sm border-0 p-4 mb-4">
                <h5 class="fw-bold mb-3">
                    <i class="fa-solid fa-envelopes-bulk me-2"></i>Email Softcopies
                    <?php if ($softcopy_via_addon): ?>
                        <span class="badge bg-success ms-2"><i class="fa-solid fa-circle-check me-1"></i>Softcopy Add-On Active</span>
                    <?php else: ?>
                        <span class="badge bg-info ms-2"><i class="fa-solid fa-circle-check me-1"></i>Included in Package</span>
                    <?php endif; ?>
                </h5>
                <p class="text-muted small mb-3">Enter up to 10 email addresses to send the photo set. Valid emails only.</p>

                <form method="POST" action="<?= e(site_url('booth/store_recipients.php')) ?>" id="recipientsForm" novalidate>
                    <input type="hidden" name="sid" value="<?= (int)$sid ?>">
                    <div class="row g-3 mb-4">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">
                                    Email <?= $i ?><?php if ($i === 1): ?> <span class="text-muted fw-normal">(at least 1 required)</span><?php endif; ?>
                                </label>
                                <input type="email"
                                       name="email<?= $i ?>"
                                       class="form-control recipient-email"
                                       placeholder="name@example.com"
                                       data-idx="<?= $i ?>">
                                <div class="invalid-feedback" id="err_email<?= $i ?>"></div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="alert alert-info small mb-3" role="alert">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        You'll get a downloadable PNG of the collage as well.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-snapit btn-lg fw-semibold">
                            <i class="fa-solid fa-paper-plane me-2"></i>Send to Recipients
                        </button>
                        <a href="<?= e(site_url('gallery/download.php?sid=' . (int)$sid)) ?>" class="btn btn-outline-primary btn-lg fw-semibold">
                            <i class="fa-solid fa-download me-2"></i>Download PNG
                        </a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card shadow-sm border-0 p-4 mb-4 text-center">
                <div class="text-muted mb-3">
                    <i class="fa-regular fa-circle-xmark fa-2x me-2"></i>
                    Softcopy add-on was not purchased for this booking.
                </div>
                <a href="<?= e(site_url('booking/index.php')) ?>" class="btn btn-outline-secondary">
                    <i class="fa-regular fa-calendar-check me-1"></i>Add Softcopy to Booking
                </a>
            </div>
            <?php endif; ?>

            <div class="text-center">
                <a href="<?= e(site_url('booth/index.php')) ?>" class="btn btn-snapit btn-lg fw-semibold px-5 py-3">
                    <i class="fa-solid fa-plus me-2"></i>Start New Session
                </a>
            </div>

        </div>
    </div>
</div>

<?php if ($softcopy): ?>
<script>
(function() {
    const form = document.getElementById('recipientsForm');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    form.addEventListener('submit', function(e) {
        let valid = true;
        let hasOne = false;
        document.querySelectorAll('.recipient-email').forEach(input => {
            const err = document.getElementById('err_' + input.name);
            input.classList.remove('is-invalid', 'is-valid');
            if (err) err.textContent = '';
            const v = (input.value || '').trim();
            if (v === '') return;
            hasOne = true;
            if (!emailRegex.test(v)) {
                valid = false;
                input.classList.add('is-invalid');
                if (err) err.textContent = 'Please enter a valid email address.';
            } else {
                input.classList.add('is-valid');
            }
        });
        if (!hasOne) {
            e.preventDefault();
            alert('Please enter at least one email address.');
            return;
        }
        if (!valid) {
            e.preventDefault();
            alert('Please fix the invalid email address(es) before submitting.');
        }
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
