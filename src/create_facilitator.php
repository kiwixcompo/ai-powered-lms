<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($email) && !empty($password)) {
        try {
            $hashed_pwd = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'facilitator')");
            $stmt->execute([$name, $email, $hashed_pwd]);
            $_SESSION['msg'] = 'Facilitator account created successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create facilitator: ' . $e->getMessage();
        }
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
