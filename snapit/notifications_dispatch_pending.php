<?php
if (!defined('SNAPIT_CRON_RUN') && php_sapi_name() !== 'cli') {
    session_start();
}
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: text/plain; charset=UTF-8', true);

$is_cli = (php_sapi_name() === 'cli');
$quiet  = $is_cli && in_array('--quiet', $argv ?? [], true);

if (!$is_cli && !empty($_GET['token'])) {
    $expected = getenv('SNAPIT_CRON_TOKEN') ?: (defined('SNAPIT_CRON_TOKEN') ? SNAPIT_CRON_TOKEN : '');
    if ($expected === '' || !hash_equals($expected, (string)$_GET['token'])) {
        http_response_code(403);
        echo "Unauthorized. Set SNAPIT_CRON_TOKEN env var and pass ?token= value, or run via CLI.\n";
        exit;
    }
}

echo "[SNAP-IT NOTIFICATION DISPATCHER] started at " . date('c') . "\n";
$totalProcessed = 0;
$totalSent = 0;
$totalFailed = 0;

$reminderStmt = mysqli_prepare($conn,
    "SELECT n.notification_id, n.booking_id, n.user_id, n.type, n.recipient_email, n.subject, n.message_body,
            b.event_name, b.event_date, b.start_time, b.duration_hours, b.venue,
            u.name AS customer_name, u.email AS customer_email
     FROM notifications n
     INNER JOIN bookings b ON n.booking_id = b.booking_id
     INNER JOIN users    u ON b.user_id     = u.user_id
     WHERE n.type = 'event_reminder'
       AND n.status IN ('pending','failed')
       AND n.scheduled_at IS NOT NULL
       AND n.scheduled_at <= NOW()
       AND DATE(n.scheduled_at) = CURDATE()
     ORDER BY n.scheduled_at ASC
     LIMIT 50");
mysqli_stmt_execute($reminderStmt);
$remRes = mysqli_stmt_get_result($reminderStmt);
while ($row = mysqli_fetch_assoc($remRes)) {
    $totalProcessed++;
    $bookingInfo = [
        'booking_id'     => $row['booking_id'],
        'event_name'     => $row['event_name'],
        'event_date'     => $row['event_date'],
        'start_time'     => $row['start_time'],
        'duration_hours' => $row['duration_hours'],
        'venue'          => $row['venue'],
        'customer_name'  => $row['customer_name'],
        'customer_email' => $row['recipient_email'] ?: $row['customer_email'],
    ];
    $today = new DateTime();
    $evt   = new DateTime($row['event_date']);
    $days  = max(0, (int)$today->diff($evt)->format('%a'));
    $send  = snapit_send_event_reminder($bookingInfo, $days);
    if (!empty($send['ok'])) {
        $totalSent++;
        snapit_mark_notification_sent($conn, (int)$row['notification_id'], true, '', $send['message_id'] ?? '');
        echo "  [OK]   event_reminder #{$row['notification_id']} to {$row['recipient_email']} ({$days}d before)\n";
    } else {
        $totalFailed++;
        snapit_mark_notification_sent($conn, (int)$row['notification_id'], false,
            ($send['error'] ?? 'unknown') . ' — ' . ($send['detail'] ?? 'no detail'), $send['message_id'] ?? '');
        echo "  [FAIL] event_reminder #{$row['notification_id']} to {$row['recipient_email']}: {$send['detail']}\n";
    }
}
mysqli_stmt_close($reminderStmt);

$retryStmt = mysqli_prepare($conn,
    "SELECT notification_id, type, recipient_email, subject, message_body
     FROM notifications
     WHERE status = 'failed'
       AND recipient_email IS NOT NULL
       AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY created_at ASC
     LIMIT 30");
mysqli_stmt_execute($retryStmt);
$retryRes = mysqli_stmt_get_result($retryStmt);
$retriedOk = 0;
$retriedNo = 0;
while ($row = mysqli_fetch_assoc($retryRes)) {
    if (!filter_var($row['recipient_email'], FILTER_VALIDATE_EMAIL)) continue;
    $totalProcessed++;
    $d = snapit_dispatch_notification_row($conn, $row);
    if (!empty($d['ok'])) { $retriedOk++; snapit_mark_notification_sent($conn, (int)$row['notification_id'], true, '', $d['message_id'] ?? ''); echo "  [RETRY OK]  #{$row['notification_id']} {$row['type']} {$row['recipient_email']}\n"; }
    else { $retriedNo++; echo "  [RETRY ×]  #{$row['notification_id']} {$row['type']} {$row['recipient_email']} : {$d['detail']}\n"; }
}
mysqli_stmt_close($retryStmt);

echo "Done. Processed=$totalProcessed sent=$totalSent failed=$totalFailed retriesOK=$retriedOk retriesFail=$retriedNo at " . date('c') . "\n";
