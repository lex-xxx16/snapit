<?php
require_once __DIR__ . '/includes/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$lockFile = __DIR__ . '/includes/installed.lock';
$message = '';
$messageType = 'info';

// Prerequisite checks shown on the page
$checks = [
    ['PHP 7.4 or newer (found ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '7.4.0', '>='), true],
    ['mysqli extension', extension_loaded('mysqli'), true],
    ['uploads/ folder writable', is_writable(__DIR__ . '/uploads') || is_writable(__DIR__ . '/uploads/sessions'), true],
    ['logs/ folder writable', is_dir(__DIR__ . '/logs') ? is_writable(__DIR__ . '/logs') : is_writable(__DIR__), false],
    ['includes/ folder writable (to save config.local.php)', is_writable(__DIR__ . '/includes'), true],
    ['curl extension (only for cache_presets.php)', extension_loaded('curl'), false],
];
$blocked = false;
foreach ($checks as $c) { if ($c[2] && !$c[1]) $blocked = true; }

if (is_file($lockFile)) {
    $message = 'Already installed. To run the installer again, delete includes/installed.lock first.';
    $messageType = 'warning';
    $blocked = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = (int)($_POST['db_port'] ?? 3306) ?: 3306;
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = trim($_POST['db_name'] ?? 'db_snapit');

    try {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_name)) {
            throw new RuntimeException('Database name may only contain letters, numbers and underscores.');
        }
        $c = mysqli_connect($db_host, $db_user, $db_pass, null, $db_port);
        mysqli_query($c, "CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        mysqli_select_db($c, $db_name);
        mysqli_set_charset($c, 'utf8mb4');

        $run = function ($file) use ($c) {
            $sql = str_replace("\r\n", "\n", file_get_contents($file));
            if (mysqli_multi_query($c, $sql)) {
                do { if ($r = mysqli_store_result($c)) mysqli_free_result($r); } while (mysqli_more_results($c) && mysqli_next_result($c));
            }
        };
        $run(__DIR__ . '/includes/schema.sql');
        $cnt = mysqli_fetch_row(mysqli_query($c, 'SELECT COUNT(*) FROM users'))[0];
        if ((int)$cnt === 0) { $run(__DIR__ . '/includes/seed.sql'); }

        // Machine-specific settings go to config.local.php; config.php is never overwritten.
        $local = "<?php\n// Written by install.php on " . date('c') . " - machine-specific, do not commit/share.\n"
               . '$db_host = ' . var_export($db_host, true) . ";\n"
               . '$db_port = ' . (int)$db_port . ";\n"
               . '$db_username = ' . var_export($db_user, true) . ";\n"
               . '$db_passwd = ' . var_export($db_pass, true) . ";\n"
               . '$db_name = ' . var_export($db_name, true) . ";\n";
        if (@file_put_contents(__DIR__ . '/includes/config.local.php', $local, LOCK_EX) === false) {
            throw new RuntimeException('Could not write includes/config.local.php (folder not writable). Create it manually with:' . "\n" . $local);
        }
        @file_put_contents($lockFile, date('c'));
        set_flash('Installation completed successfully. You can now log in.', 'success');
        redirect(site_url('users/login.php'));
    } catch (Throwable $ex) {
        @error_log('[install] ' . $ex->getMessage());
        $hint = ((int)$ex->getCode() === 2002 || (int)$ex->getCode() === 2003)
            ? ' Is MySQL started? (XAMPP Control Panel -> MySQL -> Start). Check the port.' : '';
        $message = 'Install failed: ' . $ex->getMessage() . $hint;
        $messageType = 'danger';
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/alert.php';
?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-lg">
                <div class="card-header text-center py-4">
                    <h2 class="mb-0">
                        <svg viewBox="0 0 32 26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:1.4em;height:1.1em;vertical-align:-.12em;margin-right:10px;color:var(--snapit-gold)"><path d="M10.6 5 12.3 2.4h7.4L21.4 5h5.1A3.5 3.5 0 0 1 30 8.5v12a3.5 3.5 0 0 1-3.5 3.5h-21A3.5 3.5 0 0 1 2 20.5v-12A3.5 3.5 0 0 1 5.5 5h5.1Z"/><circle cx="16" cy="14.2" r="5.4"/><circle cx="16" cy="14.2" r="2.1" fill="currentColor" stroke="none"/><circle cx="25.4" cy="9.4" r="1" fill="currentColor" stroke="none"/></svg>
                        Snap It &mdash; Installer
                    </h2>
                    <p class="text-muted mb-0 mt-2">Photo Customization Booth and Rental System</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>

                    <ul class="list-unstyled small mb-4">
                        <?php foreach ($checks as $c): ?>
                            <li><?= $c[1] ? '&#9989;' : ($c[2] ? '&#10060;' : '&#9888;&#65039;') ?> <?= e($c[0]) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="step-indicator mb-4">
                        <div class="step active">1</div>
                        <div class="step">2</div>
                        <div class="step">3</div>
                    </div>
                    <p class="text-center text-muted mb-4">Step 1 &mdash; Configure Database Connection</p>

                    <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Host</label>
                            <input type="text" name="db_host" class="form-control form-control-lg" value="127.0.0.1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Port</label>
                            <input type="number" name="db_port" class="form-control form-control-lg" value="3306" required>
                            <small class="text-muted">XAMPP default is 3306; use 3307 if your MySQL runs there.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Username</label>
                            <input type="text" name="db_user" class="form-control form-control-lg" value="root" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database Password</label>
                            <input type="password" name="db_pass" class="form-control form-control-lg">
                            <small class="text-muted">Leave blank for default XAMPP/WAMP local server.</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Database Name</label>
                            <input type="text" name="db_name" class="form-control form-control-lg" value="db_snapit" required>
                            <small class="text-muted">Will be created if it does not exist.</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-snapit btn-lg py-3" <?= $blocked ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-database me-2"></i>Install &amp; Seed Database
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <h6 class="fw-bold"><i class="fa-solid fa-info-circle me-1" style="color:var(--snapit-gold)"></i> Default accounts after install:</h6>
                            <ul class="mb-0 small">
                                <li><strong>Admin:</strong> admin@snapit.ph / <code>admin123</code></li>
                                <li><strong>Staff:</strong> staff@snapit.ph / <code>staff123</code></li>
                                <li><strong>Customer:</strong> customer@snapit.ph / <code>cust123</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
