<?php
date_default_timezone_set('Africa/Lagos'); // Fixes the time-lock bug for WAT / UTC+1
$host = 'localhost';
$db_name = 'cmp_course_module';
$username = 'root'; // default for WAMP
$password = ''; // default for WAMP

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
