<?php
require_once __DIR__ . '/config.php';

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): void {
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
    if (time() - (int)($_SESSION['last_activity'] ?? time()) > 900) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
    // Every user must replace their temporary/default password before using the system.
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME']) !== 'change_password.php') {
        header('Location: change_password.php?required=1');
        exit;
    }
}
function require_admin(): void {
    require_login();
    if ((current_user()['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        exit('Access denied. This page is for Admin/OSA Staff only.');
    }
}
function log_action(PDO $pdo, string $action, string $module, string $details = ''): void {
    $u = current_user();
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$u['user_id'] ?? null, $action, $module, $details, $_SERVER['REMOTE_ADDR'] ?? 'local']);
}
function page_header(string $title): void {
    $u = current_user();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | SSDRS</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<header class="topbar"><div class="brand">🛡 SSDRS <small>Secure Student Disciplinary Records System</small></div>';
    if ($u) echo '<div class="user-chip">' . e($u['full_name']) . ' · ' . e($u['role']) . '</div>';
    echo '</header>';
    if ($u) {
        echo '<div class="layout"><aside class="sidebar">';
        echo '<a href="dashboard.php">⌂ Dashboard</a>';
        if ($u['role'] === 'Admin') {
            echo '<a href="students.php">♙ User Management</a><a href="case_form.php">⚠ Add Disciplinary Case</a><a href="records.php">▤ Disciplinary Records</a><a href="report.php">▥ Reports</a><a href="backup.php">▣ Database Backup</a><a href="audit_logs.php">◷ Audit Logs</a>';
        } else {
            echo '<a href="records.php">▤ My Disciplinary Records</a>';
        }
        echo '<a href="change_password.php">⚙ Change Password</a><a href="logout.php">↪ Logout</a>';
        echo '</aside><main class="content">';
    } else {
        echo '<main class="login-shell">';
    }
}
function page_footer(): void {
    echo '</main></div><footer>SSDRS · Academic prototype · Keep disciplinary information confidential</footer></body></html>';
}
