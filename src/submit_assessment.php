<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $student_id = $_SESSION['user_id'];

    if ($assessment_id) {
        $a_stmt = $conn->prepare("SELECT is_active, scheduled_date, timer_minutes FROM assessments WHERE id = ?");
        $a_stmt->execute([$assessment_id]);
        $assessment = $a_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assessment) die("Assessment not found.");
        if ($assessment['is_active'] == 0) die("Assessment has been disabled.");
        
        if (!empty($assessment['scheduled_date'])) {
            // Allow a 2-minute grace period for late network requests from the auto-submit
            $end_time = strtotime($assessment['scheduled_date']) + ($assessment['timer_minutes'] * 60) + 120;
            if (time() > $end_time) {
                die("Time expired. Assessment is automatically closed and submissions are no longer accepted.");
            }
        }
        // Fetch questions for this assessment for this student
        $q_stmt = $conn->prepare("SELECT id FROM questions WHERE assessment_id = ? AND student_id = ?");
        $q_stmt->execute([$assessment_id, $student_id]);
        $questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

        $conn->beginTransaction();
        try {
            // Check if already submitted
            $check = $conn->prepare("SELECT id FROM grades WHERE assessment_id = ? AND student_id = ?");
            $check->execute([$assessment_id, $student_id]);
            if ($check->rowCount() > 0) {
                die("Already submitted.");
            }

            // Save raw responses (score_awarded will be null initially until AI grades it)
            $insert_resp = $conn->prepare("INSERT INTO responses (assessment_id, student_id, question_id, answer_text) VALUES (?, ?, ?, ?)");
            
            foreach ($questions as $q) {
                $q_id = $q['id'];
                $answer = $_POST["q_{$q_id}"] ?? '';
                $insert_resp->execute([$assessment_id, $student_id, $q_id, $answer]);
            }

            $conn->commit();
            
            // Redirect to auto-grading endpoint (Step 7)
            header(\"Location: \" . BASE_URL . \"/CMP_Course_Module/src/auto_grade.php?assessment_id=$assessment_id");
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            die("Error submitting assessment: " . $e->getMessage());
        }
    }
}
