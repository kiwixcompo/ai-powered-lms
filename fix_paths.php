<?php
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        // Skip vendor directory
        if (strpos($path, 'vendor') !== false) {
            continue;
        }
        
        $content = file_get_contents($path);
        
        // Remove /CMP_Course_Module/ from strings/HTML
        $new_content = str_replace('"/', '"/', $content);
        $new_content = str_replace("'/", "'/", $new_content);
        
        if ($new_content !== $content) {
            file_put_contents($path, $new_content);
            echo "Fixed paths in: $path\n";
        }
    }
}
echo "Done!\n";
?>
