<?php
require_once 'functions.php'; require_login();
$u = current_user();
if ($u['role'] === 'Admin') {
    $students = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $cases = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('Pending','Under Review')")->fetchColumn();
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE status='Pending'")->fetchColumn();
    $resolved = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE status='Resolved'")->fetchColumn();
    $recent = $pdo->query("SELECT c.case_id, s.student_number, CONCAT(s.first_name,' ',s.last_name) student_name, v.violation_name, c.status, c.incident_date FROM cases c JOIN students s ON s.student_id=c.student_id JOIN violations v ON v.violation_id=c.violation_id ORDER BY c.created_at DESC LIMIT 5")->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cases WHERE student_id = ?'); $stmt->execute([$u['student_id']]); $total = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE student_id = ? AND status IN ('Pending','Under Review')"); $stmt->execute([$u['student_id']]); $active = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT c.incident_date, v.violation_name, c.status FROM cases c JOIN violations v ON v.violation_id=c.violation_id WHERE c.student_id=? ORDER BY c.incident_date DESC LIMIT 5"); $stmt->execute([$u['student_id']]); $recent = $stmt->fetchAll();
}
page_header('Dashboard');
?>
<?php if(isset($_GET['pwchanged'])): ?><div class="alert">Password changed successfully. You can now use the system.</div><?php endif; ?>
<h1>Welcome, <?=e($u['full_name'])?>!</h1><p class="muted">Here is your <?= $u['role']==='Admin' ? 'system overview' : 'disciplinary record overview' ?>.</p>
<?php if ($u['role'] === 'Admin'): ?>
<div class="stats">
  <div class="stat"><span>Total Students</span><strong><?=$students?></strong></div>
  <div class="stat"><span>Active Cases</span><strong><?=$cases?></strong></div>
  <div class="stat"><span>Pending Violations</span><strong><?=$pending?></strong></div>
  <div class="stat"><span>Resolved Cases</span><strong><?=$resolved?></strong></div>
</div>
<section class="panel"><h2>Recent Case Records</h2>
<div class="table-wrap"><table><thead><tr><th>Student No.</th><th>Student</th><th>Violation</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach($recent as $r): ?><tr><td><?=e($r['student_number'])?></td><td><?=e($r['student_name'])?></td><td><?=e($r['violation_name'])?></td><td><?=e($r['incident_date'])?></td><td><span class="status"><?=e($r['status'])?></span></td></tr><?php endforeach; ?>
</tbody></table></div><p><a class="btn primary" href="case_form.php">+ Add Disciplinary Case</a> <a class="btn" href="report.php">Generate Report</a></p></section>
<?php else: ?>
<div class="stats"><div class="stat"><span>My Total Violations</span><strong><?=$total?></strong></div><div class="stat"><span>Active Cases</span><strong><?=$active?></strong></div></div>
<section class="panel"><h2>My Recent Records</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Violation</th><th>Status</th></tr></thead><tbody>
<?php foreach($recent as $r): ?><tr><td><?=e($r['incident_date'])?></td><td><?=e($r['violation_name'])?></td><td><span class="status"><?=e($r['status'])?></span></td></tr><?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="3">No disciplinary records found.</td></tr><?php endif; ?>
</tbody></table></div><p><a class="btn primary" href="records.php">View My Full Records</a></p></section>
<?php endif; ?>
<?php page_footer(); ?>
