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
        // Create table on the fly if missing
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS settings (setting_key varchar(50) NOT NULL, setting_value varchar(255) DEFAULT NULL, PRIMARY KEY (setting_key))");
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ai_provider', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$ai_provider, $ai_provider]);
            $_SESSION['msg'] = "AI Configuration saved successfully.";
        } catch (PDOException $e2) {
            $_SESSION['error'] = "Failed to update configuration: " . $e2->getMessage();
        }
    }
}

header('Location: ' . BASE_URL . '/admin');
exit;
