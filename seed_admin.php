<?php
require_once 'config/config.php';

$password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('System Admin', 'admin@example.com', ?, 'admin')");
$stmt->execute([$password]);
echo "Admin user created!\n";
?>
