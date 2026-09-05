<?php
require_once 'config/config.php';
$module_id = $_GET['id'] ?? null;
if (!$module_id) die("Module ID required.");

// Insert/Update Attendance
$enrollment_stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = (SELECT course_id FROM modules WHERE id = ?)");
$enrollment_stmt->execute([$_SESSION['user_id'], $module_id]);
$enrollment = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);

if ($enrollment) {
    // Record attendance
    $att_stmt = $conn->prepare("INSERT INTO attendance (enrollment_id, module_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE attended_at = CURRENT_TIMESTAMP");
    // SQLite/MySQL standard might not support ON DUPLICATE KEY if no unique constraint, so let's just insert if not exists
    $check_att = $conn->prepare("SELECT id FROM attendance WHERE enrollment_id = ? AND module_id = ?");
    $check_att->execute([$enrollment['id'], $module_id]);
    if ($check_att->rowCount() == 0) {
        $insert_att = $conn->prepare("INSERT INTO attendance (enrollment_id, module_id) VALUES (?, ?)");
        $insert_att->execute([$enrollment['id'], $module_id]);
    }
} else {
    die("You are not enrolled in this course.");
}

$stmt = $conn->prepare("SELECT m.*, c.title as course_title FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
$stmt->execute([$module_id]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

// Check for any active assessment covering this module
$assmt_stmt = $conn->prepare("SELECT * FROM assessments WHERE course_id = ?");
$assmt_stmt->execute([$module['course_id']]);
$assessments = $assmt_stmt->fetchAll(PDO::FETCH_ASSOC);

$blocking_assessment = null;
foreach ($assessments as $a) {
    // Check if this assessment targets this module
    $targets = json_decode($a['target_modules'] ?? '[]', true);
    if ($a['target_modules'] === 'ALL' || (is_array($targets) && in_array((string)$module_id, $targets))) {
        // Is it active?
        $scheduled = strtotime($a['scheduled_date']);
        if ($scheduled && $scheduled <= time()) {
            // Check if student has already taken it
            $grade_chk = $conn->prepare("SELECT id FROM grades WHERE assessment_id = ? AND student_id = ?");
            $grade_chk->execute([$a['id'], $_SESSION['user_id']]);
            if ($grade_chk->rowCount() == 0) {
                // Not taken, and active! It blocks the content.
                $blocking_assessment = $a;
                break;
            }
        }
    }
}

// Parse Markdown content simply using parsedown (if available, else basic replacing)
require_once 'vendor/autoload.php';
$parsedown = new Parsedown();
$html_content = $parsedown->text($module['content'] ?? 'No content generated yet.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($module['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="/CMP_Course_Module/student">Student Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="/CMP_Course_Module/student">Back to Dashboard</a></li></ul></div>
  </div>
</nav>

<div class="container mt-5">
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><?= htmlspecialchars($module['course_title']) ?> - <?= htmlspecialchars($module['title']) ?></h4>
        </div>
        <div class="card-body p-5">
            <?php
            // Hide content if there is an active assessment that they haven't taken yet (to prevent cheating)
            if ($blocking_assessment) {
                echo '<div class="alert alert-danger text-center">';
                echo '<h4>Content Hidden</h4>';
                echo '<p>An exam ('.htmlspecialchars($blocking_assessment['title']).') covering this module is currently active.</p>';
                echo '<p>The course content is hidden to prevent cheating while the assessment is open.</p>';
                echo '<a href="/CMP_Course_Module/student/assessment?id='.$blocking_assessment['id'].'" class="btn btn-warning mt-3 fw-bold">Go Take Assessment</a>';
                echo '</div>';
            } else {
                if ($module['is_pdf_mode'] && !empty($module['pdf_path'])) {
                    
                    $pdf_full_path = 'uploads/pdfs/' . $module['pdf_path'];
                    $pdf_b64 = file_exists($pdf_full_path) ? base64_encode(file_get_contents($pdf_full_path)) : '';

                    echo '
                    <style>
                        :fullscreen .pdf-viewer-container {
                            width: 100vw;
                            height: 100vh;
                            padding: 20px !important;
                            border-radius: 0 !important;
                            overflow: hidden;
                        }
                        :fullscreen #pdfCanvasContainer {
                            height: calc(100vh - 60px) !important;
                        }
                        #pdfCanvas {
                            max-width: 100%;
                            height: auto !important;
                        }
                    </style>
                    <div class="pdf-viewer-container bg-dark text-white rounded p-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2 gap-2">
                            <div>
                                <button id="prevPage" class="btn btn-sm btn-secondary">&laquo; Prev</button>
                                <button id="nextPage" class="btn btn-sm btn-secondary">Next &raquo;</button>
                                <span class="mx-3">Page: <span id="pageNum">1</span> / <span id="pageCount">?</span></span>
                            </div>
                            <div>
                                <button id="zoomOut" class="btn btn-sm btn-secondary" title="Zoom Out">-</button>
                                <button id="zoomIn" class="btn btn-sm btn-secondary" title="Zoom In">+</button>
                                <button id="readingModeBtn" class="btn btn-sm btn-primary ms-2">📖 Reading Mode</button>
                            </div>
                        </div>
                        <div id="pdfCanvasContainer" class="text-center overflow-auto rounded" style="height: 70vh; background: #525659;">
                            <canvas id="pdfCanvas" class="shadow-sm mt-3 mb-3"></canvas>
                        </div>
                    </div>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
                    <script>
                        // Make sure pdfjsLib is correctly mapped
                        const pdfjsLib = window["pdfjs-dist/build/pdf"] || window.pdfjsLib;
                        
                        pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
                        let pdfDoc = null,
                            pageNum = 1,
                            pageIsRendering = false,
                            pageNumIsPending = null,
                            scale = 1.5,
                            canvas = document.getElementById("pdfCanvas"),
                            ctx = canvas.getContext("2d");

                        const renderPage = num => {
                            pageIsRendering = true;
                            pdfDoc.getPage(num).then(page => {
                                const viewport = page.getViewport({ scale });
                                canvas.height = viewport.height;
                                canvas.width = viewport.width;

                                const renderCtx = {
                                    canvasContext: ctx,
                                    viewport: viewport
                                };

                                page.render(renderCtx).promise.then(() => {
                                    pageIsRendering = false;
                                    if (pageNumIsPending !== null) {
                                        renderPage(pageNumIsPending);
                                        pageNumIsPending = null;
                                    }
                                }).catch(err => {
                                    console.error("Render Error: ", err);
                                });

                                document.getElementById("pageNum").textContent = num;
                            }).catch(err => {
                                console.error("Get Page Error: ", err);
                            });
                        };

                        const queueRenderPage = num => {
                            if (pageIsRendering) {
                                pageNumIsPending = num;
                            } else {
                                renderPage(num);
                            }
                        };

                        const onPrevPage = () => {
                            if (pageNum <= 1) return;
                            pageNum--;
                            queueRenderPage(pageNum);
                        };

                        const onNextPage = () => {
                            if (pageNum >= pdfDoc.numPages) return;
                            pageNum++;
                            queueRenderPage(pageNum);
                        };

                        const onZoomIn = () => { scale += 0.25; queueRenderPage(pageNum); };
                        const onZoomOut = () => { if (scale <= 0.5) return; scale -= 0.25; queueRenderPage(pageNum); };

                        const readingModeBtn = document.getElementById("readingModeBtn");
                        const container = document.querySelector(".pdf-viewer-container");
                        readingModeBtn.addEventListener("click", () => {
                            if (!document.fullscreenElement) {
                                container.requestFullscreen().catch(err => {
                                    alert(`Error attempting to enable fullscreen: ${err.message}`);
                                });
                                readingModeBtn.innerHTML = "❌ Exit Reading Mode";
                                readingModeBtn.classList.replace("btn-primary", "btn-danger");
                            } else {
                                document.exitFullscreen();
                            }
                        });

                        document.addEventListener("fullscreenchange", () => {
                            if (!document.fullscreenElement) {
                                readingModeBtn.innerHTML = "📖 Reading Mode";
                                readingModeBtn.classList.replace("btn-danger", "btn-primary");
                            }
                        });

                        document.getElementById("prevPage").addEventListener("click", onPrevPage);
                        document.getElementById("nextPage").addEventListener("click", onNextPage);
                        document.getElementById("zoomIn").addEventListener("click", onZoomIn);
                        document.getElementById("zoomOut").addEventListener("click", onZoomOut);

                        // Completely bypass IDM and network requests by embedding the PDF directly into the page as Base64
                        const pdfBase64 = "'.$pdf_b64.'";
                        
                        if (!pdfBase64) {
                            document.getElementById("pdfCanvasContainer").innerHTML = `<div class="alert alert-danger m-3">Failed to load PDF. File missing on server.</div>`;
                        } else {
                            // Decode base64 to Uint8Array
                            const binaryString = atob(pdfBase64);
                            const bytes = new Uint8Array(binaryString.length);
                            for (let i = 0; i < binaryString.length; i++) {
                                bytes[i] = binaryString.charCodeAt(i);
                            }

                            // Load the PDF directly from memory
                            pdfjsLib.getDocument({ data: bytes }).promise.then(pdfDoc_ => {
                                pdfDoc = pdfDoc_;
                                document.getElementById("pageCount").textContent = pdfDoc.numPages;
                                renderPage(pageNum);
                            }).catch(err => {
                                console.error("PDF Load Error: ", err);
                                document.getElementById("pdfCanvasContainer").innerHTML = `<div class="alert alert-danger m-3">Failed to load PDF. Error: ${err.message || err}</div>`;
                            });
                        }
                        
                        // Disable right-click to make it harder to download or save images
                        document.getElementById("pdfCanvas").addEventListener("contextmenu", e => e.preventDefault());
                    </script>
                    ';
                } else {
                    echo $html_content;
                }
            }
            ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body>
</html>
