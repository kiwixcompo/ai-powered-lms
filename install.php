<?php
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_name = $_POST['admin_name'] ?? 'System Admin';
    $admin_email = $_POST['admin_email'] ?? 'admin@tsuniversity.ng';
    $admin_pass = password_hash($_POST['admin_pass'] ?? 'admin123', PASSWORD_DEFAULT);

    try {
        // Include the newly updated config file which holds the live credentials
        require_once __DIR__ . '/config/config.php';

        // 1. Import SQL Schema to create tables
        $sql_file = __DIR__ . '/database_setup.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $conn->exec($sql);
        } else {
            throw new Exception("database_setup.sql not found! Cannot create tables.");
        }

        // 2. Create Admin Account
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$admin_email]);
        if ($stmt->rowCount() == 0) {
            $insert = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $insert->execute([$admin_name, $admin_email, $admin_pass]);
        }

        $success = "Tables Created and Admin Setup Successful! You can now log in. For security, please delete install.php from your server.";
        
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Installer - Taraba State University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card tsu-card shadow">
            <div class="card-header tsu-card-header text-center py-4">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="TSU" height="60" class="mb-2">
                <h4 class="mb-0 text-white">LMS Database & Admin Setup</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success shadow-sm">
                        <h5><?= htmlspecialchars($success) ?></h5>
                        <a href="<?php echo BASE_URL; ?>/login" class="btn btn-tsu-primary mt-3 w-100">Go to Login Page</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <strong>Live Database Configured!</strong><br>
                        The application is securely connected to <code>tsuniver_tsu_lms</code>. Just set up your admin account below to generate the database tables and finish installation.
                    </div>
                    <form method="POST">
                        <h5 class="border-bottom pb-2 mb-3">Admin Account Setup</h5>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Admin Name</label>
                            <input type="text" name="admin_name" class="form-control" value="System Administrator" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Admin Email (Login ID)</label>
                            <input type="email" name="admin_email" class="form-control" value="admin@tsuniversity.ng" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Admin Password</label>
                            <input type="password" name="admin_pass" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-tsu-primary w-100 btn-lg">Generate Tables & Finish Setup</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
