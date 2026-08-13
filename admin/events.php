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
usort($events, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
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
                    <a href="events.php?action=edit&id=<?= h($ev['id']) ?>" title="Edit">✏️</a>
                    <a href="#" class="preview-btn" data-id="<?= h($ev['id']) ?>" title="Preview">👁️</a>
                    <a href="events.php?action=toggle&id=<?= h($ev['id']) ?>"
                       onclick="return confirm('Toggle this event?')" title="<?= !empty($ev['enabled']) ? 'Disable' : 'Enable' ?>">🔁</a>
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
            Applies when the event has no choices. &nbsp;
            <span class="feature-insert-row">
                <select class="feature-select admin-input-small">
                    <?php foreach ($modifiable_features as $fkey => $flabel): ?>
                    <option value="<?= h($fkey) ?>"><?= h($flabel) ?> (<?= h($fkey) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="insert-feature-btn admin-button-small"
                        data-target="consequence_json">Insert</button>
            </span>
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
                        <span class="feature-insert-row">
                            <select class="feature-select admin-input-small">
                                <?php foreach ($modifiable_features as $fkey => $flabel): ?>
                                <option value="<?= h($fkey) ?>"><?= h($flabel) ?> (<?= h($fkey) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="insert-feature-btn admin-button-small">Insert</button>
                        </span>
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
    <?php header('Location: events.php'); exit; ?>

<?php endif; ?>

</main>

<!-- Preview popover -->
<div id="preview-overlay" class="preview-overlay hidden" aria-modal="true" role="dialog">
    <div class="preview-popover">
        <div class="preview-popover-header">
            <strong id="preview-title"></strong>
            <button type="button" id="preview-close" class="admin-button-small">✕</button>
        </div>
        <div id="preview-body" class="preview-popover-body"></div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const allEvents = <?= json_encode(array_map(static function(array $ev): array {
        return [
            'id'          => $ev['id'],
            'name'        => $ev['name'],
            'description' => $ev['description'],
            'choices'     => $ev['choices'],
            'consequence' => $ev['consequence'],
        ];
    }, $events), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const featureLabels = <?= json_encode($modifiable_features, JSON_UNESCAPED_SLASHES) ?>;

    // -----------------------------------------------------------------------
    // Feature insert
    // -----------------------------------------------------------------------
    function insertFeature(btn) {
        const row = btn.closest('.form-help, p');
        const select = row ? row.querySelector('.feature-select') : null;
        const key = select ? select.value : null;
        if (!key) return;

        // Find the nearest preceding .consequence-input
        let textarea = null;
        if (btn.dataset.target) {
            textarea = document.getElementById(btn.dataset.target);
        } else {
            const parent = btn.closest('.choice-row') || btn.closest('.admin-form');
            if (parent) {
                const all = parent.querySelectorAll('.consequence-input');
                textarea = all[all.length - 1];
            }
        }
        if (!textarea) return;

        let raw = textarea.value.trim();
        let obj = {};
        if (raw !== '') {
            try { obj = JSON.parse(raw); } catch (e) { obj = {}; }
        }
        if (typeof obj !== 'object' || Array.isArray(obj)) obj = {};
        if (!(key in obj)) obj[key] = 0;
        textarea.value = JSON.stringify(obj, null, 4);
        textarea.dispatchEvent(new Event('input'));
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.insert-feature-btn')) {
            insertFeature(e.target.closest('.insert-feature-btn'));
        }
    });

    // -----------------------------------------------------------------------
    // Add / remove choices
    // -----------------------------------------------------------------------
    function featureSelectOptions() {
        return Object.entries(featureLabels)
            .map(([k, v]) => `<option value="${k}">${v} (${k})</option>`)
            .join('');
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
                <span class="feature-insert-row">
                    <select class="feature-select admin-input-small">${featureSelectOptions()}</select>
                    <button type="button" class="insert-feature-btn admin-button-small">Insert</button>
                </span>
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

    // -----------------------------------------------------------------------
    // Preview popover
    // -----------------------------------------------------------------------
    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const overlay = document.getElementById('preview-overlay');
    const previewTitle = document.getElementById('preview-title');
    const previewBody = document.getElementById('preview-body');

    function formatConsequence(consequence) {
        const parts = [];
        for (const [k, v] of Object.entries(consequence || {})) {
            if (featureLabels[k] !== undefined) {
                const sign = v > 0 ? '+' : '';
                parts.push(esc(featureLabels[k]) + ' ' + sign + v);
            }
        }
        return parts.join(', ');
    }

    function openPreview(id) {
        const ev = allEvents.find(e => e.id === id);
        if (!ev || !overlay) return;
        previewTitle.textContent = ev.name;
        let html = `<p class="event-preview-description">${esc(ev.description).replace(/\n/g, '<br>')}</p>`;
        if (ev.choices && ev.choices.length) {
            html += '<p><strong>Choices:</strong></p><ul>';
            ev.choices.forEach(c => {
                html += `<li><strong>${esc(c.text)}</strong>`;
                if (c.outcome) html += ` — <em>${esc(c.outcome)}</em>`;
                const cs = formatConsequence(c.consequence);
                if (cs) html += `<div class="event-preview-consequence">${cs}</div>`;
                html += '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p><em>No choices — this event happens automatically.</em></p>';
            const cs = formatConsequence(ev.consequence);
            if (cs) html += `<p class="event-preview-consequence"><strong>Consequence:</strong> ${cs}</p>`;
        }
        previewBody.innerHTML = html;
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closePreview() {
        if (overlay) overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.preview-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openPreview(this.dataset.id);
        });
    });

    document.getElementById('preview-close')?.addEventListener('click', closePreview);
    overlay?.addEventListener('click', function (e) {
        if (e.target === overlay) closePreview();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePreview();
    });
}());
</script>
</body>
</html>
