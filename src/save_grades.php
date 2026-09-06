<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $grades_json = $_POST['grades_json'] ?? '';
    $student_id = $_SESSION['user_id'];

    if ($assessment_id && !empty($grades_json)) {
        try {
            $grades = json_decode($grades_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($grades)) {
                die("Invalid grading data format returned by AI.");
            }
            
            // Check if it's an associative array instead of sequential array of grades
            if (isset($grades['id']) || isset($grades['choices'])) {
                die("AI returned a raw API response object instead of grades array.");
            }
            
            // If the AI returned a single object instead of an array of objects, wrap it
            if (isset($grades['question_id'])) {
                $grades = [$grades];
            }
            
            // Ensure every item is an array before looping to prevent string offset errors
            foreach ($grades as $g) {
                if (!is_array($g)) {
                    var_dump($grades);
                    die("Invalid grading data structure. Expected array of objects.");
                }
            }

            $conn->beginTransaction();

            // Self-healing migration for decimal support
            try {
                $conn->exec("ALTER TABLE questions MODIFY COLUMN max_score DECIMAL(5,2) NOT NULL DEFAULT 1.00");
                $conn->exec("ALTER TABLE responses MODIFY COLUMN score_awarded DECIMAL(5,2) DEFAULT NULL");
            } catch (Exception $e) {}

            $total_awarded = 0;
            $update_resp = $conn->prepare("UPDATE responses SET score_awarded = ? WHERE assessment_id = ? AND student_id = ? AND question_id = ?");

            foreach ($grades as $g) {
                $q_id = $g['question_id'];
                $score = floatval($g['score_awarded']);
                $total_awarded += $score;
                
                $update_resp->execute([$score, $assessment_id, $student_id, $q_id]);
            }

            // Fetch assessment target total score
            $a_stmt = $conn->prepare("SELECT total_score FROM assessments WHERE id = ?");
            $a_stmt->execute([$assessment_id]);
            $target_total_score = floatval($a_stmt->fetchColumn() ?: 5);

            // Fetch maximum possible score across all questions for this assessment
            $m_stmt = $conn->prepare("SELECT SUM(max_score) as total_max, COUNT(*) as q_count FROM questions WHERE assessment_id = ? AND (student_id = ? OR student_id IS NULL)");
            $m_stmt->execute([$assessment_id, $student_id]);
            $m_info = $m_stmt->fetch(PDO::FETCH_ASSOC);
            $total_max = floatval($m_info['total_max'] ?? 0);
            $q_count   = intval($m_info['q_count'] ?? 0);

            // Proportional scaling: if questions total_max != target_total_score (e.g. 20 raw questions vs 5 marks),
            // scale the student's score proportionally to the assessment's total marks.
            if ($total_max > 0 && $total_max != $target_total_score) {
                $scaled_score = ($total_awarded / $total_max) * $target_total_score;
                $rounded_total = min($target_total_score, max(0, round($scaled_score)));
            } elseif ($q_count > 0 && $total_awarded > $target_total_score) {
                $scaled_score = ($total_awarded / $q_count) * $target_total_score;
                $rounded_total = min($target_total_score, max(0, round($scaled_score)));
            } else {
                $rounded_total = min($target_total_score, max(0, round($total_awarded)));
            }

            // Save final grade
            $insert_grade = $conn->prepare("INSERT INTO grades (assessment_id, student_id, total_score_awarded) VALUES (?, ?, ?)");
            $insert_grade->execute([$assessment_id, $student_id, $rounded_total]);

            $conn->commit();
            
            $_SESSION['msg'] = "Assessment submitted successfully! Auto-graded score: {$rounded_total}";
            header("Location: " . BASE_URL . "/student");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            die("Error saving grades: " . $e->getMessage());
        }
    }
}
