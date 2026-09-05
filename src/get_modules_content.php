<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$course_id = $_GET['course_id'] ?? null;
$module_ids = $_GET['module_ids'] ?? ''; // comma-separated

if (!$course_id) {
    echo json_encode(['error' => 'Course ID required']);
    exit;
}

$query = "SELECT title, content, is_pdf_mode, pdf_path FROM modules WHERE course_id = ?";
$params = [$course_id];

if ($module_ids !== 'ALL') {
    $ids = array_filter(explode(',', $module_ids), 'is_numeric');
    if (count($ids) > 0) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $query .= " AND id IN ($in)";
        $params = array_merge($params, $ids);
    }
}

$query .= " ORDER BY order_num ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '../vendor/autoload.php';
$parser = new \Smalot\PdfParser\Parser();

$combined_content = "";
foreach ($modules as $m) {
    $combined_content .= "### " . $m['title'] . "\n";
    if ($m['is_pdf_mode'] && !empty($m['pdf_path'])) {
        $pdf_full_path = '../uploads/pdfs/' . $m['pdf_path'];
        if (file_exists($pdf_full_path)) {
            try {
                $pdf = $parser->parseFile($pdf_full_path);
                $combined_content .= $pdf->getText() . "\n\n";
            } catch (Exception $e) {
                $combined_content .= "[Error extracting PDF content]\n\n";
            }
        }
    } else {
        $combined_content .= $m['content'] . "\n\n";
    }
}

echo json_encode(['content' => $combined_content]);
