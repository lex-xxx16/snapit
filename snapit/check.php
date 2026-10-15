<?php
// Snap It environment check. Open http://localhost/<folder>/check.php and send a screenshot to your group.
// Only answers on the local machine. Delete this file before any public deployment.
$ra = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ra, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('check.php only works from the same computer.'); }
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: text/html; charset=utf-8');
$rows = [];
function row($label, $ok, $detail = '') { global $rows; $rows[] = [$label, $ok, $detail]; }

row('PHP version >= 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION);
foreach (['mysqli' => true, 'curl' => false, 'mbstring' => false, 'gd' => false] as $x => $req)
    row("Extension: $x" . ($req ? '' : ' (optional)'), extension_loaded($x) ? true : ($req ? false : null));
foreach (['uploads/sessions', 'uploads/preset_cache', 'logs', 'includes'] as $d)
    row("Writable: $d", is_dir(__DIR__ . "/$d") && is_writable(__DIR__ . "/$d"), __DIR__ . "/$d");
row('includes/config.local.php present', is_file(__DIR__ . '/includes/config.local.php'), 'created by install.php');

// DB settings (same precedence as config.php)
$db_host = '127.0.0.1'; $db_port = 3306; $db_username = 'root'; $db_passwd = ''; $db_name = 'db_snapit';
if (is_file(__DIR__ . '/includes/config.local.php')) include __DIR__ . '/includes/config.local.php';
$c = @mysqli_connect($db_host, $db_username, $db_passwd, null, (int)$db_port);
row("MySQL server reachable at $db_host:$db_port", (bool)$c, $c ? mysqli_get_server_info($c) : mysqli_connect_errno() . ' ' . mysqli_connect_error());
if ($c) {
    $has = mysqli_select_db($c, $db_name);
    row("Database '$db_name' exists", $has, $has ? '' : 'run install.php');
    if ($has) {
        foreach (['users','packages','bookings','payments','guest_sessions','session_photos','layouts','frame_designs','camera_presets'] as $t) {
            $r = mysqli_query($c, "SHOW TABLES LIKE '$t'"); row("Table: $t", $r && mysqli_num_rows($r) > 0);
        }
        foreach ([['guest_sessions','session_owner_user_id'],['payments','gateway_reference'],['guest_sessions','countdown_seconds']] as [$t,$col]) {
            $r = mysqli_query($c, "SHOW COLUMNS FROM `$t` LIKE '$col'"); row("Column: $t.$col", $r && mysqli_num_rows($r) > 0, 'auto-added on first page load if missing');
        }
    }
}
$host = $_SERVER['HTTP_HOST'] ?? ''; $h = preg_replace('/:\d+$/', '', $host);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || in_array($h, ['localhost', '127.0.0.1', '[::1]'], true);
row('Camera allowed (needs localhost or HTTPS)', $secure, "You are using: $host");
row('Internet for CDN (Bootstrap/FontAwesome/html2canvas)', null, 'Needed in the browser; pages look unstyled offline');
$log = __DIR__ . '/logs/php_errors.log';
?><!doctype html><meta charset="utf-8"><title>Snap It check</title>
<body style="font-family:Segoe UI,Arial,sans-serif;max-width:860px;margin:30px auto">
<h2>Snap It environment check</h2>
<table cellpadding="6" style="border-collapse:collapse;width:100%">
<?php foreach ($rows as [$l,$ok,$d]): ?>
<tr style="border-bottom:1px solid #ddd"><td><?= $ok === true ? '&#9989;' : ($ok === false ? '&#10060;' : '&#9888;&#65039;') ?></td>
<td><?= htmlspecialchars($l) ?></td><td style="color:#666"><?= htmlspecialchars((string)$d) ?></td></tr>
<?php endforeach; ?></table>
<h3>Last 25 lines of logs/php_errors.log</h3>
<pre style="background:#f4f4f4;padding:10px;overflow:auto"><?= is_file($log) ? htmlspecialchars(implode('', array_slice(file($log), -25))) : '(no log yet - good)' ?></pre>
