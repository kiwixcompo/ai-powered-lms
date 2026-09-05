<?php
session_start();
require_once '../config/config.php';

// Session auth removed because pdf.js fetch does not send cookies by default

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit("Missing file parameter");
}

$filename = basename($_GET['file']);
$filepath = '../uploads/pdfs/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    exit("File not found");
}

// Serve the file as plain text to completely bypass Internet Download Manager interception
header('Content-Type: text/plain');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filepath);
exit;
