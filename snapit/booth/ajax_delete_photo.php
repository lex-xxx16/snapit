<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

header('Content-Type: application/json');

$session_id = current_booth_session_id($conn);
if (!$session_id) {
    echo json_encode(['ok' => false, 'error' => 'No active session']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$photo_id = (int)($_POST['photo_id'] ?? 0);
$purge_file = isset($_POST['purge_file']) && (int)$_POST['purge_file'] === 1;

if ($photo_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT sp.photo_id, sp.session_id, sp.photo_path FROM session_photos sp WHERE sp.photo_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $photo_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row || (int)$row['session_id'] !== $session_id) {
    echo json_encode(['ok' => false, 'error' => 'Photo not found']);
    exit;
}

$photo_path = $row['photo_path'];
$file_abs = null;
$rel_path = photo_rel_path($photo_path);
if ($rel_path && strpos($rel_path, 'uploads/sessions/sess_' . $session_id . '/') === 0 && strpos($rel_path, '..') === false) {
    $file_abs = __DIR__ . '/../' . $rel_path;
}

$del = mysqli_prepare($conn, "DELETE FROM session_photos WHERE photo_id = ?");
mysqli_stmt_bind_param($del, 'i', $photo_id);
$ok = mysqli_stmt_execute($del);

if ($purge_file && $ok && $file_abs && is_file($file_abs)) {
    @unlink($file_abs);
}

echo json_encode(['ok' => $ok]);
