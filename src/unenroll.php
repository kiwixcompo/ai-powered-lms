<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = $_POST['course_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $student_ids = $_POST['student_ids'] ?? [];

    if ($course_id) {
        // Verify ownership
        $check = $conn->prepare("SELECT course_id FROM course_facilitators WHERE course_id = ? AND user_id = ?");
        $check->execute([$course_id, $_SESSION['user_id']]);
        if ($check->rowCount() > 0) {
            
            if ($action === 'unenroll_all') {
                $stmt = $conn->prepare("DELETE FROM enrollments WHERE course_id = ?");
                $stmt->execute([$course_id]);
                $_SESSION['msg'] = "All students have been un-enrolled from this course.";
            } 
            elseif ($action === 'unenroll_selected' && !empty($student_ids)) {
                $in = str_repeat('?,', count($student_ids) - 1) . '?';
                $params = array_merge([$course_id], $student_ids);
                $stmt = $conn->prepare("DELETE FROM enrollments WHERE course_id = ? AND student_id IN ($in)");
                $stmt->execute($params);
                $_SESSION['msg'] = count($student_ids) . " student(s) have been successfully un-enrolled.";
            } else {
                $_SESSION['error'] = "No students selected.";
            }
        } else {
            $_SESSION['error'] = "Unauthorized.";
        }
    }
}

header('Location: /CMP_Course_Module/facilitator');
exit;
