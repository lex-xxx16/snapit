<?php
if (session_status() === PHP_SESSION_NONE) {
    // Only accept session ids this server issued, and keep the cookie away from JavaScript.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Never let the browser keep a copy of a page. After logout, the Back button has to
// ask the server again, and the server will find no logged-in session.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Completely end the current login: wipe every session value (user, role, booth session,
// CSRF token), destroy the session on the server, then start a new empty session with a
// brand-new id so a flash message can still be shown on the login page.
function destroy_login_session() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    session_id(session_create_id());
    session_start();
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function set_flash($message, $type = 'warning') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function has_flash() {
    return isset($_SESSION['flash_message']);
}

function get_flash_message() {
    if (has_flash()) {
        $msg = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'warning';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $msg, 'type' => $type];
    }
    return null;
}

function is_loggedin() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_customer() {
    return is_loggedin() && ($_SESSION['role'] ?? '') === 'customer';
}

function is_staff() {
    return is_loggedin() && in_array(($_SESSION['role'] ?? ''), ['staff', 'admin']);
}

function is_admin() {
    return is_loggedin() && ($_SESSION['role'] ?? '') === 'admin';
}

function require_login() {
    if (!is_loggedin()) {
        set_flash('Please log in to access this page.', 'warning');
        redirect(site_url('users/login.php'));
    }
}

function require_staff() {
    require_login();
    if (!is_staff()) {
        set_flash('Staff access required.', 'danger');
        redirect(site_url('index.php'));
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('Administrator access required.', 'danger');
        redirect(site_url('index.php'));
    }
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function status_badge_class($status) {
    $map = [
        'pending' => 'bg-warning text-dark',
        'confirmed' => 'bg-info text-dark',
        'paid' => 'bg-success',
        'completed' => 'bg-secondary',
        'cancelled' => 'bg-danger',
        'active' => 'bg-primary',
        'inactive' => 'bg-secondary',
        'printed' => 'bg-success',
        'error' => 'bg-danger',
    ];
    return $map[strtolower($status)] ?? 'bg-secondary';
}

function format_money($amount) {
    return 'PHP ' . number_format((float)$amount, 2);
}

function format_date($dateStr, $withTime = false) {
    if (empty($dateStr)) return '-';
    $ts = strtotime($dateStr);
    if ($withTime) return date('M j, Y h:i A', $ts);
    return date('M j, Y', $ts);
}

function is_date_past($dateStr) {
    $today = date('Y-m-d');
    return $dateStr < $today;
}

function time_to_minutes($timeStr) {
    list($h, $m) = explode(':', substr($timeStr, 0, 5));
    return ((int)$h * 60) + (int)$m;
}

function booking_has_conflict($conn, $event_date, $start_time, $duration_hours, $exclude_booking_id = null) {
    $start_min = time_to_minutes($start_time);
    $end_min = $start_min + ((int)$duration_hours * 60);

    $sql = "SELECT booking_id, start_time, duration_hours FROM bookings 
            WHERE event_date = ? AND booking_type = 'event' AND status NOT IN ('cancelled')";
    $params = [$event_date];
    $types = 's';
    if ($exclude_booking_id) {
        $sql .= " AND booking_id <> ?";
        $params[] = $exclude_booking_id;
        $types .= 'i';
    }
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $exist_start = time_to_minutes($row['start_time']);
        $exist_end = $exist_start + ((int)$row['duration_hours'] * 60);
        if ($start_min < $exist_end && $end_min > $exist_start) {
            return true;
        }
    }
    return false;
}

function in_business_hours($start_time, $duration_hours) {
    $open = 8 * 60;
    $close = 22 * 60;
    $start_min = time_to_minutes($start_time);
    $end_min = $start_min + ((int)$duration_hours * 60);
    return $start_min >= $open && $end_min <= $close;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function site_base() {
    static $base = null;
    if ($base !== null) return $base;
    // Works whatever the project folder is called or wherever it is placed:
    // strip (depth of this script below the project root + 1) segments from SCRIPT_NAME.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $root = realpath(dirname(__DIR__));
    $file = !empty($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    if ($root && $file) {
        $root = str_replace('\\', '/', $root);
        $file = str_replace('\\', '/', $file);
        if (stripos($file, $root . '/') === 0) {
            $depth = substr_count(substr($file, strlen($root) + 1), '/');
            $parts = explode('/', $script);
            $parts = array_slice($parts, 0, max(0, count($parts) - 1 - $depth));
            return $base = rtrim(implode('/', $parts), '/');
        }
    }
    return $base = rtrim(dirname($script), '/');
}

// Photo paths used to be stored with the deployment folder baked in (e.g. /Snap_It-MAIN_1/snapit/uploads/...).
// Normalise any stored form to its project-relative part, then rebuild the URL for THIS machine.
function photo_rel_path($stored) {
    $p = str_replace('\\', '/', (string)$stored);
    if (preg_match('#(?:^|/)(uploads/(?:sessions|preset_cache)/[^?\#]+)#', $p, $m)) return $m[1];
    return null;
}
function photo_url($stored) {
    $r = photo_rel_path($stored);
    return $r === null ? (string)$stored : site_url($r);
}

function site_url($path = '') {
    $b = site_base();
    if ($path === '') return $b . '/';
    $path = ltrim($path, '/');
    return $b . '/' . $path;
}

function current_booth_session_id($conn) {
    if (empty($_SESSION['active_session_id'])) return null;
    $sid = $_SESSION['active_session_id'];
    $stmt = mysqli_prepare($conn, "SELECT session_id, session_owner_user_id, booking_id FROM guest_sessions WHERE session_id = ? AND status = 'active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $sid);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($r);
    if (!$row) {
        unset($_SESSION['active_session_id']);
        return null;
    }
    if (!empty($row['session_owner_user_id']) && is_loggedin()) {
        $owner = (int)$row['session_owner_user_id'];
        $me = (int)($_SESSION['user_id'] ?? 0);
        if ($owner > 0 && $me > 0 && $owner !== $me && !is_staff()) {
            $bid = (int)$row['booking_id'];
            $own_stmt = mysqli_prepare($conn, "SELECT user_id FROM bookings WHERE booking_id = ? LIMIT 1");
            mysqli_stmt_bind_param($own_stmt, 'i', $bid);
            mysqli_stmt_execute($own_stmt);
            $own_r = mysqli_fetch_assoc(mysqli_stmt_get_result($own_stmt));
            mysqli_stmt_close($own_stmt);
            $booking_owner = $own_r ? (int)$own_r['user_id'] : 0;
            if ($booking_owner !== $me) {
                unset($_SESSION['active_session_id']);
                return null;
            }
        }
    }
    return $sid;
}

// ---- Real-camera pictures shown on the camera preset cards ----
// Maps a preset name to a Wikimedia Commons file (served through Special:FilePath,
// so no hashed upload paths are needed). Presets not listed fall back to an icon.
// Two-level offline cache: first look at the disk cache (uploads/preset_cache),
// then return the remote URL which will be saved to disk + localStorage on load.
function camera_preset_source_file($preset_name) {
    static $files = [
        'Kodak Gold 200'        => 'Kodak_Gold_200_Film.jpg',
        'Kodak Portra 400'      => 'Kodak_Portra_400_120_film.jpg',
        'Fuji X Classic Chrome' => 'Fujifilm_X100V_9_feb_2020a.jpg',
        'Fuji Superia 400'      => 'Fujifilm_SUPERIA_200.jpg',
        'Canon G7X II'          => 'Canon_PowerShot_G7_X_Mark_II.jpg',
        'iPhone 5S'             => 'IPhone_5s_(model_A1457)-3319.jpg',
        'iPhone 4S'             => 'Black_iPhone_4s.jpg',
        'DV Camcorder'          => 'Sony_Handycam_MiniDV_DCR-VX2000_camcorder.jpg',
        'Disposable Flash'      => 'Kodak_FunSaver_35_(3332226374).jpg',
        'Polaroid Instant'      => 'Polaroid_636_Close_Up_instant_camera.jpg',
        'Y2K Digicam'           => 'Sony_Cyber-shot_DSC-W1.jpg',
        'B&W Film'              => 'ILFORD_HP5.jpg',
    ];
    return $files[$preset_name] ?? null;
}

function camera_photo_url($preset_name, $width = 240) {
    // 1) a downloaded real photo (uploads/preset_cache), 2) bundled local illustration. Never hotlinks.
    $root = dirname(__DIR__);
    $src_file = camera_preset_source_file($preset_name);
    $safe_key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$preset_name);
    foreach (['jpg','jpeg','png','webp'] as $ext) {
        foreach ([(int)$width, 240, 480] as $w) {
            $cached = $safe_key . '_w' . $w . '.' . $ext;
            $full = $root . '/uploads/preset_cache/' . $cached;
            if (is_file($full) && filesize($full) > 100) {
                return site_url('uploads/preset_cache/' . rawurlencode($cached));
            }
        }
    }
    $slug = preg_replace('/[^A-Za-z0-9]+/', '_', (string)$preset_name);
    $slug = trim($slug, '_');
    if ($slug === 'B_W_Film') $slug = 'BW_Film';
    if (is_file($root . '/includes/img/cameras/' . $slug . '.svg')) {
        return site_url('includes/img/cameras/' . $slug . '.svg');
    }
    return site_url('includes/img/cameras/Natural.svg');
}

// ---- Photo strip rendering (print_size "WxH" inches, e.g. 2x6 / 4x6) ----
function strip_cfg($r, $caption = '') {
    return [
        'cols' => (int)($r['grid_cols'] ?? 1), 'rows' => (int)($r['grid_rows'] ?? 1),
        'count' => (int)($r['photo_count'] ?? 1), 'print' => $r['print_size'] ?? '',
        'bc' => $r['border_color'] ?? '#6f42c1', 'bw' => (int)($r['border_width'] ?? 6),
        'bg' => $r['bg_color'] ?? '#ffffff', 'fg' => $r['text_color'] ?? '#6f42c1',
        'gap' => (int)($r['photo_gap'] ?? 10), 'rad' => (int)($r['photo_radius'] ?? 8),
        'pattern' => $r['pattern'] ?? '', 'filter' => $r['css_filter'] ?? 'none', 'caption' => $caption,
    ];
}
function strip_html($photos, $c, $id = '') {
    $w = 520; $ar = '';
    if (preg_match('/^(\d+)x(\d+)$/', $c['print'] ?? '', $m)) { $ar = "aspect-ratio:{$m[1]}/{$m[2]};"; $w = $m[1] <= 2 ? 270 : 400; }
    $bgimg = ($c['pattern'] ?? '') === 'dots' ? 'background-image:radial-gradient(rgba(128,128,128,.25) 1px,transparent 1.6px);background-size:14px 14px;' : '';
    $o = '<div ' . ($id ? 'id="' . e($id) . '" ' : '') . 'class="snap-strip" style="max-width:' . $w . 'px;' . $ar . $bgimg
       . '--bg:' . e($c['bg']) . ';--bc:' . e($c['bc']) . ';--bw:' . (int)$c['bw'] . 'px;--gap:' . (int)$c['gap'] . 'px;--rad:' . (int)$c['rad'] . 'px;--fg:' . e($c['fg']) . ';">';
    $o .= '<div class="snap-grid" style="grid-template-columns:repeat(' . (int)$c['cols'] . ',1fr);grid-template-rows:repeat(' . (int)$c['rows'] . ',1fr);">';
    $n = max((int)$c['count'], count($photos));
    for ($i = 0; $i < $n; $i++) {
        $o .= isset($photos[$i]) ? '<img src="' . e(photo_url($photos[$i])) . '" alt="Photo ' . ($i+1) . '" crossorigin="anonymous" style="filter:' . e($c['filter']) . '">'
                                 : '<div class="snap-slot"><i class="fa-regular fa-image fa-2x"></i></div>';
    }
    return $o . '</div><div class="snap-foot">' . e($c['caption']) . '</div></div>';
}

function booking_is_fully_paid($conn, $booking_id) {
    $booking_id = (int)$booking_id;
    if ($booking_id <= 0) return false;
    $total = 0.0; $paid = 0.0;
    $stmt = mysqli_prepare($conn, "SELECT total_amount, softcopy_addon, estimated_hardcopies FROM bookings WHERE booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $booking_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row) return false;
    $total = (float)$row['total_amount'];
    $stmt2 = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS paid FROM payments WHERE booking_id = ? AND status IN ('completed','captured','refunded_pending')");
    mysqli_stmt_bind_param($stmt2, 'i', $booking_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $r2 = mysqli_fetch_assoc($res2);
    mysqli_stmt_close($stmt2);
    if ($r2) $paid = (float)$r2['paid'];
    if ($total <= 0) return true;
    return ($paid + 0.0001) >= $total;
}

function booking_outstanding_balance($conn, $booking_id) {
    $booking_id = (int)$booking_id;
    if ($booking_id <= 0) return 0.0;
    $total = 0.0; $paid = 0.0;
    $stmt = mysqli_prepare($conn, "SELECT total_amount FROM bookings WHERE booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $booking_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row) return 0.0;
    $total = (float)$row['total_amount'];
    $stmt2 = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS paid FROM payments WHERE booking_id = ? AND status IN ('completed','captured','refunded_pending')");
    mysqli_stmt_bind_param($stmt2, 'i', $booking_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $r2 = mysqli_fetch_assoc($res2);
    mysqli_stmt_close($stmt2);
    if ($r2) $paid = (float)$r2['paid'];
    return max(0.0, $total - $paid);
}

function booking_has_softcopy_access($conn, $booking_id) {
    $booking_id = (int)$booking_id;
    if ($booking_id <= 0) return false;
    $stmt = mysqli_prepare($conn, "SELECT b.softcopy_addon, p.softcopy_count FROM bookings b LEFT JOIN packages p ON p.package_id = b.package_id WHERE b.booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $booking_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row) return false;
    if (!empty($row['softcopy_addon']) && (int)$row['softcopy_addon'] === 1) return true;
    if (isset($row['softcopy_count']) && (int)$row['softcopy_count'] > 0) return true;
    return false;
}

function booking_can_purchase_softcopy_addon($conn, $booking_id) {
    $booking_id = (int)$booking_id;
    if ($booking_id <= 0) return false;
    return !booking_has_softcopy_access($conn, $booking_id);
}

function is_staff_viewing_gallery() {
    if (!is_loggedin()) return false;
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'], true)) return false;
    if (basename($_SERVER['SCRIPT_NAME']) === 'view_booking.php' && !empty($_GET['staff_view'])) return true;
    if (basename($_SERVER['SCRIPT_NAME']) === 'index.php' && !empty($_GET['staff'])) return true;
    return false;
}


/* ---------------------------------------------------------------------
   Profile pictures (uploads/avatars/)
   --------------------------------------------------------------------- */
function avatar_dir() {
    return __DIR__ . '/../uploads/avatars';
}

/** Public URL of a stored avatar filename, or null when none. */
function avatar_url($file) {
    $file = basename((string)$file);
    if ($file === '' || !preg_match('/^[A-Za-z0-9_\-]+\.(jpg|png|webp)$/', $file)) return null;
    return site_url('uploads/avatars/' . $file);
}

/** Remove a stored avatar file from disk (filename only, never a path). */
function avatar_delete_file($file) {
    $file = basename((string)$file);
    if ($file === '' || !preg_match('/^[A-Za-z0-9_\-]+\.(jpg|png|webp)$/', $file)) return;
    $path = avatar_dir() . '/' . $file;
    if (is_file($path)) @unlink($path);
}

/**
 * Validate and store an uploaded profile picture.
 * Returns [filename|null, error|null]. The image is decoded and re-encoded
 * (square-cropped to 400x400) so nothing but pixel data is ever stored.
 */
function avatar_save_upload($file, $user_id) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, 'Please choose an image to upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'That image is too large (2 MB maximum).' : 'Upload failed. Please try again.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) return [null, 'That image is too large (2 MB maximum).'];
    if (!is_uploaded_file($file['tmp_name'])) return [null, 'Upload failed. Please try again.'];

    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) return [null, 'Only JPG, PNG or WEBP images are allowed.'];
    if ($info[0] < 32 || $info[1] < 32 || $info[0] > 6000 || $info[1] > 6000) {
        return [null, 'Image dimensions are not supported (min 32px, max 6000px).'];
    }
    if (!function_exists('imagecreatefromstring')) {
        // GD not enabled: store the already-validated image as-is (extension comes from the
        // detected type, never from the client; uploads/ blocks script execution).
        $ext = $types[$info[2]];
        $dir = avatar_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [null, 'Could not save the image (upload folder not writable).'];
        $name = (int)$user_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return [null, 'Could not save the image. Please try again.'];
        return [$name, null];
    }

    $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
    if (!$src) return [null, 'That file could not be read as an image.'];

    $w = imagesx($src); $h = imagesy($src);
    $side = min($w, $h);
    $dst = imagecreatetruecolor(400, 400);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), 400, 400, $side, $side);

    $ext = ($info[2] === IMAGETYPE_JPEG) ? 'jpg' : 'png';   // transparency-safe output; webp -> png
    $dir = avatar_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [null, 'Could not save the image (upload folder not writable).'];
    $name = (int)$user_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $ok = ($ext === 'jpg')
        ? (function () use ($dst, $dir, $name) { // flatten onto white for JPEG
            $flat = imagecreatetruecolor(400, 400);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $dst, 0, 0, 0, 0, 400, 400);
            return imagejpeg($flat, $dir . '/' . $name, 88);
        })()
        : imagepng($dst, $dir . '/' . $name, 6);
    if (!$ok) return [null, 'Could not save the image. Please try again.'];
    return [$name, null];
}

/** Avatar filename of the logged-in user (cached in the session). */
function current_user_avatar($conn) {
    if (!is_loggedin()) return null;
    if (!array_key_exists('avatar', $_SESSION)) {
        $_SESSION['avatar'] = null;
        try {
            $s = mysqli_prepare($conn, "SELECT avatar FROM users WHERE user_id = ? LIMIT 1");
            $uid = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param($s, 'i', $uid);
            mysqli_stmt_execute($s);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
            $_SESSION['avatar'] = $row['avatar'] ?? null;
        } catch (Throwable $ex) { /* column not yet created: no avatar */ }
    }
    return $_SESSION['avatar'];
}
