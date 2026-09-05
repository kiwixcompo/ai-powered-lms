<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $module_id = $_POST['module_id'] ?? null;
    $content = $_POST['content'] ?? '';

    if ($module_id && !empty($content)) {
        try {
            $stmt = $conn->prepare("UPDATE modules SET content = ? WHERE id = ?");
            $stmt->execute([$content, $module_id]);
            // Redirect back with success message
            header('Location: ' . BASE_URL . '/facilitator/module?id=' . $module_id);
            exit;
        } catch (PDOException $e) {
            die('Error saving content: ' . $e->getMessage());
        }
    }
}
