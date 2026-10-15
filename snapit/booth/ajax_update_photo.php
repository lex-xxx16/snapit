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
$image_data = trim($_POST['image_data'] ?? '');

if ($photo_id <= 0 || empty($image_data)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

if (strpos($image_data, 'data:image/') !== 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid image data format']);
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

$comma_pos = strpos($image_data, ',');
if ($comma_pos === false) {
    echo json_encode(['ok' => false, 'error' => 'Malformed data URL']);
    exit;
}

$meta = substr($image_data, 0, $comma_pos);
$b64 = substr($image_data, $comma_pos + 1);

$extension = 'png';
if (stripos($meta, 'image/jpeg') !== false || stripos($meta, 'image/jpg') !== false) {
    $extension = 'jpg';
} elseif (stripos($meta, 'image/webp') !== false) {
    $extension = 'webp';
}

$decoded = base64_decode(str_replace(' ', '+', $b64), true);
if ($decoded === false || strlen($decoded) < 50) {
    echo json_encode(['ok' => false, 'error' => 'Image decode failed']);
    exit;
}

$old_path = $row['photo_path'];
$old_file_abs = null;
$old_rel = photo_rel_path($old_path);
if ($old_rel && strpos($old_rel, 'uploads/sessions/sess_' . $session_id . '/') === 0 && strpos($old_rel, '..') === false) {
    $old_file_abs = __DIR__ . '/../' . $old_rel;
}

$upload_root = __DIR__ . '/../uploads/sessions';
$sess_dir = $upload_root . '/sess_' . $session_id;
if (!is_dir($sess_dir)) {
    @mkdir($sess_dir, 0755, true);
}

$token = bin2hex(random_bytes(6));
$order_idx = 0;
$res = mysqli_query($conn, "SELECT order_index FROM session_photos WHERE photo_id = " . (int)$photo_id . " LIMIT 1");
if ($res && ($r = mysqli_fetch_assoc($res))) {
    $order_idx = (int)$r['order_index'];
}
$filename = 'photo_' . $order_idx . '_r' . $token . '.' . $extension;
$absolute_path = $sess_dir . '/' . $filename;

$written = @file_put_contents($absolute_path, $decoded);
if ($written === false) {
    echo json_encode(['ok' => false, 'error' => 'Could not write photo file to disk']);
    exit;
}

$relative_web_path = 'uploads/sessions/sess_' . $session_id . '/' . $filename;

$upd = mysqli_prepare($conn, "UPDATE session_photos SET photo_path = ? WHERE photo_id = ?");
mysqli_stmt_bind_param($upd, 'si', $relative_web_path, $photo_id);
if (!mysqli_stmt_execute($upd)) {
    @unlink($absolute_path);
    echo json_encode(['ok' => false, 'error' => 'Database update failed']);
    exit;
}

if ($old_file_abs && $old_file_abs !== $absolute_path && is_file($old_file_abs)) {
    @unlink($old_file_abs);
}

echo json_encode([
    'ok' => true,
    'photo_id' => $photo_id,
    'photo_path' => photo_url($relative_web_path)
]);
