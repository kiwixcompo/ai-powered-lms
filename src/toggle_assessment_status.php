<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $is_active = $_POST['is_active'] ?? 1;

    if ($assessment_id) {
        $stmt = $conn->prepare("UPDATE assessments SET is_active = ? WHERE id = ?");
        $stmt->execute([$is_active, $assessment_id]);
        $_SESSION['msg'] = "Assessment status updated.";
    }
}

header('Location: ' . BASE_URL . '/facilitator');
exit;
