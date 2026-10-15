<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        set_flash('Invalid security token. Please refresh the page and try again.', 'danger');
        redirect(site_url('booth/session.php'));
    }
}

$session_id = current_booth_session_id($conn);
if (!$session_id) {
    set_flash('No active session found.', 'warning');
    redirect(site_url('booth/index.php'));
}

$stmt = mysqli_prepare($conn, "SELECT gs.booking_id FROM guest_sessions gs WHERE gs.session_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $session_id);
mysqli_stmt_execute($stmt);
$sess = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$booking_id = (int)$sess['booking_id'];

$stmt = mysqli_prepare($conn, "SELECT package_id, estimated_hardcopies, booking_type FROM bookings WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$bk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$has_hardcopy = $bk && ((int)$bk['estimated_hardcopies'] > 0 || true);

mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "UPDATE guest_sessions SET status = 'completed', ended_at = NOW() WHERE session_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $session_id);
    mysqli_stmt_execute($stmt);

    // Walk-ins print one strip x the number of strips they paid for; events print each photo once.
    $is_walkin = $bk && $bk['booking_type'] === 'walkin';
    $copies = $is_walkin ? max(1, (int)$bk['estimated_hardcopies']) : 1;
    $photos = mysqli_query($conn, "SELECT photo_id FROM session_photos WHERE session_id = " . (int)$session_id . " AND is_kept = 1" . ($is_walkin ? " ORDER BY order_index LIMIT 1" : ""));
    $print_count = 0;
    if ($has_hardcopy) {
        while ($ph = mysqli_fetch_assoc($photos)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO print_jobs (session_id, booking_id, photo_id, copies, status, paper_used, ink_used_ml) VALUES (?, ?, ?, ?, 'pending', 0, 0)");
            $pid = (int)$ph['photo_id'];
            mysqli_stmt_bind_param($stmt, 'iiii', $session_id, $booking_id, $pid, $copies);
            mysqli_stmt_execute($stmt);
            $print_count++;
        }
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    set_flash('An error occurred while ending the session.', 'danger');
    redirect(site_url('booth/session.php'));
}

unset($_SESSION['active_session_id']);
unset($_SESSION['capture_index'], $_SESSION['captured']);

$auto = isset($_GET['auto']) ? '&auto=1' : '';
set_flash('Session completed' . ($print_count > 0 ? ". $print_count print job(s) queued." : '') . ($auto ? ' (idle timeout).' : ''), 'success');
redirect(site_url('booth/done.php?sid=' . $session_id . $auto));
