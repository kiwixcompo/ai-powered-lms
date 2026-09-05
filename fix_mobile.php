<?php
$files = ['views/admin_dashboard.php', 'views/facilitator_dashboard.php', 'views/manage_module.php', 'views/student_dashboard.php', 'views/student_module.php', 'views/student_assessment.php'];
foreach ($files as $f) {
    if (!file_exists($f)) continue;
    $content = file_get_contents($f);
    
    // Fix navbars
    $content = preg_replace('/<a class="navbar-brand"([^>]+)>(.*?)<\/a>\s*<div class="collapse navbar-collapse">/is', 
        '<a class="navbar-brand"$1>$2</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">', $content);
        
    // Fix navbars where collapse is missing but ul is there
    $content = preg_replace('/<a class="navbar-brand"([^>]+)>(.*?)<\/a>\s*<ul class="navbar-nav ms-auto">/is', 
        '<a class="navbar-brand"$1>$2</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">', $content);
          
    // Close the div we opened for missing collapse if it was missing
    if (strpos($f, 'student_module.php') !== false) {
        $content = preg_replace('/<\/li><\/ul>/is', '</li></ul></div>', $content);
    }
    
    // Add bootstrap JS if not present
    if (strpos($content, 'bootstrap.bundle.min.js') === false && strpos($content, '</body>') !== false) {
        $content = str_replace('</body>', '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body>', $content);
    }
    
    // Wrap tables in table-responsive (only if not already wrapped)
    if (strpos($content, '<div class="table-responsive">') === false) {
        $content = preg_replace('/<table([^>]*)>/is', '<div class="table-responsive"><table$1>', $content);
        $content = preg_replace('/<\/table>/is', '</table></div>', $content);
    }
    
    file_put_contents($f, $content);
}
echo "Fixed layout for mobile";
