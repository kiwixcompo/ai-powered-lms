<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) die("Assessment ID required.");

// Fetch Assessment Info
$stmt = $conn->prepare("
    SELECT a.*, c.title as course_title, c.code as course_code 
    FROM assessments a 
    JOIN courses c ON a.course_id = c.id 
    JOIN course_facilitators cf ON c.id = cf.course_id 
    WHERE a.id = ? AND cf.user_id = ?
");
$stmt->execute([$assessment_id, $_SESSION['user_id']]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) die("Assessment not found or unauthorized.");

// Auto-heal any legacy or unscaled grades in the database where total_score_awarded > total_score
try {
    $unscaled_check = $conn->prepare("
        SELECT g.id, g.total_score_awarded, a.total_score,
               (SELECT COUNT(*) FROM questions q WHERE q.assessment_id = a.id) as q_count
        FROM grades g
        JOIN assessments a ON g.assessment_id = a.id
        WHERE g.assessment_id = ? AND g.total_score_awarded > a.total_score
    ");
    $unscaled_check->execute([$assessment_id]);
    $unscaled_rows = $unscaled_check->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($unscaled_rows)) {
        $upd_stmt = $conn->prepare("UPDATE grades SET total_score_awarded = ? WHERE id = ?");
        foreach ($unscaled_rows as $row) {
            $raw = floatval($row['total_score_awarded']);
            $max_pts = floatval($row['total_score']);
            $q_cnt = intval($row['q_count'] ?: 20);
            $scaled = min($max_pts, max(0, round(($raw / $q_cnt) * $max_pts)));
            $upd_stmt->execute([$scaled, $row['id']]);
        }
    }
} catch (Exception $e) {}

// Fetch Students who completed it
$q_stmt = $conn->prepare("
    SELECT u.name, u.reg_no, g.total_score_awarded, g.graded_at 
    FROM grades g 
    JOIN users u ON g.student_id = u.id 
    WHERE g.assessment_id = ? 
    ORDER BY g.graded_at DESC
");
$q_stmt->execute([$assessment_id]);
$results = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - <?= htmlspecialchars($assessment['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom TSU Theme -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
    
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark tsu-navbar mb-4">
      <div class="container">
        <a class="navbar-brand" href="#"><img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="TSU"> Facilitator Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link text-white">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/facilitator">Back to Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/src/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><?= htmlspecialchars($assessment['course_code']) ?> - <?= htmlspecialchars($assessment['title']) ?></h2>
                <h5 class="text-muted">Total Points: <?= $assessment['total_score'] ?> | Target Modules: <?= $assessment['target_modules'] === 'ALL' ? 'Entire Course' : 'Selected' ?></h5>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/src/export_grades_excel.php?assessment_id=<?= $assessment_id ?>" class="btn btn-success">Export to Excel</a>
                <a href="<?php echo BASE_URL; ?>/facilitator" class="btn btn-secondary">Back</a>
            </div>
        </div>
        
        <div class="card tsu-card">
            <div class="card-body">
                <h5 class="card-title">Completed Assessments</h5>
                <?php if (count($results) > 0): ?>
                    <div class="table-responsive mt-3">
                        <table class="table table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Registration No</th>
                                    <th>Score Awarded</th>
                                    <th>Percentage</th>
                                    <th>Date Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $r): ?>
                                    <?php 
                                        $awarded = floatval($r['total_score_awarded']);
                                        $total = floatval($assessment['total_score']);
                                        $percentage = $total > 0 ? round(($awarded / $total) * 100, 2) . '%' : 'N/A';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['name']) ?></td>
                                        <td><?= htmlspecialchars($r['reg_no']) ?></td>
                                        <td><strong><?= $awarded ?></strong> / <?= $total ?></td>
                                        <td><?= $percentage ?></td>
                                        <td><?= date('M j, Y, g:i a', strtotime($r['graded_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-3 mb-0">No students have completed this assessment yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
