<?php
// Enable global error logging for easier debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error_log');

// Fix Session handling on strict cPanel environments
$session_path = dirname(__DIR__) . '/sessions';
if (!file_exists($session_path)) {
    @mkdir($session_path, 0777, true);
}
session_save_path($session_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Lagos');
$is_localhost = (php_sapi_name() === 'cli' || $_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
define('BASE_URL', $is_localhost ? '/CMP_Course_Module' : '');
if ($is_localhost) {
    // Local WAMP configuration
    $host = 'localhost';
    $db_name = 'cmp_course_module';
    $username = 'root';
    $password = '';
} else {
    // Production cPanel configuration (tsuniver_tsu_lms)
    $host = 'localhost';
    $db_name = 'tsuniver_tsu_lms';
    $username = 'tsuniver_tsu_lms';
    $password = 'FvMxSL8oP96!A-}d';
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Track online status globally (updating every 60 seconds max to prevent DB overload)
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['last_activity_update']) || (time() - $_SESSION['last_activity_update'] > 60)) {
        try {
            $conn->prepare("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$_SESSION['user_id']]);
            $_SESSION['last_activity_update'] = time();
        } catch(PDOException $e) {}
    }
}
?>
