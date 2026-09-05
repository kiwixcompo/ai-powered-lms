<?php
date_default_timezone_set('Africa/Lagos'); // Fixes the time-lock bug for WAT / UTC+1

if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Local WAMP configuration
    $host = 'localhost';
    $db_name = 'cmp_course_module';
    $username = 'root';
    $password = '';
} else {
    // Production cPanel configuration (UPDATE THESE!)
    $host = 'localhost';
    $db_name = 'tsuniver_lms'; // Replace with actual live DB name
    $username = 'tsuniver_lmsuser'; // Replace with actual live DB user
    $password = 'YOUR_LIVE_DB_PASSWORD'; // Replace with actual live DB password
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>
