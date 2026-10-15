# Snap It - setup on a new computer (XAMPP / WAMP / MAMP)

1. Install XAMPP (PHP 8.x). Open the XAMPP Control Panel and **Start Apache and MySQL**.
2. Unzip so the folder is `C:\xampp\htdocs\snappit\snapit` (any folder name works; no renaming needed).
3. Open `http://localhost/snappit/snapit/check.php` - everything should be green.
4. Open `http://localhost/snappit/snapit/` - on first run you are sent to the installer automatically.
   Defaults (127.0.0.1, port 3306, root, empty password, db_snapit) work with stock XAMPP.
   Installer saves your machine's settings to `includes/config.local.php` (never shared).
5. Log in: admin@snapit.ph / admin123, staff@snapit.ph / staff123, customer@snapit.ph / cust123.
6. Use **http://localhost/** (or 127.0.0.1) in the browser, not your LAN IP - the camera only works on localhost or HTTPS.

## Sharing data between group members
The ZIP contains code only; the **database lives in each person's MySQL**. To share bookings/accounts:
- Export: `C:\xampp\mysql\bin\mysqldump -uroot db_snapit > snapit_data.sql`
- Import on the other PC (after install): `C:\xampp\mysql\bin\mysql -uroot db_snapit < snapit_data.sql`
- Photos are in `uploads/sessions/` - copy that folder too if you want the same galleries.

## Never include when zipping
`includes/config.local.php`, `includes/installed.lock`, `logs/*.log`, `uploads/sessions/*`.

## Troubleshooting
- "Cannot reach the MySQL server": start MySQL in XAMPP; if it is on port 3307, edit `config.local.php`.
- Port 3306 busy: another MySQL (Workbench/Docker) is running - stop it or change the port.
- Unstyled pages: you need internet (Bootstrap/FontAwesome load from CDNs).
- Re-run installer: delete `includes/installed.lock`.
- Before any public deployment delete: `check.php`, `repair_accounts.php`, `debug_check_hashes.php`, `migrate_*.php`.
