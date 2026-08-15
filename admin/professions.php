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
$profession = blank_profession();
$isEdit = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEdit = trim($_POST['existing_id'] ?? '') !== '';
    $posted = profession_from_post(trim($_POST['existing_id'] ?? ''));
    $profession = $posted['profession'];
    $errors = $posted['errors'];

    if (!$errors) {
        $errors = upsert_profession($profession);
        if (!$errors) {
            admin_log($isEdit ? 'profession_updated' : 'profession_created', ['id' => $profession['id'], 'name' => $profession['name']]);
            header('Location: professions.php?saved=1');
            exit;
        }
    }
    $action = $isEdit ? 'edit' : 'add';
}

if ($action === 'toggle' && $id !== '') {
    $existing = find_profession($id);
    if ($existing) {
        toggle_profession($id);
        admin_log('profession_toggled', ['id' => $id, 'state' => empty($existing['enabled']) ? 'enabled' : 'disabled']);
    }
    header('Location: professions.php');
    exit;
}

if ($action === 'edit' && $id !== '') {
    $found = find_profession($id);
    if ($found) {
        $profession = $found;
    }
}

$saved = !empty($_GET['saved']);
$professions = get_all_professions();
usort($professions, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Professions</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="admin-main">
<?php if ($action === 'list'): ?>
    <div class="admin-section-header">
        <h1>Professions</h1>
        <a href="professions.php?action=add" class="admin-button">+ Add Profession</a>
    </div>
    <?php if ($saved): ?><p class="admin-success">Profession saved successfully.</p><?php endif; ?>
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Category</th><th>Levels</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($professions as $item): ?>
            <tr>
                <td><?= h($item['name']) ?></td>
                <td><?= h($item['category']) ?></td>
                <td><?= count($item['levels']) ?></td>
                <td><span class="status-badge <?= !empty($item['enabled']) ? 'status-enabled' : 'status-disabled' ?>"><?= !empty($item['enabled']) ? 'Enabled' : 'Disabled' ?></span></td>
                <td class="admin-actions">
                    <a href="professions.php?action=edit&id=<?= h($item['id']) ?>">✏️</a>
                    <a href="professions.php?action=toggle&id=<?= h($item['id']) ?>" onclick="return confirm('Toggle this profession?')">🔁</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <h1><?= $action === 'edit' ? 'Edit Profession' : 'Add Profession' ?></h1>
    <?php if ($errors): ?><ul class="admin-error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="professions.php" class="admin-form" id="profession-form">
        <input type="hidden" name="existing_id" value="<?= h($action === 'edit' ? $profession['id'] : '') ?>">
        <label for="name">Profession name <span class="required">*</span></label>
        <input type="text" id="name" name="name" required value="<?= h($profession['name']) ?>">
        <label for="identifier">Identifier <span class="required">*</span></label>
        <input type="text" id="identifier" name="identifier" required value="<?= h($profession['id']) ?>">
        <label for="category">Category <span class="required">*</span></label>
        <input type="text" id="category" name="category" required value="<?= h($profession['category']) ?>">
        <label for="education">Default education</label>
        <input type="text" id="education" name="education" value="<?= h($profession['education']) ?>">
        <label class="checkbox-label"><input type="checkbox" name="enabled" value="1" <?= !empty($profession['enabled']) ? 'checked' : '' ?>> Enabled</label>

        <fieldset class="choices-fieldset">
            <legend>Career levels</legend>
            <div id="levels-list">
            <?php foreach ($profession['levels'] as $level): ?>
                <div class="choice-row">
                    <label>Title</label>
                    <input type="text" name="level_title[]" value="<?= h($level['title']) ?>">
                    <label>Min wage</label>
                    <input type="text" name="level_min_wage[]" value="<?= h((string)$level['minWage']) ?>">
                    <label>Max wage</label>
                    <input type="text" name="level_max_wage[]" value="<?= h((string)$level['maxWage']) ?>">
                    <label>Education requirement</label>
                    <input type="text" name="level_education[]" value="<?= h($level['education']) ?>">
                    <button type="button" class="remove-choice admin-button-small" onclick="removeLevel(this)">Remove</button>
                </div>
            <?php endforeach; ?>
            </div>
            <button type="button" id="add-level" class="admin-button-small">+ Add Level</button>
        </fieldset>
        <div class="form-actions">
            <button type="submit" class="admin-button">Save Profession</button>
            <a href="professions.php" class="admin-button admin-button-secondary">Cancel</a>
        </div>
    </form>
    <script>
    (function () {
        const list = document.getElementById('levels-list');
        document.getElementById('add-level')?.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'choice-row';
            row.innerHTML = `
                <label>Title</label>
                <input type="text" name="level_title[]" value="">
                <label>Min wage</label>
                <input type="text" name="level_min_wage[]" value="0">
                <label>Max wage</label>
                <input type="text" name="level_max_wage[]" value="0">
                <label>Education requirement</label>
                <input type="text" name="level_education[]" value="">
                <button type="button" class="remove-choice admin-button-small" onclick="removeLevel(this)">Remove</button>
            `;
            list.appendChild(row);
        });
        window.removeLevel = function (btn) {
            const rows = document.querySelectorAll('#levels-list .choice-row');
            if (rows.length > 1) {
                btn.closest('.choice-row').remove();
            }
        };
    }());
    </script>
<?php endif; ?>
</main>
</body>
</html>
