<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ' . BASE_URL . '/login'); exit;
}

try {
// ─── Pull all data ────────────────────────────────────────────────
// Enrolled courses
$stmt = $conn->prepare("SELECT c.id, c.code, c.title, c.description FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ? ORDER BY c.code");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// All assessments for enrolled courses (with grade if taken)
$all_assess_stmt = $conn->prepare("
    SELECT a.id, a.course_id, a.title, a.timer_minutes, a.total_score, a.scheduled_date, a.is_active, a.scores_released,
           c.code as course_code, c.title as course_title,
           g.total_score_awarded as student_score, g.id as grade_id
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN grades g ON g.assessment_id = a.id AND g.student_id = ?
    WHERE e.student_id = ?
    ORDER BY a.scheduled_date ASC
");
$all_assess_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$all_assessments = $all_assess_stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorise
$active_now = []; $upcoming = []; $completed = []; $closed = [];
foreach ($all_assessments as $a) {
    if ($a['grade_id'])       { $completed[] = $a; continue; }
    if ($a['is_active'] == 0) { $closed[]    = $a; continue; }
    if (!empty($a['scheduled_date'])) {
        $sch = strtotime($a['scheduled_date']);
        $end = $sch + ($a['timer_minutes'] * 60) + 60;
        if ($sch > time())     { $upcoming[]  = $a; }
        elseif (time() > $end) { $closed[]    = $a; }
        else                   { $active_now[] = $a; }
    } else {
        $active_now[] = $a;
    }
}
$total_courses   = count($courses);
$total_completed = count($completed);
$total_active    = count($active_now);
$total_upcoming  = count($upcoming);
} catch (\Throwable $e) {
    die("Dashboard error: " . $e->getMessage() . " (line " . $e->getLine() . ")");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — TSU LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
    <style>
        body { background: #f0f2f5; }

        /* ── Sidebar-style top nav ── */
        .tsu-topbar { background: linear-gradient(135deg,#1a3c5e 0%,#2d6a9f 100%); }

        /* ── Hero greeting ── */
        .hero-greeting {
            background: linear-gradient(135deg,#1a3c5e 0%,#2d6a9f 100%);
            color: #fff;
            padding: 2rem 2.5rem;
            border-radius: .75rem;
            margin-bottom: 2rem;
        }
        .hero-greeting h4 { font-weight: 700; margin-bottom: .25rem; }
        .hero-greeting p  { opacity: .85; margin: 0; }

        /* ── Stat cards ── */
        .stat-card { border: none; border-radius: .75rem; text-align: center; padding: 1.25rem 1rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .stat-card .stat-num { font-size: 2rem; font-weight: 800; }

        /* ── Main tabs ── */
        .main-tabs .nav-link { font-weight: 600; color: #495057; padding: .75rem 1.5rem; border-radius: 0; border-bottom: 3px solid transparent; }
        .main-tabs .nav-link.active { color: #1a3c5e; border-bottom-color: #1a3c5e; background: transparent; }
        .main-tabs .nav-link:hover { color: #1a3c5e; }

        /* ── Course accordion ── */
        .course-card { border: none; border-radius: .75rem; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.07); margin-bottom: 1rem; }
        .course-card .course-header { background: linear-gradient(90deg,#1a3c5e,#2d6a9f); color: #fff; padding: 1rem 1.5rem; cursor: pointer; transition: opacity .15s; }
        .course-card .course-header:hover { opacity: .92; }
        .course-card .course-header h6 { margin: 0; font-weight: 700; letter-spacing: .02em; }
        .module-row { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1rem; border-bottom: 1px solid #f0f2f5; gap: .5rem; flex-wrap: wrap; }
        .module-row:last-child { border-bottom: none; }
        .module-num { font-weight: 700; color: #1a3c5e; min-width: 2rem; }

        /* ── Assessment cards ── */
        .assess-section-title { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #6c757d; margin: 1.5rem 0 .6rem; }
        .assess-card {
            background: #fff;
            border-radius: .6rem;
            box-shadow: 0 1px 8px rgba(0,0,0,.06);
            border-left: 5px solid #dee2e6;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: .6rem;
            transition: box-shadow .15s;
        }
        .assess-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.12); }
        .assess-card.active-now  { border-left-color: #dc3545; }
        .assess-card.upcoming    { border-left-color: #fd7e14; }
        .assess-card.completed   { border-left-color: #198754; }
        .assess-card.closed      { border-left-color: #6c757d; }
        .assess-card .assess-title { font-weight: 700; color: #212529; }
        .assess-card .assess-meta  { font-size: .82rem; color: #6c757d; margin-top: .15rem; }
        .score-badge { font-size: 1.1rem; font-weight: 800; color: #198754; }
    </style>
</head>
<body>



<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark tsu-topbar mb-0">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" height="32" class="me-2" alt="TSU">TSU LMS
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center gap-1">
        <li class="nav-item"><span class="nav-link text-white-50 small">Welcome, <strong class="text-white"><?= htmlspecialchars($_SESSION['name']) ?></strong></span></li>
        <li class="nav-item"><a class="btn btn-sm btn-outline-light ms-2" href="<?= BASE_URL ?>/src/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">

    <!-- Hero -->
    <div class="hero-greeting d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4><i class="bi bi-mortarboard-fill me-2"></i>Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>!</h4>
            <p>Track your courses, notes, and assessments all in one place.</p>
        </div>
        <img src="<?= BASE_URL ?>/assets/images/logo.png" height="60" style="opacity:.85" alt="TSU">
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-num text-primary"><?= $total_courses ?></div>
                <div class="small text-muted">Enrolled Courses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-num text-danger"><?= $total_active ?></div>
                <div class="small text-muted">Active Now</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-num text-warning"><?= $total_upcoming ?></div>
                <div class="small text-muted">Upcoming</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-num text-success"><?= $total_completed ?></div>
                <div class="small text-muted">Completed</div>
            </div>
        </div>
    </div>

    <!-- Alert: active assessments -->
    <?php if ($total_active > 0): ?>
    <div class="alert alert-danger d-flex align-items-center gap-3 mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
        <div>
            <strong><?= $total_active ?> assessment<?= $total_active > 1 ? 's are' : ' is' ?> open right now!</strong>
            Click the <strong>Assessments</strong> tab below to take them before they close.
        </div>
    </div>
    <?php endif; ?>

    <!-- Main tabs -->
    <ul class="nav main-tabs border-bottom mb-0 bg-white px-3 rounded-top" id="dashTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-courses">
                <i class="bi bi-book me-1"></i>My Courses
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-assessments">
                <i class="bi bi-clipboard-check me-1"></i>My Assessments
                <?php if ($total_active > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $total_active ?></span>
                <?php elseif ($total_upcoming > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= $total_upcoming ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <div class="tab-content bg-white rounded-bottom shadow-sm p-4">

        <!-- ═══════════════ COURSES TAB ═══════════════ -->
        <div class="tab-pane fade show active" id="tab-courses">
            <?php if (empty($courses)): ?>
                <div class="alert alert-warning text-center py-4">
                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                    You are not enrolled in any courses yet. Contact your administrator.
                </div>
            <?php else: ?>
                <div id="courseAccordion">
                <?php foreach ($courses as $idx => $c):
                    $mod_stmt = $conn->prepare("SELECT id, title, order_num FROM modules WHERE course_id = ? AND is_active = 1 ORDER BY order_num ASC");
                    $mod_stmt->execute([$c['id']]);
                    $modules = $mod_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $open = $idx === 0 ? 'show' : '';
                ?>
                <div class="course-card">
                    <div class="course-header d-flex justify-content-between align-items-center"
                         data-bs-toggle="collapse" data-bs-target="#course_<?= $c['id'] ?>">
                        <div>
                            <span class="badge bg-light text-dark me-2 fw-normal"><?= htmlspecialchars($c['code']) ?></span>
                            <span class="fw-bold"><?= htmlspecialchars($c['title']) ?></span>
                        </div>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div id="course_<?= $c['id'] ?>" class="collapse <?= $open ?>" data-bs-parent="#courseAccordion">
                        <div class="p-3">
                            <?php if ($c['description']): ?>
                                <p class="text-muted small mb-3"><?= htmlspecialchars($c['description']) ?></p>
                            <?php endif; ?>

                            <?php if (empty($modules)): ?>
                                <div class="alert alert-info py-2 small">No modules available yet.</div>
                            <?php else: ?>
                                <h6 class="assess-section-title"><i class="bi bi-journals me-1"></i>Course Modules</h6>
                                <?php foreach ($modules as $m):
                                    // Check grade for this module
                                    $gr = $conn->prepare("SELECT g.total_score_awarded, a.total_score, a.scores_released FROM grades g JOIN assessments a ON g.assessment_id = a.id WHERE a.module_id = ? AND g.student_id = ?");
                                    $gr->execute([$m['id'], $_SESSION['user_id']]);
                                    $grade = $gr->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <div class="module-row">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="module-num"><?= $m['order_num'] ?>.</span>
                                        <span><?= htmlspecialchars($m['title']) ?></span>
                                    </div>
                                    <?php if ($grade): ?>
                                        <?php if ($grade['scores_released']): ?>
                                            <span class="badge bg-success px-3 py-2">Score: <?= $grade['total_score_awarded'] ?>/<?= $grade['total_score'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark px-3 py-2">Graded — Awaiting Release</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/student/module?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-journal-text me-1"></i>View Notes
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════ ASSESSMENTS TAB ═══════════════ -->
        <div class="tab-pane fade" id="tab-assessments">

            <?php
            // Helper to render an assessment card
            function renderAssessCard(array $a, string $type, string $base_url): void {
                $class_map = ['active-now'=>'active-now','upcoming'=>'upcoming','completed'=>'completed','closed'=>'closed'];
                $card_class = $class_map[$type] ?? '';
                echo "<div class='assess-card {$card_class}'>";
                echo "<div>";
                echo "<span class='badge bg-secondary mb-1'>".htmlspecialchars($a['course_code'])."</span> ";
                echo "<div class='assess-title'>".htmlspecialchars($a['title'])."</div>";
                $meta = [];
                if (!empty($a['timer_minutes'])) $meta[] = "<i class='bi bi-clock me-1'></i>{$a['timer_minutes']} mins";
                if (!empty($a['scheduled_date'])) $meta[] = "<i class='bi bi-calendar me-1'></i>".date('M d, Y g:i a', strtotime($a['scheduled_date']));
                echo "<div class='assess-meta'>".implode(' &nbsp;|&nbsp; ', $meta)."</div>";
                echo "</div>";

                if ($type === 'active-now') {
                    echo "<a href='{$base_url}/student/assessment?id={$a['id']}' class='btn btn-danger btn-sm fw-bold'><i class='bi bi-pencil-square me-1'></i>Take Now</a>";
                } elseif ($type === 'upcoming') {
                    $sch = date('M d, g:i a', strtotime($a['scheduled_date']));
                    echo "<span class='badge bg-warning text-dark px-3 py-2'><i class='bi bi-lock me-1'></i>Opens {$sch}</span>";
                } elseif ($type === 'completed') {
                    if ($a['scores_released']) {
                        $score = number_format($a['student_score'], 1);
                        $total = $a['total_score'] ?? 100;
                        $pct   = $total > 0 ? round(($a['student_score'] / $total) * 100) : 0;
                        $col   = $pct >= 50 ? '#198754' : '#dc3545';
                        echo "<div class='text-end'><div class='score-badge' style='color:{$col}'>{$score}/{$total}</div><div class='small text-muted'>{$pct}%</div></div>";
                    } else {
                        echo "<span class='badge bg-info text-dark px-3 py-2'>Graded — Awaiting Release</span>";
                    }
                } else {
                    echo "<span class='badge bg-secondary px-3 py-2'>Closed</span>";
                }
                echo "</div>";
            }
            ?>

            <!-- Active Now -->
            <?php if (!empty($active_now)): ?>
            <p class="assess-section-title"><i class="bi bi-exclamation-circle-fill text-danger me-1"></i>Active — Take Now</p>
            <?php foreach ($active_now as $a) renderAssessCard($a, 'active-now', BASE_URL); ?>
            <?php endif; ?>

            <!-- Upcoming -->
            <?php if (!empty($upcoming)): ?>
            <p class="assess-section-title"><i class="bi bi-calendar-event text-warning me-1"></i>Upcoming</p>
            <?php foreach ($upcoming as $a) renderAssessCard($a, 'upcoming', BASE_URL); ?>
            <?php endif; ?>

            <!-- Completed -->
            <?php if (!empty($completed)): ?>
            <p class="assess-section-title"><i class="bi bi-check-circle-fill text-success me-1"></i>Completed</p>
            <?php foreach ($completed as $a) renderAssessCard($a, 'completed', BASE_URL); ?>
            <?php endif; ?>

            <!-- Closed -->
            <?php if (!empty($closed)): ?>
            <p class="assess-section-title"><i class="bi bi-x-circle text-secondary me-1"></i>Closed / Expired</p>
            <?php foreach ($closed as $a) renderAssessCard($a, 'closed', BASE_URL); ?>
            <?php endif; ?>

            <?php if (empty($active_now) && empty($upcoming) && empty($completed) && empty($closed)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clipboard fs-1 d-block mb-2"></i>
                <p>No assessments found for your enrolled courses yet.</p>
            </div>
            <?php endif; ?>

        </div><!-- /assessments tab -->
    </div><!-- /tab-content -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // If there are active assessments, auto-open the assessments tab
    <?php if ($total_active > 0): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const tab = document.querySelector('[href="#tab-assessments"]');
        if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
    });
    <?php endif; ?>
</script>
</body>
</html>
