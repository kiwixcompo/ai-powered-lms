<?php
require_once 'c:/wamp64/www/CMP_Course_Module/config/config.php';

try {
    $conn->exec("ALTER TABLE assessments ADD COLUMN course_id INT NULL AFTER id");
    $conn->exec("ALTER TABLE assessments ADD COLUMN title VARCHAR(255) NULL AFTER course_id");
    $conn->exec("ALTER TABLE assessments ADD COLUMN target_modules VARCHAR(255) NULL AFTER title");
    
    // Add foreign key for course_id
    $conn->exec("ALTER TABLE assessments ADD CONSTRAINT fk_assessments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
    
    // Update existing assessments to link to their respective course
    $conn->exec("UPDATE assessments a JOIN modules m ON a.module_id = m.id SET a.course_id = m.course_id, a.title = CONCAT('Assessment for ', m.title), a.target_modules = JSON_ARRAY(CAST(m.id AS CHAR))");
    
    echo "Schema updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
