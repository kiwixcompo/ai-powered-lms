<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['module_id'])) {
    $module_id = $_POST['module_id'];
    $stmt = $conn->prepare("UPDATE modules SET is_pdf_mode = 0 WHERE id = ?");
    $stmt->execute([$module_id]);
    $_SESSION['msg'] = "Reverted to Markdown mode.";
}
header(\"Location: \" . BASE_URL . \"/CMP_Course_Module/facilitator/module?id=" . $module_id);
exit;
