<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category = $_POST['category'] ?? '';
    $course_id = $_POST['course_id'] ?? null;

    if (!empty($category) && $course_id) {
        try {
            // Fetch all students in this category
            $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'student' AND category = ?");
            $stmt->execute([$category]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $enrolled = 0;
            $insert_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $check = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");

            foreach ($students as $s) {
                $check->execute([$s['id'], $course_id]);
                if ($check->rowCount() == 0) {
                    $insert_stmt->execute([$s['id'], $course_id]);
                    $enrolled++;
                }
            }
            $_SESSION['msg'] = "$enrolled students from category '$category' successfully enrolled in the course.";
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Enrollment failed: ' . $e->getMessage();
        }
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
