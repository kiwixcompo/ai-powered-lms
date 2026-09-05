<?php
require_once 'c:/wamp64/www/CMP_Course_Module/config/config.php';

try {
    $conn->exec("ALTER TABLE modules ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER content");
    $conn->exec("ALTER TABLE modules ADD COLUMN is_pdf_mode TINYINT(1) DEFAULT 0 AFTER is_active");
    $conn->exec("ALTER TABLE modules ADD COLUMN pdf_path VARCHAR(255) NULL AFTER is_pdf_mode");
    
    $conn->exec("ALTER TABLE assessments ADD COLUMN scores_released TINYINT(1) DEFAULT 0 AFTER scheduled_date");
    
    echo "Schema updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
