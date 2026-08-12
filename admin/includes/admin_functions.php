<?php
/**
 * LifeSim — shared admin helper functions (event management etc.)
 */

require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ---------------------------------------------------------------------------
// Events
// ---------------------------------------------------------------------------

/**
 * Return all events from the events data file.
 */
function get_all_events(): array
{
    return read_json_file(EVENTS_FILE, []);
}

/**
 * Persist the events array back to disk.
 */
function save_events(array $events): bool
{
    return write_json_file(EVENTS_FILE, $events);
}

/**
 * Find an event by its id. Returns the event array or null.
 */
function find_event(string $id): ?array
{
    foreach (get_all_events() as $event) {
        if (($event['id'] ?? '') === $id) {
            return $event;
        }
    }
    return null;
}

/**
 * Create a blank event skeleton with required fields.
 */
function blank_event(): array
{
    return [
        'id'          => generate_uuid(),
        'name'        => '',
        'description' => '',
        'enabled'     => true,
        'choices'     => [],
        'created_at'  => now_iso(),
        'updated_at'  => now_iso(),
    ];
}

/**
 * Validate event fields. Returns an array of error messages (empty = valid).
 */
function validate_event(array $event): array
{
    $errors = [];
    if (empty(trim($event['name'] ?? ''))) {
        $errors[] = 'Event name is required.';
    }
    if (empty(trim($event['description'] ?? ''))) {
        $errors[] = 'Event description is required.';
    }
    return $errors;
}

/**
 * Upsert an event (insert or update by id).
 * Returns an array of validation errors, or an empty array on success.
 */
function upsert_event(array $event): array
{
    $errors = validate_event($event);
    if ($errors) {
        return $errors;
    }

    $events = get_all_events();
    $found  = false;
    foreach ($events as &$existing) {
        if ($existing['id'] === $event['id']) {
            $event['updated_at'] = now_iso();
            $existing = $event;
            $found    = true;
            break;
        }
    }
    unset($existing);

    if (!$found) {
        $event['created_at'] = now_iso();
        $event['updated_at'] = now_iso();
        $events[]            = $event;
    }

    save_events($events);
    return [];
}

/**
 * Toggle the enabled flag of an event. Returns true on success.
 */
function toggle_event(string $id): bool
{
    $events = get_all_events();
    foreach ($events as &$event) {
        if ($event['id'] === $id) {
            $event['enabled']    = !($event['enabled'] ?? true);
            $event['updated_at'] = now_iso();
            return save_events($events);
        }
    }
    return false;
}

/**
 * Sanitise and extract event fields from $_POST.
 */
function event_from_post(string $id = ''): array
{
    $event = blank_event();
    if ($id !== '') {
        $event['id'] = $id;
    }

    $event['name']        = trim($_POST['name'] ?? '');
    $event['description'] = trim($_POST['description'] ?? '');
    $event['enabled']     = isset($_POST['enabled']);

    // Parse choices: parallel arrays choices_text[] and choices_outcome[]
    $choices_text    = $_POST['choices_text'] ?? [];
    $choices_outcome = $_POST['choices_outcome'] ?? [];
    $choices = [];
    foreach ($choices_text as $i => $text) {
        $text    = trim($text);
        $outcome = trim($choices_outcome[$i] ?? '');
        if ($text !== '') {
            $choices[] = ['text' => $text, 'outcome' => $outcome];
        }
    }
    $event['choices'] = $choices;

    return $event;
}
