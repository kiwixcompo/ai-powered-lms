<?php
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

        // Log the login gracefully
        try {
            $logStmt = $conn->prepare("INSERT INTO login_logs (user_id) VALUES (?)");
            $logStmt->execute([$user['id']]);
        } catch (PDOException $e) {
            // If table doesn't exist, create it and alter users table on the fly for the live server
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS login_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
                $conn->exec("ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL");
                
                // Retry logging
                $logStmt = $conn->prepare("INSERT INTO login_logs (user_id) VALUES (?)");
                $logStmt->execute([$user['id']]);
            } catch (PDOException $e2) {
                // Ignore if it fails again (e.g. column already exists)
            }
        }

        if ($user['role'] === 'admin') {
            header('Location: ' . BASE_URL . '/admin');
        } elseif ($user['role'] === 'facilitator') {
            header('Location: ' . BASE_URL . '/facilitator');
        } else {
            header('Location: ' . BASE_URL . '/student');
        }
        exit;
    } else {
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}
