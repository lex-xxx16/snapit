<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($email)) $errors[] = 'Email is required.';
        elseif (!valid_email($email)) $errors[] = 'Invalid email format.';
        if (empty($password)) $errors[] = 'Password is required.';
        elseif (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['staff', 'admin'])) $errors[] = 'Invalid role.';

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $check = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($check) > 0) {
                $errors[] = 'Email is already registered.';
            }
        }

        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $status = 'active';
            $stmt = mysqli_prepare($conn, "
                INSERT INTO users (name, email, password, role, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hashed, $role, $status);
            if (mysqli_stmt_execute($stmt)) {
                set_flash("Staff account for {$name} created successfully.", 'success');
                redirect(site_url('admin/staff.php'));
            } else {
                $errors[] = 'Failed to create staff account: ' . mysqli_error($conn);
            }
        }

        if (!empty($errors)) {
            set_flash(implode(' ', $errors), 'danger');
        }
    }

    elseif ($action === 'update_role' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? 'staff';
        if (!in_array($role, ['staff', 'admin'])) {
            set_flash('Invalid role selected.', 'danger');
        } elseif ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
            set_flash('You cannot change your own role.', 'danger');
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE user_id = ? AND role IN ('staff','admin')");
            mysqli_stmt_bind_param($stmt, 'si', $role, $user_id);
            mysqli_stmt_execute($stmt);
            set_flash('Staff role updated.', 'success');
        }
        redirect(site_url('admin/staff.php'));
    }

    elseif ($action === 'toggle_status' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        if ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
            set_flash('You cannot change your own status.', 'danger');
        } else {
            $stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE user_id = ? AND role IN ('staff','admin') LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            if ($user) {
                $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
                $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ? AND role IN ('staff','admin')");
                mysqli_stmt_bind_param($stmt, 'si', $new_status, $user_id);
                mysqli_stmt_execute($stmt);
                set_flash("Staff status updated to {$new_status}.", 'success');
            } else {
                set_flash('Staff member not found.', 'danger');
            }
        }
        redirect(site_url('admin/staff.php'));
    }
}

$stmt = mysqli_prepare($conn, "
    SELECT user_id, name, email, role, status, created_at
    FROM users
    WHERE role IN ('staff','admin')
    ORDER BY role DESC, created_at ASC
");
mysqli_stmt_execute($stmt);
$staff_list = mysqli_stmt_get_result($stmt);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<section class="container py-4">
    <h1 class="fw-bold mb-4">
        <i class="fa-solid fa-user-tie me-2"></i>Staff Management
    </h1>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-3" style="background:linear-gradient(135deg,#3a2a1c,#1d140d);color:#fff;border-radius:16px 16px 0 0">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-user-plus me-2"></i>Create New Staff Account
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa-solid fa-id-card" style="color:var(--snapit-gold)"></i>
                                </span>
                                <input type="text" name="name" class="form-control"
                                    value="<?= e($_POST['name'] ?? '') ?>" required
                                    placeholder="Juan Dela Cruz">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa-solid fa-envelope" style="color:var(--snapit-gold)"></i>
                                </span>
                                <input type="email" name="email" class="form-control"
                                    value="<?= e($_POST['email'] ?? '') ?>" required
                                    placeholder="staff@example.com">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa-solid fa-lock" style="color:var(--snapit-gold)"></i>
                                </span>
                                <input type="password" name="password" class="form-control"
                                    required minlength="6"
                                    placeholder="At least 6 characters">
                            </div>
                            <div class="form-text">Password will be securely hashed before storage.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="staff" <?= ($_POST['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>
                                    Staff &mdash; Can manage bookings, inventory, and booth sessions
                                </option>
                                <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                    Administrator &mdash; Full access including user management
                                </option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-snapit w-100 py-2">
                            <i class="fa-solid fa-user-check me-2"></i>Create Staff Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-people-group me-2"></i>Staff &amp; Administrators
                    </h5>
                    <span class="badge badge-snapit px-3 py-2">
                        <?= e(mysqli_num_rows($staff_list)) ?> user(s)
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($staff_list) > 0): ?>
                                    <?php while ($s = mysqli_fetch_assoc($staff_list)):
                                        $is_self = (int)$s['user_id'] === (int)($_SESSION['user_id'] ?? 0);
                                    ?>
                                        <tr class="<?= $is_self ? 'table-light' : '' ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                                                         style="width:42px;height:42px;background:linear-gradient(90deg,<?= $s['role'] === 'admin' ? '#8c6b45,#d8c09a' : '#3a2a1c,#8c6b45' ?>);color:#fff;font-weight:700">
                                                        <?= e(strtoupper(substr($s['name'], 0, 1))) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold">
                                                            <?= e($s['name']) ?>
                                                            <?php if ($is_self): ?>
                                                                <span class="badge bg-secondary ms-2">You</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            Registered: <?= e(format_date($s['created_at'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-flex align-items-center gap-2">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="user_id" value="<?= e((int)$s['user_id']) ?>">
                                                    <select name="role" class="form-select form-select-sm"
                                                            onchange="this.form.submit()"
                                                            <?= $is_self ? 'disabled' : '' ?>>
                                                        <option value="staff" <?= $s['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                                        <option value="admin" <?= $s['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td>
                                                <a href="mailto:<?= e($s['email']) ?>" class="text-decoration-none small">
                                                    <?= e($s['email']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill px-3 py-2 <?= e(status_badge_class($s['status'])) ?>">
                                                    <?= e(ucfirst($s['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline"
                                                      onsubmit="return confirm('Toggle staff status for <?= e($s['name']) ?>?');">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?= e((int)$s['user_id']) ?>">
                                                    <button type="submit"
                                                        class="btn btn-sm <?= $s['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                                        <?= $is_self ? 'disabled title="Cannot change your own status"' : '' ?>>
                                                        <i class="fa-solid <?= $s['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?> me-1"></i>
                                                        <?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-user-slash fa-2xl mb-3 d-block" style="color:#dee2e6"></i>
                                            No staff accounts yet. Create the first one using the form.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
