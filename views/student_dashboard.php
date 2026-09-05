<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
      <div class="container">
        <a class="navbar-brand" href="#">Student Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link text-white">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></a></li>
            <li class="nav-item"><a class="nav-link" href="/CMP_Course_Module/src/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="container">
        <?php
            require_once 'config/config.php';
            // Check for upcoming assessments
            $up_stmt = $conn->prepare("
                SELECT a.id as assessment_id, a.course_id, a.title as assessment_title, a.scheduled_date, a.timer_minutes, c.code as course_code 
                FROM assessments a 
                JOIN courses c ON a.course_id = c.id 
                JOIN enrollments e ON c.id = e.course_id 
                WHERE e.student_id = ? AND a.scheduled_date > NOW() 
                ORDER BY a.scheduled_date ASC
            ");
            $up_stmt->execute([$_SESSION['user_id']]);
            $upcoming = $up_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($upcoming) > 0) {
                echo '<div class="alert alert-warning mb-4">';
                echo '<h5 class="alert-heading">Upcoming Course Assessments!</h5><ul>';
                foreach ($upcoming as $up) {
                    $date = date('F j, Y, g:i a', strtotime($up['scheduled_date']));
                    echo "<li><a href='/CMP_Course_Module/student/assessment?id={$up['assessment_id']}' class='text-decoration-none fw-bold text-dark'>{$up['course_code']} - {$up['assessment_title']}</a>: Scheduled for $date ({$up['timer_minutes']} minutes)</li>";
                }
                echo '</ul></div>';
            }
        ?>

        <h3 class="mb-4">My Enrolled Courses</h3>
        <div class="row" id="coursesAccordion">
            <?php
                require_once 'config/config.php';
                $stmt = $conn->prepare("SELECT c.id, c.code, c.title, c.description FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($courses) > 0) {
                    foreach ($courses as $index => $c) {
                        echo '<div class="col-12 mb-4">';
                        echo '<div class="card shadow-sm h-100 border-success accordion-item">';
                        echo '<div class="card-header bg-success text-white accordion-header cursor-pointer" id="heading_'.$c['id'].'" data-bs-toggle="collapse" data-bs-target="#collapse_'.$c['id'].'" style="cursor: pointer;">';
                        echo '<h5 class="mb-0">'.htmlspecialchars($c['code'] . ' - ' . $c['title']).'</h5>';
                        echo '</div>';
                        
                        // Collapse div
                        $showClass = $index === 0 ? 'show' : '';
                        echo '<div id="collapse_'.$c['id'].'" class="accordion-collapse collapse '.$showClass.'" aria-labelledby="heading_'.$c['id'].'" data-bs-parent="#coursesAccordion">';
                        echo '<div class="card-body">';
                        echo '<p class="text-muted small">'.htmlspecialchars($c['description']).'</p>';
                        
                        // Modules
                        $mod_stmt = $conn->prepare("SELECT id, title, order_num FROM modules WHERE course_id = ? AND is_active = 1 ORDER BY order_num ASC");
                        $mod_stmt->execute([$c['id']]);
                        $modules = $mod_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($modules) > 0) {
                            echo '<ul class="list-group list-group-flush">';
                            foreach ($modules as $m) {
                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                echo '<span>Module '.$m['order_num'].': '.htmlspecialchars($m['title']).'</span>';
                                
                                // Check if graded (legacy module-level)
                                $grade_stmt = $conn->prepare("SELECT g.total_score_awarded, a.total_score, a.scores_released FROM grades g JOIN assessments a ON g.assessment_id = a.id WHERE a.module_id = ? AND g.student_id = ?");
                                $grade_stmt->execute([$m['id'], $_SESSION['user_id']]);
                                $grade = $grade_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($grade) {
                                    if ($grade['scores_released']) {
                                        echo '<span class="badge bg-success rounded-pill">Score: '.$grade['total_score_awarded'].'/'.$grade['total_score'].'</span>';
                                    } else {
                                        echo '<span class="badge bg-info rounded-pill text-dark">Graded (Awaiting Release)</span>';
                                    }
                                } else {
                                    echo '<a href="/CMP_Course_Module/student/module?id='.$m['id'].'" class="btn btn-sm btn-outline-success">View Notes & Assessments</a>';
                                }
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<div class="alert alert-info py-2">No modules yet, or currently hidden by facilitator.</div>';
                        }
                        
                        // Assessments
                        $assmt_stmt = $conn->prepare("SELECT id, title, timer_minutes, total_score, scheduled_date, scores_released FROM assessments WHERE course_id = ? ORDER BY scheduled_date ASC");
                        $assmt_stmt->execute([$c['id']]);
                        $assessments = $assmt_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($assessments) > 0) {
                            echo '<h6 class="mt-4 mb-2 text-warning fw-bold border-bottom pb-1">Course Assessments</h6>';
                            echo '<ul class="list-group list-group-flush">';
                            foreach ($assessments as $a) {
                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                echo '<span><strong>'.htmlspecialchars($a['title'] ?? 'Assessment').'</strong> - '.$a['timer_minutes'].' mins</span>';
                                
                                $grade_stmt = $conn->prepare("SELECT total_score_awarded FROM grades WHERE assessment_id = ? AND student_id = ?");
                                $grade_stmt->execute([$a['id'], $_SESSION['user_id']]);
                                $grade = $grade_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($grade) {
                                    if ($a['scores_released']) {
                                        echo '<span class="badge bg-success rounded-pill">Score: '.$grade['total_score_awarded'].'/'.$a['total_score'].'</span>';
                                    } else {
                                        echo '<span class="badge bg-info rounded-pill text-dark">Graded (Awaiting Release)</span>';
                                    }
                                } else {
                                    $is_locked = false;
                                    $is_expired = false;
                                    $is_disabled = ($a['is_active'] == 0);
                                    
                                    if ($a['scheduled_date']) {
                                        $sch_time = strtotime($a['scheduled_date']);
                                        if ($sch_time > time()) {
                                            $is_locked = true;
                                        } else {
                                            // Expire exactly 1 minute after timer ends
                                            $end_time = $sch_time + ($a['timer_minutes'] * 60) + 60;
                                            if (time() > $end_time) {
                                                $is_expired = true;
                                            }
                                        }
                                    }

                                    if ($is_disabled || $is_expired) {
                                        echo '<span class="badge bg-danger">Closed</span>';
                                    } elseif ($is_locked) {
                                        echo '<span class="badge bg-secondary">Locked until '.date('M j, g:i a', strtotime($a['scheduled_date'])).'</span>';
                                    } else {
                                        echo '<a href="/CMP_Course_Module/student/assessment?id='.$a['id'].'" class="btn btn-sm btn-warning text-dark fw-bold">Take Assessment</a>';
                                    }
                                }
                                echo '</li>';
                            }
                            echo '</ul>';
                        }

                        echo '</div></div></div></div>';
                    }
                } else {
                    echo '<div class="col-12"><div class="alert alert-warning">You are not enrolled in any courses yet.</div></div>';
                }
            ?>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
