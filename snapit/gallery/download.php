<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$sid = isset($_GET['sid']) ? (int)$_GET['sid'] : 0;
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

if ($sid <= 0) {
    set_flash('Invalid session reference.', 'danger');
    redirect(site_url('gallery/index.php'));
}

$sstmt = mysqli_prepare($conn, "SELECT gs.*, b.booking_id, b.user_id AS booking_user_id, b.softcopy_addon,
    l.name AS layout_name, l.photo_count, l.grid_cols, l.grid_rows,
    f.name AS filter_name, f.css_filter,
    fd.name AS frame_name, fd.border_color, fd.border_width, fd.bg_color, fd.text_color, fd.photo_gap, fd.photo_radius, fd.pattern, l.print_size,
    bk.event_name
    FROM guest_sessions gs
    INNER JOIN bookings b ON gs.booking_id = b.booking_id
    LEFT JOIN layouts l ON gs.layout_id = l.layout_id
    LEFT JOIN filters f ON gs.filter_id = f.filter_id
    LEFT JOIN frame_designs fd ON gs.frame_design_id = fd.design_id
    LEFT JOIN bookings bk ON gs.booking_id = bk.booking_id
    WHERE gs.session_id = ? LIMIT 1");
mysqli_stmt_bind_param($sstmt, 'i', $sid);
mysqli_stmt_execute($sstmt);
$sresult = mysqli_stmt_get_result($sstmt);
$session = mysqli_fetch_assoc($sresult);

if (!$session) {
    set_flash('Photo session not found.', 'danger');
    redirect(site_url('gallery/index.php'));
}

$booking_id = (int)$session['booking_id'];

if ((int)$session['booking_user_id'] !== $current_user_id) {
    set_flash('You are not authorized to access this session.', 'danger');
    redirect(site_url('gallery/index.php'));
}

if (!booking_has_softcopy_access($conn, (int)$booking_id)) {
    set_flash('Softcopy download is not available for this booking. Purchase the softcopy add-on or an upgraded package to enable downloads.', 'danger');
    redirect(site_url('gallery/view_booking.php?id=' . $booking_id));
}

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

$photo_count = (int)($session['photo_count'] ?? count($photos));
$grid_class = 'photo-grid-solo';
if ($photo_count >= 6) $grid_class = 'photo-grid-6';
elseif ($photo_count >= 2) $grid_class = 'photo-grid-4';

$border_color = $session['border_color'] ?? '#6f42c1';
$border_width = (int)($session['border_width'] ?? 6);
$css_filter = $session['css_filter'] ?? 'none';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"
    crossorigin="anonymous"></script>

<div class="container my-5 no-print">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(site_url('gallery/index.php')) ?>" class="text-decoration-none text-muted">Gallery</a></li>
            <li class="breadcrumb-item"><a href="<?= e(site_url('gallery/view_booking.php?id=' . (int)$booking_id)) ?>" class="text-decoration-none text-muted">Booking #<?= (int)$booking_id ?></a></li>
            <li class="breadcrumb-item active text-muted" aria-current="page">Download Session #<?= (int)$sid ?></li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 p-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-file-image me-2"></i>Session #<?= (int)$sid ?> &mdash; Download
                </h2>
                <div class="text-muted small">
                    <?= e($session['event_name'] ?? 'Event') ?>
                    <?php if (!empty($session['guest_name'])): ?>&bull; Guest: <?= e($session['guest_name']) ?><?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fa-solid fa-print me-2"></i>Print / Save as PDF
                </button>
                <button type="button" class="btn btn-snapit btn-lg" id="downloadBtn" onclick="downloadPNG()">
                    <i class="fa-solid fa-download me-2"></i>Download as PNG
                </button>
            </div>
        </div>
    </div>

    <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info d-flex align-items-center mb-4 no-print" role="alert">
        <i class="fa-solid fa-circle-info me-2 fs-5"></i>
        <div>
            Click <strong>"Download as PNG"</strong> to capture the layout below as a PNG image. You can also use <strong>Print / Save as PDF</strong> for a printer-friendly copy.
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="d-flex justify-content-center">
        <?= strip_html(array_column($photos,"photo_path"), strip_cfg($session, ($session["event_name"] ?? "Snap It").(!empty($session["guest_name"]) ? " · ".$session["guest_name"] : "")), "layoutFrame") ?>
        </div>
    </div>
</div>

<div class="container mb-5 no-print">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2" style="color:var(--snapit-gold)"></i>Layout Details</h6>
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="text-muted mb-1">Layout</div>
                    <div class="fw-semibold"><?= e($session['layout_name'] ?? 'Solo') ?> (<?= (int)$photo_count ?> photo<?= (int)$photo_count !== 1 ? 's' : '' ?>)</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted mb-1">Filter</div>
                    <div class="fw-semibold"><?= e($session['filter_name'] ?? 'None') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted mb-1">Frame</div>
                    <div class="fw-semibold"><?= e($session['frame_name'] ?? 'Default') ?> &middot; <?= (int)$border_width ?>px</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted mb-1">Photos</div>
                    <div class="fw-semibold"><?= count($photos) ?> kept</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function downloadPNG() {
    const btn = document.getElementById('downloadBtn');
    if (!btn) return;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Generating PNG...';

    const el = document.getElementById('layoutFrame');
    if (!el) {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        alert('Layout element not found.');
        return;
    }

    const sid = <?= (int)$sid ?>;
    html2canvas(el, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: null
    }).then(function(canvas) {
        try {
            const dataUrl = canvas.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = dataUrl;
            a.download = 'snapit-session-' + sid + '.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        } catch (err) {
            alert('PNG generation completed, but auto-download failed. Please right-click the image below and choose "Save Image As..." when prompted.');
            const w = window.open();
            if (w) {
                w.document.title = 'Snap It Session #' + sid + ' - Right click to save';
                w.document.body.innerHTML = '<div style="padding:20px;text-align:center;"><h3>Right-click the image and choose "Save image as..."</h3><br></div>';
                w.document.body.appendChild(canvas);
            }
        }
    }).catch(function(err) {
        alert('Could not generate PNG automatically: ' + (err && err.message ? err.message : err) + '\n\nTry using "Print / Save as PDF" instead.');
    }).finally(function() {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
