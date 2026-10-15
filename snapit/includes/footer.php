<footer class="footer-lux">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <span class="footer-brand">
                    <svg class="brand-mark" viewBox="0 0 32 26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5 12.3 2.4h7.4L21.4 5h5.1A3.5 3.5 0 0 1 30 8.5v12a3.5 3.5 0 0 1-3.5 3.5h-21A3.5 3.5 0 0 1 2 20.5v-12A3.5 3.5 0 0 1 5.5 5h5.1Z"/><circle cx="16" cy="14.2" r="5.4"/><circle cx="16" cy="14.2" r="2.1" fill="currentColor" stroke="none"/><circle cx="25.4" cy="9.4" r="1" fill="currentColor" stroke="none"/></svg>
                    Snap It
                </span>
                <p>Photo customization booth and rental, composed for the occasions you&rsquo;ll want to remember &mdash; capture, customize, celebrate.</p>
            </div>
            <div class="col-6 col-lg-3 offset-lg-1">
                <h6>Explore</h6>
                <ul>
                    <li><a href="<?= e(site_url()) ?>">Home</a></li>
                    <li><a href="<?= e(site_url('booking/create.php')) ?>">Reserve an event</a></li>
                    <?php if (is_loggedin()): ?>
                        <li><a href="<?= e(site_url('gallery/index.php')) ?>">Gallery</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h6>Account</h6>
                <ul>
                    <?php if (is_loggedin()): ?>
                        <li><a href="<?= e(site_url('users/profile.php')) ?>">Profile</a></li>
                        <li><a href="<?= e(site_url('users/logout.php')) ?>">Sign out</a></li>
                    <?php else: ?>
                        <li><a href="<?= e(site_url('users/login.php')) ?>">Sign in</a></li>
                        <li><a href="<?= e(site_url('users/register.php')) ?>">Create account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-base">
            <span>&copy; <?= date('Y') ?> Snap It. All rights reserved.</span>
            <span>Photo Customization Booth &amp; Rental System</span>
        </div>
    </div>
</footer>
</body>
</html>
