<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $module_id = $_POST['module_id'] ?? null;
    $is_active = $_POST['is_active'] ?? 1;

    if ($module_id) {
        $stmt = $conn->prepare("UPDATE modules SET is_active = ? WHERE id = ?");
        $stmt->execute([$is_active, $module_id]);
        $_SESSION['msg'] = "Module visibility updated.";
    }
}
header("Location: /CMP_Course_Module/facilitator");
exit;
