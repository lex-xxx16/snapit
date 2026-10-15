<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$customer = null;

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT user_id, name, email, status, avatar, created_at FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$customer) {
        set_flash('Customer not found.', 'danger');
        redirect(site_url('admin/customers.php'));
    }
}

// Re-fill the form after a validation error (set by customer_store.php).
$old = $_SESSION['customer_form_old'] ?? null;
unset($_SESSION['customer_form_old']);
$val = function ($k, $default = '') use ($old, $customer) {
    if (is_array($old) && array_key_exists($k, $old)) return $old[$k];
    return $customer[$k] ?? $default;
};
$is_edit = $customer !== null;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<section class="container py-4" style="max-width:720px">
    <h1 class="fw-bold mb-4">
        <i class="fa-solid <?= $is_edit ? 'fa-user-pen' : 'fa-user-plus' ?> me-2"></i><?= $is_edit ? 'Edit Customer' : 'Add Customer' ?>
    </h1>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="<?= e(site_url('admin/customer_store.php')) ?>" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= $is_edit ? e((int)$customer['user_id']) : 0 ?>">

                <?php if ($is_edit): $av = avatar_url($customer['avatar'] ?? null); ?>
                    <div class="d-flex align-items-center mb-4">
                        <?php if ($av): ?>
                            <img src="<?= e($av) ?>" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover" class="me-3">
                        <?php else: ?>
                            <div class="avatar-circle d-flex align-items-center justify-content-center fw-bold me-3"
                                 style="width:64px;height:64px;border-radius:50%;font-size:1.6rem"><?= e(strtoupper(substr($customer['name'], 0, 1))) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted">ID #<?= e((int)$customer['user_id']) ?> &middot; Registered <?= e(format_date($customer['created_at'])) ?></div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="150" required value="<?= e($val('name')) ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="150" required value="<?= e($val('email')) ?>">
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach (['active', 'inactive'] as $st): ?>
                            <option value="<?= e($st) ?>" <?= $val('status', 'active') === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold"><?= $is_edit ? 'New Password' : 'Password' ?></label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password"
                           minlength="6" <?= $is_edit ? '' : 'required' ?>>
                    <div class="form-text"><?= $is_edit ? 'Leave blank to keep the current password.' : 'At least 6 characters.' ?></div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-snapit fw-semibold px-4">
                        <i class="fa-solid fa-floppy-disk me-2"></i><?= $is_edit ? 'Save Changes' : 'Create Customer' ?>
                    </button>
                    <a href="<?= e(site_url('admin/customers.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
