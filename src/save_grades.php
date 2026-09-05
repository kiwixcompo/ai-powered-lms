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

            $total_awarded = 0;
            $update_resp = $conn->prepare("UPDATE responses SET score_awarded = ? WHERE assessment_id = ? AND student_id = ? AND question_id = ?");

            foreach ($grades as $g) {
                $q_id = $g['question_id'];
                $score = floatval($g['score_awarded']);
                $total_awarded += $score;
                
                $update_resp->execute([$score, $assessment_id, $student_id, $q_id]);
            }

            // The user requested: "if it's a decimal number, the system is expected to provide a round figure as the score."
            $rounded_total = round($total_awarded);

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
