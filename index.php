<?php
require_once 'config/config.php';

$request = $_SERVER['REQUEST_URI'];
$base_path = BASE_URL;

$path = str_replace($base_path, '', $request);
$path = parse_url($path, PHP_URL_PATH);

// Middleware for checking auth
function checkAuth($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo "403 Forbidden - You don't have permission to access this page.";
        exit;
    }
}

switch ($path) {
    case '':
    case '/':
    case '/login':
        require 'views/login.php';
        break;
    case '/admin':
        checkAuth(['admin']);
        require 'views/admin_dashboard.php';
        break;
    case '/facilitator':
        checkAuth(['facilitator']);
        require 'views/facilitator_dashboard.php';
        break;
    case '/facilitator/module':
        checkAuth(['facilitator']);
        require 'views/manage_module.php';
        break;
    case '/student':
        checkAuth(['student']);
        require 'views/student_dashboard.php';
        break;
    case '/student/module':
        checkAuth(['student']);
        require 'views/student_module.php';
        break;
    case '/student/assessment':
        checkAuth(['student']);
        require 'views/student_assessment.php';
        break;
    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
?>
