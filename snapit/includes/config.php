<?php

// ---- PHP runtime hardening ----
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

// Same timezone and error behaviour on every machine (override with SNAPIT_TZ).
@date_default_timezone_set(getenv('SNAPIT_TZ') ?: 'Asia/Manila');
// PHP < 8.1 does not throw on mysqli errors by default; the code expects exceptions.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$snapitRoot = dirname(__DIR__);
$logsDir = $snapitRoot . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$errorLog = $logsDir . DIRECTORY_SEPARATOR . 'php_errors.log';
@ini_set('error_log', $errorLog);

// Static error page: NEVER redirects, so a failure can no longer cause a redirect loop.
if (!function_exists('snapit_static_error')) {
function snapit_static_error($title, array $lines, $code = 500) {
    if (!headers_sent()) { http_response_code($code); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title></head>'
       . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#fafafa;color:#333;padding:48px;max-width:760px;margin:auto;">'
       . '<h2>' . htmlspecialchars($title) . '</h2>';
    foreach ($lines as $l) echo '<p>' . htmlspecialchars($l) . '</p>';
    echo '<p style="color:#777;font-size:.9em">Details are in <code>logs/php_errors.log</code>. Run <code>check.php</code> for a full diagnosis.</p></body></html>';
}
}

set_exception_handler(function (Throwable $e) {
    @error_log('[uncaught] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    // Redirect once only; if we already bounced here, show a static page instead of looping.
    if (headers_sent() || isset($_GET['_err'])) {
        snapit_static_error('Something went wrong.', ['Please try again in a few moments.']);
    } else {
        require_once __DIR__ . '/functions.php';
        set_flash('Something went wrong while loading this page. Please try again, or contact support if the problem persists.', 'danger');
        header('Location: ' . site_url('index.php') . '?_err=1');
    }
    exit(1);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    @error_log('[php:' . $severity . '] ' . $message . ' in ' . $file . ':' . $line);
    return false;
});

// ---- Database connection ----
// Defaults -> includes/config.local.php (written by install.php, per machine) -> environment variables.
$db_host = '127.0.0.1';   // 127.0.0.1 avoids the slow IPv6 "localhost" lookup on Windows/XAMPP
$db_port = 3306;
$db_username = 'root';
$db_passwd = '';
$db_name = 'db_snapit';
if (is_file(__DIR__ . '/config.local.php')) { include __DIR__ . '/config.local.php'; }
foreach (['host' => 'db_host', 'port' => 'db_port', 'user' => 'db_username', 'pass' => 'db_passwd', 'name' => 'db_name'] as $k => $v) {
    $envv = getenv('SNAPIT_DB_' . strtoupper($k));
    if ($envv !== false && $envv !== '') $$v = $envv;
}

$__onInstaller = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php';
$conn = null;
try {
    $conn = mysqli_connect($db_host, $db_username, $db_passwd, $db_name, (int)$db_port);
} catch (Throwable $__ex) {
    $__no = (int)$__ex->getCode();
    @error_log('[db] connect failed (' . $__no . '): ' . $__ex->getMessage());
    require_once __DIR__ . '/functions.php';
    if ($__no === 1049 && !$__onInstaller) {            // database does not exist yet -> first run
        header('Location: ' . site_url('install.php'));
    } elseif ($__no === 1045 || $__no === 1698) {
        snapit_static_error('Database login was refused.', ["MySQL rejected the username/password. Edit includes/config.local.php (or re-run install.php after deleting includes/installed.lock)."], 503);
    } elseif ($__no === 2002 || $__no === 2006 || $__no === 2003) {
        snapit_static_error('Cannot reach the MySQL server.', ["Start MySQL/MariaDB (XAMPP Control Panel -> MySQL -> Start) and check the port. Tried {$db_host}:{$db_port}. If MySQL runs on another port (often 3307), set it in includes/config.local.php."], 503);
    } else {
        snapit_static_error('Database is temporarily unavailable.', ['MySQL error ' . $__no . '. Please check the log.'], 503);
    }
    exit(1);
}

mysqli_set_charset($conn, "utf8mb4");
try { mysqli_query($conn, "SET time_zone = '" . date('P') . "'"); } catch (Throwable $__ex) {}

// ---- Mailer settings (placeholders; no SMTP code is wired up yet) ----
$smtp_service    = getenv('SNAPIT_SMTP_SERVICE')   ?: 'mailtrap';
$smtp_host       = getenv('SNAPIT_SMTP_HOST')      ?: 'sandbox.smtp.mailtrap.io';
$smtp_port       = (int)(getenv('SNAPIT_SMTP_PORT') ?: 2525);
$smtp_user       = getenv('SNAPIT_SMTP_USER')      ?: '';
$smtp_pass       = getenv('SNAPIT_SMTP_PASS')      ?: '';
$smtp_secure     = getenv('SNAPIT_SMTP_SECURE')    ?: 'tls';
$smtp_auth       = getenv('SNAPIT_SMTP_AUTH') === '' ? true : (bool)getenv('SNAPIT_SMTP_AUTH');
if ($smtp_service === 'google') {
    if (!getenv('SNAPIT_SMTP_HOST'))   $smtp_host = 'smtp.gmail.com';
    if (!getenv('SNAPIT_SMTP_PORT'))   $smtp_port = 587;
    if (!getenv('SNAPIT_SMTP_SECURE')) $smtp_secure = 'tls';
}
$mail_from_email = getenv('SNAPIT_MAIL_FROM_EMAIL') ?: 'no-reply@snapit.ph';
$mail_from_name  = getenv('SNAPIT_MAIL_FROM_NAME')  ?: 'Snap It Photo Booth';
$mail_reply_to   = getenv('SNAPIT_MAIL_REPLY_TO')   ?: 'support@snapit.ph';

// ---- First-run detection + self-healing schema ----
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (empty($_SESSION['schema_ok_v4'])) {
    $__t = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if ($__t && mysqli_num_rows($__t) === 0 && !$__onInstaller) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . site_url('install.php'));
        exit;
    }
    $__ensure = [
        ['payments', 'status', "ALTER TABLE payments ADD COLUMN status ENUM('pending','completed','captured','failed','refunded_pending') NOT NULL DEFAULT 'completed' AFTER payment_date"],
        ['payments', 'gateway_reference', "ALTER TABLE payments ADD COLUMN gateway_reference VARCHAR(255) NULL AFTER or_number"],
        ['guest_sessions', 'countdown_seconds', "ALTER TABLE guest_sessions ADD COLUMN countdown_seconds TINYINT NOT NULL DEFAULT 3"],
        ['guest_sessions', 'mirror_preview', "ALTER TABLE guest_sessions ADD COLUMN mirror_preview TINYINT(1) NOT NULL DEFAULT 1"],
        ['guest_sessions', 'session_owner_user_id', "ALTER TABLE guest_sessions ADD COLUMN session_owner_user_id INT NULL AFTER guest_name, ADD INDEX idx_gs_owner (session_owner_user_id)"],
        ['users', 'avatar', "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER status"],
        ['bookings', 'inventory_deducted', "ALTER TABLE bookings ADD COLUMN inventory_deducted TINYINT(1) NOT NULL DEFAULT 0"],
    ];
    foreach ($__ensure as $__e) {
        try {
            $__r = mysqli_query($conn, "SHOW COLUMNS FROM `{$__e[0]}` LIKE '{$__e[1]}'");
            if ($__r && mysqli_num_rows($__r) === 0) { mysqli_query($conn, $__e[2]); }
        } catch (Throwable $__ex) { @error_log('[schema] ' . $__ex->getMessage()); }
    }
    $_SESSION['schema_ok_v4'] = 1;
}
