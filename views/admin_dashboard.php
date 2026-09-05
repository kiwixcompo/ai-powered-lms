<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom TSU Theme -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark tsu-navbar mb-4">
      <div class="container">
        <a class="navbar-brand" href="#"><img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="TSU"> Admin Portal</a>
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

    <div class="container mt-4 pb-5">
        <?php
            require_once 'config/config.php';
            if (isset($_SESSION['msg'])) { echo "<div class='alert alert-success'>".$_SESSION['msg']."</div>"; unset($_SESSION['msg']); }
            if (isset($_SESSION['error'])) { echo "<div class='alert alert-danger'>".$_SESSION['error']."</div>"; unset($_SESSION['error']); }
            
            $fac_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'facilitator'")->fetchColumn();
            $course_count = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();
            $student_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
        ?>

        <!-- System Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white text-center shadow-sm">
                    <div class="card-body">
                        <h3 class="mb-0"><?= $course_count ?></h3>
                        <small>Courses Created</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white text-center shadow-sm">
                    <div class="card-body">
                        <h3 class="mb-0"><?= $fac_count ?></h3>
                        <small>Active Facilitators</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-dark text-center shadow-sm">
                    <div class="card-body">
                        <h3 class="mb-0"><?= $student_count ?></h3>
                        <small>Enrolled Students</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-dark">
            <div class="card-header tsu-card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Course Overview & Export Materials</h6>
                <a href="<?php echo BASE_URL; ?>/src/export_grades_excel.php" class="btn btn-sm btn-success fw-bold">Download All Grades (Excel)</a>
            </div>
            <div class="card-body p-0 table-responsive">
                <div class="table-responsive"><table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course Code & Title</th>
                            <th>Assigned Facilitators</th>
                            <th>Enrolled Users</th>
                            <th>Export Materials</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $courses_full = $conn->query("SELECT id, code, title FROM courses")->fetchAll(PDO::FETCH_ASSOC);
                            if (count($courses_full) > 0) {
                                foreach ($courses_full as $cf) {
                                    $enrolled = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
                                    $enrolled->execute([$cf['id']]);
                                    $enr_count = $enrolled->fetchColumn();
                                    
                                    $facs = $conn->prepare("SELECT u.name FROM users u JOIN course_facilitators cf ON u.id = cf.user_id WHERE cf.course_id = ?");
                                    $facs->execute([$cf['id']]);
                                    $fac_list = $facs->fetchAll(PDO::FETCH_COLUMN);
                                    $fac_names = implode(", ", $fac_list) ?: "Unassigned";

                                    echo '<tr>
                                        <td><strong>'.htmlspecialchars($cf['code']).'</strong> - '.htmlspecialchars($cf['title']).'</td>
                                        <td>'.htmlspecialchars($fac_names).'</td>
                                        <td>'.$enr_count.' students</td>
                                        <td>
                                            <a href="' . BASE_URL . '/src/export_course.php?course_id='.$cf['id'].'&format=pdf" target="_blank" class="btn btn-sm btn-outline-danger">Export PDF</a>
                                            <a href="' . BASE_URL . '/src/export_course.php?course_id='.$cf['id'].'&format=md" class="btn btn-sm btn-outline-secondary">Export MD</a>
                                        </td>
                                    </tr>';
                                }
                            } else {
                                echo '<tr><td colspan="4" class="text-center">No courses found.</td></tr>';
                            }
                        ?>
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="accordion shadow-sm" id="adminAccordion">
            
            <!-- Category 1: Student Management -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingStudents">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStudents" aria-expanded="true" aria-controls="collapseStudents">
                        Student Management & Bulk Upload
                    </button>
                </h2>
                <div id="collapseStudents" class="accordion-collapse collapse show" aria-labelledby="headingStudents" data-bs-parent="#adminAccordion">
                    <div class="accordion-body">
                        <div class="row">
                            <!-- Single Student Addition -->
                            <div class="col-md-6 border-end">
                                <h6>Option 1: Add Single Student</h6>
                                <p class="text-muted small">Manually add a student. Password defaults to Reg. Number.</p>
                                <form action="<?php echo BASE_URL; ?>/src/add_single_student.php" method="POST">
                                    <div class="mb-2">
                                        <input type="text" class="form-control form-control-sm" name="name" placeholder="Full Name" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" class="form-control form-control-sm" name="reg_no" placeholder="Registration Number (e.g. TSU/FCA/CS/22/1001)" required>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control form-control-sm" name="category" placeholder="Student Category (e.g. 100 Level CS)" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-tsu-primary w-100">Add Student</button>
                                </form>
                            </div>
                            
                            <!-- Bulk Upload -->
                            <div class="col-md-6">
                                <h6>Option 2: Bulk Upload Students</h6>
                                <p class="text-muted small">Upload an Excel file (.xlsx, .csv). Columns: <strong>Name, Registration Number</strong>.</p>
                                <form action="<?php echo BASE_URL; ?>/src/upload_students.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-2">
                                        <label class="small">Category for these students</label>
                                        <input type="text" class="form-control form-control-sm" name="category" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small">Excel/CSV File</label>
                                        <input type="file" class="form-control form-control-sm" name="student_file" accept=".xlsx, .xls, .csv" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">Bulk Upload</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category 2: Enrollment Management -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingEnrollment">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEnrollment" aria-expanded="false" aria-controls="collapseEnrollment">
                        Course Enrollment
                    </button>
                </h2>
                <div id="collapseEnrollment" class="accordion-collapse collapse" aria-labelledby="headingEnrollment" data-bs-parent="#adminAccordion">
                    <div class="accordion-body">
                        <?php
                            $courses = $conn->query("SELECT id, title, code FROM courses")->fetchAll(PDO::FETCH_ASSOC);
                            $categories = $conn->query("SELECT DISTINCT category FROM users WHERE role = 'student' AND category IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <h6>Bulk Enroll by Category</h6>
                        <form action="<?php echo BASE_URL; ?>/src/enroll_category.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Student Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="">Select category...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Course</label>
                                    <select name="course_id" class="form-select" required>
                                        <option value="">Select course...</option>
                                        <?php foreach ($courses as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'] . ' - ' . $c['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">Enroll Category</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Category 3: Course Management -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingCourses">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCourses" aria-expanded="false" aria-controls="collapseCourses">
                        Course Management
                    </button>
                </h2>
                <div id="collapseCourses" class="accordion-collapse collapse" aria-labelledby="headingCourses" data-bs-parent="#adminAccordion">
                    <div class="accordion-body">
                        <form action="<?php echo BASE_URL; ?>/src/create_course.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="code" placeholder="Course Code (e.g. CMP 408)" required>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" name="title" placeholder="Course Title" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="description" placeholder="Description (Optional)" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-info text-white">Create Course</button>
                        </form>
                        <p class="text-muted mt-2 small">* Modules are now created automatically when Facilitators upload their Course Markdown.</p>
                    </div>
                </div>
            </div>

            <!-- Category 4: Facilitator Management -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFacilitators">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFacilitators" aria-expanded="false" aria-controls="collapseFacilitators">
                        Facilitator Management
                    </button>
                </h2>
                <div id="collapseFacilitators" class="accordion-collapse collapse" aria-labelledby="headingFacilitators" data-bs-parent="#adminAccordion">
                    <div class="accordion-body">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h6>Create Facilitator</h6>
                                <form action="<?php echo BASE_URL; ?>/src/create_facilitator.php" method="POST">
                                    <div class="mb-2"><input type="text" class="form-control" name="name" placeholder="Name" required></div>
                                    <div class="mb-2"><input type="email" class="form-control" name="email" placeholder="Email" required></div>
                                    <div class="mb-2"><input type="password" class="form-control" name="password" placeholder="Password" required></div>
                                    <button type="submit" class="btn btn-tsu-accent w-100">Create Facilitator</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <h6>Assign Facilitator to Course</h6>
                                <?php $facilitators = $conn->query("SELECT id, name FROM users WHERE role = 'facilitator'")->fetchAll(PDO::FETCH_ASSOC); ?>
                                <form action="<?php echo BASE_URL; ?>/src/assign_facilitator.php" method="POST">
                                    <div class="mb-2">
                                        <select name="user_id" class="form-select" required>
                                            <option value="">Select facilitator...</option>
                                            <?php foreach ($facilitators as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <select name="course_id" class="form-select" required>
                                            <option value="">Select course...</option>
                                            <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'].' - '.$c['title']) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-outline-warning w-100">Assign Facilitator</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- End Accordion -->
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
