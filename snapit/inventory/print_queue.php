<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

$countStmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS pending_count FROM print_jobs WHERE status = 'pending'");
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$countRow = mysqli_fetch_assoc($countResult);
$pendingCount = (int)($countRow['pending_count'] ?? 0);

$groupStmt = mysqli_prepare($conn,
    "SELECT DISTINCT pj.booking_id, b.event_name
     FROM print_jobs pj
     LEFT JOIN bookings b ON pj.booking_id = b.booking_id
     WHERE pj.status = 'pending'
     ORDER BY pj.booking_id DESC");
mysqli_stmt_execute($groupStmt);
$groupResult = mysqli_stmt_get_result($groupStmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($groupResult)) {
    $bookings[] = $row;
}

$jobsByBooking = [];
foreach ($bookings as $b) {
    $bid = (int)$b['booking_id'];
    $jobStmt = mysqli_prepare($conn,
        "SELECT pj.*, sp.photo_path, gs.guest_name, b.event_name
         FROM print_jobs pj
         LEFT JOIN session_photos sp ON pj.photo_id = sp.photo_id
         LEFT JOIN guest_sessions gs ON pj.session_id = gs.session_id
         LEFT JOIN bookings b ON pj.booking_id = b.booking_id
         WHERE pj.status = 'pending' AND pj.booking_id = ?
         ORDER BY pj.created_at ASC");
    mysqli_stmt_bind_param($jobStmt, 'i', $bid);
    mysqli_stmt_execute($jobStmt);
    $jobResult = mysqli_stmt_get_result($jobStmt);
    $jobs = [];
    while ($j = mysqli_fetch_assoc($jobResult)) {
        $jobs[] = $j;
    }
    $jobsByBooking[$bid] = [
        'event_name' => $b['event_name'] ?? ('Booking #' . $bid),
        'jobs' => $jobs
    ];
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fa-solid fa-print me-2"></i>Print Queue</h2>
        <a href="<?= e(site_url('inventory/index.php')) ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Inventory
        </a>
    </div>

    <?php if ($pendingCount > 0): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <i class="fa-solid fa-circle-exclamation me-3 fs-4"></i>
            <div>
                <strong>Pending print jobs: <?= e($pendingCount) ?></strong>
                &mdash; <?= $pendingCount === 1 ? '1 job' : e($pendingCount) . ' jobs' ?> awaiting printing.
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fa-solid fa-inbox text-muted fs-1 mb-3"></i>
                <h5 class="text-muted">No pending print jobs</h5>
                <p class="text-muted mb-0">All caught up! Photos from guest sessions will appear here.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-3">
            <form method="POST" action="<?= e(site_url('inventory/mark_printed.php')) ?>" id="batchForm">
                <?php $first = true; ?>
                <?php foreach ($jobsByBooking as $bid => $group): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center"
                            role="button" data-bs-toggle="collapse"
                            data-bs-target="#booking-<?= e($bid) ?>" aria-expanded="true">
                            <div>
                                <i class="fa-solid fa-calendar-event me-2"></i>
                                <strong><?= e($group['event_name']) ?></strong>
                                <span class="badge bg-secondary ms-2">
                                    <?= e(count($group['jobs'])) ?> job<?= count($group['jobs']) !== 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-success mark-booking-btn"
                                    data-booking="<?= e($bid) ?>">
                                    <i class="fa-solid fa-check me-1"></i>Mark All Printed
                                </button>
                                <i class="fa-solid fa-chevron-down ms-2 text-muted"></i>
                            </div>
                        </div>
                        <div id="booking-<?= e($bid) ?>" class="collapse show">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40px;">
                                                    <input type="checkbox" class="form-check-input check-all-booking"
                                                        data-booking="<?= e($bid) ?>">
                                                </th>
                                                <th>Job #</th>
                                                <th>Photo</th>
                                                <th>Guest Session</th>
                                                <th class="text-end">Copies</th>
                                                <th>Status</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($group['jobs'] as $job): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="job_ids[]"
                                                            value="<?= e($job['job_id']) ?>"
                                                            class="form-check-input job-check booking-<?= e($bid) ?>">
                                                    </td>
                                                    <td class="fw-bold">#<?= e($job['job_id']) ?></td>
                                                    <td>
                                                        <?php if (!empty($job['photo_path'])): ?>
                                                            <img src="<?= e(photo_url($job['photo_path'])) ?>"
                                                                alt="Photo <?= e($job['photo_id']) ?>"
                                                                style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                                        <?php else: ?>
                                                            <span class="text-muted small">(no photo)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div><?= e($job['guest_name'] ?? 'Guest') ?></div>
                                                        <small class="text-muted">Session #<?= e($job['session_id']) ?></small>
                                                    </td>
                                                    <td class="text-end fw-bold"><?= e($job['copies']) ?></td>
                                                    <td>
                                                        <span class="badge <?= e(status_badge_class($job['status'])) ?>">
                                                            <?= e(ucfirst($job['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="submit" name="single_job_id"
                                                            value="<?= e($job['job_id']) ?>"
                                                            class="btn btn-sm btn-success">
                                                            <i class="fa-solid fa-check"></i> Mark Printed
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $first = false; ?>
                <?php endforeach; ?>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="submit" name="action" value="batch" class="btn btn-success">
                        <i class="fa-solid fa-check-double me-1"></i>Mark Selected as Printed
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.mark-booking-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const bid = this.dataset.booking;
        const form = document.getElementById('batchForm');
        const temp = document.createElement('input');
        temp.type = 'hidden';
        temp.name = 'booking_mark_all';
        temp.value = bid;
        form.appendChild(temp);
        document.querySelectorAll('.job-check.booking-' + bid).forEach(cb => cb.checked = true);
        form.submit();
    });
});

document.querySelectorAll('.check-all-booking').forEach(cb => {
    cb.addEventListener('change', function() {
        const bid = this.dataset.booking;
        document.querySelectorAll('.job-check.booking-' + bid).forEach(c => c.checked = this.checked);
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
