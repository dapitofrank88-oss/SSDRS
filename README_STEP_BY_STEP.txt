SSDRS UPDATED PROJECT — PHP + MySQL + XAMPP + VS CODE
====================================================
1. Put this folder in C:\xampp\htdocs\ssdrs_xampp_project\
2. Start Apache and MySQL in XAMPP.
3. The database name is ssdrs_db. If you already created its six tables, do not re-import database.sql because it drops/recreates tables.
4. Check config.php: DB_NAME='ssdrs_db', DB_USER='root', DB_PASS='' for default XAMPP.
5. Run the one-time password setup script below, then DELETE setup_passwords.php.
6. Visit http://localhost/ssdrs_xampp_project/

TEMPORARY setup_passwords.php contents:
<?php
$pdo=new PDO('mysql:host=127.0.0.1;dbname=ssdrs_db;charset=utf8mb4','root','');
$accounts=[['admin','Admin@12345'],['student1','Student@12345'],['student2','Student@12345']];
$stmt=$pdo->prepare('UPDATE users SET password_hash=? WHERE username=?');
foreach($accounts as [$username,$password]){$stmt->execute([password_hash($password,PASSWORD_DEFAULT),$username]);echo htmlspecialchars($username).': configured<br>';}
echo 'Finished';
?>

LOGIN: choose Admin / OSA Staff for admin username admin / password Admin@12345. Choose Student for student1 / Student@12345.
FEATURES: role-selector login; student own-record access; add/edit cases and statuses; student/admin account creation; activate/deactivate accounts; printable notice (Print/Save as PDF); CSV reports; SQL backup download; audit logs; password change; 15-minute timeout.

SECURITY: academic prototype only. Use fictional demo data. Before real deployment add CSRF protection, login rate limiting, secure password reset, HTTPS, least-privilege database account, protected backups, and security review. The role selector is not authorization by itself; server-side code checks the selected role against the database.


ACCOUNT LOCKOUT (ADDED)
----------------------------------------
For an existing database, open phpMyAdmin > select ssdrs_db > SQL and run account_lockout_migration.sql contents ONCE. Do not rerun it; ALTER TABLE will error if columns already exist.
This adds date_of_birth (retained only for compatibility), failed_attempts, and locked_until. Email OTP recovery uses the registered email address; birthday is not used.
The birthday column is retained only for compatibility and is not used by recovery. Email OTP is now the recovery method.


EMAIL OTP RECOVERY SETUP (REQUIRED)
1. Open phpMyAdmin at http://localhost/phpmyadmin and select ssdrs_db.
2. Open the SQL tab and run the contents of email_otp_migration.sql ONCE.
3. In phpMyAdmin, open the users table and edit each account's email field. Every account that may need recovery must have a valid email address you can access. Do not use a fake email address.
4. Install Composer for Windows if not already installed: https://getcomposer.org/Composer-Setup.exe
5. Open Command Prompt in C:\xampp\htdocs\ssdrs_xampp_project (or your extracted project folder) and run: composer install
   This installs PHPMailer into the vendor folder.
6. Open smtp_config.php in VS Code. Set SMTP_USERNAME and SMTP_FROM_EMAIL to your sending email address. Set SMTP_PASSWORD to an SMTP app password. For Gmail, enable 2-Step Verification in the Google account and create an App Password; do not use your normal Gmail password. Keep this file private.
7. Start Apache and MySQL in XAMPP and open the project URL.
8. Test with an account that has a real email address. After 3 incorrect login attempts, wait for the 5-minute lockout to expire, open Unlock Account, request the code, and enter it within 5 minutes.

SECURITY BEHAVIOR
- OTP is a random 6-digit code; only a password hash is stored in the database.
- OTP expires in 5 minutes, is deleted after successful use, and is invalidated after 5 wrong entries.
- New codes are rate-limited to one request per 60 seconds per account.
- Account unlock is allowed only after the login lockout cooldown expires.
- Birthday is no longer used for account recovery. The old date_of_birth column is retained in the database for compatibility but is not read by the recovery page.
- If email sending fails, check Apache's PHP error log and the SMTP settings. Never display the OTP on the page or write it to logs.


FORCED PASSWORD CHANGE (ADDED)
----------------------------------------
For an existing database, run must_change_password_migration.sql ONCE in phpMyAdmin (ssdrs_db > SQL).
After every login, users whose must_change_password = 1 (all existing and newly created accounts) see an "Action required" alert and are redirected to change_password.php. All other pages are blocked until the password is changed. After a successful change the flag is cleared.
To force a user to change their password again: UPDATE users SET must_change_password=1 WHERE username='...';
