<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/src'));
foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $path = $file->getRealPath();
        $content = file_get_contents($path);
        
        $new_content = $content;
        
        $new_content = str_replace('header(\\"Location: \\" . BASE_URL . \\"/CMP_Course_Module', 'header("Location: " . BASE_URL . "', $new_content);
        $new_content = str_replace('header(\\"Location: \\" . BASE_URL . \\"', 'header("Location: " . BASE_URL . "', $new_content);
        
        // Sometimes it is header(\"Location: \" . BASE_URL . \"/admin\");
        // So we also need to replace the ending \" if it exists right before the closing parenthesis.
        $new_content = str_replace('\");', '");', $new_content);
        
        if ($new_content !== $content) {
            file_put_contents($path, $new_content);
            echo "Fixed: $path\n";
        }
    }
}
echo "Done fixing syntax errors!\n";
?>
