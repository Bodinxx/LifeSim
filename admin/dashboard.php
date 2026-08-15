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

$events = get_all_events();
$countries = get_all_countries();
$professions = get_all_professions();
$worldEvents = get_all_world_events();
$log = read_json_file(ADMIN_LOG_FILE, []);
$recentLog = array_slice(array_reverse($log), 0, 5);
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

    <div class="admin-quick-links" style="margin-bottom:2rem;">
        <div class="admin-stat-card admin-stat-card--single">
            <span class="stat-item"><span class="stat-value"><?= count($events) ?></span> <span class="stat-label">Events</span></span>
        </div>
        <div class="admin-stat-card admin-stat-card--single">
            <span class="stat-item"><span class="stat-value"><?= count($countries) ?></span> <span class="stat-label">Countries</span></span>
        </div>
        <div class="admin-stat-card admin-stat-card--single">
            <span class="stat-item"><span class="stat-value"><?= count($professions) ?></span> <span class="stat-label">Professions</span></span>
        </div>
        <div class="admin-stat-card admin-stat-card--single">
            <span class="stat-item"><span class="stat-value"><?= count($worldEvents) ?></span> <span class="stat-label">World Events</span></span>
        </div>
    </div>

    <section class="admin-section">
        <h2>Quick Links</h2>
        <nav class="admin-quick-links">
            <a href="events.php" class="admin-button">Manage Events</a>
            <a href="countries.php" class="admin-button">Manage Countries</a>
            <a href="professions.php" class="admin-button">Manage Professions</a>
            <a href="world_events.php" class="admin-button">Manage World Events</a>
        </nav>
    </section>

    <section class="admin-section">
        <h2>Recent Activity</h2>
        <?php if ($recentLog): ?>
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
            <?php foreach ($recentLog as $entry): ?>
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
