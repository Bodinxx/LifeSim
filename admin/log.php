<?php
/**
 * LifeSim — Admin change log viewer
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/admin_functions.php';
admin_session_start();
require_admin_login();

if (admin_password_is_default()) {
    header('Location: change_password.php');
    exit;
}

$log     = read_json_file(ADMIN_LOG_FILE, []);
$entries = array_reverse($log);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Change Log</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>

<main class="admin-main">
    <h1>Change Log</h1>

    <?php if ($entries): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= h($entry['timestamp'] ?? '') ?></td>
                <td><?= h($entry['username']  ?? '') ?></td>
                <td><?= h($entry['action']    ?? '') ?></td>
                <td><?= h(json_encode($entry['details'] ?? [], JSON_UNESCAPED_UNICODE)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No changes have been recorded yet.</p>
    <?php endif; ?>
</main>
</body>
</html>
