# Snap It - core build
1. Import sql/schema.sql in phpMyAdmin.
2. Edit includes/config.php with your DB credentials.
3. Insert a staff user (hash the password with PHP password_hash()).
4. Serve over HTTPS or localhost. Booth: public/booth.html. Admin: admin/login.php.
