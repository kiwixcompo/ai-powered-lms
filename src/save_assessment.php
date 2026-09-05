<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id      = $_POST['course_id'] ?? null;
    $title          = $_POST['title'] ?? 'Assessment';
    $timer          = intval($_POST['timer_minutes'] ?? 30);
    $total_score    = intval($_POST['total_score'] ?? 5);   // INTEGER marks only
    $scheduled_date = $_POST['scheduled_date'] ?? null;
    $num_questions  = intval($_POST['num_questions'] ?? 5);
    $question_type  = $_POST['question_type'] ?? 'mcq';
    $target_modules = isset($_POST['target_modules']) ? json_encode($_POST['target_modules']) : 'ALL';

    $ca_max = 30; // Maximum CA marks for the entire course

    if (!$course_id) {
        die("Course ID required");
    }

    // ── Server-side cap enforcement ─────────────────────────────────
    $used_stmt = $conn->prepare("SELECT COALESCE(SUM(total_score),0) FROM assessments WHERE course_id = ?");
    $used_stmt->execute([$course_id]);
    $used = (int)$used_stmt->fetchColumn();

    $remaining = $ca_max - $used;

    if ($total_score < 1) {
        $_SESSION['error'] = "Marks must be at least 1.";
        header('Location: ' . BASE_URL . '/facilitator');
        exit;
    }

    if ($total_score > $remaining) {
        $_SESSION['error'] = "Cannot allocate {$total_score} marks — only {$remaining} of the {$ca_max} CA marks remain for this course.";
        header('Location: ' . BASE_URL . '/facilitator');
        exit;
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO assessments (course_id, title, target_modules, timer_minutes, total_score, scheduled_date, num_questions, question_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $title, $target_modules, $timer, $total_score, $scheduled_date, $num_questions, $question_type]);

        $conn->commit();
        $_SESSION['msg'] = "Assessment '{$title}' saved! Marks allocated: {$total_score}/{$ca_max}.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error saving assessment: " . $e->getMessage();
    }
}

header('Location: ' . BASE_URL . '/facilitator');
exit;
