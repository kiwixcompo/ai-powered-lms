<?php
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    
    $admin_name = $_POST['admin_name'] ?? 'System Admin';
    $admin_email = $_POST['admin_email'] ?? 'admin@tsuniversity.ng';
    $admin_pass = password_hash($_POST['admin_pass'] ?? 'admin123', PASSWORD_DEFAULT);

    try {
        // 1. Test Connection
        $conn = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 2. Create Database if it doesn't exist (May require root privileges, so we catch errors)
        $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $conn->exec("USE `$db_name`;");

        // 3. Import SQL Schema
        $sql_file = __DIR__ . '/database_setup.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $conn->exec($sql);
        } else {
            throw new Exception("database_setup.sql not found!");
        }

        // 4. Create Admin Account
        // Check if admin exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$admin_email]);
        if ($stmt->rowCount() == 0) {
            $insert = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $insert->execute([$admin_name, $admin_email, $admin_pass]);
        }

        // 5. Write to config/config.php
        $config_content = "<?php\n"
            . "date_default_timezone_set('Africa/Lagos');\n"
            . "\$host = '$db_host';\n"
            . "\$db_name = '$db_name';\n"
            . "\$username = '$db_user';\n"
            . "\$password = '$db_pass';\n\n"
            . "try {\n"
            . "    \$conn = new PDO(\"mysql:host=\$host;dbname=\$db_name\", \$username, \$password);\n"
            . "    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n"
            . "} catch(PDOException \$e) {\n"
            . "    die(\"Connection failed: \" . \$e->getMessage());\n"
            . "}\n"
            . "?>";
            
        file_put_contents(__DIR__ . '/config/config.php', $config_content);

        $success = "Installation Successful! You can now log in. For security, please delete install.php.";
        
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
    <link href="/CMP_Course_Module/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="/CMP_Course_Module/assets/images/logo.png" type="image/png">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card tsu-card shadow">
            <div class="card-header tsu-card-header text-center py-4">
                <img src="/CMP_Course_Module/assets/images/logo.png" alt="TSU" height="60" class="mb-2">
                <h4 class="mb-0 text-white">LMS Setup & Installation</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h5><?= htmlspecialchars($success) ?></h5>
                        <a href="/CMP_Course_Module/login" class="btn btn-tsu-primary mt-3">Go to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <h5 class="border-bottom pb-2 mb-3">1. Database Credentials</h5>
                        <p class="text-muted small">Create a database and user in cPanel first, then enter the details here.</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Database Host</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Database Name</label>
                            <input type="text" name="db_name" class="form-control" placeholder="e.g. tsuniver_lms" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Database User</label>
                            <input type="text" name="db_user" class="form-control" placeholder="e.g. tsuniver_lmsuser" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Database Password</label>
                            <input type="password" name="db_pass" class="form-control" required>
                        </div>
                        
                        <h5 class="border-bottom pb-2 mt-4 mb-3">2. Admin Account Setup</h5>
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
                        
                        <button type="submit" class="btn btn-tsu-primary w-100 btn-lg">Install & Setup Database</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
