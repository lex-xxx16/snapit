<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('inventory/print_queue.php'));
}

$jobIds = [];

if (isset($_POST['single_job_id'])) {
    $singleId = (int)$_POST['single_job_id'];
    if ($singleId > 0) $jobIds[] = $singleId;
} elseif (isset($_POST['booking_mark_all'])) {
    $bid = (int)$_POST['booking_mark_all'];
    if ($bid > 0) {
        $stmt = mysqli_prepare($conn,
            "SELECT job_id FROM print_jobs WHERE booking_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, 'i', $bid);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($r)) {
            $jobIds[] = (int)$row['job_id'];
        }
    }
} elseif (!empty($_POST['job_ids']) && is_array($_POST['job_ids'])) {
    foreach ($_POST['job_ids'] as $jid) {
        $jidInt = (int)$jid;
        if ($jidInt > 0) $jobIds[] = $jidInt;
    }
}

$jobIds = array_unique($jobIds);

if (empty($jobIds)) {
    set_flash('No print jobs selected.', 'warning');
    redirect(site_url('inventory/print_queue.php'));
}

$paperStmt = mysqli_prepare($conn,
    "SELECT item_id FROM inventory_items WHERE category = 'paper' AND quantity_on_hand > 0 ORDER BY quantity_on_hand DESC LIMIT 1");
mysqli_stmt_execute($paperStmt);
$paperResult = mysqli_stmt_get_result($paperStmt);
$paperRow = mysqli_fetch_assoc($paperResult);
$paperItemId = $paperRow ? (int)$paperRow['item_id'] : null;

// Block printing when paper is insufficient (stock must never go negative).
$needStmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(copies),0) AS need FROM print_jobs WHERE status = 'pending' AND job_id IN (" . implode(',', array_map('intval', $jobIds)) . ")");
mysqli_stmt_execute($needStmt);
$needRow = mysqli_fetch_assoc(mysqli_stmt_get_result($needStmt));
$sheetsNeeded = (int)($needRow['need'] ?? 0);
$stockRes = mysqli_query($conn, "SELECT COALESCE(SUM(quantity_on_hand),0) AS s FROM inventory_items WHERE category = 'paper'");
$sheetsInStock = (int)mysqli_fetch_assoc($stockRes)['s'];
if ($paperItemId === null || $sheetsNeeded > $sheetsInStock) {
    set_flash("Not enough photo paper to print these jobs (need $sheetsNeeded sheet(s), have $sheetsInStock). Please restock paper in Inventory first.", 'danger');
    redirect(site_url('inventory/print_queue.php'));
}

$doneBy = $_SESSION['user_id'] ?? null;
$printedCount = 0;

mysqli_begin_transaction($conn);

try {
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $types = str_repeat('i', count($jobIds));

    $fetchStmt = mysqli_prepare($conn,
        "SELECT job_id, copies FROM print_jobs WHERE job_id IN ($placeholders) AND status = 'pending' FOR UPDATE");
    mysqli_stmt_bind_param($fetchStmt, $types, ...$jobIds);
    mysqli_stmt_execute($fetchStmt);
    $jobsResult = mysqli_stmt_get_result($fetchStmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($jobsResult)) {
        $jobs[] = $row;
    }

    $updateJobStmt = mysqli_prepare($conn,
        "UPDATE print_jobs SET status = 'printed', printed_at = NOW(), paper_used = ?, ink_used_ml = ? WHERE job_id = ?");
    $insertUsageStmt = mysqli_prepare($conn,
        "INSERT INTO inventory_usage (job_id, item_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
    $updateItemStmt = mysqli_prepare($conn,
        "UPDATE inventory_items SET quantity_on_hand = quantity_on_hand - ? WHERE item_id = ?");
    $insertLogStmt = mysqli_prepare($conn,
        "INSERT INTO inventory_logs (item_id, quantity_delta, reason, reference_id, done_by, created_at) VALUES (?, ?, 'Print consumption', ?, ?, NOW())");

    foreach ($jobs as $job) {
        $jobId = (int)$job['job_id'];
        $copies = (int)$job['copies'];
        $inkUsedMl = 0; // ink is tracked by manual restock/replace entries, not estimated
        $paperUsed = $copies;

        mysqli_stmt_bind_param($updateJobStmt, 'iii', $paperUsed, $inkUsedMl, $jobId);
        mysqli_stmt_execute($updateJobStmt);

        if ($paperItemId !== null && $paperUsed > 0) {
            mysqli_stmt_bind_param($insertUsageStmt, 'iii', $jobId, $paperItemId, $paperUsed);
            mysqli_stmt_execute($insertUsageStmt);

            $paperDelta = -$paperUsed;
            mysqli_stmt_bind_param($updateItemStmt, 'ii', $paperUsed, $paperItemId);
            mysqli_stmt_execute($updateItemStmt);

            mysqli_stmt_bind_param($insertLogStmt, 'iiii', $paperItemId, $paperDelta, $jobId, $doneBy);
            mysqli_stmt_execute($insertLogStmt);
        }

        $printedCount++;
    }

    mysqli_commit($conn);

    if ($printedCount > 0) {
        set_flash("{$printedCount} print job(s) marked as printed. Inventory updated.", 'success');
    } else {
        set_flash('No pending jobs found to mark as printed.', 'warning');
    }
} catch (Throwable $e) {
    mysqli_rollback($conn);
    set_flash('Failed to mark jobs printed: ' . $e->getMessage(), 'danger');
}

redirect(site_url('inventory/print_queue.php'));
