<?php
/**
 * Snap It Account Repair Utility
 *
 * Use this page if you already ran install.php but the default accounts
 * (admin@snapit.ph / admin123, staff@snapit.ph / staff123,
 * customer@snapit.ph / cust123) do not work.
 *
 * It will:
 *   - Verify the database connection & users table exists.
 *   - Run password_verify() on each default account to confirm the issue.
 *   - On POST, RESET the three seeded accounts to valid bcrypt hashes
 *     matching admin123 / staff123 / cust123 (or create them if missing).
 *   - Optionally create a new custom staff/customer account of your choice.
 *
 * DELETE THIS FILE AFTER SUCCESSFUL REPAIR FOR SECURITY.
 */

require_once __DIR__ . '/includes/functions.php';

$messages = [];
$messageType = 'info';

$usersTableExists = false;
$connectionOk = false;
$report = [];

try {
    $cfg = @include __DIR__ . '/includes/config.php';
    if (isset($conn) && $conn instanceof mysqli && !mysqli_connect_error()) {
        $connectionOk = true;
    }
} catch (\Throwable $e) {}

if (!$connectionOk) {
    $messages[] = 'Database configuration not found. Please run install.php first.';
    $messageType = 'danger';
}

if ($connectionOk) {
    $res = @mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if ($res && mysqli_num_rows($res) > 0) {
        $usersTableExists = true;
    } else {
        $messages[] = "The `users` table doesn't exist yet. Please complete the installer first.";
        $messageType = 'danger';
    }
}

if ($usersTableExists) {
    $checkAccounts = [
        ['email' => 'admin@snapit.ph',    'plain' => 'admin123', 'role' => 'admin',    'name' => 'Admin User'],
        ['email' => 'staff@snapit.ph',    'plain' => 'staff123', 'role' => 'staff',    'name' => 'Staff Member'],
        ['email' => 'customer@snapit.ph', 'plain' => 'cust123',  'role' => 'customer', 'name' => 'Customer Demo'],
    ];

    foreach ($checkAccounts as &$acc) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, email, password, status FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $acc['email']);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $acc['exists'] = (bool)$row;
        $acc['status'] = $row['status'] ?? '-';
        $acc['password_matches'] = $row ? password_verify($acc['plain'], $row['password']) : false;
        $acc['new_hash'] = password_hash($acc['plain'], PASSWORD_BCRYPT);
        $report[] = $acc;
    }
    unset($acc);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usersTableExists && isset($_POST['repair']) && $_POST['repair'] === '1') {
    $fixed = 0;
    $created = 0;
    foreach ($checkAccounts as $acc) {
        if ($acc['exists']) {
            $upd = mysqli_prepare($conn, "UPDATE users SET password = ?, status = 'active' WHERE email = ?");
            mysqli_stmt_bind_param($upd, 'ss', $acc['new_hash'], $acc['email']);
            if (mysqli_stmt_execute($upd)) $fixed++;
        } else {
            $ins = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
            mysqli_stmt_bind_param($ins, 'ssss', $acc['name'], $acc['email'], $acc['new_hash'], $acc['role']);
            if (mysqli_stmt_execute($ins)) $created++;
        }
    }

    if (!empty($_POST['new_email']) && !empty($_POST['new_password']) && !empty($_POST['new_name'])) {
        $newName     = trim($_POST['new_name']);
        $newEmail    = trim($_POST['new_email']);
        $newPassword = $_POST['new_password'];
        $newRole     = in_array($_POST['new_role'] ?? 'customer', ['customer','staff','admin'], true) ? $_POST['new_role'] : 'customer';

        if (valid_email($newEmail) && strlen($newPassword) >= 6 && strlen($newName) >= 2) {
            $dup = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($dup, 's', $newEmail);
            mysqli_stmt_execute($dup);
            if (mysqli_num_rows(mysqli_stmt_get_result($dup)) === 0) {
                $h = password_hash($newPassword, PASSWORD_BCRYPT);
                $insCust = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                mysqli_stmt_bind_param($insCust, 'ssss', $newName, $newEmail, $h, $newRole);
                if (mysqli_stmt_execute($insCust)) {
                    $messages[] = "Created new account: $newEmail ($newRole)";
                }
            } else {
                $messages[] = "Skipped creating $newEmail: account already exists.";
            }
        } else {
            $messages[] = 'Could not create custom account: email invalid or password shorter than 6 characters.';
        }
    }

    $messages[] = "Repair complete. Reset $fixed existing account(s) and created $created missing default account(s).";
    $messageType = 'success';

    foreach ($checkAccounts as &$acc) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, email, password, status FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $acc['email']);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $acc['exists'] = (bool)$row;
        $acc['status'] = $row['status'] ?? '-';
        $acc['password_matches'] = $row ? password_verify($acc['plain'], $row['password']) : false;
    }
    unset($acc);
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/alert.php';
?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-lg border-0">
                <div class="card-header text-center py-4" style="background:linear-gradient(135deg,#3a2a1c,#1d140d);color:#fff;border-radius:var(--bs-card-inner-border-radius) var(--bs-card-inner-border-radius) 0 0;">
                    <h2 class="mb-0"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Account Repair Utility</h2>
                    <p class="mb-0 opacity-90 mt-1">Diagnose &amp; fix login issues with the default Snap It accounts</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($messages): ?>
                        <div class="alert alert-<?= e($messageType) ?> mb-4">
                            <?php foreach ($messages as $m): ?><div><?= e($m) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$connectionOk || !$usersTableExists): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading"><i class="fa-solid fa-triangle-exclamation me-1"></i>Cannot proceed</h5>
                            <p class="mb-0">Run the installer first at <a href="<?= e(site_url('install.php')) ?>" class="alert-link">install.php</a>.</p>
                        </div>
                    <?php else: ?>

                        <h4 class="fw-bold mb-3"><i class="fa-solid fa-stethoscope me-1" style="color:var(--snapit-gold)"></i> Diagnostic Report</h4>
                        <div class="table-responsive mb-4">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Role</th>
                                        <th>Exists</th>
                                        <th>Status</th>
                                        <th>Password valid for advertised text?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report as $r): ?>
                                        <tr>
                                            <td><strong><?= e($r['email']) ?></strong></td>
                                            <td><span class="badge badge-snapit"><?= e(ucfirst($r['role'])) ?></span></td>
                                            <td><?= $r['exists'] ? '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Yes</span>' : '<span class="text-danger"><i class="fa-solid fa-circle-xmark"></i> No</span>' ?></td>
                                            <td><span class="badge rounded-pill <?= e(status_badge_class($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                                            <td>
                                                <?php if (!$r['exists']): ?>
                                                    <span class="text-muted">N/A (row missing)</span>
                                                <?php elseif ($r['password_matches']): ?>
                                                    <span class="text-success fw-semibold"><i class="fa-solid fa-check"></i> MATCH — <?= e($r['plain']) ?> works</span>
                                                <?php else: ?>
                                                    <span class="text-danger fw-semibold"><i class="fa-solid fa-xmark"></i> MISMATCH — <?= e($r['plain']) ?> fails (this is the root cause)</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <hr class="my-4">

                        <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>">
                            <input type="hidden" name="repair" value="1">
                            <h4 class="fw-bold mb-3"><i class="fa-solid fa-hammer me-1" style="color:var(--butter)"></i> Apply Repair</h4>

                            <div class="card bg-light border-0 mb-4">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2">What this does:</h6>
                                    <ul class="mb-0 small text-muted">
                                        <li>Resets passwords for <code>admin@snapit.ph</code>, <code>staff@snapit.ph</code>, <code>customer@snapit.ph</code> to valid bcrypt hashes.</li>
                                        <li>Re-creates any of the three rows if they are missing from the <code>users</code> table.</li>
                                        <li>Sets all three accounts to <code>status = 'active'</code>.</li>
                                        <li>Plaintexts after repair: <code>admin123</code>, <code>staff123</code>, <code>cust123</code>.</li>
                                    </ul>
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3">Optional &mdash; Create a brand new account (alternative to the above defaults)</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Full Name</label>
                                    <input type="text" name="new_name" class="form-control" placeholder="e.g. Jane Doe">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Email (will be username)</label>
                                    <input type="email" name="new_email" class="form-control" placeholder="jane@example.com">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold small">Password (≥ 6 chars)</label>
                                    <input type="text" name="new_password" class="form-control" placeholder="myp@ss1">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold small">Role</label>
                                    <select name="new_role" class="form-select">
                                        <option value="customer">Customer</option>
                                        <option value="staff">Staff</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?= e(site_url('index.php')) ?>" class="btn btn-outline-secondary">Cancel &amp; Go Home</a>
                                <button type="submit" class="btn btn-snapit btn-lg">
                                    <i class="fa-solid fa-wrench me-2"></i>Run Repair &amp; Reset Default Accounts
                                </button>
                            </div>
                        </form>

                        <div class="alert alert-warning mt-4 mb-0 small">
                            <i class="fa-solid fa-shield-halved me-1"></i>
                            <strong>Security note:</strong> After you can log in successfully, <strong>delete</strong> this file (<code>repair_accounts.php</code>) and the helper <code>debug_check_hashes.php</code> so they cannot be run by visitors.
                        </div>

                        <div class="mt-4 text-center">
                            <a href="<?= e(site_url('users/login.php')) ?>" class="btn btn-outline-primary">
                                <i class="fa-solid fa-right-to-bracket me-1"></i>Return to the Login Page
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
