<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$selectedRole = (string)($_POST['role'] ?? '');
if (!in_array($selectedRole, ['Student', 'Admin'], true) || $username === '') { header('Location: index.php?error=1'); exit; }
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== $selectedRole || $user['status'] !== 'Active') {
    // Generic failure message avoids revealing whether a username exists.
    header('Location: index.php?error=1'); exit;
}
if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
    header('Location: index.php?locked=1&remaining=' . max(1, strtotime($user['locked_until']) - time())); exit;
}
// Expired cooldown requires email OTP verification before the account can be unlocked.
if ((int)$user['failed_attempts'] >= 3 && empty($user['locked_until'])) {
    header('Location: index.php?locked=1'); exit;
}
if ((int)$user['failed_attempts'] >= 3 && !empty($user['locked_until']) && strtotime($user['locked_until']) <= time()) {
    header('Location: index.php?recovery=1'); exit;
}
if (!password_verify($password, $user['password_hash'])) {
    $attempts = (int)$user['failed_attempts'] + 1;
    if ($attempts >= 3) {
        $until = date('Y-m-d H:i:s', time() + 300);
        $up = $pdo->prepare('UPDATE users SET failed_attempts=?, locked_until=? WHERE user_id=?');
        $up->execute([$attempts, $until, $user['user_id']]);
        log_action($pdo, 'Account temporarily locked', 'Authentication', 'Three failed login attempts for username ' . $username);
        header('Location: index.php?locked=1&remaining=300'); exit;
    }
    $up = $pdo->prepare('UPDATE users SET failed_attempts=? WHERE user_id=?');
    $up->execute([$attempts, $user['user_id']]);
    header('Location: index.php?error=1&attempts=' . $attempts); exit;
}
if ((int)$user['failed_attempts'] >= 3) { header('Location: index.php?recovery=1'); exit; }
$up = $pdo->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL WHERE user_id=?');
$up->execute([$user['user_id']]);
session_regenerate_id(true);
$_SESSION['user'] = ['user_id'=>(int)$user['user_id'],'username'=>$user['username'],'role'=>$user['role'],'student_id'=>$user['student_id'] ? (int)$user['student_id'] : null,'full_name'=>$user['full_name']];
$_SESSION['last_activity'] = time();
$_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 1) === 1;
log_action($pdo, 'Login', 'Authentication', 'Successful login as ' . $selectedRole);
if (!empty($_SESSION['must_change_password'])) { header('Location: change_password.php?required=1'); exit; }
header('Location: dashboard.php'); exit;
