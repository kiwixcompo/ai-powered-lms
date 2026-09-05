<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Course Module</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); background: #fff; }
    </style>
</head>
<body>
    <div class="login-card">
        <h3 class="text-center mb-4">Course Module Login</h3>
        <?php 
            if (session_status() === PHP_SESSION_NONE) session_start();
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
                unset($_SESSION['error']);
            }
        ?>
        <form action="/CMP_Course_Module/src/auth.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email or Registration Number</label>
                <input type="text" class="form-control" id="email" name="email" placeholder="e.g. admin@example.com or CMP/2023/001" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>
