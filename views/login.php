<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TSU Learning Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom TSU Theme -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/logo.png" type="image/png">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="TSU Logo">
                <h3>Taraba State University</h3>
                <small class="text-light">Learning Management System</small>
            </div>
            
            <div class="p-4">
                <?php 
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    if (isset($_SESSION['error'])) {
                        echo '<div class="alert alert-danger shadow-sm">'.htmlspecialchars($_SESSION['error']).'</div>';
                        unset($_SESSION['error']);
                    }
                ?>
                <form action="<?php echo BASE_URL; ?>/src/auth.php" method="POST">
                    <div class="mb-4">
                        <label for="email" class="form-label fw-bold">Email or Reg Number</label>
                        <input type="text" class="form-control form-control-lg" id="email" name="email" placeholder="Enter your ID..." required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-bold">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-tsu-primary btn-lg w-100 shadow-sm">Sign In</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
