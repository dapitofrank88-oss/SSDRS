<?php
require_once 'functions.php'; require_admin();
$rows = $pdo->query('SELECT a.log_id, u.username, a.action, a.module, a.details, a.ip_address, a.timestamp FROM audit_logs a LEFT JOIN users u ON u.user_id=a.user_id ORDER BY a.timestamp DESC LIMIT 200')->fetchAll();
page_header('Audit Logs');
?>
<h1>Audit Logs</h1><p class="muted">Recent login and system activity. Latest 200 events are displayed.</p>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th><th>Timestamp</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=e($r['log_id'])?></td><td><?=e($r['username'] ?? 'Deleted user')?></td><td><?=e($r['action'])?></td><td><?=e($r['module'])?></td><td><?=e($r['details'])?></td><td><?=e($r['ip_address'])?></td><td><?=e($r['timestamp'])?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">No audit events yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php page_footer(); ?>
