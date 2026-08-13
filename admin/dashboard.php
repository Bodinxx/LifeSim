<?php
/**
 * LifeSim — Admin dashboard
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/admin_functions.php';
admin_session_start();
require_admin_login();

if (admin_password_is_default()) {
    header('Location: change_password.php');
    exit;
}

$events         = get_all_events();
$enabled_count  = count(array_filter($events, fn($e) => !empty($e['enabled'])));
$disabled_count = count($events) - $enabled_count;

$log        = read_json_file(ADMIN_LOG_FILE, []);
$recent_log = array_slice(array_reverse($log), 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>

<main class="admin-main">
    <h1>Dashboard</h1>

    <div class="admin-stat-card admin-stat-card--single">
        <span class="stat-item"><span class="stat-value"><?= count($events) ?></span> <span class="stat-label">Total</span></span>
        <span class="stat-divider">|</span>
        <span class="stat-item"><span class="stat-value stat-value--enabled"><?= $enabled_count ?></span> <span class="stat-label">Enabled</span></span>
        <span class="stat-divider">|</span>
        <span class="stat-item"><span class="stat-value stat-value--disabled"><?= $disabled_count ?></span> <span class="stat-label">Disabled</span></span>
    </div>

    <section class="admin-section">
        <h2>Quick Links</h2>
        <nav class="admin-quick-links">
            <a href="events.php" class="admin-button">Manage Events</a>
            <a href="events.php?action=add" class="admin-button">Add Event</a>
            <a href="log.php" class="admin-button">Change Log</a>
        </nav>
    </section>

    <section class="admin-section">
        <h2>Recent Activity</h2>
        <?php if ($recent_log): ?>
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
            <?php foreach ($recent_log as $entry): ?>
                <tr>
                    <td><?= h($entry['timestamp'] ?? '') ?></td>
                    <td><?= h($entry['username'] ?? '') ?></td>
                    <td><?= h($entry['action'] ?? '') ?></td>
                    <td><?= h(json_encode($entry['details'] ?? [])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No activity recorded yet.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
