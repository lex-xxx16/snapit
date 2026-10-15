<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);
// Customer photos are private: staff/admin never get an all-bookings photo overview.
$staff_overview_requested = false;
$is_staff_view = $staff_overview_requested;

if ($is_staff_view) {
    $sql = "SELECT b.*, u.name AS customer_name,
            (SELECT COUNT(*) FROM guest_sessions gs WHERE gs.booking_id = b.booking_id) AS session_count
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.user_id
            ORDER BY b.event_date DESC, b.booking_id DESC";
    $stmt = mysqli_prepare($conn, $sql);
} else {
    $sql = "SELECT b.*,
            (SELECT COUNT(*) FROM guest_sessions gs WHERE gs.booking_id = b.booking_id) AS session_count
            FROM bookings b
            WHERE b.user_id = ?
            ORDER BY b.event_date DESC, b.booking_id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
}
mysqli_stmt_execute($stmt);
$bookings_result = mysqli_stmt_get_result($stmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($bookings_result)) {
    $bookings[] = $row;
}

$thumb_photos = [];
foreach ($bookings as $b) {
    $bid = (int)$b['booking_id'];
    $tstmt = mysqli_prepare($conn, "SELECT sp.photo_path
        FROM session_photos sp
        INNER JOIN guest_sessions gs ON sp.session_id = gs.session_id
        WHERE gs.booking_id = ? AND sp.is_kept = 1
        ORDER BY gs.session_id ASC, sp.order_index ASC
        LIMIT 4");
    mysqli_stmt_bind_param($tstmt, 'i', $bid);
    mysqli_stmt_execute($tstmt);
    $tres = mysqli_stmt_get_result($tstmt);
    $photos = [];
    while ($p = mysqli_fetch_assoc($tres)) {
        $photos[] = $p['photo_path'];
    }
    $thumb_photos[$bid] = $photos;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container my-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <?php if ($is_staff_view): ?>
                    <i class="fa-solid fa-users-gear me-2 text-secondary-emphasis"></i>All Bookings &mdash; Staff Overview
                <?php else: ?>
                    <i class="fa-solid fa-images me-2" style="color:var(--snapit-gold)"></i>My Photo Gallery
                <?php endif; ?>
            </h2>
            <p class="text-muted mb-0">
                <?php if ($is_staff_view): ?>
                    Administrative overview &mdash; all customer bookings and sessions visible to staff only.
                <?php else: ?>
                    Browse photos from your booked events. Only bookings owned by your account appear here.
                <?php endif; ?>
            </p>
        </div>
        <?php if (is_staff()): ?>
            <?php if ($is_staff_view): ?>
                <a href="<?= e(site_url('gallery/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-user me-1"></i>Switch to My Personal Gallery
                </a>
            <?php else: ?>
                <a href="<?= e(site_url('gallery/index.php?staff=1')) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-chart-line me-1"></i>Open Staff Booking Overview
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="card text-center border-0 shadow-sm py-5">
            <div class="card-body">
                <i class="fa-regular fa-folder-open fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No bookings yet</h5>
                <p class="text-muted small">
                    <?= $is_staff_view ? 'No bookings have been made in the system.' : 'Once your event photos are ready, they will appear here.' ?>
                </p>
                <?php if (!$is_staff_view): ?>
                    <a href="<?= e(site_url('booking/create.php')) ?>" class="btn btn-snapit mt-2">
                        <i class="fa-regular fa-calendar-plus me-2"></i>Book an Event
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($bookings as $b):
                $bid = (int)$b['booking_id'];
                $thumbs = $thumb_photos[$bid] ?? [];
                $has_softcopy = (int)$b['softcopy_addon'] === 1;
            ?>
                <div class="col-lg-4 col-md-6">
                    <a href="<?= e(site_url('gallery/view_booking.php?id=' . $bid)) ?>" class="text-decoration-none">
                        <div class="card h-100 shadow-sm border-0 overflow-hidden hover-lift">
                            <div class="position-relative">
                                <?php if (!empty($thumbs)): ?>
                                    <div class="d-flex flex-wrap">
                                        <?php for ($i = 0; $i < 4; $i++):
                                            $col = $i === 0 ? 'col-12' : 'col-4';
                                            if (count($thumbs) === 1) $col = 'col-12';
                                            elseif (count($thumbs) === 2) $col = $i < 2 ? 'col-6' : 'col-6';
                                            elseif (count($thumbs) === 3) $col = $i === 0 ? 'col-12' : 'col-6';
                                        ?>
                                            <div class="<?= $col ?>" style="<?= $i === 0 ? 'height:180px;' : 'height:90px;' ?>">
                                                <?php if (isset($thumbs[$i])): ?>
                                                    <img src="<?= e($thumbs[$i]) ?>" class="w-100 h-100" style="object-fit:cover;" alt="Photo">
                                                <?php else: ?>
                                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background:#2b1f15;">
                                                        <i class="fa-solid fa-image text-muted opacity-50 fa-lg"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center" style="height:200px;background:linear-gradient(135deg,#2b1f15,#1a120c);">
                                        <div class="text-center">
                                            <i class="fa-regular fa-image fa-3x text-muted opacity-50 mb-2"></i>
                                            <div class="small text-muted">No photos yet</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge rounded-pill <?= e(status_badge_class($b['status'])) ?>">
                                        <?= e(ucfirst($b['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="fw-bold mb-2"><?= e($b['event_name']) ?></h5>
                                <div class="d-flex align-items-center text-muted small mb-2">
                                    <i class="fa-regular fa-calendar me-2"></i>
                                    <?= e(format_date($b['event_date'])) ?>
                                </div>
                                <?php if ($is_staff_view): ?>
                                    <div class="d-flex align-items-center text-muted small mb-2">
                                        <i class="fa-regular fa-user me-2"></i>
                                        <?= e($b['customer_name'] ?? 'Unknown') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex align-items-center text-muted small mb-3">
                                    <i class="fa-solid fa-people-group me-2"></i>
                                    <?= (int)$b['session_count'] ?> guest session<?= (int)$b['session_count'] !== 1 ? 's' : '' ?>
                                </div>
                                <?php if (!$has_softcopy): ?>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-light text-muted border">
                                            <i class="fa-solid fa-circle-xmark me-1 opacity-75"></i>
                                            Softcopy not purchased &mdash; email delivery &amp; download disabled
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <i class="fa-solid fa-circle-check me-1"></i>
                                            Softcopy included
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
