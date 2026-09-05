<?php
require_once '../config/config.php';
require '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['course_id'])) {
    $course_id = $_POST['course_id'];
    $pdf_mode = $_POST['pdf_mode']; // 'multiple' or 'single'
    $files = $_FILES['course_pdfs'];

    $upload_dir = '../uploads/pdfs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if ($pdf_mode === 'multiple') {
        // Individual PDFs per module
        $stmt = $conn->prepare("SELECT MAX(order_num) as max_order FROM modules WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $max = $stmt->fetchColumn() ?? 0;

        $insert = $conn->prepare("INSERT INTO modules (course_id, title, content, order_num, is_pdf_mode, pdf_path) VALUES (?, ?, ?, ?, 1, ?)");
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $filename = uniqid('mod_multi_') . '.pdf';
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                        $max++;
                        $title = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                        $insert->execute([$course_id, $title, 'PDF Module Content', $max, $filename]);
                    }
                }
            }
        }
        $_SESSION['msg'] = "Individual PDF modules successfully created.";
        header("Location: " . BASE_URL . "/facilitator");
        exit;
    } else {
        // Single PDF -> Extract text and let AI split into Markdown modules
        if ($files['error'][0] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($files['name'][0], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                try {
                    $pdf = $parser->parseFile($files['tmp_name'][0]);
                    $pages = $pdf->getPages();

                    $module_pages = [];
                    $current_module = "Extracted Module 1";
                    $current_module_start_page = 1;

                    $page_num = 1;
                    foreach ($pages as $page) {
                        $text = $page->getText();
                        if (preg_match('/(?:^|\n)\s*(Module|Chapter|Unit|Session|Week)\s+\d+[:\-\.]?\s*(.*?)(?=\n|$)/i', $text, $matches)) {
                            $title = trim($matches[0]);
                            if (strlen($title) <= 200) {
                                if ($page_num > 1) {
                                    $module_pages[] = [
                                        'title' => $current_module,
                                        'start' => $current_module_start_page,
                                        'end' => $page_num - 1
                                    ];
                                }
                                $current_module = $title;
                                $current_module_start_page = $page_num;
                            }
                        }
                        $page_num++;
                    }
                    
                    // Add the last module
                    if ($page_num > 1) {
                        $module_pages[] = [
                            'title' => $current_module,
                            'start' => $current_module_start_page,
                            'end' => $page_num - 1
                        ];
                    }

                    if (count($module_pages) > 0) {
                        $stmt = $conn->prepare("SELECT MAX(order_num) as max_order FROM modules WHERE course_id = ?");
                        $stmt->execute([$course_id]);
                        $order_num = $stmt->fetchColumn() ?? 0;

                        $insert = $conn->prepare("INSERT INTO modules (course_id, title, content, order_num, is_pdf_mode, pdf_path) VALUES (?, ?, ?, ?, 1, ?)");

                        $processed_count = 0;
                        foreach ($module_pages as $mod) {
                            $fpdi = new \setasign\Fpdi\Fpdi();
                            $pageCount = $fpdi->setSourceFile($files['tmp_name'][0]);
                            
                            for ($i = $mod['start']; $i <= $mod['end']; $i++) {
                                $tplId = $fpdi->importPage($i);
                                $size = $fpdi->getTemplateSize($tplId);
                                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                                $fpdi->useTemplate($tplId);
                            }
                            
                            $outName = uniqid('mod_split_') . '.pdf';
                            $fpdi->Output('F', $upload_dir . $outName);
                            
                            $order_num++;
                            $insert->execute([$course_id, $mod['title'], 'PDF Module', $order_num, $outName]);
                            $processed_count++;
                        }
                        
                        if ($processed_count > 0) {
                            $_SESSION['msg'] = $processed_count . " physical PDF modules were successfully extracted, retaining exact design and formatting.";
                        } else {
                            $_SESSION['error'] = "Could not find valid headings to split the PDF.";
                        }
                    } else {
                        $_SESSION['error'] = "Failed to parse PDF pages.";
                    }

                } catch (Exception $e) {
                    $_SESSION['error'] = "Error extracting text or calling AI: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Uploaded file must be a PDF.";
            }
        } else {
            $_SESSION['error'] = "Error uploading PDF.";
        }
        header("Location: " . BASE_URL . "/facilitator");
        exit;
    }
}
header("Location: " . BASE_URL . "/facilitator");
exit;
