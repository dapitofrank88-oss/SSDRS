<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/smtp_config.php';
$message = '';
$error = '';
$stage = 'request';
if (!empty($_SESSION['otp_pending_user_id'])) $stage = 'verify';

function otp_mailer(): PHPMailer\PHPMailer\PHPMailer {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Email library is not installed. Follow the SMTP setup instructions in README_STEP_BY_STEP.txt.');
    require_once $autoload;
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    return $mail;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_code'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $stmt = $pdo->prepare("SELECT user_id, username, email, failed_attempts, locked_until, status FROM users WHERE username=? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'Active' || (int)$user['failed_attempts'] < 3) {
        $error = 'Unable to send a verification code for this account. Check the username and lockout status, or contact the system administrator.';
    } elseif (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $error = 'The 5-minute lockout is still active. Please wait until it expires before requesting a code.';
    } elseif (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'This account does not have a valid registered email address. Contact the system administrator.';
    } else {
        // Limit code requests to one per 60 seconds and invalidate older codes.
        $q = $pdo->prepare('SELECT created_at FROM account_recovery_codes WHERE user_id=? ORDER BY code_id DESC LIMIT 1');
        $q->execute([$user['user_id']]);
        $last = $q->fetchColumn();
        if ($last && (time() - strtotime($last)) < 60) {
            $error = 'A code was requested recently. Please wait at least 60 seconds before requesting another.';
        } else {
            $code = (string)random_int(100000, 999999);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $pdo->prepare('DELETE FROM account_recovery_codes WHERE user_id=?')->execute([$user['user_id']]);
            $ins = $pdo->prepare('INSERT INTO account_recovery_codes (user_id, code_hash, expires_at, attempts, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0, NOW())');
            $ins->execute([$user['user_id'], $hash]);
            try {
                $mail = otp_mailer();
                $mail->addAddress($user['email']);
                $mail->Subject = 'SSDRS account verification code';
                $mail->isHTML(true);
                $mail->Body = '<p>A request was made to unlock your SSDRS account.</p><p>Your one-time verification code is:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . e($code) . '</p><p>This code expires in 5 minutes and can be used once. If you did not request it, you can ignore this email.</p>';
                $mail->AltBody = "Your SSDRS one-time verification code is: $code. It expires in 5 minutes and can be used once.";
                $mail->send();
                $_SESSION['otp_pending_user_id'] = (int)$user['user_id'];
                $_SESSION['otp_pending_username'] = $user['username'];
                $_SESSION['otp_last_request'] = time();
                $stage = 'verify';
                $message = 'A verification code has been sent to the registered email address for this account. Check your inbox and spam folder.';
                log_action($pdo, 'Recovery OTP sent', 'Authentication', 'Email verification code sent for username ' . $user['username']);
            } catch (Throwable $ex) {
                $pdo->prepare('DELETE FROM account_recovery_codes WHERE user_id=?')->execute([$user['user_id']]);
                error_log('SSDRS OTP email error: ' . $ex->getMessage());
                $error = 'The email could not be sent. Check the SMTP configuration and try again later.';
                $stage = 'request';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $stage = 'verify';
    $userId = (int)($_SESSION['otp_pending_user_id'] ?? 0);
    $code = trim((string)($_POST['code'] ?? ''));
    if (!$userId || !preg_match('/^\d{6}$/', $code)) {
        $error = 'Enter the 6-digit code sent to your registered email.';
    } else {
        $stmt = $pdo->prepare('SELECT code_id, code_hash, expires_at, attempts FROM account_recovery_codes WHERE user_id=? ORDER BY code_id DESC LIMIT 1');
        $stmt->execute([$userId]); $otp = $stmt->fetch();
        if (!$otp || strtotime($otp['expires_at']) <= time()) {
            $pdo->prepare('DELETE FROM account_recovery_codes WHERE user_id=?')->execute([$userId]);
            unset($_SESSION['otp_pending_user_id'], $_SESSION['otp_pending_username']);
            $stage = 'request';
            $error = 'The code has expired. Request a new verification code.';
        } elseif ((int)$otp['attempts'] >= 5) {
            $pdo->prepare('DELETE FROM account_recovery_codes WHERE user_id=?')->execute([$userId]);
            unset($_SESSION['otp_pending_user_id'], $_SESSION['otp_pending_username']);
            $stage = 'request';
            $error = 'Too many incorrect code attempts. Request a new code after 60 seconds.';
        } elseif (!password_verify($code, $otp['code_hash'])) {
            $pdo->prepare('UPDATE account_recovery_codes SET attempts=attempts+1 WHERE code_id=?')->execute([$otp['code_id']]);
            $error = 'The verification code is incorrect. You have ' . max(0, 4 - (int)$otp['attempts']) . ' attempt(s) remaining.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM account_recovery_codes WHERE user_id=?')->execute([$userId]);
            $pdo->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL WHERE user_id=?')->execute([$userId]);
            $pdo->commit();
            log_action($pdo, 'Account unlocked by email OTP', 'Authentication', 'Username ' . ($_SESSION['otp_pending_username'] ?? 'unknown'));
            unset($_SESSION['otp_pending_user_id'], $_SESSION['otp_pending_username'], $_SESSION['otp_last_request']);
            $stage = 'success';
            $message = 'Your account has been unlocked. Return to the login page and sign in.';
        }
    }
}

if (isset($_GET['restart'])) {
    unset($_SESSION['otp_pending_user_id'], $_SESSION['otp_pending_username'], $_SESSION['otp_last_request']);
    header('Location: recover_account.php'); exit;
}
page_header('Email OTP Account Recovery');
?>
<section class="login-card"><div class="crest">🔐</div><h1>Email Verification</h1>
<p class="muted">After the 5-minute login lockout expires, verify your identity using a one-time code sent to your registered email address.</p>
<?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<?php if($message): ?><div class="alert"><?=e($message)?></div><?php endif; ?>
<?php if($stage === 'request'): ?>
<form method="post"><label>Username</label><input name="username" required maxlength="50" autocomplete="username"><button class="btn primary full" type="submit" name="request_code" value="1">SEND VERIFICATION CODE</button></form>
<?php elseif($stage === 'verify'): ?>
<p>Account: <b><?=e($_SESSION['otp_pending_username'] ?? '')?></b></p>
<form method="post"><label>6-digit verification code</label><input name="code" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="000000"><button class="btn primary full" type="submit" name="verify_code" value="1">VERIFY AND UNLOCK</button></form>
<p><a href="recover_account.php?restart=1">Start over / request a new code</a></p>
<?php elseif($stage === 'success'): ?>
<p><a class="btn primary full" href="index.php">BACK TO LOGIN</a></p>
<?php endif; ?>
<p class="tiny muted">The code expires after 5 minutes and can be used once. Five incorrect code entries invalidate it. Never share your code with anyone.</p><p><a href="index.php">Back to Login</a></p></section>
<?php page_footer(); ?>
