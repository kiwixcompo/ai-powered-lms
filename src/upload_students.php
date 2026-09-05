<?php
session_start();
require_once '../config/config.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Middleware check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['student_file'])) {
    $file = $_FILES['student_file'];
    $category = $_POST['category'] ?? 'General';
    
    if ($file['error'] == UPLOAD_ERR_OK) {
        $tmpName = $file['tmp_name'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
            try {
                $spreadsheet = IOFactory::load($tmpName);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                $successCount = 0;
                
                // Assuming first row is header: Name | Reg No
                foreach ($rows as $index => $row) {
                    if ($index == 0) continue; // Skip header
                    
                    $name = $row[0] ?? '';
                    $reg_no = $row[1] ?? '';
                    
                    if (!empty($name) && !empty($reg_no)) {
                        // Password is the same as the Registration Number
                        $hashed_pwd = password_hash($reg_no, PASSWORD_DEFAULT);
                        
                        // Insert into DB. Set a dummy email based on reg_no to satisfy any NOT NULL constraint if modification fails.
                        $dummy_email = preg_replace('/[^a-zA-Z0-9]/', '', $reg_no) . '@student.local';
                        
                        try {
                            $stmt = $conn->prepare("INSERT INTO users (name, reg_no, email, password, role, category) VALUES (?, ?, ?, ?, 'student', ?)");
                            $stmt->execute([$name, $reg_no, $dummy_email, $hashed_pwd, $category]);
                            $successCount++;
                        } catch (PDOException $e) {
                            // Ignore duplicates for now, just continue
                        }
                    }
                }
                
                $_SESSION['msg'] = "$successCount students successfully uploaded into category '$category'.";
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error reading the file: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Invalid file format. Please upload .xlsx, .xls, or .csv';
        }
    } else {
        $_SESSION['error'] = 'File upload error.';
    }
}

header('Location: /CMP_Course_Module/admin');
exit;
