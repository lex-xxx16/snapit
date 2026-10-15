<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

if ($booking_id <= 0) {
    set_flash('Invalid booking reference.', 'danger');
    redirect(site_url('gallery/index.php'));
}

$bstmt = mysqli_prepare($conn, "SELECT b.*, u.name AS customer_name, u.email AS customer_email, p.name AS package_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN packages p ON b.package_id = p.package_id
    WHERE b.booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($bstmt, 'i', $booking_id);
mysqli_stmt_execute($bstmt);
$bresult = mysqli_stmt_get_result($bstmt);
$booking = mysqli_fetch_assoc($bresult);

if (!$booking) {
    set_flash('Booking not found.', 'danger');
    redirect(site_url('gallery/index.php'));
}

if ((int)$booking['user_id'] !== $current_user_id) {
    set_flash('You are not authorized to view this booking.', 'danger');
    redirect(site_url('gallery/index.php'));
}

$has_softcopy = booking_has_softcopy_access($conn, (int)$booking['booking_id']);
$softcopy_via_package = $has_softcopy && (int)($booking['softcopy_addon'] ?? 0) !== 1;

$sstmt = mysqli_prepare($conn, "SELECT gs.*, l.name AS layout_name, l.photo_count, l.grid_cols, l.grid_rows,
    f.name AS filter_name, f.css_filter, fd.name AS frame_name, fd.border_color, fd.border_width, fd.bg_color, fd.text_color, fd.photo_gap, fd.photo_radius, fd.pattern, l.print_size
    FROM guest_sessions gs
    LEFT JOIN layouts l ON gs.layout_id = l.layout_id
    LEFT JOIN filters f ON gs.filter_id = f.filter_id
    LEFT JOIN frame_designs fd ON gs.frame_design_id = fd.design_id
    WHERE gs.booking_id = ? AND gs.status = 'completed'
    ORDER BY gs.ended_at ASC, gs.session_id ASC");
mysqli_stmt_bind_param($sstmt, 'i', $booking_id);
mysqli_stmt_execute($sstmt);
$sresult = mysqli_stmt_get_result($sstmt);
$sessions = [];
while ($s = mysqli_fetch_assoc($sresult)) {
    $sessions[] = $s;
}

$session_photos = [];
$session_recipients = [];
foreach ($sessions as $s) {
    $sid = (int)$s['session_id'];

    $pstmt = mysqli_prepare($conn, "SELECT * FROM session_photos
        WHERE session_id = ? AND is_kept = 1
        ORDER BY order_index ASC, photo_id ASC");
    mysqli_stmt_bind_param($pstmt, 'i', $sid);
    mysqli_stmt_execute($pstmt);
    $pres = mysqli_stmt_get_result($pstmt);
    $photos = [];
    while ($p = mysqli_fetch_assoc($pres)) {
        $photos[] = $p;
    }
    $session_photos[$sid] = $photos;

    $rstmt = mysqli_prepare($conn, "SELECT * FROM delivery_recipients
        WHERE session_id = ? ORDER BY created_at ASC, recipient_id ASC");
    mysqli_stmt_bind_param($rstmt, 'i', $sid);
    mysqli_stmt_execute($rstmt);
    $rres = mysqli_stmt_get_result($rstmt);
    $recipients = [];
    while ($r = mysqli_fetch_assoc($rres)) {
        $recipients[] = $r;
    }
    $session_recipients[$sid] = $recipients;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container my-5">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(site_url('gallery/index.php')) ?>" class="text-decoration-none text-muted">Gallery</a></li>
            <li class="breadcrumb-item active text-muted" aria-current="page">Booking #<?= (int)$booking_id ?></li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h2 class="fw-bold mb-1">
                        <i class="fa-solid fa-camera-retro me-2"></i><?= e($booking['event_name']) ?>
                    </h2>
                    <div class="text-muted small">
                        Booking #<?= (int)$booking_id ?> &bull; <?= e($booking['package_name'] ?? 'Package') ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge rounded-pill fs-6 <?= e(status_badge_class($booking['status'])) ?> px-3 py-2">
                        <?= e(ucfirst($booking['status'])) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body pt-0 px-4 pb-4">
            <div class="row g-3 small">
                <div class="col-md-4">
                    <div class="text-muted mb-1"><i class="fa-regular fa-calendar me-2"></i>Event Date</div>
                    <div class="fw-semibold"><?= e(format_date($booking['event_date'])) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted mb-1"><i class="fa-regular fa-clock me-2"></i>Start Time</div>
                    <div class="fw-semibold"><?= e(date('g:i A', strtotime($booking['start_time']))) ?> &bull; <?= (int)$booking['duration_hours'] ?> hrs</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted mb-1"><i class="fa-regular fa-user me-2"></i>Customer</div>
                    <div class="fw-semibold"><?= e($booking['customer_name'] ?? '-') ?></div>
                </div>
            </div>
            <hr class="my-3">
            <?php if (!$has_softcopy): ?>
                <div class="alert alert-secondary border-0 d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                    <div>
                        <strong>Softcopy not purchased.</strong> Email delivery &amp; PNG download are disabled for this booking.
                    </div>
                </div>
            <?php elseif ($softcopy_via_package): ?>
                <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info-emphasis d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-gift me-2 fs-5"></i>
                    <div>
                        <strong>Softcopy is included in your package.</strong> You can download each session as PNG or email the photos to recipients.
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-circle-check me-2 fs-5"></i>
                    <div>
                        <strong>Softcopy add-on active.</strong> You can download each session as PNG or email the photos to recipients.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($sessions)): ?>
        <div class="card text-center border-0 shadow-sm py-5">
            <div class="card-body">
                <i class="fa-regular fa-file-image fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No completed sessions yet</h5>
                <p class="text-muted small">Photo sessions for this event will appear here once they are completed.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-5">
            <?php foreach ($sessions as $idx => $s):
                $sid = (int)$s['session_id'];
                $photos = $session_photos[$sid] ?? [];
                $recipients = $session_recipients[$sid] ?? [];
                $photo_count = (int)($s['photo_count'] ?? count($photos));
                $grid_class = 'photo-grid-solo';
                if ($photo_count >= 6) $grid_class = 'photo-grid-6';
                elseif ($photo_count >= 2) $grid_class = 'photo-grid-4';

                $border_color = $s['border_color'] ?? '#6f42c1';
                $border_width = (int)($s['border_width'] ?? 6);
                $css_filter = $s['css_filter'] ?? 'none';
            ?>
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="card-header bg-white border-0 d-flex flex-wrap align-items-center justify-content-between gap-2 py-3 px-4">
                        <div>
                            <h5 class="fw-bold mb-1">
                                <i class="fa-solid fa-images me-2"></i>Session #<?= $sid ?>
                                <?php if (!empty($s['guest_name'])): ?>
                                    <span class="fw-normal text-muted fs-6 ms-2">&mdash; <?= e($s['guest_name']) ?></span>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                <span><i class="fa-solid fa-layer-group me-1"></i><?= e($s['layout_name'] ?? 'Solo') ?> Layout</span>
                                <span><i class="fa-solid fa-palette me-1"></i><?= e($s['filter_name'] ?? 'No Filter') ?></span>
                                <span><i class="fa-regular fa-square me-1"></i><?= e($s['frame_name'] ?? 'Default Frame') ?></span>
                                <span><i class="fa-regular fa-clock me-1"></i><?= e(format_date($s['ended_at'], true)) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="mx-auto mb-4" style="max-width:520px;">
                            <?= strip_html(array_column($photos,"photo_path"), strip_cfg($s, ($booking["event_name"] ?? "Snap It").(!empty($s["guest_name"]) ? " · ".$s["guest_name"] : ""))) ?>
                        </div>

                        <?php if ($has_softcopy): ?>
                            <hr class="my-4">
                            <div class="row g-4">
                                <div class="col-lg-5">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fa-solid fa-file-arrow-down me-2" style="color:var(--snapit-gold)"></i>
                                        <h6 class="fw-bold mb-0">Download PNG</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Save this photo strip as a PNG image to your device.</p>
                                    <a href="<?= e(site_url('gallery/download.php?sid=' . $sid)) ?>" class="btn btn-snapit w-100">
                                        <i class="fa-solid fa-download me-2"></i>Download All
                                    </a>
                                </div>
                                <div class="col-lg-7">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fa-regular fa-paper-plane me-2" style="color:var(--champagne)"></i>
                                        <h6 class="fw-bold mb-0">Email to Recipients</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Enter up to 10 email addresses to deliver the photo set.</p>
                                    <form method="post" action="<?= e(site_url('gallery/send_emails.php')) ?>" class="mb-3" onsubmit="return collectEmails(this, <?= (int)$sid ?>);">
                                        <input type="hidden" name="session_id" value="<?= (int)$sid ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <div id="emailInputs_<?= $sid ?>">
                                            <?php
                                            $seed_emails = array_column($recipients, 'email');
                                            $display_count = max(1, min(10, count($seed_emails) + 1));
                                            for ($i = 0; $i < $display_count; $i++):
                                                $val = $seed_emails[$i] ?? '';
                                            ?>
                                                <div class="input-group mb-2 email-row">
                                                    <span class="input-group-text bg-light"><i class="fa-regular fa-envelope text-muted"></i></span>
                                                    <input type="email" name="emails[]" class="form-control" placeholder="name@example.com" value="<?= e($val) ?>">
                                                    <?php if ($i > 0): ?>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="removeEmailRow(this)" title="Remove">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addEmailRow(<?= (int)$sid ?>)" id="addBtn_<?= $sid ?>">
                                                <i class="fa-solid fa-plus me-1"></i>Add Another Email
                                            </button>
                                            <small class="text-muted align-self-center" id="count_<?= $sid ?>"><?= $display_count ?> / 10 emails</small>
                                        </div>
                                        <button type="submit" class="btn btn-accent">
                                            <i class="fa-regular fa-paper-plane me-2"></i>Send Emails
                                        </button>
                                    </form>

                                    <?php if (!empty($recipients)): ?>
                                        <div class="border rounded p-3 bg-light bg-opacity-50">
                                            <div class="small fw-semibold text-muted mb-2">
                                                <i class="fa-solid fa-envelopes-bulk me-1"></i>Already sent to <?= count($recipients) ?> recipient<?= count($recipients) !== 1 ? 's' : '' ?>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($recipients as $r): ?>
                                                    <span class="badge rounded-pill bg-white border d-inline-flex align-items-center gap-1 py-2 px-3">
                                                        <i class="fa-regular fa-envelope text-muted small"></i>
                                                        <span class="small"><?= e($r['email']) ?></span>
                                                        <?php if (!empty($r['sent_at'])): ?>
                                                            <span class="text-success small"><i class="fa-solid fa-check" title="Sent"></i></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function addEmailRow(sid) {
    const container = document.getElementById('emailInputs_' + sid);
    const rows = container.querySelectorAll('.email-row');
    if (rows.length >= 10) return;
    const div = document.createElement('div');
    div.className = 'input-group mb-2 email-row';
    div.innerHTML = `
        <span class="input-group-text bg-light"><i class="fa-regular fa-envelope text-muted"></i></span>
        <input type="email" name="emails[]" class="form-control" placeholder="name@example.com">
        <button type="button" class="btn btn-outline-secondary" onclick="removeEmailRow(this)" title="Remove">
            <i class="fa-solid fa-xmark"></i>
        </button>`;
    container.appendChild(div);
    updateEmailCount(sid);
}
function removeEmailRow(btn) {
    const row = btn.closest('.email-row');
    const container = row.parentElement;
    const sid = container.id.split('_')[1];
    const rows = container.querySelectorAll('.email-row');
    if (rows.length <= 1) {
        row.querySelector('input').value = '';
        return;
    }
    row.remove();
    updateEmailCount(sid);
}
function updateEmailCount(sid) {
    const container = document.getElementById('emailInputs_' + sid);
    const cnt = container.querySelectorAll('.email-row').length;
    const el = document.getElementById('count_' + sid);
    const addBtn = document.getElementById('addBtn_' + sid);
    if (el) el.textContent = cnt + ' / 10 emails';
    if (addBtn) addBtn.disabled = cnt >= 10;
}
function collectEmails(form, sid) {
    const inputs = form.querySelectorAll('input[name="emails[]"]');
    let filled = 0;
    inputs.forEach(inp => {
        if (inp.value.trim() !== '') filled++;
    });
    if (filled === 0) {
        alert('Please enter at least one email address.');
        return false;
    }
    return true;
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id^="emailInputs_"]').forEach(c => {
        const sid = c.id.split('_')[1];
        updateEmailCount(sid);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
