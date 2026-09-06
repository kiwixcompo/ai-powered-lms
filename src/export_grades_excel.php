<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'facilitator'])) {
    http_response_code(403);
    exit("Forbidden");
}

$assessment_id = $_GET['assessment_id'] ?? null;

// Fetch Grades Data
$query = "
    SELECT 
        c.code as course_code, 
        c.title as course_title, 
        u.name as student_name, 
        u.reg_no as reg_number, 
        a.title as assessment_title, 
        g.total_score_awarded, 
        a.total_score
    FROM grades g
    JOIN users u ON g.student_id = u.id
    JOIN assessments a ON g.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
";

$params = [];
$conditions = [];

if ($_SESSION['role'] === 'facilitator') {
    $query .= " JOIN course_facilitators cf ON c.id = cf.course_id ";
    $conditions[] = "cf.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($assessment_id) {
    $conditions[] = "g.assessment_id = ?";
    $params[] = $assessment_id;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Auto-heal any legacy assessment where scores were saved as raw question counts instead of scaled marks
try {
    $affected_assmts = $conn->query("
        SELECT a.id, a.total_score,
               (SELECT COUNT(DISTINCT q.id) FROM questions q WHERE q.assessment_id = a.id) as q_count
        FROM assessments a
        WHERE EXISTS (SELECT 1 FROM grades g WHERE g.assessment_id = a.id AND g.total_score_awarded > a.total_score)
    ");
    if ($affected_assmts) {
        $assmt_rows = $affected_assmts->fetchAll(PDO::FETCH_ASSOC);
        $upd_stmt = $conn->prepare("UPDATE grades SET total_score_awarded = ? WHERE id = ?");
        foreach ($assmt_rows as $aff) {
            $max_pts = floatval($aff['total_score']);
            $q_cnt = intval($aff['q_count'] ?: 20);
            
            $all_g = $conn->prepare("SELECT id, total_score_awarded FROM grades WHERE assessment_id = ?");
            $all_g->execute([$aff['id']]);
            $rows = $all_g->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                $raw = floatval($row['total_score_awarded']);
                $scaled = min($max_pts, max(0, round(($raw / $q_cnt) * $max_pts)));
                $upd_stmt->execute([$scaled, $row['id']]);
            }
        }
    }
} catch (Exception $e) {}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clear buffers
if (ob_get_length()) ob_end_clean();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment;filename="System_Grades_Export_' . date('Y_m_d_H_i') . '.csv"');
header('Cache-Control: max-age=0');

$output = fopen('php://output', 'w');

// Set Headers
fputcsv($output, ['Course Code', 'Course Title', 'Student Name', 'Reg Number', 'Assessment Title', 'Score Awarded', 'Total Score', 'Percentage']);

foreach ($grades as $g) {
    $awarded = floatval($g['total_score_awarded']);
    $total = floatval($g['total_score']);
    $percentage = $total > 0 ? round(($awarded / $total) * 100, 2) . '%' : 'N/A';
    
    fputcsv($output, [
        $g['course_code'],
        $g['course_title'],
        $g['student_name'],
        $g['reg_number'],
        $g['assessment_title'],
        $awarded,
        $total,
        $percentage
    ]);
}

fclose($output);
exit;
