<?php
/**
 * LifeSim — Development and debugging tools
 *
 * ⚠️  This file must NEVER be accessible on a production server.
 *
 * Access is blocked unless the request comes from localhost or the
 * LIFESIM_DEV environment variable is set to '1'.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// -------------------------------------------------------------------
// Guard: block non-local access
// -------------------------------------------------------------------
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$dev_flag = getenv('LIFESIM_DEV') === '1';

if (!$is_local && !$dev_flag) {
    http_response_code(403);
    exit('403 Forbidden — dev tools are not available on this server.');
}

// -------------------------------------------------------------------
// Actions
// -------------------------------------------------------------------
$action  = $_GET['action'] ?? '';
$message = '';

if ($action === 'clear_save') {
    // Remind the developer that client-side saves are in the browser.
    $message = 'Note: save data is stored in the browser\'s localStorage. '
             . 'Clear it from the browser console with: localStorage.removeItem(\'lifesim_save\')';
}

if ($action === 'reset_admin') {
    $creds = [
        'username'         => ADMIN_DEFAULT_USERNAME,
        'password_hash'    => password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_BCRYPT),
        'password_changed' => false,
    ];
    write_json_file(ADMIN_CREDENTIALS_FILE, $creds);
    $message = 'Admin credentials reset to defaults (admin / admin).';
}

if ($action === 'clear_log') {
    write_json_file(ADMIN_LOG_FILE, []);
    $message = 'Admin change log cleared.';
}

// -------------------------------------------------------------------
// Diagnostics
// -------------------------------------------------------------------
$events       = read_json_file(EVENTS_FILE, []);
$admin_creds  = read_json_file(ADMIN_CREDENTIALS_FILE, []);
$admin_log    = read_json_file(ADMIN_LOG_FILE, []);
$php_version  = PHP_VERSION;
$save_format  = SAVE_FORMAT_VER;
$game_version = LIFESIM_VERSION;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim — Dev Tools</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background:#0d0d1a; color:#e0e0e0; font-family:monospace; padding:1.5rem; }
        h1   { color:#e94560; margin-bottom:1rem; }
        h2   { color:#a0aec0; margin-top:1.5rem; margin-bottom:0.5rem; font-size:0.9rem;
               text-transform:uppercase; letter-spacing:0.06em; }
        pre  { background:#16213e; border:1px solid #234069; border-radius:6px;
               padding:1rem; overflow:auto; font-size:0.85rem; line-height:1.5; }
        .warn  { background:#7c3a1e; border:1px solid #e94560; border-radius:6px;
                 padding:0.8rem 1rem; margin-bottom:1rem; }
        .msg   { background:#1f3b2f; border:1px solid #48bb78; border-radius:6px;
                 padding:0.8rem 1rem; margin-bottom:1rem; color:#9ae6b4; }
        .actions { display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem; }
        a.btn { display:inline-block; padding:0.45rem 1rem; background:#234069;
                color:#e0e0e0; text-decoration:none; border-radius:5px; font-size:0.85rem; }
        a.btn:hover { background:#2d5080; }
        a.btn-danger { background:#7c3a1e; }
        a.btn-danger:hover { background:#a04020; }
        table { border-collapse:collapse; width:100%; font-size:0.85rem; }
        th,td { text-align:left; padding:0.4rem 0.7rem; border-bottom:1px solid #234069; }
        th    { color:#718096; }
    </style>
</head>
<body>

<h1>⚙️ LifeSim Dev Tools</h1>

<div class="warn">
    ⚠️ <strong>Development only.</strong>
    This page must not be reachable on a public server.
</div>

<?php if ($message): ?>
    <div class="msg"><?= h($message) ?></div>
<?php endif; ?>

<h2>Environment</h2>
<table>
    <tr><th>Game version</th><td><?= h($game_version) ?></td></tr>
    <tr><th>Save-format version</th><td><?= h((string)$save_format) ?></td></tr>
    <tr><th>PHP version</th><td><?= h($php_version) ?></td></tr>
    <tr><th>Data path</th><td><?= h(DATA_PATH) ?></td></tr>
    <tr><th>Events file</th><td><?= h(EVENTS_FILE) ?> (<?= count($events) ?> events)</td></tr>
    <tr><th>Credentials file</th><td><?= h(ADMIN_CREDENTIALS_FILE) ?></td></tr>
    <tr><th>Log file</th><td><?= h(ADMIN_LOG_FILE) ?> (<?= count($admin_log) ?> entries)</td></tr>
    <tr>
        <th>Admin password changed</th>
        <td><?= !empty($admin_creds['password_changed']) ? '✅ Yes' : '❌ No (default)' ?></td>
    </tr>
</table>

<h2>Client-Side Save (localStorage)</h2>
<p style="color:#a0aec0;font-size:0.85rem;margin-bottom:0.5rem;">
    Save data lives in the browser. Use the browser console to inspect:
</p>
<pre>JSON.parse(localStorage.getItem('lifesim_save'))</pre>
<pre>// To delete the save:
localStorage.removeItem('lifesim_save')</pre>

<h2>Events Data</h2>
<pre><?= h(json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<h2>Admin Change Log (last 20 entries)</h2>
<?php $recent = array_slice(array_reverse($admin_log), 0, 20); ?>
<?php if ($recent): ?>
<table>
    <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $entry): ?>
        <tr>
            <td><?= h($entry['timestamp'] ?? '') ?></td>
            <td><?= h($entry['username']  ?? '') ?></td>
            <td><?= h($entry['action']    ?? '') ?></td>
            <td><?= h(json_encode($entry['details'] ?? [])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="color:#718096">No log entries.</p>
<?php endif; ?>

<h2>Actions</h2>
<div class="actions">
    <a href="debug.php?action=reset_admin" class="btn btn-danger"
       onclick="return confirm('Reset admin credentials to admin/admin?')">
        Reset Admin Password
    </a>
    <a href="debug.php?action=clear_log" class="btn btn-danger"
       onclick="return confirm('Clear the admin change log?')">
        Clear Change Log
    </a>
    <a href="../index.php" class="btn">← Game</a>
    <a href="../admin/" class="btn">Admin Panel</a>
</div>

</body>
</html>
