# vendor/ folder for Snap It

This project uses **PHPMailer** for SMTP delivery (Mailtrap for staging, Google SMTP for production).

## Install PHPMailer — two options:

### Option A — Composer (recommended, 1 command)
From the `snapit/` directory run:
```
composer install
```
Composer will read `../composer.json`, install `phpmailer/phpmailer:^6.9` into `phpmailer/phpmailer/src/`, and generate the optimized autoloader. The stub `autoload.php` above will detect the presence of `composer/autoload_real.php` and chain to it.

### Option B — No composer, manual (copy 4 files)
1. Download the latest PHPMailer 6.x release zip: https://github.com/PHPMailer/PHPMailer/releases
2. Extract the zip
3. Copy **4 files** from `PHPMailer-6.x.x/src/` into this folder:
   ```
   snapit/vendor/phpmailer/src/Exception.php
   snapit/vendor/phpmailer/src/SMTP.php
   snapit/vendor/phpmailer/src/PHPMailer.php
   snapit/vendor/phpmailer/src/OAuth.php
   ```
   (OAuth.php is optional for Google XOAUTH2 but included for completeness.)
4. Done. The PSR-4 autoloader above loads the classes from `vendor/phpmailer/src/`.

## How to verify PHPMailer is loaded
Open `test_email.php` in your browser. It will show **PHPMailer status: LOADED** and let you fire test emails to both Mailtrap and Google SMTP to confirm config + connectivity + TLS/SSL.
