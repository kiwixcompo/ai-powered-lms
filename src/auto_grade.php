<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit("Forbidden");
}

$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) die("Assessment ID missing.");

// Fetch Assessment total score
$a_stmt = $conn->prepare("SELECT total_score FROM assessments WHERE id = ?");
$a_stmt->execute([$assessment_id]);
$assessment_total_score = floatval($a_stmt->fetchColumn() ?: 5);

// Fetch questions and student responses
$stmt = $conn->prepare("
    SELECT q.id as question_id, q.question_text, q.question_type, q.correct_answer, q.max_score, r.answer_text 
    FROM questions q 
    JOIN responses r ON q.id = r.question_id 
    WHERE q.assessment_id = ? AND r.student_id = ?
");
$stmt->execute([$assessment_id, $_SESSION['user_id']]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$data) die("No responses found to grade.");

// Divide total score by total number of questions to find score per question
$total_questions = count($data);
$score_per_question = $total_questions > 0 ? ($assessment_total_score / $total_questions) : 1.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto-Grading in Progress...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Removed puter.js -->
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light text-center">
    <div>
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
        <h4>AI is evaluating your assessment...</h4>
        <p class="text-muted">Please do not close this window.</p>
        <form id="saveGradesForm" action="<?php echo BASE_URL; ?>/src/save_grades.php" method="POST" class="d-none">
            <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
            <input type="hidden" name="grades_json" id="gradesJson">
        </form>
    </div>

    <script>
        const assessmentData = <?= json_encode($data) ?>;
        const scorePerQuestion = <?= json_encode($score_per_question) ?>;
        const assessmentTotal = <?= json_encode($assessment_total_score) ?>;
        
        async function runAutoGrader() {
            try {
                let grades = [];
                
                for (let row of assessmentData) {
                    // System divides total score by total number of questions
                    let max = scorePerQuestion;
                    let correct = String(row.correct_answer || "").toLowerCase().trim();
                    let student = String(row.answer_text || "").toLowerCase().trim();
                    
                    let score = 0;
                    
                    // If the student left it blank, they get 0 (avoids empty string matching correct)
                    if (student === "") {
                        score = 0;
                    }
                    else if (correct === student) {
                        score = max;
                    } else if (correct.length > 3 && student.includes(correct)) {
                        score = max;
                    } else if (student.length > 3 && correct.includes(student)) {
                        score = max / 2; // Exact half for partial matching
                    } else if (student.length > 10) {
                        // Give a tiny bit of credit for trying if it's a long answer
                        score = max * 0.1; 
                    }
                    
                    grades.push({
                        question_id: row.question_id,
                        score_awarded: score,
                        feedback: "Locally auto-graded."
                    });
                }
                
                document.getElementById('gradesJson').value = JSON.stringify(grades);
                document.getElementById('saveGradesForm').submit();

            } catch (err) {
                alert("An error occurred during local auto-grading: " + err.message);
                console.error(err);
            }
        }

        // Start grading immediately
        window.onload = runAutoGrader;
    </script>
</body>
</html>
