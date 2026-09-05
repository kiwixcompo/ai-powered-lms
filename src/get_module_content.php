<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

$course_id = $_POST['course_id'] ?? null;
$target_modules = $_POST['target_modules'] ?? 'ALL'; // JSON string if specific

if (!$course_id) {
    http_response_code(400);
    exit("Course ID required");
}

$query = "SELECT title, content, is_pdf_mode, pdf_path FROM modules WHERE course_id = ?";
$params = [$course_id];

if ($target_modules !== 'ALL') {
    $ids = json_decode($target_modules, true);
    if (is_array($ids) && count($ids) > 0) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $query .= " AND id IN ($in)";
        $params = array_merge($params, $ids);
    }
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$combined_content = "";
if (count($modules) > 0) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $parser = new \Smalot\PdfParser\Parser();
    
    foreach ($modules as $m) {
        $combined_content .= "Module Title: " . $m['title'] . "\n";
        if ($m['is_pdf_mode'] && !empty($m['pdf_path'])) {
            $pdf_full_path = '../uploads/pdfs/' . $m['pdf_path'];
            if (file_exists($pdf_full_path)) {
                try {
                    $pdf = $parser->parseFile($pdf_full_path);
                    // Extract text but truncate to avoid massive payload (AI context limit)
                    $text = $pdf->getText();
                    $combined_content .= mb_substr($text, 0, 15000) . "\n\n";
                } catch (Exception $e) {}
            }
        } else {
            $combined_content .= mb_substr($m['content'], 0, 15000) . "\n\n";
        }
    }
}

// Truncate total content to roughly 20000 characters to fit in prompt limits safely
$combined_content = mb_substr(trim($combined_content), 0, 20000);

header('Content-Type: application/json');
echo json_encode(['content' => $combined_content ?: 'General course content']);
