<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $reg_no = $_POST['reg_no'] ?? '';
    $category = $_POST['category'] ?? 'General';

    if (!empty($name) && !empty($reg_no)) {
        try {
            // Password defaults to Registration Number
            $hashed_pwd = password_hash($reg_no, PASSWORD_DEFAULT);
            $dummy_email = preg_replace('/[^a-zA-Z0-9]/', '', $reg_no) . '@student.local';
            
            $stmt = $conn->prepare("INSERT INTO users (name, reg_no, email, password, role, category) VALUES (?, ?, ?, ?, 'student', ?)");
            $stmt->execute([$name, $reg_no, $dummy_email, $hashed_pwd, $category]);
            
            $_SESSION['msg'] = "Student '$name' ($reg_no) was successfully added to category '$category'.";
        } catch (PDOException $e) {
            // Usually duplicate entry for reg_no or email
            $_SESSION['error'] = 'Failed to add student. Ensure the Registration Number is unique. Error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Please fill in all required fields (Name and Registration Number).';
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
