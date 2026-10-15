<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

// Always wipe the session, whether or not it looks logged in.
destroy_login_session();

set_flash('You have been logged out successfully.', 'success');
redirect(site_url('users/login.php'));
