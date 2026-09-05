<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = $_POST['code'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    if (!empty($title)) {
        try {
            $stmt = $conn->prepare("INSERT INTO courses (code, title, description) VALUES (?, ?, ?)");
            $stmt->execute([$code, $title, $description]);
            $_SESSION['msg'] = 'Course created successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create course: ' . $e->getMessage();
        }
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
