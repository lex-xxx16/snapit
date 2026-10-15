<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(site_url('admin/customers.php'));
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('Your session expired. Please try again.', 'danger');
    redirect(site_url('admin/customers.php'));
}

$id       = (int)($_POST['user_id'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$status   = $_POST['status'] ?? 'active';
$password = (string)($_POST['password'] ?? '');
$is_edit  = $id > 0;
$back     = site_url('admin/customer_form.php') . ($is_edit ? '?id=' . $id : '');

$errors = [];
if ($name === '') $errors[] = 'Full name is required.';
elseif (strlen($name) > 150) $errors[] = 'Full name is too long (150 characters maximum).';
if ($email === '') $errors[] = 'Email is required.';
elseif (strlen($email) > 150) $errors[] = 'Email is too long (150 characters maximum).';
elseif (!valid_email($email)) $errors[] = 'Please enter a valid email address.';
if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
if (!$is_edit && $password === '') $errors[] = 'Password is required for a new customer.';
if ($password !== '' && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

if ($is_edit) {
    $chk = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
    mysqli_stmt_bind_param($chk, 'i', $id);
    mysqli_stmt_execute($chk);
    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($chk))) {
        set_flash('Customer not found.', 'danger');
        redirect(site_url('admin/customers.php'));
    }
}

if (!$errors) {
    $dup = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
    mysqli_stmt_bind_param($dup, 'si', $email, $id);
    mysqli_stmt_execute($dup);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($dup))) $errors[] = 'That email address is already in use.';
}

if ($errors) {
    $_SESSION['customer_form_old'] = ['name' => $name, 'email' => $email, 'status' => $status];
    set_flash(implode(' ', $errors), 'danger');
    redirect($back);
}

try {
    if ($is_edit) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $st = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, status = ?, password = ? WHERE user_id = ? AND role = 'customer'");
            mysqli_stmt_bind_param($st, 'ssssi', $name, $email, $status, $hash, $id);
        } else {
            $st = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, status = ? WHERE user_id = ? AND role = 'customer'");
            mysqli_stmt_bind_param($st, 'sssi', $name, $email, $status, $id);
        }
        mysqli_stmt_execute($st);
        set_flash('Customer account updated.', 'success');
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $st = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'customer', ?)");
        mysqli_stmt_bind_param($st, 'ssss', $name, $email, $hash, $status);
        mysqli_stmt_execute($st);
        set_flash('Customer account created.', 'success');
    }
} catch (Throwable $ex) {
    @error_log('[admin/customer_store] ' . $ex->getMessage());
    $_SESSION['customer_form_old'] = ['name' => $name, 'email' => $email, 'status' => $status];
    set_flash('Could not save the customer. Please try again.', 'danger');
    redirect($back);
}

redirect(site_url('admin/customers.php'));
