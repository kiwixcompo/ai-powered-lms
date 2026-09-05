<?php
require 'config/config.php';
$conn->exec("ALTER TABLE assessments ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
echo "Added is_active";
