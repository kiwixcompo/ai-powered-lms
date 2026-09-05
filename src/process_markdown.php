<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['markdown_file'])) {
    $course_id = $_POST['course_id'] ?? null;
    $file = $_FILES['markdown_file'];

    if ($course_id && $file['error'] == UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        
        // Use regex to split content by "## Module "
        // It looks for "## Module 1: Title", "## Module 2 - Title", etc.
        $pattern = '/(?=## Module\s+\d+)/i';
        $sections = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);
        
        $conn->beginTransaction();
        try {
            // Optional: You could clear existing modules if desired, but we will just add them.
            $stmt = $conn->prepare("INSERT INTO modules (course_id, title, content, order_num) VALUES (?, ?, ?, ?)");
            
            $modulesCreated = 0;
            
            foreach ($sections as $section) {
                $section = trim($section);
                if (preg_match('/^## Module\s+(\d+)\s*[:\-]?\s*(.*)$/im', $section, $matches)) {
                    $order_num = intval($matches[1]);
                    $title = trim($matches[2]) ?: "Module $order_num";
                    
                    // The rest of the string is the content
                    // We can keep the heading or remove it. Let's keep it.
                    $module_content = $section;
                    
                    $stmt->execute([$course_id, $title, $module_content, $order_num]);
                    $modulesCreated++;
                }
            }
            
            $conn->commit();
            
            if ($modulesCreated > 0) {
                $_SESSION['msg'] = "$modulesCreated modules successfully extracted and created!";
            } else {
                $_SESSION['error'] = "No modules found. Please ensure headers start exactly with '## Module 1', '## Module 2', etc.";
            }

        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = 'Failed to process markdown: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Error uploading file.';
    }
}

header('Location: /CMP_Course_Module/facilitator');
exit;
