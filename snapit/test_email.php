<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';
require_staff();

$phpmailer_available = snapit_mailer_enabled();

$services = ['mailtrap', 'google'];
$results = [];
$form = [
    'to'      => $_POST['to']      ?? '',
    'service' => in_array($_POST['service'] ?? '', $services, true) ? $_POST['service'] : ($MAILER['service'] ?? 'mailtrap'),
    'host'    => $_POST['host']    ?? '',
    'port'    => $_POST['port']    ?? '',
    'user'    => $_POST['user']    ?? '',
    'pass'    => $_POST['pass']    ?? '',
    'secure'  => in_array($_POST['secure'] ?? '', ['tls','ssl','none'], true) ? $_POST['secure'] : 'tls',
    'auth'    => isset($_POST['auth']),
    'from'    => $_POST['from']    ?? ($MAILER['from_email'] ?? ''),
    'name'    => $_POST['name']    ?? ($MAILER['from_name']  ?? ''),
    'debug'   => isset($_POST['debug']),
    'attach'  => isset($_POST['attach']),
];

function snapit_apply_form_override($form) {
    global $MAILER;
    if ($form['host'] === '' && $form['user'] === '' && $form['pass'] === '') return null;
    return [
        'service' => $form['service'],
        'from_email' => $form['from'] ?: ($MAILER['from_email'] ?? ''),
        'from_name'  => $form['name'] ?: ($MAILER['from_name']  ?? ''),
        'svc' => [
            'host'        => $form['host'] ?: ($MAILER[$form['service']]['host'] ?? ''),
            'port'        => $form['port'] !== '' ? (int)$form['port'] : (int)($MAILER[$form['service']]['port'] ?? 2525),
            'username'    => $form['user'] ?: ($MAILER[$form['service']]['username'] ?? ''),
            'password'    => $form['pass'] ?: ($MAILER[$form['service']]['password'] ?? ''),
            'smtp_auth'   => (bool)$form['auth'],
            'smtp_secure' => $form['secure'],
            'timeout'     => 30,
        ],
    ];
}

function snapit_send_with_override($form, $to, $subj, $html, $text, $attachments) {
    global $MAILER;
    $over = snapit_apply_form_override($form);
    $backup = $MAILER;
    if ($over) {
        $MAILER['service']    = $over['service'];
        $MAILER['from_email'] = $over['from_email'];
        $MAILER['from_name']  = $over['from_name'];
        foreach ($over['svc'] as $k => $v) $MAILER[$over['service']][$k] = $v;
    }
    $r = snapit_send_email([
        'to'          => $to,
        'subject'     => $subj,
        'body_html'   => $html,
        'body_text'   => $text,
        'attachments' => $attachments,
        'service'     => null,
        'debug'       => !empty($form['debug']),
    ]);
    $MAILER = $backup;
    return $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['send'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $results[] = ['ok'=>false,'service'=>$form['service'],'error'=>'csrf','detail'=>'Invalid CSRF token. Please refresh.'];
    } elseif ($form['to'] === '' || !filter_var($form['to'], FILTER_VALIDATE_EMAIL)) {
        $results[] = ['ok'=>false,'service'=>$form['service'],'error'=>'to','detail'=>'Please enter a valid recipient email address.'];
    } elseif (!$phpmailer_available) {
        $results[] = ['ok'=>false,'service'=>$form['service'],'error'=>'phpmailer_missing','detail'=>'PHPMailer is not installed. See vendor/README.md: either run "composer install" or copy Exception.php/SMTP.php/PHPMailer.php/OAuth.php into vendor/phpmailer/src/.'];
    } else {
        $bodyHtml = '<h2 style="font-size:17px;color:#6f42c1;margin:0 0 12px">🎉 SMTP configuration test: <span style="color:#e83e8c">' . htmlspecialchars($form['service']) . '</span></h2>'
                 . '<p>If you are reading this email in your inbox, the selected SMTP service is fully configured, authenticated, and reachable from this Snap It installation.</p>'
                 . '<div class="meta" style="background:#faf7ff;border-left:4px solid #fd7e14;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0;font-size:14px">'
                 . '<div><strong>Host:</strong> ' . htmlspecialchars($form['host'] ? $form['host'] : '(using service default)') . '</div>'
                 . '<div><strong>Port:</strong> ' . htmlspecialchars($form['port'] ? $form['port'] : '(default)') . '</div>'
                 . '<div><strong>Auth:</strong> ' . ($form['auth'] ? 'Yes' : 'No') . ' &bull; <strong>Secure:</strong> ' . htmlspecialchars(strtoupper($form['secure'])) . '</div>'
                 . '<div><strong>Sent by:</strong> ' . htmlspecialchars($form['name']) . ' &lt;' . htmlspecialchars($form['from']) . '&gt;</div>'
                 . '<div><strong>Generated:</strong> ' . date('r') . '</div>'
                 . '</div>';
        $bodyText = strip_tags($bodyHtml);
        $attachments = [];
        $tmp = null;
        if (!empty($form['attach'])) {
            $tmp = tempnam(sys_get_temp_dir(), 'snapit_');
            file_put_contents($tmp, "Snap It SMTP test attachment generated at " . date('r') . "\nService: {$form['service']}\n\nIf this file arrived intact, PHPMailer attachment encoding works correctly.\n");
            $attachments[] = ['path' => $tmp, 'name' => 'snapit-smtp-test.txt', 'type' => 'text/plain'];
        }
        $r = snapit_send_with_override(
            $form,
            $form['to'],
            '[Snap It Test] ' . ucfirst($form['service']) . ' SMTP connectivity from ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            snapit_email_wrap_html('SMTP Test — ' . ucfirst($form['service']) . ' service', $bodyHtml),
            $bodyText,
            $attachments
        );
        $results[] = $r;
        if ($tmp && file_exists($tmp)) @unlink($tmp);
    }
}

$current_cfg = snapit_current_mailer_cfg();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/alert.php';
?>
<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="card shadow-lg mb-4">
                <div class="card-header py-4 text-center">
                    <h2 class="mb-0"><i class="fa-solid fa-paper-plane me-2" style="color:#6f42c1"></i> Snap It — SMTP Email Tester</h2>
                    <p class="text-muted mt-2 mb-0">Staff-only utility. Sends real email through Mailtrap or Google SMTP to validate your configuration end-to-end.</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card h-100 border-<?= $phpmailer_available ? 'success' : 'danger' ?> p-3 rounded-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-<?= $phpmailer_available ? 'success' : 'danger' ?> me-2" style="width:14px;height:14px;border-radius:50%;display:inline-block"></span>
                                    <h6 class="mb-0">PHPMailer availability</h6>
                                </div>
                                <p class="small mb-0 mt-1">
                                    <?php if ($phpmailer_available): ?>
                                        ✅ LOADED — <code>PHPMailer</code> class ready.
                                    <?php else: ?>
                                        ❌ MISSING — see vendor/README.md: either run <code>composer install</code> or copy 4 src files into <code>vendor/phpmailer/src/</code>.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 bg-light border-0 p-3">
                                <h6 class="mb-2">Configured defaults (<code><?= e($current_cfg['service_key']) ?></code> service)</h6>
                                <table class="table table-sm small mb-0"><tbody>
                                    <tr><th style="width:34%">Host:Port</th><td><code><?= e($current_cfg['svc']['host']) ?>:<?= e($current_cfg['svc']['port']) ?></code></td></tr>
                                    <tr><th>Auth / Secure</th><td><?= !empty($current_cfg['svc']['smtp_auth']) ? 'SMTPAuth on' : 'no auth' ?> &middot; secure=<code><?= e($current_cfg['svc']['smtp_secure'] ?? 'none') ?></code></td></tr>
                                    <tr><th>From</th><td><?= e($current_cfg['global']['from_name']) ?> &lt;<?= e($current_cfg['global']['from_email']) ?>&gt;</td></tr>
                                </tbody></table>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $r): ?>
                            <div class="card mb-4 border-<?= !empty($r['ok']) ? 'success' : 'danger' ?>">
                                <div class="card-header bg-<?= !empty($r['ok']) ? 'success text-white' : 'danger text-white' ?> small">
                                    <?= !empty($r['ok']) ? '✅ Email accepted by ' . e($r['service'] ?? 'SMTP') : '❌ Failed with ' . e($r['error'] ?? 'unknown') ?>
                                    <?php if (!empty($r['recipients_total'])): ?>
                                        &middot; recipients=<?= (int)$r['recipients_ok'] ?>/<?= (int)$r['recipients_total'] ?>
                                    <?php endif; ?>
                                    <?php if (!empty($r['message_id'])): ?>
                                        &middot; MSG-ID <code class="bg-white text-dark px-1 rounded"><?= htmlspecialchars($r['message_id']) ?></code>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($r['detail'])): ?>
                                        <p class="mb-2"><strong>Detail:</strong><br><span class="font-monospace small"><?= htmlspecialchars($r['detail']) ?></span></p>
                                    <?php endif; ?>
                                    <?php if (!empty($r['smtp_log'])): ?>
                                        <details class="mt-3">
                                            <summary class="fw-semibold small">SMTP debug trace (SMTPDebug=SERVER) &mdash; click to expand</summary>
                                            <pre class="bg-dark text-light rounded p-3 small overflow-auto mt-2" style="max-height:300px"><?php foreach ($r['smtp_log'] as $line) echo htmlspecialchars($line)."\n"; ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" class="row g-3" novalidate>
                        <?= csrf_input() ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Send test email to</label>
                            <input type="email" required name="to" class="form-control form-control-lg" placeholder="you@example.com" value="<?= e($form['to']) ?>">
                            <small class="text-muted">Mailtrap sandbox inbox for Mailtrap tests, or a real Gmail inbox for Google SMTP tests.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">SMTP Service</label>
                            <select name="service" id="fsvc" class="form-select form-select-lg">
                                <option value="mailtrap" <?= $form['service']==='mailtrap' ? 'selected' : '' ?>>Mailtrap (staging / debugging)</option>
                                <option value="google"   <?= $form['service']==='google'   ? 'selected' : '' ?>>Google SMTP (production)</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" name="send" value="1" class="btn btn-snapit btn-lg w-100 py-2">
                                <i class="fa-solid fa-paper-plane me-2"></i>Send Test Email
                            </button>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="card bg-light border-0"><div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="fa-solid fa-sliders me-1" style="color:#6f42c1"></i> Override credentials (optional &mdash; leave blank to use defaults from config.php / env vars)</h6>
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label small">SMTP Host</label>
                                        <input name="host" class="form-control" value="<?= e($form['host']) ?>" placeholder="smtp.mailtrap.io" id="fhost">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label small">Port</label>
                                        <input name="port" class="form-control" value="<?= e($form['port']) ?>" placeholder="2525/587/465" id="fport">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Username</label>
                                        <input name="user" class="form-control" value="<?= e($form['user']) ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Password / App-Password</label>
                                        <input type="password" name="pass" class="form-control" value="<?= e($form['pass']) ?>" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label small">Secure</label>
                                        <select name="secure" class="form-select" id="fsec">
                                            <option value="tls"  <?= $form['secure']==='tls'  ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl"  <?= $form['secure']==='ssl'  ? 'selected' : '' ?>>SSL</option>
                                            <option value="none" <?= $form['secure']==='none' ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="auth" id="chk_auth" <?= $form['auth'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="chk_auth">Use SMTPAuth</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">From email</label>
                                        <input name="from" class="form-control" value="<?= e($form['from']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">From name</label>
                                        <input name="name" class="form-control" value="<?= e($form['name']) ?>">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end gap-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="debug" id="chk_dbg" <?= $form['debug'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="chk_dbg">Show SMTP trace</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="attach" id="chk_att" <?= $form['attach'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="chk_att">Include attachment</label>
                                        </div>
                                    </div>
                                </div>
                            </div></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const svc = document.getElementById('fsvc'), host = document.getElementById('fhost'), port = document.getElementById('fport'), sec = document.getElementById('fsec');
    const MAP = {
        mailtrap: { host:'smtp.mailtrap.io', port:'2525', secure:'tls' },
        google:   { host:'smtp.gmail.com',    port:'587',  secure:'tls' }
    };
    function apply(){
        const m = MAP[svc.value];
        if (!m) return;
        if (!host.value) host.placeholder = m.host;
        if (!port.value) port.placeholder = m.port;
    }
    svc && svc.addEventListener('change', apply);
    apply();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
