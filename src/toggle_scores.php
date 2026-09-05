<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $scores_released = $_POST['scores_released'] ?? 0;

    if ($assessment_id) {
        $stmt = $conn->prepare("UPDATE assessments SET scores_released = ? WHERE id = ?");
        $stmt->execute([$scores_released, $assessment_id]);
        $_SESSION['msg'] = "Assessment scores visibility updated.";
    }
}
header("Location: /CMP_Course_Module/facilitator");
exit;
