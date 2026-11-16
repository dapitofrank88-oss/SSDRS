<?php
require_once 'functions.php'; require_login();
$msg=''; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $old=$_POST['current_password'] ?? ''; $new=$_POST['new_password'] ?? ''; $confirm=$_POST['confirm_password'] ?? '';
    $st=$pdo->prepare('SELECT password_hash FROM users WHERE user_id=?'); $st->execute([current_user()['user_id']]); $hash=$st->fetchColumn();
    if (!password_verify($old,$hash)) $err='Current password is incorrect.';
    elseif (strlen($new)<10) $err='New password must contain at least 10 characters.';
    elseif (!preg_match('/[^A-Za-z0-9]/',$new)) $err='New password must contain at least one special character (for example ! @ # $ % ^ & *).';
    elseif ($new===$old) $err='New password must be different from your current password.';
    elseif ($new!==$confirm) $err='New password and confirmation do not match.';
    else {
        $st=$pdo->prepare('UPDATE users SET password_hash=?, must_change_password=0 WHERE user_id=?'); $st->execute([password_hash($new,PASSWORD_DEFAULT),current_user()['user_id']]);
        log_action($pdo,'Changed password','User Management','Password changed successfully');
        if (!empty($_SESSION['must_change_password'])) { $_SESSION['must_change_password']=false; header('Location: dashboard.php?pwchanged=1'); exit; }
        $msg='Password changed successfully.';
    }
}
page_header('Change Password');
?>
<h1>Change Password</h1><section class="panel form-panel">
<?php if(!empty($_SESSION['must_change_password'])): ?><div class="alert error"><b>Action required:</b> For your security, you must change your temporary/default password before you can continue using SSDRS.</div><?php endif; ?>
<?php if($msg): ?><div class="alert"><?=e($msg)?></div><?php endif; ?><?php if($err): ?><div class="alert error"><?=e($err)?></div><?php endif; ?>
<form method="post"><label>Current Password</label><input type="password" name="current_password" required>
<label>New Password (minimum 10 characters, must include a special character)</label>
<input type="password" id="new_password" name="new_password" minlength="10" required>
<div id="strength-box" style="margin:6px 0 12px;display:none">
  <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden"><div id="strength-bar" style="height:100%;width:0;transition:.2s"></div></div>
  <div id="strength-text" style="font-weight:bold;margin-top:4px"></div>
  <small id="strength-tips" style="color:#555"></small>
</div>
<label>Confirm New Password</label><input type="password" name="confirm_password" minlength="10" required>
<button class="btn primary" type="submit">Update Password</button></form></section>

<script>
(function(){
  const input = document.getElementById('new_password');
  const box = document.getElementById('strength-box');
  const bar = document.getElementById('strength-bar');
  const text = document.getElementById('strength-text');
  const tips = document.getElementById('strength-tips');
  input.addEventListener('input', function(){
    const p = input.value;
    if (!p) { box.style.display = 'none'; return; }
    box.style.display = 'block';
    const hasLower = /[a-z]/.test(p), hasUpper = /[A-Z]/.test(p),
          hasDigit = /[0-9]/.test(p), hasSpecial = /[^A-Za-z0-9]/.test(p);
    const types = [hasLower, hasUpper, hasDigit, hasSpecial].filter(Boolean).length;
    let level, color, width;
    if (p.length >= 12 && types === 4)      { level = 'Hard (strong)';  color = '#16a34a'; width = '100%'; }
    else if (p.length >= 10 && types >= 3)  { level = 'Medium';         color = '#f59e0b'; width = '66%'; }
    else                                    { level = 'Easy (weak)';    color = '#dc2626'; width = '33%'; }
    bar.style.width = width; bar.style.background = color;
    text.textContent = 'Password strength: ' + level; text.style.color = color;
    const missing = [];
    if (p.length < 10) missing.push('at least 10 characters');
    if (!hasUpper) missing.push('an uppercase letter');
    if (!hasLower) missing.push('a lowercase letter');
    if (!hasDigit) missing.push('a number');
    if (!hasSpecial) missing.push('a special character (required)');
    if (p.length < 12 && missing.length === 0) missing.push('12 or more characters for Hard');
    tips.textContent = missing.length ? 'Add: ' + missing.join(', ') + '.' : 'Great, this is a strong password.';
  });
})();
</script>
<?php page_footer(); ?>
