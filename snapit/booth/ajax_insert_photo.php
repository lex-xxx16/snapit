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

$sid = (int)($_POST['session_id'] ?? 0);
$image_data = trim($_POST['image_data'] ?? '');
$order_index = (int)($_POST['order_index'] ?? 0);

if ($sid !== $session_id || empty($image_data)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

if (strpos($image_data, 'data:image/') !== 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid image data format']);
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

$upload_root = __DIR__ . '/../uploads/sessions';
$sess_dir = $upload_root . '/sess_' . $session_id;
if (!is_dir($sess_dir)) {
    if (!@mkdir($sess_dir, 0755, true)) {
        echo json_encode(['ok' => false, 'error' => 'Could not create session upload directory']);
        exit;
    }
}

$token = bin2hex(random_bytes(6));
$filename = 'photo_' . $order_index . '_' . $token . '.' . $extension;
$absolute_path = $sess_dir . '/' . $filename;

$written = @file_put_contents($absolute_path, $decoded);
if ($written === false) {
    echo json_encode(['ok' => false, 'error' => 'Could not write photo file to disk']);
    exit;
}

$relative_web_path = 'uploads/sessions/sess_' . $session_id . '/' . $filename; // stored project-relative (portable)

$stmt = mysqli_prepare($conn, "INSERT INTO session_photos (session_id, photo_path, order_index, is_kept, created_at) VALUES (?, ?, ?, 1, NOW())");
mysqli_stmt_bind_param($stmt, 'isi', $session_id, $relative_web_path, $order_index);
if (!mysqli_stmt_execute($stmt)) {
    @unlink($absolute_path);
    echo json_encode(['ok' => false, 'error' => 'Database insert failed']);
    exit;
}

echo json_encode([
    'ok' => true,
    'photo_id' => mysqli_insert_id($conn),
    'photo_path' => photo_url($relative_web_path)
]);
