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
        $assessment_id = $conn->lastInsertId();

        // Process AI generated questions if provided
        $ai_questions_json = $_POST['ai_questions'] ?? '';
        if (!empty($ai_questions_json)) {
            $questions = json_decode($ai_questions_json, true);
            if (is_array($questions) && count($questions) > 0) {
                // Self-healing migration for decimal support on live server
                try {
                    $conn->exec("ALTER TABLE questions MODIFY COLUMN max_score DECIMAL(5,2) NOT NULL DEFAULT 1.00");
                    $conn->exec("ALTER TABLE responses MODIFY COLUMN score_awarded DECIMAL(5,2) DEFAULT NULL");
                } catch (Exception $e) {}

                // Determine max score per question based on total marks
                $per_question_score = round($total_score / count($questions), 2);
                
                $q_stmt = $conn->prepare("INSERT INTO questions (assessment_id, student_id, question_text, question_type, options, correct_answer, max_score) VALUES (?, NULL, ?, ?, ?, ?, ?)");
                
                foreach ($questions as $q) {
                    $q_text = $q['question_text'] ?? 'Question';
                    $q_type = $q['question_type'] ?? 'mcq';
                    $opts   = (isset($q['options']) && is_array($q['options'])) ? json_encode($q['options']) : null;
                    $ans    = $q['correct_answer'] ?? '';
                    
                    $q_stmt->execute([
                        $assessment_id,
                        $q_text,
                        $q_type,
                        $opts,
                        $ans,
                        $per_question_score
                    ]);
                }
            }
        }

        $conn->commit();
        $_SESSION['msg'] = "Assessment '{$title}' generated and saved! Marks allocated: {$total_score}/{$ca_max}.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error saving assessment: " . $e->getMessage();
    }
}

header('Location: ' . BASE_URL . '/facilitator');
exit;
