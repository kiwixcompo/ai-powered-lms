<?php
session_start();
require_once '../config/config.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'facilitator'])) {
    http_response_code(403);
    exit("Forbidden");
}

$assessment_id = $_GET['assessment_id'] ?? null;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Headers
$headers = ['Course Code', 'Course Title', 'Student Name', 'Reg Number', 'Assessment Title', 'Score Awarded', 'Total Score', 'Percentage'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Make header bold
$sheet->getStyle('A1:H1')->getFont()->setBold(true);

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

$query .= " ORDER BY c.code ASC, u.name ASC, a.scheduled_date ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$row = 2;
foreach ($grades as $g) {
    $awarded = floatval($g['total_score_awarded']);
    $total = floatval($g['total_score']);
    $percentage = $total > 0 ? round(($awarded / $total) * 100, 2) . '%' : 'N/A';
    
    $sheet->setCellValue('A' . $row, $g['course_code']);
    $sheet->setCellValue('B' . $row, $g['course_title']);
    $sheet->setCellValue('C' . $row, $g['student_name']);
    $sheet->setCellValue('D' . $row, $g['reg_number']);
    $sheet->setCellValue('E' . $row, $g['assessment_title']);
    $sheet->setCellValue('F' . $row, $awarded);
    $sheet->setCellValue('G' . $row, $total);
    $sheet->setCellValue('H' . $row, $percentage);
    $row++;
}

// Clear buffers and send the file
if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="System_Grades_Export_' . date('Y_m_d_H_i') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
