<?php
require_once 'config/config.php';
$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) die("Assessment ID required.");

// Fetch Assessment
$stmt = $conn->prepare("SELECT a.*, c.title as course_title FROM assessments a JOIN courses c ON a.course_id = c.id WHERE a.id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) die("Assessment not found.");

if ($assessment['is_active'] == 0) {
    die("This assessment has been manually disabled by the facilitator.");
}

if (!empty($assessment['scheduled_date'])) {
    $sch_time = strtotime($assessment['scheduled_date']);
    if ($sch_time > time()) {
        die("This assessment is not yet open. It is scheduled for: " . date('F j, Y, g:i a', $sch_time));
    }
    
    // Auto disable threshold: scheduled_date + timer_minutes + 1 extra minute
    $end_time = $sch_time + ($assessment['timer_minutes'] * 60) + 60;
    if (time() > $end_time) {
        die("This assessment has been automatically closed as the time limit has expired.");
    }
}

// Check if already taken
$check = $conn->prepare("SELECT id FROM grades WHERE assessment_id = ? AND student_id = ?");
$check->execute([$assessment_id, $_SESSION['user_id']]);
if ($check->rowCount() > 0) {
    die("You have already completed this assessment.");
}

// Fetch Questions for this specific student
$q_stmt = $conn->prepare("SELECT id, question_text, question_type, options FROM questions WHERE assessment_id = ? AND student_id = ?");
$q_stmt->execute([$assessment_id, $_SESSION['user_id']]);
$questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no questions exist for this student, generate them uniquely on the fly
if (empty($questions)) {
    // 1. Fetch module contents
    $course_id = $assessment['course_id'];
    $target_modules = $assessment['target_modules'];
    
    $query = "SELECT title, content, is_pdf_mode, pdf_path FROM modules WHERE course_id = ?";
    $params = [$course_id];
    
    if ($target_modules !== 'ALL') {
        $ids = json_decode($target_modules, true);
        if (is_array($ids) && count($ids) > 0) {
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $query .= " AND id IN ($in)";
            $params = array_merge($params, $ids);
        }
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $combined_content = "Default course material fallback.";
    if (count($modules) > 0) {
        $combined_content = "";
        require_once __DIR__ . '/../vendor/autoload.php';
        $parser = new \Smalot\PdfParser\Parser();
        foreach ($modules as $m) {
            if ($m['is_pdf_mode'] && !empty($m['pdf_path'])) {
                $pdf_full_path = 'uploads/pdfs/' . $m['pdf_path'];
                if (file_exists($pdf_full_path)) {
                    try {
                        $pdf = $parser->parseFile($pdf_full_path);
                        $combined_content .= $pdf->getText() . " ";
                    } catch (Exception $e) {}
                }
            } else {
                $combined_content .= $m['content'] . " ";
            }
        }
    }
    
    // 2. Extract sentences
    $combined_content = preg_replace("/\r|\n/", " ", $combined_content);
    $sentences_raw = preg_split('/(?<=[.?!])\s+/', $combined_content, -1, PREG_SPLIT_NO_EMPTY);
    $sentences = [];
    foreach ($sentences_raw as $s) {
        $s = trim($s);
        if (strlen($s) > 20 && strpos($s, '#') === false && strpos($s, '*') === false) {
            $sentences[] = $s;
        }
    }
    if (count($sentences) < 10) {
        $sentences = array_fill(0, 10, "What is the primary function of this module component?");
    }
    
    // 3. Generate Unique Questions
    $num_questions = $assessment['num_questions'] ?? 5;
    $q_type_pref = $assessment['question_type'] ?? 'mcq';
    $per_question_score = round($assessment['total_score'] / max(1, $num_questions), 2);
    
    $insert_q = $conn->prepare("INSERT INTO questions (assessment_id, student_id, question_text, question_type, options, correct_answer, max_score) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    shuffle($sentences);
    
    for ($i = 0; $i < $num_questions; $i++) {
        $correct_ans = $sentences[$i % count($sentences)];
        
        $q_type = $q_type_pref;
        if ($q_type === 'mixed') {
            $q_type = (rand(0,1) == 0) ? 'mcq' : 'subjective';
        }
        
        if ($q_type === 'subjective') {
            // Re-map subjective to theory to match schema ENUM
            $q_type = 'theory';
            $insert_q->execute([
                $assessment_id,
                $_SESSION['user_id'],
                "Explain the following concept: " . $correct_ans,
                $q_type,
                null,
                $correct_ans,
                $per_question_score
            ]);
        } else {
            // MCQ
            $q_type = 'mcq';
            $opts = [$correct_ans];
            while (count($opts) < 4) {
                $rand = $sentences[array_rand($sentences)];
                if (!in_array($rand, $opts)) {
                    $opts[] = $rand;
                }
            }
            shuffle($opts);
            $insert_q->execute([
                $assessment_id,
                $_SESSION['user_id'],
                "Which of the following statements is true regarding this module?",
                $q_type,
                json_encode($opts),
                $correct_ans,
                $per_question_score
            ]);
        }
    }
    
    // 4. Re-fetch the generated questions
    $q_stmt->execute([$assessment_id, $_SESSION['user_id']]);
    $questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment - <?= htmlspecialchars($assessment['title'] ?? 'Course Exam') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom TSU Theme -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
    <style>
        body { background-color: #f0f2f5; user-select: none; }
        .timer-header { position: sticky; top: 0; z-index: 1000; background: #dc3545; color: white; padding: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="timer-header text-center fs-4">
    <strong>Time Remaining: <span id="timerDisplay">--:--</span></strong>
</div>

<div class="container mt-4 mb-5 pb-5">
    <div class="card tsu-card mb-4">
        <div class="card-body text-center">
            <h2><?= htmlspecialchars($assessment['course_title']) ?></h2>
            <h4 class="text-muted"><?= htmlspecialchars($assessment['title'] ?? 'Course Exam') ?></h4>
        </div>
    </div>

    <form id="assessmentForm" action="<?php echo BASE_URL; ?>/src/submit_assessment.php" method="POST">
        <input type="hidden" name="assessment_id" value="<?= $assessment_id ?>">
        
        <?php foreach ($questions as $index => $q): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5><?= ($index + 1) ?>. <?= htmlspecialchars($q['question_text']) ?></h5>
                    
                    <?php if ($q['question_type'] == 'mcq'): 
                        $options = json_decode($q['options'], true);
                    ?>
                        <?php if (is_array($options)): foreach ($options as $opt_index => $opt): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="q_<?= $q['id'] ?>" id="q_<?= $q['id'] ?>_<?= $opt_index ?>" value="<?= htmlspecialchars($opt) ?>">
                                <label class="form-check-label" for="q_<?= $q['id'] ?>_<?= $opt_index ?>">
                                    <?= htmlspecialchars($opt) ?>
                                </label>
                            </div>
                        <?php endforeach; endif; ?>
                    <?php else: ?>
                        <textarea class="form-control mt-3" name="q_<?= $q['id'] ?>" rows="4" placeholder="Type your answer here..."></textarea>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <button type="submit" class="btn btn-tsu-primary btn-lg w-100 mt-3 shadow">Submit Assessment</button>
    </form>
</div>

<script>
    // Timer Logic
    const timerMinutes = <?= $assessment['timer_minutes'] ?>;
    let timeRemaining = timerMinutes * 60; // in seconds

    const timerDisplay = document.getElementById('timerDisplay');
    const form = document.getElementById('assessmentForm');

    function updateTimer() {
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        
        timerDisplay.textContent = 
            (minutes < 10 ? "0" + minutes : minutes) + ":" + 
            (seconds < 10 ? "0" + seconds : seconds);
            
        if (timeRemaining <= 0) {
            clearInterval(interval);
            alert('Time is up! Your assessment is being submitted automatically.');
            form.submit();
        }
        
        timeRemaining--;
    }

    // Initialize timer
    updateTimer();
    const interval = setInterval(updateTimer, 1000);

    // Prevent accidental navigation
    window.onbeforeunload = function() {
        return "Are you sure you want to leave? Your assessment progress will be lost.";
    };
    
    // Remove warning upon intentional form submission
    form.addEventListener('submit', function() {
        window.onbeforeunload = null;
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body>
</html>
