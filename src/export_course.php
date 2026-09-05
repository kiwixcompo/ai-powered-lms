<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'facilitator'])) {
    http_response_code(403);
    exit("Forbidden");
}

$course_id = $_GET['course_id'] ?? null;
$format = $_GET['format'] ?? 'md';

if (!$course_id) die("Course ID required");

// Fetch Course
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) die("Course not found");

// Fetch Modules
$mod_stmt = $conn->prepare("SELECT * FROM modules WHERE course_id = ? ORDER BY order_num ASC");
$mod_stmt->execute([$course_id]);
$modules = $mod_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'md') {
    $filename = preg_replace('/[^a-zA-Z0-9]+/', '_', $course['title']) . "_Course_Material.md";
    
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    
    echo "# " . $course['code'] . " - " . $course['title'] . "\n\n";
    echo $course['description'] . "\n\n";
    
    foreach ($modules as $m) {
        echo $m['content'] . "\n\n";
    }
    exit;
} elseif ($format === 'pdf') {
    // Generate Printable HTML
    require_once '../vendor/autoload.php';
    $parsedown = new Parsedown();
    
    $full_markdown = "# " . $course['code'] . " - " . $course['title'] . "\n\n";
    $full_markdown .= $course['description'] . "\n\n";
    foreach ($modules as $m) {
        $full_markdown .= $m['content'] . "\n\n";
    }
    
    $html_content = $parsedown->text($full_markdown);
    
    // Fix image paths to ensure they load in the printable view (make them absolute URLs if necessary, but relative /CMP_Course_Module/... works in browser)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($course['code'] . ' - ' . $course['title']) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #fff; color: #000; }
            img { max-width: 100%; height: auto; display: block; margin: 15px auto; }
            .print-btn { position: fixed; top: 20px; right: 20px; z-index: 1000; }
            @media print {
                .print-btn { display: none; }
                body { padding: 0; margin: 0; }
                /* Ensure page breaks before modules if they are H2s */
                h2 { page-break-before: always; }
                h2:first-of-type { page-break-before: avoid; }
            }
            .container { max-width: 800px; margin-top: 40px; margin-bottom: 40px; }
        </style>
    </head>
    <body>
        <button class="btn btn-primary print-btn" onclick="window.print()">Print / Save as PDF</button>
        <div class="container">
            <?= $html_content ?>
        </div>
        <script>
            // Automatically prompt print dialog on load
            window.onload = function() {
                setTimeout(() => { window.print(); }, 500);
            };
        </script>
    </body>
    </html>
    <?php
    exit;
} else {
    die("Invalid format.");
}
