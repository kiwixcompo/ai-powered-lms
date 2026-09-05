<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ai_provider = $_POST['ai_provider'] === 'pollinations' ? 'pollinations' : 'puter';

    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ai_provider', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$ai_provider, $ai_provider]);
        $_SESSION['msg'] = "AI Configuration saved successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update configuration: " . $e->getMessage();
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
