<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;

    if ($student_id && $course_id) {
        try {
            // Check if already enrolled
            $check = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
            $check->execute([$student_id, $course_id]);
            if ($check->rowCount() > 0) {
                $_SESSION['error'] = 'Student is already enrolled in this course.';
            } else {
                $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
                $stmt->execute([$student_id, $course_id]);
                $_SESSION['msg'] = 'Student successfully enrolled.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Enrollment failed: ' . $e->getMessage();
        }
    }
}

header('Location: /CMP_Course_Module/admin');
exit;
