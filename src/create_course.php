<?php
session_start();
// Force PHP to log errors to a specific file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');
file_put_contents(__DIR__ . '/error_log', "Session data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

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
