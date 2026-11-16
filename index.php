<?php
require_once 'functions.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
page_header('Login');
?>
<section class="login-card"><div class="crest">🎓</div><h1>Secure Student Disciplinary<br>Records System</h1><p class="muted">Choose your account type and sign in.</p>
<?php if(isset($_GET['error'])): ?><div class="alert error">Login failed. Check your username, password, and selected account type. <?php if(isset($_GET['attempts'])): ?>Failed attempts: <?=e($_GET['attempts'])?> of 3.<?php endif; ?></div><?php endif; ?>
<?php if(isset($_GET['locked'])): $remaining = max(0, (int)($_GET['remaining'] ?? 0)); ?>
<div class="alert error">
  This account is temporarily locked after 3 failed attempts.
  <?php if($remaining > 0): ?>
    Please wait <b id="lock-timer" data-seconds="<?= $remaining ?>">--:--</b> before you can request an email verification code to unlock it.
  <?php else: ?>
    Wait 5 minutes, then request an email verification code to unlock it.
  <?php endif; ?>
</div>
<?php if($remaining > 0): ?>
<script>
(function(){
  const el = document.getElementById('lock-timer');
  const end = Date.now() + parseInt(el.dataset.seconds, 10) * 1000;
  function tick(){
    const left = Math.max(0, Math.round((end - Date.now()) / 1000));
    const m = String(Math.floor(left / 60)).padStart(2, '0');
    const s = String(left % 60).padStart(2, '0');
    el.textContent = m + ':' + s;
    if (left <= 0) { window.location.href = 'index.php?recovery=1'; return; }
    setTimeout(tick, 250);
  }
  tick();
})();
</script>
<?php endif; ?>
<?php endif; ?>
<?php if(isset($_GET['recovery'])): ?><div class="alert">The cooldown has ended. Request a one-time verification code sent to your registered email address to unlock your account.</div><?php endif; ?>
<?php if(isset($_GET['timeout'])): ?><div class="alert">You were logged out after 15 minutes of inactivity. Please sign in again.</div><?php endif; ?>
<form method="post" action="login.php"><label>Login As</label><select name="role" required><option value="">Choose account type</option><option value="Student">Student</option><option value="Admin">Admin / OSA Staff</option></select>
<label>Username</label><input name="username" required maxlength="50" autocomplete="username" placeholder="Enter username"><label>Password</label><input type="password" name="password" required autocomplete="current-password" placeholder="Enter password"><button class="btn primary full" type="submit">LOGIN</button></form>
<p><a href="recover_account.php">Unlock account / Forgot login access?</a></p>
<p class="muted tiny">Demo Admin: <b>admin</b> / <b>Admin@12345</b><br>Demo Student: <b>student1</b> / <b>Student@12345</b></p></section><?php page_footer(); ?>
