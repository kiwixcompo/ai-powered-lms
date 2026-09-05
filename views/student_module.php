<?php
try {
require_once __DIR__ . '/../config/config.php';
$module_id = $_GET['id'] ?? null;
if (!$module_id) die("Module ID required.");

// Session guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// Insert/Update Attendance
$enrollment_stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = (SELECT course_id FROM modules WHERE id = ?)");
$enrollment_stmt->execute([$_SESSION['user_id'], $module_id]);
$enrollment = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) die("You are not enrolled in this course.");

$check_att = $conn->prepare("SELECT id FROM attendance WHERE enrollment_id = ? AND module_id = ?");
$check_att->execute([$enrollment['id'], $module_id]);
if ($check_att->rowCount() == 0) {
    $insert_att = $conn->prepare("INSERT INTO attendance (enrollment_id, module_id) VALUES (?, ?)");
    $insert_att->execute([$enrollment['id'], $module_id]);
}

// Fetch module
$stmt = $conn->prepare("SELECT m.*, c.title as course_title FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
$stmt->execute([$module_id]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$module) die("Module not found.");

// Fetch all assessments for this course
$assmt_stmt = $conn->prepare("SELECT * FROM assessments WHERE course_id = ? ORDER BY scheduled_date ASC");
$assmt_stmt->execute([$module['course_id']]);
$all_assessments = $assmt_stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorise assessments: taken, active/blocking, upcoming
$blocking_assessment = null;
$taken_assessments   = [];
$upcoming_assessments = [];

foreach ($all_assessments as $a) {
    $targets   = json_decode($a['target_modules'] ?? '[]', true);
    $is_target = ($a['target_modules'] === 'ALL' || (is_array($targets) && in_array((string)$module_id, $targets)));
    if (!$is_target) continue; // only show assessments that cover this module

    $scheduled = strtotime($a['scheduled_date']);
    $grade_chk = $conn->prepare("SELECT g.score FROM grades g WHERE g.assessment_id = ? AND g.student_id = ?");
    $grade_chk->execute([$a['id'], $_SESSION['user_id']]);
    $grade_row = $grade_chk->fetch(PDO::FETCH_ASSOC);

    if ($grade_row) {
        $a['student_score'] = $grade_row['score'];
        $taken_assessments[] = $a;
    } elseif ($scheduled && $scheduled <= time() && ($a['is_active'] ?? 1)) {
        // Active and not taken — blocks content
        if (!$blocking_assessment) $blocking_assessment = $a;
    } else {
        // Future or not yet active
        $upcoming_assessments[] = $a;
    }
}

// Parse Markdown content — load Parsedown directly to bypass autoloader issues on live server
require_once __DIR__ . '/../vendor/autoload.php';
if (!class_exists('Parsedown')) {
    require_once __DIR__ . '/../vendor/erusev/parsedown/Parsedown.php';
}
$parsedown    = new Parsedown();
$html_content = $parsedown->text($module['content'] ?? 'No content generated yet.');

$pdf_url = BASE_URL . '/uploads/pdfs/' . $module['pdf_path'];

} catch (\Throwable $e) {
    die("FATAL ERROR IN STUDENT MODULE: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($module['title']) ?> — TSU LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/logo.png" type="image/png">
    <style>
        body { background-color: #f0f2f5; }
        .module-header {
            background: linear-gradient(135deg, #1a3c5e 0%, #2d6a9f 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .nav-tabs .nav-link { font-weight: 600; color: #495057; }
        .nav-tabs .nav-link.active { color: #1a3c5e; border-bottom: 3px solid #1a3c5e; }
        .module-content { line-height: 1.9; font-size: 1.05rem; }
        .module-content h1, .module-content h2, .module-content h3 { margin-top: 1.8rem; margin-bottom: 0.8rem; color: #1a3c5e; }
        .module-content pre { background: #f4f4f4; border-left: 4px solid #2d6a9f; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        .module-content code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .module-content blockquote { border-left: 4px solid #2d6a9f; padding-left: 1rem; color: #666; }
        .assessment-card { border-left: 4px solid #1a3c5e; }
        .assessment-card.taken { border-left-color: #198754; }
        .assessment-card.upcoming { border-left-color: #fd7e14; }
        .assessment-card.active { border-left-color: #dc3545; }
        :fullscreen .pdf-viewer-container { width:100vw; height:100vh; padding:20px!important; border-radius:0!important; }
        :fullscreen #pdfCanvasContainer { height:calc(100vh - 60px)!important; }
        #pdfCanvas { max-width:100%; height:auto!important; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: #1a3c5e;">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/student">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" height="32" class="me-2" alt="TSU">TSU LMS
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">

    <!-- Module Header -->
    <div class="module-header mb-0 shadow">
        <small class="text-white-50"><i class="bi bi-book me-1"></i><?= htmlspecialchars($module['course_title']) ?></small>
        <h3 class="mt-1 mb-0 fw-bold"><?= htmlspecialchars($module['title']) ?></h3>
        <?php if ($blocking_assessment): ?>
            <span class="badge bg-danger mt-2"><i class="bi bi-lock-fill me-1"></i>Assessment Active — Content Hidden</span>
        <?php else: ?>
            <span class="badge bg-success mt-2"><i class="bi bi-check-circle me-1"></i>Content Available</span>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="card shadow border-0">
        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
            <ul class="nav nav-tabs" id="moduleTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab-notes">
                        <i class="bi bi-journal-text me-1"></i>Module Notes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-assessments">
                        <i class="bi bi-clipboard-check me-1"></i>Assessments
                        <?php $total = count($taken_assessments) + count($upcoming_assessments) + ($blocking_assessment ? 1 : 0); ?>
                        <?php if ($total > 0): ?>
                            <span class="badge bg-secondary ms-1"><?= $total ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4 tab-content">

            <!-- ===================== NOTES TAB ===================== -->
            <div class="tab-pane fade show active" id="tab-notes">
                <?php if ($blocking_assessment): ?>
                    <div class="alert alert-danger text-center py-4">
                        <i class="bi bi-lock-fill fs-2 d-block mb-2"></i>
                        <h5 class="fw-bold">Content Hidden</h5>
                        <p class="mb-3">The assessment <strong><?= htmlspecialchars($blocking_assessment['title']) ?></strong> is currently active and covers this module.<br>Content is hidden to maintain exam integrity.</p>
                        <a href="<?= BASE_URL ?>/student/assessment?id=<?= $blocking_assessment['id'] ?>" class="btn btn-danger fw-bold">
                            <i class="bi bi-pencil-square me-1"></i>Go Take Assessment
                        </a>
                    </div>

                <?php elseif ($module['is_pdf_mode'] && !empty($module['pdf_path'])): ?>
                    <style>
                        :fullscreen .pdf-viewer-container { width:100vw;height:100vh;padding:20px!important;border-radius:0!important;overflow:hidden; }
                        :fullscreen #pdfCanvasContainer { height:calc(100vh - 60px)!important; }
                    </style>
                    <div class="pdf-viewer-container bg-dark text-white rounded p-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2 gap-2">
                            <div>
                                <button id="prevPage" class="btn btn-sm btn-secondary">&laquo; Prev</button>
                                <button id="nextPage" class="btn btn-sm btn-secondary">Next &raquo;</button>
                                <span class="mx-3">Page: <span id="pageNum">1</span> / <span id="pageCount">?</span></span>
                            </div>
                            <div>
                                <button id="zoomOut" class="btn btn-sm btn-secondary">-</button>
                                <button id="zoomIn" class="btn btn-sm btn-secondary">+</button>
                                <button id="readingModeBtn" class="btn btn-sm btn-primary ms-2">📖 Reading Mode</button>
                            </div>
                        </div>
                        <div id="pdfCanvasContainer" class="text-center overflow-auto rounded" style="height:70vh;background:#525659;">
                            <canvas id="pdfCanvas" class="shadow-sm mt-3 mb-3"></canvas>
                        </div>
                    </div>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
                    <script>
                        const pdfjsLib = window["pdfjs-dist/build/pdf"] || window.pdfjsLib;
                        pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
                        let pdfDoc = null, pageNum = 1, pageIsRendering = false, pageNumIsPending = null, scale = 1.5;
                        const canvas = document.getElementById("pdfCanvas");
                        const ctx = canvas.getContext("2d");

                        const renderPage = num => {
                            pageIsRendering = true;
                            pdfDoc.getPage(num).then(page => {
                                const viewport = page.getViewport({ scale });
                                canvas.height = viewport.height;
                                canvas.width  = viewport.width;
                                page.render({ canvasContext: ctx, viewport }).promise.then(() => {
                                    pageIsRendering = false;
                                    if (pageNumIsPending !== null) { renderPage(pageNumIsPending); pageNumIsPending = null; }
                                });
                                document.getElementById("pageNum").textContent = num;
                            });
                        };
                        const queueRenderPage = num => pageIsRendering ? (pageNumIsPending = num) : renderPage(num);
                        document.getElementById("prevPage").addEventListener("click", () => { if (pageNum > 1) queueRenderPage(--pageNum); });
                        document.getElementById("nextPage").addEventListener("click", () => { if (pageNum < pdfDoc.numPages) queueRenderPage(++pageNum); });
                        document.getElementById("zoomIn").addEventListener("click", () => { scale += 0.25; queueRenderPage(pageNum); });
                        document.getElementById("zoomOut").addEventListener("click", () => { if (scale > 0.5) { scale -= 0.25; queueRenderPage(pageNum); } });

                        const readingModeBtn = document.getElementById("readingModeBtn");
                        const container = document.querySelector(".pdf-viewer-container");
                        readingModeBtn.addEventListener("click", () => {
                            if (!document.fullscreenElement) {
                                container.requestFullscreen();
                                readingModeBtn.innerHTML = "❌ Exit Reading Mode";
                                readingModeBtn.classList.replace("btn-primary","btn-danger");
                            } else {
                                document.exitFullscreen();
                            }
                        });
                        document.addEventListener("fullscreenchange", () => {
                            if (!document.fullscreenElement) {
                                readingModeBtn.innerHTML = "📖 Reading Mode";
                                readingModeBtn.classList.replace("btn-danger","btn-primary");
                            }
                        });
                        canvas.addEventListener("contextmenu", e => e.preventDefault());

                        fetch("<?= htmlspecialchars($pdf_url) ?>")
                            .then(r => { if(!r.ok) throw new Error("PDF file not found on server."); return r.blob(); })
                            .then(blob => pdfjsLib.getDocument(URL.createObjectURL(blob)).promise)
                            .then(doc => { pdfDoc = doc; document.getElementById("pageCount").textContent = doc.numPages; renderPage(1); })
                            .catch(err => { document.getElementById("pdfCanvasContainer").innerHTML = `<div class="alert alert-danger m-3">${err.message}</div>`; });
                    </script>

                <?php else: ?>
                    <div class="module-content">
                        <?= $html_content ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===================== ASSESSMENTS TAB ===================== -->
            <div class="tab-pane fade" id="tab-assessments">

                <?php if ($blocking_assessment): ?>
                <div class="mb-4">
                    <h6 class="text-danger fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i>Active Assessment</h6>
                    <div class="card assessment-card active shadow-sm">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($blocking_assessment['title']) ?></h6>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= $blocking_assessment['timer_minutes'] ?? '—' ?> mins &nbsp;|&nbsp; Opened: <?= date('M d, Y g:i a', strtotime($blocking_assessment['scheduled_date'])) ?></small>
                            </div>
                            <a href="<?= BASE_URL ?>/student/assessment?id=<?= $blocking_assessment['id'] ?>" class="btn btn-danger btn-sm fw-bold">
                                <i class="bi bi-pencil-square me-1"></i>Take Now
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($taken_assessments)): ?>
                <div class="mb-4">
                    <h6 class="text-success fw-bold mb-3"><i class="bi bi-check-circle-fill me-1"></i>Completed Assessments</h6>
                    <?php foreach ($taken_assessments as $ta): ?>
                    <div class="card assessment-card taken shadow-sm mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($ta['title']) ?></h6>
                                <small class="text-muted"><i class="bi bi-calendar-check me-1"></i><?= date('M d, Y', strtotime($ta['scheduled_date'])) ?></small>
                            </div>
                            <span class="badge bg-success fs-6 px-3 py-2">
                                <?= number_format($ta['student_score'], 1) ?> / <?= $ta['total_marks'] ?? 100 ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($upcoming_assessments)): ?>
                <div class="mb-4">
                    <h6 class="text-warning fw-bold mb-3"><i class="bi bi-calendar-event me-1"></i>Upcoming Assessments</h6>
                    <?php foreach ($upcoming_assessments as $ua): ?>
                    <div class="card assessment-card upcoming shadow-sm mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($ua['title']) ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?php if (!empty($ua['scheduled_date']) && strtotime($ua['scheduled_date'])): ?>
                                        Scheduled: <?= date('M d, Y g:i a', strtotime($ua['scheduled_date'])) ?>
                                    <?php else: ?>
                                        Date not yet set
                                    <?php endif; ?>
                                    &nbsp;|&nbsp;<i class="bi bi-clock me-1"></i><?= $ua['timer_minutes'] ?? '—' ?> mins
                                </small>
                            </div>
                            <span class="badge bg-warning text-dark px-3 py-2">Upcoming</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!$blocking_assessment && empty($taken_assessments) && empty($upcoming_assessments)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard fs-1 d-block mb-2"></i>
                    <p>No assessments are linked to this module yet.</p>
                </div>
                <?php endif; ?>

            </div><!-- /assessments tab -->

        </div>
    </div><!-- /card -->

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
