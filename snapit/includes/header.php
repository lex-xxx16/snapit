<?php
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@300;400;500;600&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="<?= e(site_url('includes/style/style.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/style/style.css') ?>" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
    <script src="<?= e(site_url('includes/strip.js')) ?>"></script>
    <script src="<?= e(site_url('includes/camfx.js')) ?>"></script>
    <meta name="theme-color" content="#120d09">
    <link rel="icon" type="image/svg+xml" href="<?= e(site_url('includes/favicon.svg')) ?>">
    <title>Snap It &mdash; Luxury Photo Booth Experiences</title>
    <?php if (is_loggedin()): ?>
    <script>
        // If the browser restores this page from its back/forward cache (for example after
        // logging out and pressing Back), reload it so the server can check the login again.
        window.addEventListener('pageshow', function (ev) {
            var nav = (performance.getEntriesByType('navigation')[0] || {}).type;
            if (ev.persisted || nav === 'back_forward') { window.location.reload(); }
        });
    </script>
    <?php endif; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-snapit">
    <div class="container-fluid container-lg">
        <a class="navbar-brand" href="<?= e(site_url()) ?>" aria-label="Snap It — home">
            <svg class="brand-mark" viewBox="0 0 32 26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5 12.3 2.4h7.4L21.4 5h5.1A3.5 3.5 0 0 1 30 8.5v12a3.5 3.5 0 0 1-3.5 3.5h-21A3.5 3.5 0 0 1 2 20.5v-12A3.5 3.5 0 0 1 5.5 5h5.1Z"/><circle cx="16" cy="14.2" r="5.4"/><circle cx="16" cy="14.2" r="2.1" fill="currentColor" stroke="none"/><circle cx="25.4" cy="9.4" r="1" fill="currentColor" stroke="none"/></svg>
            <span>Snap It</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#snapitNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="snapitNavbar">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= e(site_url()) ?>">Home</a></li>
                <?php if (!is_loggedin()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(site_url('index.php')) ?>#experience">Experience</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(site_url('index.php')) ?>#packages">Packages</a></li>
                <?php endif; ?>
                <?php if (is_loggedin()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(site_url('booking/index.php')) ?>">Bookings</a></li>
                    <?php if (is_customer()): ?><li class="nav-item"><a class="nav-link" href="<?= e(site_url('walkin/index.php')) ?>">Walk-in</a></li><?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(site_url('gallery/index.php')) ?>">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(site_url('booth/index.php')) ?>">Studio</a></li>
                <?php endif; ?>
                <?php if (is_staff()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Admin</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= e(site_url('admin/index.php')) ?>"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= e(site_url('booking/index.php')) ?>"><i class="fa-regular fa-calendar me-2"></i>Bookings</a></li>
                            <li><a class="dropdown-item" href="<?= e(site_url('inventory/index.php')) ?>"><i class="fa-solid fa-boxes-stacked me-2"></i>Inventory</a></li>
                            <li><a class="dropdown-item" href="<?= e(site_url('inventory/print_queue.php')) ?>"><i class="fa-solid fa-print me-2"></i>Print Queue</a></li>
                            <li><a class="dropdown-item" href="<?= e(site_url('payment/index.php')) ?>"><i class="fa-solid fa-money-bill-wave me-2"></i>Payments</a></li>
                            <?php if (is_admin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= e(site_url('admin/customers.php')) ?>"><i class="fa-solid fa-users me-2"></i>Customers</a></li>
                                <li><a class="dropdown-item" href="<?= e(site_url('admin/staff.php')) ?>"><i class="fa-solid fa-user-tie me-2"></i>Staff</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if (is_loggedin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(site_url('users/profile.php')) ?>">
                            <i class="fa-regular fa-circle-user me-1"></i><?= e($_SESSION['name'] ?? 'Account') ?>
                            <span class="badge badge-snapit ms-1"><?= e(ucfirst($_SESSION['role'] ?? 'customer')) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(site_url('users/logout.php')) ?>" title="Log out" aria-label="Log out">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <?php $auth_active = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'login.php') ? 'login' : 'register'; ?>
                    <li class="nav-item ms-lg-1">
                        <div class="auth-switch" role="group" aria-label="Account">
                            <span class="auth-indicator" aria-hidden="true"></span>
                            <a class="auth-link<?= $auth_active === 'login' ? ' active' : '' ?>" href="<?= e(site_url('users/login.php')) ?>">Login</a>
                            <a class="auth-link<?= $auth_active === 'register' ? ' active' : '' ?>" href="<?= e(site_url('users/register.php')) ?>">Register</a>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (is_loggedin()): ?>
<!-- Logout confirmation -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm-down">
        <div class="modal-content logout-modal text-center">
            <div class="modal-body p-4 p-md-5">
                <span class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                <h4 class="mb-2" id="logoutModalLabel">Leaving so soon?</h4>
                <p class="text-muted mb-4">Do you really want to log out?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-ghost px-4" data-bs-dismiss="modal">No, stay</button>
                    <a href="<?= e(site_url('users/logout.php')) ?>" class="btn btn-snapit px-4" id="logoutConfirm">Yes, log out</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Ask before logging out: "No" keeps the user on the page, "Yes" follows the logout link.
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href*="logout.php"]') : null;
        if (!a || a.closest('#logoutModal')) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        var modal = document.getElementById('logoutModal');
        if (!modal || typeof bootstrap === 'undefined') {
            if (window.confirm('Do you really want to log out?')) { window.location.href = a.href; }
            return;
        }
        document.getElementById('logoutConfirm').setAttribute('href', a.href);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
</script>
<?php else: ?>
<script>
    // Login / Register switch: the white pill slides to whichever button was clicked.
    (function () {
        var sw = document.querySelector('.auth-switch');
        if (!sw) return;
        var ind = sw.querySelector('.auth-indicator');
        var links = sw.querySelectorAll('.auth-link');
        function current() { return sw.querySelector('.auth-link.active'); }
        function place(link, animate) {
            if (!link || !link.offsetWidth) return;
            ind.style.transition = animate ? '' : 'none';
            ind.style.width = link.offsetWidth + 'px';
            ind.style.transform = 'translateX(' + link.offsetLeft + 'px)';
            if (!animate) { void ind.offsetWidth; ind.style.transition = ''; }
        }
        function init() { place(current(), false); }
        init();
        window.addEventListener('load', init);
        window.addEventListener('resize', init);
        document.addEventListener('shown.bs.collapse', init);
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(init); }
        if (window.ResizeObserver) { new ResizeObserver(init).observe(sw); }
        links.forEach(function (l) {
            l.addEventListener('click', function (e) {
                if (l.classList.contains('active')) return;
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                e.preventDefault();
                links.forEach(function (x) { x.classList.remove('active'); });
                l.classList.add('active');
                place(l, true);
                var href = l.href;
                setTimeout(function () { window.location.href = href; }, 340);
            });
        });
    })();
</script>
<?php endif; ?>
