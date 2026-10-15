<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$sid = (int)($_GET['sid'] ?? 0);
if ($sid > 0 && isset($_SESSION['active_session_id']) && (int)$_SESSION['active_session_id'] === $sid) {
    mysqli_query($conn, "UPDATE guest_sessions SET idle_timeout_at = NOW() + INTERVAL 3 MINUTE WHERE session_id = " . (int)$sid . " AND status = 'active'");
}

echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
