<?php
date_default_timezone_set('Africa/Lagos');
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
define('BASE_URL', $is_localhost ? '/CMP_Course_Module' : '');
 // Fixes the time-lock bug for WAT / UTC+1

if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
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
?>
