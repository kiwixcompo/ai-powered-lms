<?php
require_once '../config/config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'facilitator'])) {
    http_response_code(403); exit('Forbidden');
}
$course_id = $_GET['course_id'] ?? 'all';
if ($course_id === 'all') {
    $stmt = $conn->query('SELECT u.name, u.reg_no, COUNT(l.id) as login_count FROM users u JOIN login_logs l ON u.id = l.user_id WHERE u.role = \'student\' GROUP BY u.id ORDER BY login_count DESC LIMIT 50');
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare('SELECT u.name, u.reg_no, COUNT(l.id) as login_count FROM users u JOIN enrollments e ON u.id = e.student_id LEFT JOIN login_logs l ON u.id = l.user_id WHERE e.course_id = ? GROUP BY u.id ORDER BY login_count DESC');
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$chartData = [];
foreach ($students as $s) {
    $chartData[] = ['label' => $s['name'] . ' (' . $s['reg_no'] . ')', 'logins' => (int)$s['login_count']];
}
header('Content-Type: application/json');
echo json_encode($chartData);
?>
