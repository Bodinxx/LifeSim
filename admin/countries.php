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
$code = strtoupper($_GET['code'] ?? '');
$errors = [];
$country = blank_country();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existingCountry = find_country(strtoupper(trim($_POST['code'] ?? '')));
    $posted = country_from_post();
    $country = $posted['country'];
    $errors = $posted['errors'];

    if (!$errors) {
        $errors = upsert_country($country);
        if (!$errors) {
            admin_log($existingCountry ? 'country_updated' : 'country_created', ['code' => $country['code'], 'name' => $country['name']]);
            header('Location: countries.php?saved=1');
            exit;
        }
    }
    $action = 'edit';
}

if ($action === 'toggle' && $code !== '') {
    $existing = find_country($code);
    if ($existing) {
        toggle_country($code);
        admin_log('country_toggled', ['code' => $code, 'state' => empty($existing['enabled']) ? 'enabled' : 'disabled']);
    }
    header('Location: countries.php');
    exit;
}

if ($action === 'edit' && $code !== '') {
    $found = find_country($code);
    if ($found) {
        $country = $found;
    }
}

$saved = !empty($_GET['saved']);
$countries = get_all_countries();
usort($countries, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Countries</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="admin-main">
<?php if ($action === 'list'): ?>
    <div class="admin-section-header">
        <h1>Countries & Cities</h1>
        <a href="countries.php?action=add" class="admin-button">+ Add Country</a>
    </div>
    <?php if ($saved): ?><p class="admin-success">Country saved successfully.</p><?php endif; ?>
    <table class="admin-table">
        <thead><tr><th>Code</th><th>Name</th><th>Cities</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($countries as $item): ?>
            <tr>
                <td><?= h($item['code']) ?></td>
                <td><?= h($item['name']) ?></td>
                <td><?= count($item['cities']) ?></td>
                <td><span class="status-badge <?= !empty($item['enabled']) ? 'status-enabled' : 'status-disabled' ?>"><?= !empty($item['enabled']) ? 'Enabled' : 'Disabled' ?></span></td>
                <td class="admin-actions">
                    <a href="countries.php?action=edit&code=<?= h($item['code']) ?>">✏️</a>
                    <a href="countries.php?action=toggle&code=<?= h($item['code']) ?>" onclick="return confirm('Toggle this country?')">🔁</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <h1><?= $action === 'edit' ? 'Edit Country' : 'Add Country' ?></h1>
    <?php if ($errors): ?><ul class="admin-error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="countries.php" class="admin-form">
        <label for="code">Country code <span class="required">*</span></label>
        <input type="text" id="code" name="code" maxlength="3" required value="<?= h($country['code']) ?>">
        <label for="name">Country name <span class="required">*</span></label>
        <input type="text" id="name" name="name" required value="<?= h($country['name']) ?>">
        <label for="cities_text">Cities <span class="required">*</span></label>
        <textarea id="cities_text" name="cities_text" rows="10" required><?= h(implode("\n", $country['cities'] ?? [])) ?></textarea>
        <p class="form-help">One city per line.</p>
        <label class="checkbox-label"><input type="checkbox" name="enabled" value="1" <?= !empty($country['enabled']) ? 'checked' : '' ?>> Enabled</label>
        <div class="form-actions">
            <button type="submit" class="admin-button">Save Country</button>
            <a href="countries.php" class="admin-button admin-button-secondary">Cancel</a>
        </div>
    </form>
<?php endif; ?>
</main>
</body>
</html>
