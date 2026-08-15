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
$type = blank_education_type();
$isEdit = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalId = trim($_POST['original_id'] ?? '');
    $isEdit = $originalId !== '';
    $posted = education_type_from_post($originalId);
    $type = $posted['type'];
    $errors = $posted['errors'];

    if (!$errors) {
        $errors = upsert_education_type($type, $originalId);
        if (!$errors) {
            admin_log($isEdit ? 'education_type_updated' : 'education_type_created', ['id' => $type['id'], 'label' => $type['label']]);
            header('Location: education_types.php?saved=1');
            exit;
        }
    }
    $action = $isEdit ? 'edit' : 'add';
}

if ($action === 'toggle' && $id !== '') {
    $existing = find_education_type($id);
    if ($existing) {
        toggle_education_type($id);
        admin_log('education_type_toggled', ['id' => $id, 'state' => empty($existing['enabled']) ? 'enabled' : 'disabled']);
    }
    header('Location: education_types.php');
    exit;
}

if ($action === 'delete' && $id !== '') {
    $existing = find_education_type($id);
    if ($existing) {
        delete_education_type($id);
        admin_log('education_type_deleted', ['id' => $id]);
    }
    header('Location: education_types.php');
    exit;
}

if ($action === 'edit' && $id !== '') {
    $found = find_education_type($id);
    if ($found) {
        $type = $found;
    }
}

$saved = !empty($_GET['saved']);
$types = get_all_education_types();
usort($types, fn($a, $b) => strcasecmp($a['label'] ?? '', $b['label'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Education Types</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="admin-main">
<?php if ($action === 'list'): ?>
    <div class="admin-section-header">
        <h1>Education Types</h1>
        <a href="education_types.php?action=add" class="admin-button">+ Add Education Type</a>
    </div>
    <?php if ($saved): ?><p class="admin-success">Education type saved successfully.</p><?php endif; ?>
    <table class="admin-table">
        <thead><tr><th>Identifier</th><th>Label</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($types as $item): ?>
            <tr>
                <td><?= h($item['id']) ?></td>
                <td><?= h($item['label']) ?></td>
                <td><span class="status-badge <?= !empty($item['enabled']) ? 'status-enabled' : 'status-disabled' ?>"><?= !empty($item['enabled']) ? 'Enabled' : 'Disabled' ?></span></td>
                <td class="admin-actions">
                    <a href="education_types.php?action=edit&id=<?= h($item['id']) ?>">✏️</a>
                    <a href="education_types.php?action=toggle&id=<?= h($item['id']) ?>" onclick="return confirm('Toggle this education type?')">🔁</a>
                    <a href="education_types.php?action=delete&id=<?= h($item['id']) ?>" onclick="return confirm('Delete this education type? This cannot be undone.')">🗑️</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <h1><?= $action === 'edit' ? 'Edit Education Type' : 'Add Education Type' ?></h1>
    <?php if ($errors): ?><ul class="admin-error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="education_types.php" class="admin-form">
        <input type="hidden" name="original_id" value="<?= h($action === 'edit' ? $type['id'] : '') ?>">
        <label for="id">Identifier <span class="required">*</span></label>
        <input type="text" id="id" name="id" required value="<?= h($type['id']) ?>"<?= $action === 'edit' ? ' readonly' : '' ?>>
        <p class="form-help">Used internally and stored in profession data. Cannot be changed after creation.</p>
        <label for="label">Label <span class="required">*</span></label>
        <input type="text" id="label" name="label" required value="<?= h($type['label']) ?>">
        <p class="form-help">Displayed in education dropdowns throughout the admin.</p>
        <label class="checkbox-label"><input type="checkbox" name="enabled" value="1" <?= !empty($type['enabled']) ? 'checked' : '' ?>> Enabled</label>
        <div class="form-actions">
            <button type="submit" class="admin-button">Save Education Type</button>
            <a href="education_types.php" class="admin-button admin-button-secondary">Cancel</a>
        </div>
    </form>
<?php endif; ?>
</main>
</body>
</html>
