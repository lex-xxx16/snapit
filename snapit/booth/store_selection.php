<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$session_id = current_booth_session_id($conn);
if (!$session_id) {
    set_flash('No active session found. Start a new session.', 'warning');
    redirect(site_url('booth/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('booth/capture.php'));
}

if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    set_flash('Invalid security token. Please refresh the page and try again.', 'danger');
    redirect(site_url('booth/capture.php'));
}

$preset_id = (int)($_POST['preset_id_val'] ?? $_POST['preset_id'] ?? 0);
$filter_id = (int)($_POST['filter_id_val'] ?? $_POST['filter_id'] ?? 0);
$layout_id = (int)($_POST['layout_id_val'] ?? $_POST['layout_id'] ?? 0);
$design_id = (int)($_POST['design_id_val'] ?? $_POST['design_id'] ?? 0);

if ($preset_id <= 0 || $filter_id <= 0 || $layout_id <= 0 || $design_id <= 0) {
    set_flash('Please make a selection in each category before continuing.', 'warning');
    redirect(site_url('booth/capture.php'));
}

$stmt = mysqli_prepare($conn, "UPDATE guest_sessions SET camera_preset_id = ?, filter_id = ?, layout_id = ?, frame_design_id = ? WHERE session_id = ?");
mysqli_stmt_bind_param($stmt, 'iiiii', $preset_id, $filter_id, $layout_id, $design_id, $session_id);
mysqli_stmt_execute($stmt);

$_SESSION['capture_index'] = 0;
$_SESSION['captured'] = [];

set_flash('Selections saved. Start capturing your photos!', 'success');
redirect(site_url('booth/session.php'));
