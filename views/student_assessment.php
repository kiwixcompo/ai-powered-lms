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

// Fetch Questions
// If this is a modern assessment, questions are generated globally (student_id IS NULL)
// If it's a legacy assessment, they might be generated per student
$q_stmt = $conn->prepare("SELECT id, question_text, question_type, options FROM questions WHERE assessment_id = ? AND (student_id = ? OR student_id IS NULL)");
$q_stmt->execute([$assessment_id, $_SESSION['user_id']]);
$questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("No questions found for this assessment. Please contact your facilitator.");
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
