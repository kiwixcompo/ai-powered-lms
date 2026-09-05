<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $course_id = $_POST['course_id'] ?? null;
    $module_id = $_POST['module_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    try {
        if ($action === 'delete_all' && $course_id) {
            // Verify facilitator owns the course
            $check = $conn->prepare("SELECT course_id FROM course_facilitators WHERE course_id = ? AND user_id = ?");
            $check->execute([$course_id, $user_id]);
            if ($check->rowCount() == 0) throw new Exception("Unauthorized.");

            // Let MySQL ON DELETE CASCADE handle all related records
            $conn->prepare("DELETE FROM modules WHERE course_id = ?")->execute([$course_id]);
            
            $_SESSION['msg'] = "All modules and associated data for the course have been successfully cleared.";

        } elseif ($action === 'delete_single' && $module_id) {
            // Verify ownership via module's course
            $check = $conn->prepare("SELECT m.id FROM modules m JOIN course_facilitators cf ON m.course_id = cf.course_id WHERE m.id = ? AND cf.user_id = ?");
            $check->execute([$module_id, $user_id]);
            if ($check->rowCount() == 0) throw new Exception("Unauthorized.");

            // Let MySQL ON DELETE CASCADE handle all related records
            $conn->prepare("DELETE FROM modules WHERE id = ?")->execute([$module_id]);

            $_SESSION['msg'] = "Module deleted successfully.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting modules: " . $e->getMessage();
    }
}

header('Location: ' . BASE_URL . '/facilitator');
exit;
