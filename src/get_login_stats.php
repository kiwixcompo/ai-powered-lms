<?php
require_once '../config/config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'facilitator'])) {
    http_response_code(403); exit('Forbidden');
}
$course_id = $_GET['course_id'] ?? 'all';
$students = [];

try {
    if ($course_id === 'all') {
        $stmt = $conn->query('SELECT u.id, u.name, u.reg_no, u.last_activity, COUNT(l.id) as login_count, MAX(l.login_time) as last_login FROM users u JOIN login_logs l ON u.id = l.user_id WHERE u.role = \'student\' GROUP BY u.id ORDER BY login_count DESC LIMIT 50');
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare('SELECT u.id, u.name, u.reg_no, u.last_activity, COUNT(l.id) as login_count, MAX(l.login_time) as last_login FROM users u JOIN enrollments e ON u.id = e.student_id LEFT JOIN login_logs l ON u.id = l.user_id WHERE e.course_id = ? GROUP BY u.id ORDER BY login_count DESC');
        $stmt->execute([$course_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Tables might not exist yet on the live server, return empty
    $students = [];
}

$chartData = [];
$tableHtml = '<div class="table-responsive"><table class="table table-sm table-hover border">
    <thead class="table-light"><tr><th>Name</th><th>Reg No</th><th>Total Logins</th><th>Status</th></tr></thead><tbody>';

if (count($students) > 0) {
    foreach ($students as $s) {
        $loginCount = isset($s['login_count']) ? (int)$s['login_count'] : 0;
        $chartData[] = ['label' => $s['name'] . ' (' . $s['reg_no'] . ')', 'logins' => $loginCount];
        
        $is_online = isset($s['last_activity']) && $s['last_activity'] && (time() - strtotime($s['last_activity']) <= 300);
        $lastLogin = !empty($s['last_login']) ? date('M j, Y, g:i a', strtotime($s['last_login'])) : 'Never logged in';
        $status_badge = $is_online ? '<span class="badge bg-success">Online Now</span>' : '<span class="text-muted small">Offline (Last: '.$lastLogin.')</span>';
        
        $tableHtml .= '<tr>
            <td>'.htmlspecialchars($s['name']).'</td>
            <td>'.htmlspecialchars($s['reg_no']).'</td>
            <td>'.$loginCount.'</td>
            <td>'.$status_badge.'</td>
        </tr>';
    }
} else {
    $tableHtml .= '<tr><td colspan="4" class="text-center text-muted py-3">No student activity found</td></tr>';
}
$tableHtml .= '</tbody></table></div>';

header('Content-Type: application/json');
echo json_encode(['chart' => $chartData, 'table' => $tableHtml]);
?>
