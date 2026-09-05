<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file']) && isset($_POST['module_id'])) {
    $module_id = $_POST['module_id'];
    $file = $_FILES['pdf_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'pdf') {
            $_SESSION['error'] = "Only PDF files are allowed.";
            header("Location: /CMP_Course_Module/facilitator");
            exit;
        }

        $upload_dir = '../uploads/pdfs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid('mod_' . $module_id . '_') . '.pdf';
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $stmt = $conn->prepare("UPDATE modules SET is_pdf_mode = 1, pdf_path = ? WHERE id = ?");
            $stmt->execute([$filename, $module_id]);
            $_SESSION['msg'] = "PDF uploaded successfully. Module is now in PDF mode.";
        } else {
            $_SESSION['error'] = "Failed to move uploaded file.";
        }
    } else {
        $_SESSION['error'] = "Error uploading file.";
    }
}
header("Location: /CMP_Course_Module/facilitator");
exit;
