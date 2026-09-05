<?php
require_once 'config/config.php';
$module_id = $_GET['id'] ?? null;
if (!$module_id) die("Module ID required.");

$stmt = $conn->prepare("SELECT m.*, c.title as course_title FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
$stmt->execute([$module_id]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) die("Module not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Module - <?= htmlspecialchars($module['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .spinner-border { display: none; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">Course Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/facilitator">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/src/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-5">
    <h2 class="mb-4">Course: <?= htmlspecialchars($module['course_title']) ?> - Module: <?= htmlspecialchars($module['title']) ?></h2>

    <div class="row">
        <!-- AI Content Generation Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Manage Course Content</h5>
                </div>
                <div class="card-body">
                    <form id="saveContentForm" action="/src/save_module_content.php" method="POST">
                        <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Module Content (Markdown)</label>
                            <textarea name="content" id="moduleContent" class="form-control" rows="20"><?= htmlspecialchars($module['content'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Save Content to Database</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- PDF Upload Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">PDF Mode</h5>
                </div>
                <div class="card-body">
                    <?php if ($module['is_pdf_mode'] && !empty($module['pdf_path'])): ?>
                        <div class="alert alert-success">
                            This module is currently in PDF Mode. Students will see the uploaded PDF instead of the Markdown text.
                            <br><br>
                            <a href="/uploads/pdfs/<?= htmlspecialchars($module['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-outline-dark">View Current PDF</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Upload a PDF file to replace the Markdown content. If uploaded, the system will embed the PDF for students and AI will extract text directly from the PDF for assessments.
                        </div>
                    <?php endif; ?>

                    <form action="/src/upload_module_pdf.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Upload Course Material (.pdf)</label>
                            <input type="file" name="pdf_file" class="form-control" accept="application/pdf" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Upload & Enable PDF Mode</button>
                    </form>
                    
                    <?php if ($module['is_pdf_mode']): ?>
                    <form action="/src/disable_pdf_mode.php" method="POST" class="mt-3" onsubmit="return confirm('Switch back to Markdown mode?');">
                        <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100">Revert to Markdown Mode</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body>
</html>
