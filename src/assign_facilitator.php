<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;

    if ($user_id && $course_id) {
        try {
            // Check if already assigned
            $check = $conn->prepare("SELECT * FROM course_facilitators WHERE course_id = ? AND user_id = ?");
            $check->execute([$course_id, $user_id]);
            if ($check->rowCount() > 0) {
                $_SESSION['error'] = 'Facilitator is already assigned to this course.';
            } else {
                $stmt = $conn->prepare("INSERT INTO course_facilitators (course_id, user_id) VALUES (?, ?)");
                $stmt->execute([$course_id, $user_id]);
                $_SESSION['msg'] = 'Facilitator assigned successfully.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to assign facilitator: ' . $e->getMessage();
        }
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
