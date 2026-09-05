<?php
session_start();
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_reg = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? OR reg_no = ?");
    $stmt->execute([$email_or_reg, $email_or_reg]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Using password_verify for secure password checking
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        if ($user['role'] === 'admin') {
            header('Location: /CMP_Course_Module/admin');
        } elseif ($user['role'] === 'facilitator') {
            header('Location: /CMP_Course_Module/facilitator');
        } else {
            header('Location: /CMP_Course_Module/student');
        }
        exit;
    } else {
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: /CMP_Course_Module/login');
        exit;
    }
}
