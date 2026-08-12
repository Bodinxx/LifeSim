<?php
/**
 * LifeSim — Admin event manager (list / add / edit / toggle)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/admin_functions.php';
admin_session_start();
require_admin_login();

if (admin_password_is_default()) {
    header('Location: change_password.php');
    exit;
}

$action = $_GET['action'] ?? 'list';
$id     = $_GET['id'] ?? '';
$errors = [];
$event  = blank_event();
$modifiable_features = modifiable_event_features();

// -------------------------------------------------------------------
// Handle POST: save event
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_id = trim($_POST['event_id'] ?? '');
    $posted    = event_from_post($posted_id);
    $event     = $posted['event'];
    $errors    = $posted['errors'];

    if (!$errors) {
        $errors = upsert_event($event);
        if (!$errors) {
            $log_action = $posted_id !== '' ? 'event_updated' : 'event_created';
            admin_log($log_action, ['event_id' => $event['id'], 'name' => $event['name']]);
            header('Location: events.php?saved=1');
            exit;
        }
    }
    $action = ($posted_id !== '') ? 'edit' : 'add';
}

// -------------------------------------------------------------------
// Handle toggle via GET
// -------------------------------------------------------------------
if ($action === 'toggle' && $id !== '') {
    $existing = find_event($id);
    if ($existing) {
        toggle_event($id);
        $state = !($existing['enabled'] ?? true) ? 'enabled' : 'disabled';
        admin_log('event_toggled', ['event_id' => $id, 'state' => $state]);
    }
    header('Location: events.php');
    exit;
}

// -------------------------------------------------------------------
// Load event for editing
// -------------------------------------------------------------------
if ($action === 'edit' && $id !== '') {
    $found = find_event($id);
    if ($found) {
        $event = $found;
    } else {
        header('Location: events.php');
        exit;
    }
}

$saved  = !empty($_GET['saved']);
$events = get_all_events();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSim Admin — Events</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/nav.php'; ?>

<main class="admin-main">

<?php if ($action === 'list'): ?>

    <div class="admin-section-header">
        <h1>Random Events</h1>
        <a href="events.php?action=add" class="admin-button">+ Add Event</a>
    </div>

    <?php if ($saved): ?>
        <p class="admin-success">Event saved successfully.</p>
    <?php endif; ?>

    <?php if ($events): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Choices</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><?= h($ev['name'] ?? '') ?></td>
                <td><?= count($ev['choices'] ?? []) ?></td>
                <td>
                    <span class="status-badge <?= !empty($ev['enabled']) ? 'status-enabled' : 'status-disabled' ?>">
                        <?= !empty($ev['enabled']) ? 'Enabled' : 'Disabled' ?>
                    </span>
                </td>
                <td class="admin-actions">
                    <a href="events.php?action=edit&id=<?= h($ev['id']) ?>">Edit</a>
                    <a href="events.php?action=preview&id=<?= h($ev['id']) ?>">Preview</a>
                    <a href="events.php?action=toggle&id=<?= h($ev['id']) ?>"
                       onclick="return confirm('Toggle this event?')">
                        <?= !empty($ev['enabled']) ? 'Disable' : 'Enable' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No events yet. <a href="events.php?action=add">Add the first event.</a></p>
    <?php endif; ?>

<?php elseif ($action === 'add' || $action === 'edit'): ?>

    <h1><?= $action === 'edit' ? 'Edit Event' : 'Add Event' ?></h1>

    <?php if ($errors): ?>
        <ul class="admin-error-list">
            <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="events.php" class="admin-form" id="event-form">
        <input type="hidden" name="event_id"
               value="<?= $action === 'edit' ? h($event['id']) : '' ?>">

        <label for="name">Event Name <span class="required">*</span></label>
        <input type="text" id="name" name="name" required
               value="<?= h($event['name']) ?>">

        <label for="description">Description <span class="required">*</span></label>
        <textarea id="description" name="description" rows="4" required><?= h($event['description']) ?></textarea>

        <label for="consequence_json">Automatic consequence (JSON object)</label>
        <textarea id="consequence_json"
                  name="consequence_json"
                  rows="4"
                  class="consequence-input"
                  data-feature-list="modifiable"><?= h(!empty($event['consequence']) ? json_encode($event['consequence'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') ?></textarea>
        <p class="form-help">
            Applies when the event has no choices.
            <button type="button" class="view-features-btn admin-button-small"
                    onclick="openFeatureMenuFor(this.closest('p').previousElementSibling)">View modifiable features</button>
        </p>

        <label class="checkbox-label">
            <input type="checkbox" name="enabled" value="1"
                   <?= !empty($event['enabled']) ? 'checked' : '' ?>>
            Enabled
        </label>

        <fieldset class="choices-fieldset">
            <legend>Choices</legend>
            <div id="choices-list">
            <?php
            $choices = $event['choices'] ?: [['text' => '', 'outcome' => '', 'consequence' => []]];
            foreach ($choices as $i => $choice):
            ?>
                <div class="choice-row" data-index="<?= $i ?>">
                    <label>Choice text</label>
                    <input type="text" name="choices_text[]"
                           value="<?= h($choice['text'] ?? '') ?>">
                    <label>Outcome description</label>
                    <input type="text" name="choices_outcome[]"
                           value="<?= h($choice['outcome'] ?? '') ?>">
                    <label>Consequence (JSON object)</label>
                    <textarea name="choices_consequence[]"
                              rows="3"
                              class="consequence-input"
                              data-feature-list="modifiable"><?= h(!empty($choice['consequence']) ? json_encode($choice['consequence'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') ?></textarea>
                    <p class="form-help">
                        <button type="button" class="view-features-btn admin-button-small"
                                onclick="openFeatureMenuFor(this.closest('p').previousElementSibling)">View modifiable features</button>
                    </p>
                    <button type="button" class="remove-choice admin-button-small"
                            onclick="removeChoice(this)">Remove</button>
                </div>
            <?php endforeach; ?>
            </div>
            <button type="button" id="add-choice" class="admin-button-small">+ Add Choice</button>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-button">Save Event</button>
            <a href="events.php" class="admin-button admin-button-secondary">Cancel</a>
        </div>
    </form>

<?php elseif ($action === 'preview'): ?>

    <?php
    $preview = ($id !== '') ? find_event($id) : null;
    if (!$preview) {
        echo '<p>Event not found. <a href="events.php">Back to list</a></p>';
    } else {
    ?>
    <div class="admin-section-header">
        <h1>Preview: <?= h($preview['name']) ?></h1>
        <a href="events.php" class="admin-button admin-button-secondary">Back</a>
    </div>

    <div class="event-preview-card">
        <p class="event-preview-description"><?= nl2br(h($preview['description'])) ?></p>
        <?php if ($preview['choices']): ?>
        <div class="event-preview-choices">
            <p><strong>Choices:</strong></p>
            <ul>
            <?php foreach ($preview['choices'] as $choice): ?>
                <li>
                    <strong><?= h($choice['text']) ?></strong>
                    <?php if (!empty($choice['outcome'])): ?>
                        — <em><?= h($choice['outcome']) ?></em>
                    <?php endif; ?>
                    <?php $choice_summary = format_consequence_summary($choice['consequence'] ?? []); ?>
                    <?php if ($choice_summary !== ''): ?>
                        <div class="event-preview-consequence"><?= h($choice_summary) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
            <p><em>No choices defined — this event happens automatically.</em></p>
            <?php $event_summary = format_consequence_summary($preview['consequence'] ?? []); ?>
            <?php if ($event_summary !== ''): ?>
                <p class="event-preview-consequence"><strong>Consequence:</strong> <?= h($event_summary) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php } ?>

<?php endif; ?>

</main>

<div id="feature-context-menu" class="feature-context-menu hidden" role="note" aria-live="polite">
    <p>Modifiable features</p>
    <ul></ul>
</div>
<script>
(function () {
    'use strict';

    const modifiableFeatures = <?= json_encode(array_map(
        static fn(string $key, string $label): array => ['key' => $key, 'label' => $label],
        array_keys($modifiable_features),
        array_values($modifiable_features)
    ), JSON_UNESCAPED_SLASHES) ?>;
    const featureMenu = document.getElementById('feature-context-menu');

    function hideFeatureMenu() {
        if (!featureMenu) return;
        featureMenu.classList.add('hidden');
    }

    function showFeatureMenu(x, y) {
        if (!featureMenu) return;
        featureMenu.style.left = `${x}px`;
        featureMenu.style.top = `${y}px`;
        featureMenu.classList.remove('hidden');
    }

    document.getElementById('add-choice')?.addEventListener('click', function () {
        const list = document.getElementById('choices-list');
        const idx  = list.querySelectorAll('.choice-row').length;
        const row  = document.createElement('div');
        row.className = 'choice-row';
        row.dataset.index = idx;
        row.innerHTML = `
            <label>Choice text</label>
            <input type="text" name="choices_text[]" value="">
            <label>Outcome description</label>
            <input type="text" name="choices_outcome[]" value="">
            <label>Consequence (JSON object)</label>
            <textarea name="choices_consequence[]" rows="3"
                      class="consequence-input" data-feature-list="modifiable"></textarea>
            <p class="form-help">
                <button type="button" class="view-features-btn admin-button-small"
                        onclick="openFeatureMenuFor(this.closest('p').previousElementSibling)">View modifiable features</button>
            </p>
            <button type="button" class="remove-choice admin-button-small"
                    onclick="removeChoice(this)">Remove</button>
        `;
        list.appendChild(row);
    });

    window.removeChoice = function (btn) {
        const row = btn.closest('.choice-row');
        if (document.querySelectorAll('.choice-row').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach(i => i.value = '');
            row.querySelectorAll('textarea').forEach(i => i.value = '');
        }
    };

    function openFeatureMenuFor(textarea, x, y) {
        if (!textarea || !featureMenu) return;
        const list = featureMenu.querySelector('ul');
        if (list) {
            list.innerHTML = modifiableFeatures
                .map((feature) => `<li><strong>${feature.label}</strong> <span>(${feature.key})</span></li>`)
                .join('');
        }
        if (x !== undefined && y !== undefined) {
            showFeatureMenu(x, y);
        } else {
            const rect = textarea.getBoundingClientRect();
            showFeatureMenu(rect.left + window.scrollX, rect.bottom + window.scrollY + 4);
        }
    }

    window.openFeatureMenuFor = function (textarea) {
        if (featureMenu && !featureMenu.classList.contains('hidden')) {
            hideFeatureMenu();
            return;
        }
        openFeatureMenuFor(textarea);
    };

    document.addEventListener('contextmenu', function (event) {
        const target = event.target.closest('.consequence-input');
        if (!target) {
            hideFeatureMenu();
            return;
        }

        event.preventDefault();
        openFeatureMenuFor(target, event.pageX, event.pageY);
    });

    document.addEventListener('click', function (event) {
        if (!featureMenu || featureMenu.contains(event.target)) return;
        if (event.target.closest('.view-features-btn')) return;
        hideFeatureMenu();
    });

    window.addEventListener('resize', hideFeatureMenu);
    document.addEventListener('scroll', hideFeatureMenu, true);
}());
</script>
</body>
</html>
