<?php
/**
 * LifeSim — Admin login page
 */

require_once __DIR__ . '/auth.php';
admin_session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (admin_login($username, $password)) {
        if (admin_password_is_default()) {
            header('Location: change_password.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim — Admin Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<main class="admin-login-wrapper">
    <div class="admin-login-box">
        <h1>LifeSim Admin</h1>

        <?php if ($error): ?>
            <p class="admin-error"><?= h($error) ?></p>
        <?php endif; ?>

        <form method="post" action="index.php" autocomplete="off">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   autocomplete="username" required
                   value="<?= h($_POST['username'] ?? '') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>

            <button type="submit">Log In</button>
        </form>
    </div>
</main>
</body>
</html>
