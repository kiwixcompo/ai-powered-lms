<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = $_POST['course_id'] ?? null;
    $title = $_POST['title'] ?? 'Assessment';
    $timer = $_POST['timer_minutes'] ?? 30;
    $total_score = $_POST['total_score'] ?? 100;
    $scheduled_date = $_POST['scheduled_date'] ?? null;
    $num_questions = $_POST['num_questions'] ?? 5;
    $question_type = $_POST['question_type'] ?? 'mcq';
    $target_modules = isset($_POST['target_modules']) ? json_encode($_POST['target_modules']) : 'ALL';

    if (!$course_id) {
        die("Course ID required");
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO assessments (course_id, title, target_modules, timer_minutes, total_score, scheduled_date, num_questions, question_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $title, $target_modules, $timer, $total_score, $scheduled_date, $num_questions, $question_type]);
        $assessment_id = $conn->lastInsertId();
        
        $conn->commit();
        $_SESSION['msg'] = "Course assessment '$title' successfully generated and saved!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error saving assessment: " . $e->getMessage();
    }
}

header('Location: ' . BASE_URL . '/facilitator');
exit;
