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
    $events = read_json_file(EVENTS_FILE, []);
    return array_map('normalise_event', array_values(array_filter(is_array($events) ? $events : [], 'is_array')));
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
        'consequence' => [],
        'choices'     => [],
        'created_at'  => now_iso(),
        'updated_at'  => now_iso(),
    ];
}

/**
 * Features that event consequences are allowed to modify.
 */
function modifiable_event_features(): array
{
    return [
        'health'       => 'Health',
        'happiness'    => 'Happiness',
        'intelligence' => 'Intelligence',
        'appearance'   => 'Appearance',
        'discipline'   => 'Discipline',
        'stress'       => 'Stress',
        'reputation'   => 'Reputation',
        'money'        => 'Money',
        'annualIncome' => 'Annual income',
    ];
}

/**
 * Normalise an event record loaded from JSON.
 */
function normalise_event(array $event): array
{
    $event['consequence'] = sanitise_consequence_array($event['consequence'] ?? []);
    $event['choices'] = array_values(array_map(static function (array $choice): array {
        return [
            'text'        => trim((string)($choice['text'] ?? '')),
            'outcome'     => trim((string)($choice['outcome'] ?? '')),
            'consequence' => sanitise_consequence_array($choice['consequence'] ?? []),
        ];
    }, array_values(array_filter($event['choices'] ?? [], 'is_array'))));

    return $event;
}

/**
 * Keep only supported numeric consequence values.
 */
function sanitise_consequence_array($consequence): array
{
    if (!is_array($consequence)) {
        return [];
    }

    $allowed = modifiable_event_features();
    $cleaned = [];

    foreach ($consequence as $feature => $delta) {
        if (!array_key_exists($feature, $allowed) || !is_numeric($delta)) {
            continue;
        }

        $number = $delta + 0;
        $cleaned[$feature] = (int)$number == $number ? (int)$number : (float)$number;
    }

    return $cleaned;
}

/**
 * Parse a consequence JSON object from form input.
 */
function parse_consequence_input(string $raw, string $label): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return ['value' => [], 'errors' => []];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['value' => [], 'errors' => [$label . ' must be valid JSON.']];
    }

    if (!is_array($decoded) || consequence_array_is_list($decoded)) {
        return ['value' => [], 'errors' => [$label . ' must be a JSON object keyed by feature name.']];
    }

    $allowed = modifiable_event_features();
    $errors  = [];
    $value   = [];

    foreach ($decoded as $feature => $delta) {
        if (!array_key_exists($feature, $allowed)) {
            $errors[] = $label . ' contains an unsupported feature: ' . $feature . '.';
            continue;
        }
        if (!is_numeric($delta)) {
            $errors[] = $label . ' for ' . $allowed[$feature] . ' must be numeric.';
            continue;
        }

        $number = $delta + 0;
        $value[$feature] = (int)$number == $number ? (int)$number : (float)$number;
    }

    return ['value' => $value, 'errors' => $errors];
}

/**
 * Compatibility helper for identifying list-style arrays.
 */
function consequence_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }

    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * Format a consequence for preview output.
 */
function format_consequence_summary(array $consequence): string
{
    if (!$consequence) {
        return '';
    }

    $labels = modifiable_event_features();
    $parts  = [];

    foreach ($consequence as $feature => $delta) {
        if (!array_key_exists($feature, $labels) || !is_numeric($delta)) {
            continue;
        }

        $number = $delta + 0;
        $sign   = $number > 0 ? '+' : '';
        $parts[] = $labels[$feature] . ' ' . $sign . $number;
    }

    return implode(', ', $parts);
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
    $event = normalise_event($event);
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
    $errors = [];
    if ($id !== '') {
        $event['id'] = $id;
    }

    $event['name']        = trim($_POST['name'] ?? '');
    $event['description'] = trim($_POST['description'] ?? '');
    $event['enabled']     = isset($_POST['enabled']);
    $event_consequence    = parse_consequence_input((string)($_POST['consequence_json'] ?? ''), 'Automatic event consequence');
    $event['consequence'] = $event_consequence['value'];
    $errors               = array_merge($errors, $event_consequence['errors']);

    // Parse choices: parallel arrays choices_text[] / choices_outcome[] / choices_consequence[]
    $choices_text        = $_POST['choices_text'] ?? [];
    $choices_outcome     = $_POST['choices_outcome'] ?? [];
    $choices_consequence = $_POST['choices_consequence'] ?? [];
    $choices = [];
    foreach ($choices_text as $i => $text) {
        $text    = trim($text);
        $outcome = trim($choices_outcome[$i] ?? '');
        $parsed_consequence = parse_consequence_input(
            (string)($choices_consequence[$i] ?? ''),
            'Choice consequence #' . ($i + 1)
        );
        $errors = array_merge($errors, $parsed_consequence['errors']);
        if ($text !== '') {
            $choices[] = [
                'text'        => $text,
                'outcome'     => $outcome,
                'consequence' => $parsed_consequence['value'],
            ];
        }
    }
    $event['choices'] = $choices;

    return ['event' => $event, 'errors' => $errors];
}
