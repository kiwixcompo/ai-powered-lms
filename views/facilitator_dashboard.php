<?php 
require_once 'config/config.php';
// Get AI setting safely before sending headers
$ai_provider = 'puter';
try {
    $ai_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'ai_provider'");
    if ($ai_stmt) {
        $ai_provider = $ai_stmt->fetchColumn() ?: 'puter';
    }
} catch (PDOException $e) {
    // If table doesn't exist, try to create it gracefully
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS settings (setting_key varchar(50) NOT NULL, setting_value varchar(255) DEFAULT NULL, PRIMARY KEY (setting_key))");
        $conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('ai_provider', 'pollinations')");
        $ai_provider = 'pollinations';
    } catch (PDOException $e2) {
        $ai_provider = 'puter';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilitator Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/bootstrap.min.css" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';">
    <!-- Custom TSU Theme -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
    
    <?php if ($ai_provider === 'puter'): ?>
    <script src="https://js.puter.com/v2/"></script>
    <?php endif; ?>
    
    <script>
        const AI_PROVIDER = '<?php echo $ai_provider; ?>';
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark tsu-navbar mb-4">
      <div class="container">
        <a class="navbar-brand" href="#"><img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="TSU"> Facilitator Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link text-white">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/src/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['msg'])) { echo "<div class='alert alert-success'>".$_SESSION['msg']."</div>"; unset($_SESSION['msg']); } ?>
        <?php if (isset($_SESSION['error'])) { echo "<div class='alert alert-danger'>".$_SESSION['error']."</div>"; unset($_SESSION['error']); } ?>

        <h3 class="mb-4">My Assigned Courses</h3>
        <div class="row" id="coursesAccordion">
            <?php
                require_once 'config/config.php';
                $stmt = $conn->prepare("SELECT c.id, c.code, c.title, c.description FROM courses c JOIN course_facilitators cf ON c.id = cf.course_id WHERE cf.user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($courses) > 0) {
                    foreach ($courses as $index => $c) {
                        echo '<div class="col-12 mb-4">';
                        echo '<div class="card tsu-card h-100 accordion-item">';
                        
                        echo '<div class="card-header tsu-card-header accordion-header cursor-pointer" id="heading_'.$c['id'].'" data-bs-toggle="collapse" data-bs-target="#collapse_'.$c['id'].'" style="cursor: pointer;">';
                        echo '<h5 class="mb-0">'.htmlspecialchars($c['code'] . ' - ' . $c['title']).'</h5>';
                        echo '</div>';
                        
                        $showClass = $index === 0 ? 'show' : '';
                        echo '<div id="collapse_'.$c['id'].'" class="accordion-collapse collapse '.$showClass.'" aria-labelledby="heading_'.$c['id'].'" data-bs-parent="#coursesAccordion">';
                        echo '<div class="card-body p-0">';
                        
                        echo '
                        <ul class="nav nav-tabs bg-light px-3 pt-2" id="courseTabs_'.$c['id'].'" role="tablist">
                          <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="modules-tab-'.$c['id'].'" data-bs-toggle="tab" data-bs-target="#modules-pane-'.$c['id'].'" type="button" role="tab">Modules & Setup</button>
                          </li>
                          <li class="nav-item" role="presentation">
                            <button class="nav-link" id="students-tab-'.$c['id'].'" data-bs-toggle="tab" data-bs-target="#students-pane-'.$c['id'].'" type="button" role="tab">Enrolled Students</button>
                          </li>
                          <li class="nav-item" role="presentation">
                            <button class="nav-link" id="assessments-tab-'.$c['id'].'" data-bs-toggle="tab" data-bs-target="#assessments-pane-'.$c['id'].'" type="button" role="tab">Course Assessments</button>
                          </li>
                        </ul>
                        <div class="tab-content p-4" id="courseTabContent_'.$c['id'].'">
                        ';

                        // --- TAB 1: MODULES & SETUP ---
                        echo '<div class="tab-pane fade show active" id="modules-pane-'.$c['id'].'" role="tabpanel">';
                        
                        echo '
                        <div class="row mb-4">
                            <div class="col-md-4 border-end">
                                <h6>Option 1: Upload Markdown</h6>
                                <p class="small text-muted mb-2">Upload a single Markdown file (.md).</p>
                                <form action="' . BASE_URL . '/src/process_markdown.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="course_id" value="'.$c['id'].'">
                                    <div class="mb-2">
                                        <input type="file" class="form-control form-control-sm" name="markdown_file" accept=".md, .txt" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-tsu-primary w-100">Process & Create</button>
                                </form>
                            </div>
                            <div class="col-md-4 border-end">
                                <h6>Option 2: Generate via AI</h6>
                                <p class="small text-muted mb-2">Provide an outline for AI generation.</p>
                                <form id="aiGenForm_'.$c['id'].'" action="' . BASE_URL . '/src/process_markdown_raw.php" method="POST">
                                    <input type="hidden" name="course_id" value="'.$c['id'].'">
                                    <input type="hidden" name="markdown_content" id="mdContent_'.$c['id'].'">
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" id="gradeLvl_'.$c['id'].'">
                                            <option value="100 Level (Beginner)">100 Level</option>
                                            <option value="200 Level (Intermediate)">200 Level</option>
                                            <option value="300 Level (Advanced)">300 Level</option>
                                            <option value="400 Level (Expert)">400 Level</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <textarea class="form-control form-control-sm" id="outline_'.$c['id'].'" rows="1" placeholder="Course outline..."></textarea>
                                    </div>
                                    <button type="button" class="btn btn-success btn-sm w-100" onclick="generateAICourse('.$c['id'].')">
                                        <span id="aiBtnText_'.$c['id'].'">Generate</span>
                                        <div class="spinner-border spinner-border-sm text-light d-none" id="aiSpinner_'.$c['id'].'" role="status"></div>
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <h6>Option 3: Upload PDF(s)</h6>
                                <p class="small text-muted mb-2">Upload PDFs to create modules.</p>
                                <form action="' . BASE_URL . '/src/process_course_pdfs.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="course_id" value="'.$c['id'].'">
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" name="pdf_mode" required>
                                            <option value="multiple">Individual PDFs per Module</option>
                                            <option value="single">Single Full PDF (AI Splits)</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <input type="file" class="form-control form-control-sm" name="course_pdfs[]" accept=".pdf" multiple required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-danger w-100">Upload & Process</button>
                                </form>
                            </div>
                        </div>
                        ';

                        $mod_stmt = $conn->prepare("SELECT id, title, order_num, is_active, is_pdf_mode FROM modules WHERE course_id = ? ORDER BY order_num ASC");
                        $mod_stmt->execute([$c['id']]);
                        $modules = $mod_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($modules) > 0) {
                            echo '<div class="d-flex justify-content-between align-items-center bg-light p-2 rounded mb-2 border">
                                    <h6 class="mb-0 text-primary">Current Modules ('.count($modules).')</h6>
                                    <div>
                                        <a href="' . BASE_URL . '/src/export_course.php?course_id='.$c['id'].'&format=md" class="btn btn-sm btn-outline-secondary me-1" title="Export as Markdown">Download .md</a>
                                        <a href="' . BASE_URL . '/src/export_course.php?course_id='.$c['id'].'&format=pdf" target="_blank" class="btn btn-sm btn-outline-dark me-3" title="Export as PDF">Print / Save PDF</a>
                                        <form action="' . BASE_URL . '/src/delete_module.php" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete ALL modules for this course? This will also wipe related assessments and grades.\');">
                                            <input type="hidden" name="action" value="delete_all">
                                            <input type="hidden" name="course_id" value="'.$c['id'].'">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete All</button>
                                        </form>
                                    </div>
                                  </div>';
                            
                            echo '<ul class="list-group list-group-flush border">';
                            foreach ($modules as $m) {
                                $badge = $m['is_active'] ? '<span class="badge bg-success ms-2">Visible</span>' : '<span class="badge bg-secondary ms-2">Hidden</span>';
                                $pdfBadge = $m['is_pdf_mode'] ? '<span class="badge bg-danger ms-2">PDF</span>' : '';
                                
                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                echo '<span><strong>Module '.$m['order_num'].':</strong> '.htmlspecialchars($m['title']) . $badge . $pdfBadge . '</span>';
                                echo '<div>
                                        <form action="' . BASE_URL . '/src/toggle_module.php" method="POST" class="d-inline me-1">
                                            <input type="hidden" name="module_id" value="'.$m['id'].'">
                                            <input type="hidden" name="is_active" value="'.($m['is_active'] ? 0 : 1).'">
                                            <button type="submit" class="btn btn-sm '.($m['is_active'] ? 'btn-secondary' : 'btn-success').'">'.($m['is_active'] ? 'Hide' : 'Show').'</button>
                                        </form>
                                        <a href="' . BASE_URL . '/facilitator/module?id='.$m['id'].'" class="btn btn-sm btn-outline-primary me-1">Manage</a>
                                        <form action="' . BASE_URL . '/src/delete_module.php" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this module and its assessments?\');">
                                            <input type="hidden" name="action" value="delete_single">
                                            <input type="hidden" name="module_id" value="'.$m['id'].'">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                      </div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<div class="alert alert-info py-2">No modules yet. Upload a Markdown file or generate via AI above to create them.</div>';
                        }
                        echo '</div>'; // End Tab 1

                        // --- TAB 2: ENROLLED STUDENTS ---
                        echo '<div class="tab-pane fade" id="students-pane-'.$c['id'].'" role="tabpanel">';
                        $en_stmt = $conn->prepare("SELECT u.id, u.name, u.reg_no FROM users u JOIN enrollments e ON u.id = e.student_id WHERE e.course_id = ? ORDER BY u.name");
                        $en_stmt->execute([$c['id']]);
                        $students = $en_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($students) > 0) {
                            echo '
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <input type="text" class="form-control form-control-sm w-50" placeholder="Search students..." onkeyup="searchStudents('.$c['id'].', this.value)">
                                <form action="' . BASE_URL . '/src/unenroll.php" method="POST" onsubmit="return confirm(\'Unenroll ALL students?\');">
                                    <input type="hidden" name="action" value="unenroll_all">
                                    <input type="hidden" name="course_id" value="'.$c['id'].'">
                                    <button type="submit" class="btn btn-sm btn-danger">Unenroll All ('.count($students).')</button>
                                </form>
                            </div>
                            <form action="' . BASE_URL . '/src/unenroll.php" method="POST">
                                <input type="hidden" name="action" value="unenroll_selected">
                                <input type="hidden" name="course_id" value="'.$c['id'].'">
                                <div class="table-responsive border" style="max-height: 400px; overflow-y: auto;">
                                    <div class="table-responsive"><table class="table table-sm table-hover mb-0" id="studentTable_'.$c['id'].'">
                                        <thead class="table-light position-sticky top-0" style="z-index: 1;">
                                            <tr>
                                                <th style="width: 50px;"><input type="checkbox" onclick="toggleAllStudents('.$c['id'].', this.checked)"></th>
                                                <th>Name</th>
                                                <th>Registration No</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                            foreach ($students as $s) {
                                echo '<tr>
                                        <td><input type="checkbox" name="student_ids[]" value="'.$s['id'].'" class="student-chk-'.$c['id'].'"></td>
                                        <td class="student-name">'.htmlspecialchars($s['name']).'</td>
                                        <td class="student-reg">'.htmlspecialchars($s['reg_no']).'</td>
                                      </tr>';
                            }
                            echo '      </tbody>
                                    </table></div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-danger mt-3" onclick="return confirm(\'Unenroll selected students?\');">Unenroll Selected</button>
                            </form>';
                        } else {
                            echo '<p class="text-muted text-center py-4">No students are currently enrolled in this course.</p>';
                        }
                        echo '</div>'; // End Tab 2

                        // --- TAB 3: COURSE ASSESSMENTS ---
                        echo '<div class="tab-pane fade" id="assessments-pane-'.$c['id'].'" role="tabpanel">';
                        
                        // Show existing assessments for this course
                        $assmt_stmt = $conn->prepare("SELECT * FROM assessments WHERE course_id = ? ORDER BY scheduled_date ASC");
                        $assmt_stmt->execute([$c['id']]);
                        $assessments = $assmt_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($assessments) > 0) {
                            echo '<h6 class="mb-3 text-primary">Existing Assessments</h6>';
                            echo '<div class="table-responsive mb-4 border"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Title</th><th>Targets</th><th>Timer</th><th>Score</th><th>Scheduled For</th><th>Actions</th></tr></thead><tbody>';
                            foreach ($assessments as $a) {
                                $sch = $a['scheduled_date'] ? date('M j, Y, g:i a', strtotime($a['scheduled_date'])) : 'Not Set';
                                $targets = $a['target_modules'] === 'ALL' ? 'Entire Course' : 'Selected Modules';
                                $scoreBadge = $a['scores_released'] ? '<span class="badge bg-success">Released</span>' : '<span class="badge bg-secondary">Hidden</span>';
                                echo '<tr>
                                        <td>'.htmlspecialchars($a['title'] ?? 'Assessment').' ' . $scoreBadge . '</td>
                                        <td>'.$targets.'</td>
                                        <td>'.$a['timer_minutes'].' mins</td>
                                        <td>'.$a['total_score'].' pts</td>
                                        <td>'.$sch.'</td>
                                        <td>
                                            <a href="' . BASE_URL . '/views/assessment_results.php?id='.$a['id'].'" class="btn btn-sm btn-tsu-primary me-1">View Results</a>
                                            <form action="' . BASE_URL . '/src/toggle_assessment_status.php" method="POST" class="d-inline me-1">
                                                <input type="hidden" name="assessment_id" value="'.$a['id'].'">
                                                <input type="hidden" name="is_active" value="'.($a['is_active'] ? 0 : 1).'">
                                                <button type="submit" class="btn btn-sm '.($a['is_active'] ? 'btn-outline-warning' : 'btn-success').'">'.($a['is_active'] ? 'Disable' : 'Enable').'</button>
                                            </form>
                                            <form action="' . BASE_URL . '/src/toggle_scores.php" method="POST" class="d-inline me-1">
                                                <input type="hidden" name="assessment_id" value="'.$a['id'].'">
                                                <input type="hidden" name="scores_released" value="'.($a['scores_released'] ? 0 : 1).'">
                                                <button type="submit" class="btn btn-sm '.($a['scores_released'] ? 'btn-secondary' : 'btn-success').'">'.($a['scores_released'] ? 'Hide Scores' : 'Release Scores').'</button>
                                            </form>
                                            <form action="' . BASE_URL . '/src/delete_assessment.php" method="POST" class="d-inline" onsubmit="return confirm(\'Cancel and delete this assessment?\');">
                                                <input type="hidden" name="assessment_id" value="'.$a['id'].'">
                                                <input type="hidden" name="course_id" value="'.$c['id'].'">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                      </tr>';
                            }
                            echo '</tbody></table></div></div>';
                        }
                        
                        // Generate New Assessment Form
                        echo '
                        <div class="card bg-light shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title">Generate New Assessment via AI</h6>
                                <hr>
                                <form id="saveAssmtForm_'.$c['id'].'" action="' . BASE_URL . '/src/save_assessment.php" method="POST">
                                    <input type="hidden" name="course_id" value="'.$c['id'].'">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Assessment Title</label>
                                            <input type="text" name="title" id="assessmentTitle_'.$c['id'].'" class="form-control form-control-sm" placeholder="e.g. Midterm Exam, Quiz 1" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Target Modules</label>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="dropdownMenuButton_'.$c['id'].'" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                                    Select Modules to Cover...
                                                </button>
                                                <ul class="dropdown-menu w-100 p-2" aria-labelledby="dropdownMenuButton_'.$c['id'].'">
                                                    <li>
                                                        <div class="form-check">
                                                            <input class="form-check-input mod-chk-all-'.$c['id'].'" type="checkbox" id="modAll_'.$c['id'].'" onclick="toggleAllModules('.$c['id'].', this.checked)">
                                                            <label class="form-check-label" for="modAll_'.$c['id'].'"><strong>Entire Course (All Modules)</strong></label>
                                                        </div>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>';
                                                    if (count($modules) > 0) {
                                                        foreach ($modules as $m) {
                                                            echo '<li>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input mod-chk-'.$c['id'].'" type="checkbox" name="target_modules[]" value="'.$m['id'].'" id="mod_'.$m['id'].'" onclick="updateAssessmentTitle('.$c['id'].')">
                                                                        <label class="form-check-label" for="mod_'.$m['id'].'">Module '.$m['order_num'].': '.htmlspecialchars($m['title']).'</label>
                                                                    </div>
                                                                  </li>';
                                                        }
                                                    } else {
                                                        echo '<li><span class="text-muted small">No modules created yet.</span></li>';
                                                    }
                        echo '                  </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">No. Questions</label>
                                            <input type="number" name="num_questions" id="numQuestions_'.$c['id'].'" class="form-control form-control-sm" value="5">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Question Type</label>
                                            <select name="question_type" id="questionType_'.$c['id'].'" class="form-select form-select-sm">
                                                <option value="mixed">Mixed</option>
                                                <option value="mcq">Multiple Choice</option>
                                                <option value="subjective">Subjective / Theory</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Difficulty</label>
                                            <select name="difficulty" id="difficulty_'.$c['id'].'" class="form-select form-select-sm">
                                                <option value="Easy">Easy</option>
                                                <option value="Medium" selected>Medium</option>
                                                <option value="Hard">Hard</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Timer (Mins)</label>
                                            <input type="number" name="timer_minutes" class="form-control form-control-sm" value="30" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Marks for this Assessment</label>';
                                            // Calculate marks already allocated for this course
                                            $used_marks_stmt = $conn->prepare("SELECT COALESCE(SUM(total_score),0) as used FROM assessments WHERE course_id = ?");
                                            $used_marks_stmt->execute([$c['id']]);
                                            $used_marks = (int)$used_marks_stmt->fetchColumn();
                                            $ca_max      = 30;
                                            $remaining   = max(0, $ca_max - $used_marks);
                                            $bar_pct     = min(100, round(($used_marks / $ca_max) * 100));
                                            $bar_color   = $bar_pct >= 100 ? 'bg-danger' : ($bar_pct >= 80 ? 'bg-warning' : 'bg-success');
                                            echo '
                                            <div class="mb-1">
                                                <div class="d-flex justify-content-between small text-muted">
                                                    <span>CA Marks Used: <strong>'.$used_marks.'/'.$ca_max.'</strong></span>
                                                    <span id="remainLbl_'.$c['id'].'" class="fw-bold '.($remaining == 0 ? 'text-danger' : 'text-success').'">'.$remaining.' remaining</span>
                                                </div>
                                                <div class="progress" style="height:6px">
                                                    <div class="progress-bar '.$bar_color.'" id="markBar_'.$c['id'].'" style="width:'.$bar_pct.'%"></div>
                                                </div>
                                            </div>
                                            <input type="number" name="total_score" id="totalScore_'.$c['id'].'"
                                                   class="form-control form-control-sm"
                                                   value="'.min(5, $remaining).'" min="1" max="'.$remaining.'"
                                                   '.($remaining == 0 ? 'disabled' : '').' required
                                                   data-used="'.$used_marks.'" data-max="'.$ca_max.'" data-course="'.$c['id'].'">
                                            '.($remaining == 0 ? '<div class="text-danger small mt-1">All 30 CA marks have been allocated.</div>' : '').'
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Scheduled Date & Time (Start Time)</label>
                                        <input type="datetime-local" name="scheduled_date" class="form-control form-control-sm" required>
                                    </div>
                                    
                                    <input type="hidden" name="ai_questions" id="aiQuestions_'.$c['id'].'" value="">

                                    <button type="button" class="btn btn-dark btn-sm w-100" onclick="generateAndSaveAssessment('.$c['id'].')">
                                        <span id="assmtBtnText_'.$c['id'].'">Generate Questions via AI & Save Assessment</span>
                                        <div class="spinner-border spinner-border-sm ms-2 d-none" id="assmtSpinner_'.$c['id'].'" role="status"></div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        ';
                        echo '</div>'; // End Tab 3
                        
                        echo '</div></div></div></div></div>'; // End Tab Content, Card Body, Collapse Div, Card, Col
                    }
                } else {
                    echo '<div class="col-12"><div class="alert alert-warning">You have not been assigned to any courses.</div></div>';
                }
            ?>
        </div>
    </div>

    <!-- Switched to Pollinations AI, no external JS required -->
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live remaining marks updater for CA mark allocator
        document.querySelectorAll('input[id^="totalScore_"]').forEach(function(input) {
            input.addEventListener('input', function() {
                const used    = parseInt(this.dataset.used, 10);
                const caMax   = parseInt(this.dataset.max, 10);
                const cId     = this.dataset.course;
                const entered = parseInt(this.value, 10) || 0;
                const newUsed = used + entered;
                const remain  = caMax - newUsed;
                const pct     = Math.min(100, Math.round((newUsed / caMax) * 100));

                const lbl = document.getElementById('remainLbl_' + cId);
                const bar = document.getElementById('markBar_' + cId);
                if (lbl) {
                    lbl.textContent = remain + ' remaining';
                    lbl.className   = 'fw-bold ' + (remain < 0 ? 'text-danger' : 'text-success');
                }
                if (bar) {
                    bar.style.width = pct + '%';
                    bar.className = 'progress-bar ' + (pct >= 100 ? 'bg-danger' : (pct >= 80 ? 'bg-warning' : 'bg-success'));
                }
                // Highlight input red if over limit
                this.classList.toggle('is-invalid', remain < 0);
            });
        });

        async function generateAICourse(courseId) {
            const outline = document.getElementById('outline_' + courseId).value;
            const gradeLvl = document.getElementById('gradeLvl_' + courseId).value;
            
            if (!outline) {
                alert("Please provide an outline.");
                return;
            }

            const btnText = document.getElementById('aiBtnText_' + courseId);
            const spinner = document.getElementById('aiSpinner_' + courseId);
            
            btnText.innerText = 'Generating...';
            spinner.classList.remove('d-none');

            try {
                const prompt = `You are an expert Professor. Create a comprehensive course based on this outline: ${outline}.
                Target audience grade level: ${gradeLvl}.
                Ensure you use Markdown. For images, just put markdown placeholders like ![Image: description here].
                Format each module explicitly starting with '## Module X: [Title]' so the system can slice it correctly.`;

                // Call AI
                let responseText = "";
                if (AI_PROVIDER === 'pollinations') {
                    let aiReq = await fetch('https://text.pollinations.ai/', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            messages: [{ role: 'user', content: prompt }]
                        })
                    });
                    if (!aiReq.ok) throw new Error("Pollinations API returned error " + aiReq.status);
                    responseText = await aiReq.text();
                } else {
                    responseText = await puter.ai.chat(prompt);
                }
                
                document.getElementById('mdContent_' + courseId).value = responseText;
                document.getElementById('aiGenForm_' + courseId).submit();
            } catch (error) {
                console.warn('AI API failed, falling back to local heuristic generator.', error);
                // Fallback local course generator
                const mockContent = `## Module 1: Introduction\n\nWelcome to the course! This module covers the foundational concepts of the topic.\n\n### Key Concepts\n- Fundamentals\n- Advanced Topics\n- Practical Applications\n\n![Image: Conceptual Diagram]\n\n## Module 2: Deep Dive\n\nThis module dives deeper into the specific mechanics and workflows discussed in the outline.\n\n### Workflows\n1. Setup\n2. Execution\n3. Review\n\nThank you for reading.`;
                document.getElementById('mdContent_' + courseId).value = mockContent;
                document.getElementById('aiGenForm_' + courseId).submit();
            }
        }

        async function generateAndSaveAssessment(courseId) {
            const form = document.getElementById('saveAssmtForm_' + courseId);
            
            // Basic HTML5 validation
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const btnText = document.getElementById('assmtBtnText_' + courseId);
            const spinner = document.getElementById('assmtSpinner_' + courseId);
            const hiddenInput = document.getElementById('aiQuestions_' + courseId);
            
            const numQ = document.getElementById('numQuestions_' + courseId).value;
            const qType = document.getElementById('questionType_' + courseId).value;
            const diff = document.getElementById('difficulty_' + courseId).value;

            // Gather selected modules
            const modChks = document.querySelectorAll('.mod-chk-' + courseId + ':checked');
            let targetMods = 'ALL';
            if (!document.getElementById('modAll_' + courseId).checked && modChks.length > 0) {
                targetMods = Array.from(modChks).map(c => c.value);
            }

            btnText.innerText = 'Extracting Text & Generating...';
            spinner.classList.remove('d-none');
            const submitBtn = spinner.closest('button');
            submitBtn.disabled = true;

            try {
                // 1. Fetch module text
                let formData = new FormData();
                formData.append('course_id', courseId);
                formData.append('target_modules', targetMods === 'ALL' ? 'ALL' : JSON.stringify(targetMods));
                
                let res = await fetch(BASE_URL + '/src/get_module_content.php', {
                    method: 'POST',
                    body: formData
                });
                if (!res.ok) throw new Error("Failed to extract course text.");
                let data = await res.json();
                let courseText = data.content;

                // 2. Formulate AI Prompt
                let prompt = `You are an expert Professor creating an assessment for a university course.
Create ${numQ} questions of type '${qType}' (where mixed means some MCQ, some Subjective/Theory).
The difficulty level should be ${diff}.

CRITICAL INSTRUCTIONS:
- The questions MUST make sense and test deep conceptual understanding.
- DO NOT use generic or lazy phrases like "Which of the following statements is true regarding this module?".
- Questions must be clearly understandable on their own.
- For MCQ, provide exactly 4 options.
- The output MUST be a valid JSON array of objects.
- Each object must match this schema:
{
    "question_text": "Clear, contextual question here",
    "question_type": "mcq" OR "theory",
    "options": ["opt1", "opt2", "opt3", "opt4"], // Only for mcq, else null
    "correct_answer": "The correct option exactly as written OR the model answer for theory"
}

Here is the extracted course material to base your questions on:
${courseText}

OUTPUT STRICTLY JSON ONLY. No markdown blocks, no other text.`;

                // 3. Call AI
                btnText.innerText = 'AI Thinking...';
                let responseText = "";
                
                if (AI_PROVIDER === 'pollinations') {
                    let aiReq = await fetch('https://text.pollinations.ai/', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            messages: [{ role: 'user', content: prompt }]
                        })
                    });
                    if (!aiReq.ok) throw new Error("Pollinations API returned error " + aiReq.status);
                    responseText = await aiReq.text();
                } else {
                    responseText = await puter.ai.chat(prompt);
                }

                // Clean up JSON block if AI wraps it in markdown
                responseText = responseText.replace(/^```json\s*/i, '').replace(/```\s*$/, '').trim();
                
                // Parse to ensure it's valid JSON
                let questionsData = JSON.parse(responseText);
                if (!Array.isArray(questionsData)) throw new Error("AI did not return an array.");

                // 4. Save to hidden field and submit
                hiddenInput.value = JSON.stringify(questionsData);
                form.submit();

            } catch (error) {
                console.error("AI Generation Error: ", error);
                alert("Failed to generate AI questions: " + error.message + "\nPlease try again or select fewer modules.");
                btnText.innerText = 'Generate Questions via AI & Save Assessment';
                spinner.classList.add('d-none');
                submitBtn.disabled = false;
            }
        }

        const BASE_URL = '<?php echo BASE_URL; ?>';

        function toggleAllStudents(courseId, isChecked) {
            const checkboxes = document.querySelectorAll('.student-chk-' + courseId);
            checkboxes.forEach(chk => chk.checked = isChecked);
        }

        function toggleAllModules(courseId, isChecked) {
            const checkboxes = document.querySelectorAll('.mod-chk-' + courseId);
            checkboxes.forEach(chk => chk.checked = isChecked);
            updateAssessmentTitle(courseId);
        }

        function updateAssessmentTitle(courseId) {
            const titleInput = document.getElementById('assessmentTitle_' + courseId);
            const allChecked = document.getElementById('modAll_' + courseId).checked;
            const checkboxes = document.querySelectorAll('.mod-chk-' + courseId + ':checked');
            
            // Only update if the input is empty or matches our auto-generated formats
            const current = titleInput.value;
            const isAuto = current === "" || current === "Course Final Assessment" || current === "Multi-Module Assessment" || current.includes(" Assessment");
            
            if (isAuto) {
                if (allChecked) {
                    titleInput.value = "Course Final Assessment";
                } else if (checkboxes.length === 1) {
                    const label = checkboxes[0].nextElementSibling.innerText;
                    titleInput.value = label.split(':')[0] + " Assessment";
                } else if (checkboxes.length > 1) {
                    titleInput.value = "Multi-Module Assessment";
                } else {
                    titleInput.value = "";
                }
            }
        }

        function searchStudents(courseId, query) {
            const lowerQuery = query.toLowerCase();
            const table = document.getElementById('studentTable_' + courseId);
            if (!table) return;
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const name = rows[i].querySelector('.student-name').textContent.toLowerCase();
                const reg = rows[i].querySelector('.student-reg').textContent.toLowerCase();
                
                if (name.includes(lowerQuery) || reg.includes(lowerQuery)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>
