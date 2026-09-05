<?php
header("Content-Type: text/plain");
$logPath = __DIR__ . '/error_log';
if (file_exists($logPath)) {
    echo "--- ROOT error_log ---\n";
    $lines = file($logPath);
    echo implode("", array_slice($lines, -50));
} else {
    echo "No error_log in root.\n";
}

$studentLog = __DIR__ . '/student/error_log';
if (file_exists($studentLog)) {
    echo "\n\n--- student/error_log ---\n";
    $lines = file($studentLog);
    echo implode("", array_slice($lines, -50));
}
?>
