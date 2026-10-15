<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';

$alreadyInstalled = false;
$alreadyChecked = false;
$applied = [];
$skipped = [];
$errors  = [];
$message = '';
$messageType = 'info';

function col_exists($conn, $table, $column) {
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
    return $r && mysqli_num_rows($r) > 0;
}
function idx_exists($conn, $table, $indexName) {
    $r = mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '" . mysqli_real_escape_string($conn, $indexName) . "'");
    return $r && mysqli_num_rows($r) > 0;
}

$migrations = [
    'notifications_status' => function ($conn) use (&$applied, &$skipped, &$errors) {
        if (!col_exists($conn, 'notifications', 'status')) {
            $ok = mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN status ENUM('pending','sent','partial','failed') NOT NULL DEFAULT 'pending' AFTER message_body");
            if ($ok) $applied[] = 'notifications.status'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'notifications.status (exists)'; }
    },
    'notifications_error_detail' => function ($conn) use (&$applied, &$errors) {
        if (!col_exists($conn, 'notifications', 'error_detail')) {
            $ok = mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN error_detail VARCHAR(1000) NULL AFTER status");
            if ($ok) $applied[] = 'notifications.error_detail'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'notifications.error_detail (exists)'; }
    },
    'notifications_message_id' => function ($conn) use (&$applied, &$errors) {
        if (!col_exists($conn, 'notifications', 'message_id')) {
            $ok = mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN message_id VARCHAR(255) NULL AFTER error_detail");
            if ($ok) $applied[] = 'notifications.message_id'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'notifications.message_id (exists)'; }
    },
    'notifications_scheduled_at' => function ($conn) use (&$applied, &$errors) {
        if (!col_exists($conn, 'notifications', 'scheduled_at')) {
            $ok = mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN scheduled_at DATETIME NULL AFTER message_id");
            if ($ok) $applied[] = 'notifications.scheduled_at'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'notifications.scheduled_at (exists)'; }
    },
    'notifications_idx_scheduled' => function ($conn) use (&$applied, &$errors) {
        if (!idx_exists($conn, 'notifications', 'idx_notif_scheduled_type_status')) {
            $ok = mysqli_query($conn, "ALTER TABLE notifications ADD INDEX idx_notif_scheduled_type_status (scheduled_at, type, status)");
            if ($ok) $applied[] = 'notifications.idx_scheduled'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'notifications.idx_scheduled (exists)'; }
    },
    'recipients_error_detail' => function ($conn) use (&$applied, &$errors) {
        if (!col_exists($conn, 'delivery_recipients', 'error_detail')) {
            $ok = mysqli_query($conn, "ALTER TABLE delivery_recipients ADD COLUMN error_detail VARCHAR(1000) NULL AFTER sent_at");
            if ($ok) $applied[] = 'delivery_recipients.error_detail'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'delivery_recipients.error_detail (exists)'; }
    },
    'recipients_message_id' => function ($conn) use (&$applied, &$errors) {
        if (!col_exists($conn, 'delivery_recipients', 'message_id')) {
            $ok = mysqli_query($conn, "ALTER TABLE delivery_recipients ADD COLUMN message_id VARCHAR(255) NULL AFTER error_detail");
            if ($ok) $applied[] = 'delivery_recipients.message_id'; else $errors[] = mysqli_error($conn);
        } else { $skipped[] = 'delivery_recipients.message_id (exists)'; }
    },
    'recipients_unique' => function ($conn) use (&$applied, &$errors) {
        if (!idx_exists($conn, 'delivery_recipients', 'uniq_booking_session_email')) {
            $ok = @mysqli_query($conn, "ALTER TABLE delivery_recipients ADD UNIQUE KEY uniq_booking_session_email (booking_id, session_id, email)");
            if ($ok) { $applied[] = 'delivery_recipients.uniq_booking_session_email'; }
            else { $skipped[] = 'delivery_recipients unique skip (dup rows may exist): ' . mysqli_error($conn); }
        } else { $skipped[] = 'delivery_recipients.unique (exists)'; }
    },
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['run'])) {
    foreach ($migrations as $name => $fn) {
        $fn($conn);
    }
    if (empty($errors)) {
        $message = (count($applied) > 0 ? count($applied) . ' migration(s) applied. ' : 'No migrations required. ')
                 . (count($skipped) ? count($skipped) . ' skipped (already in place).' : '');
        $messageType = 'success';
    } else {
        $message = 'Applied: ' . count($applied) . '. Errors: ' . implode(' | ', array_slice($errors, 0, 3));
        $messageType = 'danger';
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/alert.php';
?>
<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg">
                <div class="card-header text-center py-4">
                    <h2 class="mb-0"><i class="fa-solid fa-envelope-open-text me-2" style="color:#6f42c1"></i> Snap It &mdash; Mailer Schema Migration</h2>
                    <p class="text-muted mt-2 mb-0">Adds columns to <code>notifications</code> and <code>delivery_recipients</code> required by the PHPMailer SMTP integration and event reminder scheduler.</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>Step required after upgrading Snap It to use SMTP emails.</strong> Click the button below to apply the schema migrations.
                            Existing data and indexes are preserved &mdash; each step is idempotent (safe to re-run).
                        </div>
                    <?php endif; ?>

                    <table class="table table-sm table-hover align-middle mb-4">
                        <thead class="table-light">
                            <tr><th>Migration</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($migrations as $name => $fn): ?>
                                <?php
                                $status = 'Pending';
                                $badge  = 'secondary';
                                if (in_array($name, array_map(fn($s) => preg_replace('/ \(.*/', '', $s), $skipped))) {
                                    $status = 'Already applied';
                                    $badge  = 'success';
                                } elseif (in_array($name, $applied)) {
                                    $status = 'Applied this run';
                                    $badge  = 'primary';
                                }
                                ?>
                                <tr><td><code><?= e($name) ?></code></td><td><span class="badge bg-<?= $badge ?>"><?= e($status) ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" class="d-grid">
                        <button type="submit" name="run" value="1" class="btn btn-snapit btn-lg py-3">
                            <i class="fa-solid fa-gears me-2"></i>Run / Re-run Migrations
                        </button>
                    </form>

                    <hr class="my-4">
                    <div class="card bg-light border-0">
                        <div class="card-body small">
                            <h6 class="fw-bold mb-2">What this migration adds:</h6>
                            <ul class="mb-2">
                                <li><code>notifications.status</code> — pending / sent / partial / failed, for tracking dispatch results.</li>
                                <li><code>notifications.error_detail</code> — PHPMailer exception + SMTP last error captured on failure.</li>
                                <li><code>notifications.message_id</code> — returned Message-Id from SMTP server on success.</li>
                                <li><code>notifications.scheduled_at</code> — DATETIME of when event reminders should fire.</li>
                                <li><code>notifications.idx_notif_scheduled_type_status</code> — composite index so the reminder cron scans efficiently.</li>
                                <li><code>delivery_recipients.error_detail</code> and <code>delivery_recipients.message_id</code> — per-email observability for the softcopy delivery flow (gallery and booth done screen).</li>
                                <li><code>delivery_recipients.uniq_booking_session_email</code> — unique key to prevent duplicate softcopy sends per email.</li>
                            </ul>
                            <p class="mb-0">If install.php has <strong>not yet been run</strong>, you do <strong>not</strong> need this page — install.php runs all migrations automatically during setup. Use this page if you are upgrading an existing installed DB.</p>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="test_email.php" class="btn btn-outline-secondary"><i class="fa-solid fa-flask me-1"></i> Next: Test SMTP Connectivity</a>
                        <a href="admin/index.php" class="btn btn-outline-primary"><i class="fa-solid fa-house me-1"></i> Back to Admin Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
