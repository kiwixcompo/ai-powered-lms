<?php
$file = 'views/facilitator_dashboard.php';
$content = file_get_contents($file);

// Replace <?php echo BASE_URL; ?> with ' . BASE_URL . ' but only if inside a PHP string
// The easiest way is to just str_replace because there's no normal HTML <?php after line 37
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if ($i > 37) {
        $lines[$i] = str_replace('<?php echo BASE_URL; ?>', "' . BASE_URL . '", $line);
    }
}

file_put_contents($file, implode("\n", $lines));
echo "Fixed facilitator_dashboard.php!\n";
?>
