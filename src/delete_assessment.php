<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if ($assessment_id && $course_id) {
        // Verify ownership
        $check = $conn->prepare("
            SELECT a.id 
            FROM assessments a 
            JOIN course_facilitators cf ON a.course_id = cf.course_id 
            WHERE a.id = ? AND a.course_id = ? AND cf.user_id = ?
        ");
        $check->execute([$assessment_id, $course_id, $user_id]);

        if ($check->rowCount() > 0) {
            // Let ON DELETE CASCADE do the work
            $stmt = $conn->prepare("DELETE FROM assessments WHERE id = ?");
            $stmt->execute([$assessment_id]);
            $_SESSION['msg'] = "Assessment successfully cancelled and deleted.";
        } else {
            $_SESSION['error'] = "Unauthorized or assessment not found.";
        }
    }
}

header('Location: /CMP_Course_Module/facilitator');
exit;
