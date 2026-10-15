<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

if (is_loggedin()) {
    redirect(site_url('index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) > 150) {
        $errors[] = 'Full name is too long (150 characters maximum).';
    }
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (strlen($email) > 150) {
        $errors[] = 'Email is too long (150 characters maximum).';
    } elseif (!valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!empty($errors)) {
        set_flash(implode(' ', $errors), 'danger');
        redirect(site_url('users/register.php'));
    }

    $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, 's', $email);
    mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
        set_flash('Email is already registered. Please use a different email or login.', 'danger');
        redirect(site_url('users/register.php'));
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $role = 'customer';
    $status = 'active';

    // Every new account is written to the users table as its own row (own user_id).
    // The UNIQUE index on email is the final guard if two people register at the same moment.
    $saved = false;
    $duplicate = false;
    try {
        $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert_stmt, 'sssss', $name, $email, $hashed_password, $role, $status);
        $saved = mysqli_stmt_execute($insert_stmt) && mysqli_stmt_affected_rows($insert_stmt) === 1;
    } catch (mysqli_sql_exception $ex) {
        $duplicate = ((int)$ex->getCode() === 1062);
    }

    if ($saved) {
        set_flash('Registration successful! Please sign in to continue.', 'success');
        redirect(site_url('users/login.php'));
    }
    if ($duplicate) {
        set_flash('Email is already registered. Please use a different email or login.', 'danger');
        redirect(site_url('users/register.php'));
    }

    set_flash('Registration failed. Please try again.', 'danger');
    redirect(site_url('users/register.php'));
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/alert.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <span class="eyebrow mb-3">Join Snap It</span>
                        <h2 class="fw-bold">Create your account</h2>
                        <p class="text-muted">Begin reserving unforgettable event experiences</p>
                    </div>

                    <form action="<?= e(site_url('users/register.php')) ?>" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                <input type="text" class="form-control form-control-lg" id="name" name="name"
                                    value="<?= e($_POST['name'] ?? '') ?>" required autocomplete="name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" class="form-control form-control-lg" id="email" name="email"
                                    value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" class="form-control form-control-lg" id="password" name="password"
                                        required autocomplete="new-password">
                                </div>
                                <div class="form-text">At least 6 characters</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lock-open"></i></span>
                                    <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password"
                                        required autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-snapit btn-lg w-100 fw-semibold">
                            <i class="fa-solid fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="mb-0 text-muted">Already have an account?
                            <a href="<?= e(site_url('users/login.php')) ?>" class="fw-semibold text-decoration-none" style="color:var(--snapit-gold)">Sign in here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
