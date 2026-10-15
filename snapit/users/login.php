<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

if (is_loggedin()) {
    redirect(site_url('index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        set_flash('Email and password are required.', 'warning');
        redirect(site_url('users/login.php'));
    }

    $stmt = mysqli_prepare($conn, "SELECT user_id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if ($row['status'] !== 'active') {
            set_flash('Your account is inactive. Please contact support.', 'danger');
            redirect(site_url('users/login.php'));
        }
        if (password_verify($password, $row['password'])) {
            // Fresh session id and an empty session: nothing left over from a previous
            // visitor on this browser (booth session, old name, CSRF token) can carry
            // into this account.
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['user_id'] = (int)$row['user_id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['role'] = $row['role'];
            unset($_SESSION['avatar']);

            set_flash('Welcome back, ' . e($row['name']) . '!', 'success');

            if (is_staff()) {
                redirect(site_url('admin/index.php'));
            }
            redirect(site_url('booking/create.php'));
        }
    }

    set_flash('Invalid email or password.', 'danger');
    redirect(site_url('users/login.php'));
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <span class="eyebrow mb-3">Member Access</span>
                        <h2 class="fw-bold">Welcome back</h2>
                        <p class="text-muted">Sign in to continue your Snap It experience</p>
                    </div>

                    <form action="<?= e(site_url('users/login.php')) ?>" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" class="form-control form-control-lg" id="email" name="email"
                                    value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control form-control-lg" id="password" name="password"
                                    required autocomplete="current-password">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4 small">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label text-muted" for="remember">Remember me</label>
                            </div>
                            <a href="<?= e(site_url('users/register.php')) ?>" class="text-decoration-none" style="color:var(--snapit-gold)">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-snapit btn-lg w-100 fw-semibold">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="mb-0 text-muted">Don't have an account?
                            <a href="<?= e(site_url('users/register.php')) ?>" class="fw-semibold text-decoration-none" style="color:var(--snapit-gold)">Create one</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
