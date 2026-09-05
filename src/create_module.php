<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = $_POST['course_id'] ?? null;
    $title = $_POST['title'] ?? '';
    $order_num = $_POST['order_num'] ?? 1;

    if ($course_id && !empty($title)) {
        try {
            $stmt = $conn->prepare("INSERT INTO modules (course_id, title, order_num) VALUES (?, ?, ?)");
            $stmt->execute([$course_id, $title, $order_num]);
            $_SESSION['msg'] = 'Module created successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create module: ' . $e->getMessage();
        }
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
