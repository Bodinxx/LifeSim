<?php
/**
 * LifeSim — Forced first-time password change
 */

require_once __DIR__ . '/auth.php';
admin_session_start();
require_admin_login();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new  = $_POST['new_password'] ?? '';
    $conf = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $conf) {
        $error = 'Passwords do not match.';
    } elseif ($new === ADMIN_DEFAULT_PASSWORD) {
        $error = 'You must choose a password that is not the default.';
    } else {
        change_admin_password($new);
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Set Password</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<main class="admin-login-wrapper">
    <div class="admin-login-box">
        <div class="admin-warning-banner">
            ⚠️ <strong>Security Warning:</strong> You are using the default administrator
            password. You must set a new password before continuing.
        </div>

        <h1>Set Administrator Password</h1>

        <?php if ($error): ?>
            <p class="admin-error"><?= h($error) ?></p>
        <?php endif; ?>

        <form method="post" action="change_password.php" autocomplete="off">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" required minlength="8">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" required minlength="8">

            <button type="submit">Set Password</button>
        </form>
    </div>
</main>
</body>
</html>
