<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];

        $stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user) {
            $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
            $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ? AND role = 'customer'");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $user_id);
            mysqli_stmt_execute($stmt);
            set_flash("Customer status updated to {$new_status}.", 'success');
        } else {
            set_flash('Customer not found.', 'danger');
        }
        redirect(site_url('admin/customers.php'));
    }
}

$search = $_GET['search'] ?? '';
$search_term = "%{$search}%";

if (!empty($search)) {
    $stmt = mysqli_prepare($conn, "
        SELECT user_id, name, email, status, created_at
        FROM users
        WHERE role = 'customer' AND (name LIKE ? OR email LIKE ?)
        ORDER BY created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, 'ss', $search_term, $search_term);
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT user_id, name, email, status, created_at
        FROM users
        WHERE role = 'customer'
        ORDER BY created_at DESC
    ");
}
mysqli_stmt_execute($stmt);
$customers = mysqli_stmt_get_result($stmt);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<section class="container py-4">
    <h1 class="fw-bold mb-4">
        <i class="fa-solid fa-users me-2"></i>Customer Management
    </h1>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Search Customers</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="fa-solid fa-magnifying-glass" style="color:var(--snapit-gold)"></i>
                        </span>
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name or email..."
                            value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-snapit flex-grow-1">
                        <i class="fa-solid fa-search me-1"></i>Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= e(site_url('admin/customers.php')) ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-user-group me-2"></i>
                    Customers
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-secondary ms-2">
                            Search: "<?= e($search) ?>"
                        </span>
                    <?php endif; ?>
                </h5>
                <span class="badge badge-snapit px-3 py-2">
                    <?= e(mysqli_num_rows($customers)) ?> record(s)
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($customers) > 0): ?>
                            <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                                                 style="width:42px;height:42px;background:linear-gradient(135deg,#3a2a1c,#1d140d);color:#fff;font-weight:700">
                                                <?= e(strtoupper(substr($c['name'], 0, 1))) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= e($c['name']) ?></div>
                                                <div class="small text-muted">ID: #<?= e((int)$c['user_id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= e($c['email']) ?>" class="text-decoration-none">
                                            <?= e($c['email']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill px-3 py-2 <?= e(status_badge_class($c['status'])) ?>">
                                            <?= e(ucfirst($c['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= e(format_date($c['created_at'])) ?></td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Toggle customer status?');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= e((int)$c['user_id']) ?>">
                                            <button type="submit"
                                                class="btn btn-sm <?= $c['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                <i class="fa-solid <?= $c['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?> me-1"></i>
                                                <?= $c['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-users-slash fa-2xl mb-3 d-block" style="color:#dee2e6"></i>
                                    <?php if (!empty($search)): ?>
                                        No customers match your search.
                                    <?php else: ?>
                                        No customers registered yet.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
