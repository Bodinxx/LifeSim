<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/admin_functions.php';
admin_session_start();
require_admin_login();

if (admin_password_is_default()) {
    header('Location: change_password.php');
    exit;
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$errors = [];
$worldEvent = blank_world_event();
$isEdit = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEdit = trim($_POST['existing_id'] ?? '') !== '';
    $posted = world_event_from_post(trim($_POST['existing_id'] ?? ''));
    $worldEvent = $posted['world_event'];
    $errors = $posted['errors'];

    if (!$errors) {
        $errors = upsert_world_event($worldEvent);
        if (!$errors) {
            admin_log($isEdit ? 'world_event_updated' : 'world_event_created', ['id' => $worldEvent['id'], 'name' => $worldEvent['name']]);
            header('Location: world_events.php?saved=1');
            exit;
        }
    }
    $action = $isEdit ? 'edit' : 'add';
}

if ($action === 'toggle' && $id !== '') {
    $existing = find_world_event($id);
    if ($existing) {
        toggle_world_event($id);
        admin_log('world_event_toggled', ['id' => $id, 'state' => empty($existing['enabled']) ? 'enabled' : 'disabled']);
    }
    header('Location: world_events.php');
    exit;
}

if ($action === 'edit' && $id !== '') {
    $found = find_world_event($id);
    if ($found) {
        $worldEvent = $found;
    }
}

$saved = !empty($_GET['saved']);
$worldEvents = get_all_world_events();
usort($worldEvents, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — World Events</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="admin-main">
<?php if ($action === 'list'): ?>
    <div class="admin-section-header">
        <h1>World Events</h1>
        <a href="world_events.php?action=add" class="admin-button">+ Add World Event</a>
    </div>
    <?php if ($saved): ?><p class="admin-success">World event saved successfully.</p><?php endif; ?>
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Regions</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($worldEvents as $item): ?>
            <tr>
                <td><?= h($item['name']) ?></td>
                <td><?= h(implode(', ', $item['regions'])) ?></td>
                <td><span class="status-badge <?= !empty($item['enabled']) ? 'status-enabled' : 'status-disabled' ?>"><?= !empty($item['enabled']) ? 'Enabled' : 'Disabled' ?></span></td>
                <td class="admin-actions">
                    <a href="world_events.php?action=edit&id=<?= h($item['id']) ?>">✏️</a>
                    <a href="world_events.php?action=toggle&id=<?= h($item['id']) ?>" onclick="return confirm('Toggle this world event?')">🔁</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <h1><?= $action === 'edit' ? 'Edit World Event' : 'Add World Event' ?></h1>
    <?php if ($errors): ?><ul class="admin-error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="world_events.php" class="admin-form">
        <input type="hidden" name="existing_id" value="<?= h($action === 'edit' ? $worldEvent['id'] : '') ?>">
        <label for="name">Name <span class="required">*</span></label>
        <input type="text" id="name" name="name" required value="<?= h($worldEvent['name']) ?>">
        <label for="description">Description <span class="required">*</span></label>
        <textarea id="description" name="description" rows="5" required><?= h($worldEvent['description']) ?></textarea>
        <label for="regions_text">Regions / countries</label>
        <textarea id="regions_text" name="regions_text" rows="5"><?= h(implode("\n", $worldEvent['regions'] ?? [])) ?></textarea>
        <p class="form-help">One region or country per line.</p>
        <label class="checkbox-label"><input type="checkbox" name="enabled" value="1" <?= !empty($worldEvent['enabled']) ? 'checked' : '' ?>> Enabled</label>
        <div class="form-actions">
            <button type="submit" class="admin-button">Save World Event</button>
            <a href="world_events.php" class="admin-button admin-button-secondary">Cancel</a>
        </div>
    </form>
<?php endif; ?>
</main>
</body>
</html>
