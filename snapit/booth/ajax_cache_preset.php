<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
    exit;
}

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Auth required']);
    exit;
}

$preset_name = trim($_POST['preset_name'] ?? '');
$width = (int)($_POST['width'] ?? 240);
$data_url  = trim($_POST['data_url'] ?? '');

$src_file = camera_preset_source_file($preset_name);
if ($src_file === null) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Unknown preset']);
    exit;
}

$preset_cache_dir = dirname(__DIR__) . '/uploads/preset_cache';
if (!is_dir($preset_cache_dir)) {
    @mkdir($preset_cache_dir, 0755, true);
}
$safe_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $src_file);
$safe_key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$preset_name);
$width_tag = 'w' . (int)$width;
$ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
$cached_filename = $safe_key . '_' . $width_tag . '.' . $ext;
$cached_full = $preset_cache_dir . '/' . $cached_filename;

if (is_file($cached_full) && filesize($cached_full) > 100) {
    echo json_encode(['ok'=>true,'cached'=>true,'url'=>camera_photo_url($preset_name, $width)]);
    exit;
}

$raw = '';
if (!empty($data_url) && preg_match('/^data:image\\/(png|jpeg|jpg|webp);base64,(.+)$/i', $data_url, $m)) {
    $raw = base64_decode($m[2], true);
    if ($raw === false || strlen($raw) < 100) {
        $raw = '';
    }
}

if ($raw === '' && function_exists('curl_init')) {
    $remote = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($src_file) . '?width=' . (int)$width;
    $ch = curl_init($remote);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SnapItPhotoBooth/1.0 (local preset cache warm)');
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !is_string($raw) || strlen($raw) < 100) {
        $raw = '';
    }
}

if ($raw === '' && ini_get('allow_url_fopen')) {
    $remote = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($src_file) . '?width=' . (int)$width;
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'SnapItPhotoBooth/1.0']]);
    $raw = @file_get_contents($remote, false, $ctx);
    if (!is_string($raw) || strlen($raw) < 100) {
        $raw = '';
    }
}

if ($raw === '') {
    http_response_code(502);
    echo json_encode(['ok'=>false,'error'=>'Unable to fetch preset image']);
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'snapit_preset_');
if ($tmp === false || file_put_contents($tmp, $raw) === false) {
    @unlink($tmp);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Write failed (temp)']);
    exit;
}
if (!@rename($tmp, $cached_full)) {
    @unlink($tmp);
    if (file_put_contents($cached_full, $raw) === false) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Write failed (final)']);
        exit;
    }
}
@chmod($cached_full, 0644);

echo json_encode(['ok'=>true,'cached'=>false,'size'=>filesize($cached_full),'url'=>camera_photo_url($preset_name, $width)]);
