<?php
/**
 * LifeSim — shared admin helper functions
 */

require_once dirname(__DIR__, 2) . '/includes/functions.php';

function content_files(): array
{
    return [
        'events'          => EVENTS_FILE,
        'countries'       => COUNTRIES_FILE,
        'cities'          => CITIES_FILE,
        'professions'     => PROFESSIONS_FILE,
        'world_events'    => WORLD_EVENTS_FILE,
        'education_types' => EDUCATION_TYPES_FILE,
    ];
}

function slugify_identifier(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return trim($value, '_') ?: generate_uuid();
}

function csv_or_lines_to_list(string $raw): array
{
    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    return array_values(array_filter(array_map(static fn(string $item): string => trim($item), $parts), static fn(string $item): bool => $item !== ''));
}

function normalise_enabled(array $record): bool
{
    return !array_key_exists('enabled', $record) || !empty($record['enabled']);
}

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

function consequence_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

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
    $errors = [];
    $value = [];
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

function format_consequence_summary(array $consequence): string
{
    if (!$consequence) {
        return '';
    }

    $labels = modifiable_event_features();
    $parts = [];
    foreach ($consequence as $feature => $delta) {
        if (!array_key_exists($feature, $labels) || !is_numeric($delta)) {
            continue;
        }
        $number = $delta + 0;
        $sign = $number > 0 ? '+' : '';
        $parts[] = $labels[$feature] . ' ' . $sign . $number;
    }

    return implode(', ', $parts);
}

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
    $event['enabled'] = normalise_enabled($event);
    return $event;
}

function get_all_events(): array
{
    $events = read_json_file(EVENTS_FILE, []);
    return array_map('normalise_event', array_values(array_filter(is_array($events) ? $events : [], 'is_array')));
}

function save_events(array $events): bool
{
    return write_json_file(EVENTS_FILE, $events);
}

function find_event(string $id): ?array
{
    foreach (get_all_events() as $event) {
        if (($event['id'] ?? '') === $id) {
            return $event;
        }
    }
    return null;
}

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

function upsert_event(array $event): array
{
    $event = normalise_event($event);
    $errors = validate_event($event);
    if ($errors) {
        return $errors;
    }

    $events = get_all_events();
    $found = false;
    foreach ($events as &$existing) {
        if ($existing['id'] === $event['id']) {
            $event['updated_at'] = now_iso();
            $existing = $event;
            $found = true;
            break;
        }
    }
    unset($existing);

    if (!$found) {
        $event['created_at'] = now_iso();
        $event['updated_at'] = now_iso();
        $events[] = $event;
    }

    save_events($events);
    return [];
}

function toggle_event(string $id): bool
{
    $events = get_all_events();
    foreach ($events as &$event) {
        if ($event['id'] === $id) {
            $event['enabled'] = !($event['enabled'] ?? true);
            $event['updated_at'] = now_iso();
            return save_events($events);
        }
    }
    return false;
}

function event_from_post(string $id = ''): array
{
    $event = blank_event();
    $errors = [];
    if ($id !== '') {
        $event['id'] = $id;
    }

    $event['name'] = trim($_POST['name'] ?? '');
    $event['description'] = trim($_POST['description'] ?? '');
    $event['enabled'] = isset($_POST['enabled']);
    $eventConsequence = parse_consequence_input((string)($_POST['consequence_json'] ?? ''), 'Automatic event consequence');
    $event['consequence'] = $eventConsequence['value'];
    $errors = array_merge($errors, $eventConsequence['errors']);

    $choicesText = $_POST['choices_text'] ?? [];
    $choicesOutcome = $_POST['choices_outcome'] ?? [];
    $choicesConsequence = $_POST['choices_consequence'] ?? [];
    $choices = [];

    foreach ($choicesText as $index => $text) {
        $text = trim((string)$text);
        $outcome = trim((string)($choicesOutcome[$index] ?? ''));
        $parsedConsequence = parse_consequence_input((string)($choicesConsequence[$index] ?? ''), 'Choice consequence #' . ($index + 1));
        $errors = array_merge($errors, $parsedConsequence['errors']);
        if ($text !== '') {
            $choices[] = [
                'text'        => $text,
                'outcome'     => $outcome,
                'consequence' => $parsedConsequence['value'],
            ];
        }
    }
    $event['choices'] = $choices;

    return ['event' => $event, 'errors' => $errors];
}

function blank_country(): array
{
    return [
        'code' => '',
        'name' => '',
        'enabled' => true,
        'cities' => [],
    ];
}

function normalise_country(array $country, array $citiesMap = []): array
{
    $code = strtoupper(trim((string)($country['code'] ?? '')));
    return [
        'code' => $code,
        'name' => trim((string)($country['name'] ?? '')),
        'enabled' => normalise_enabled($country),
        'cities' => array_values(array_filter(array_map('trim', $country['cities'] ?? ($citiesMap[$code] ?? [])), static fn(string $city): bool => $city !== '')),
    ];
}

function get_all_countries(): array
{
    $countries = read_json_file(COUNTRIES_FILE, []);
    $citiesMap = read_json_file(CITIES_FILE, []);
    return array_map(static fn(array $country): array => normalise_country($country, is_array($citiesMap) ? $citiesMap : []), array_values(array_filter(is_array($countries) ? $countries : [], 'is_array')));
}

function save_countries(array $countries): bool
{
    $countryRecords = [];
    $citiesMap = [];

    foreach ($countries as $country) {
        $normalised = normalise_country($country);
        $countryRecords[] = [
            'code' => $normalised['code'],
            'name' => $normalised['name'],
            'enabled' => $normalised['enabled'],
        ];
        $citiesMap[$normalised['code']] = $normalised['cities'];
    }

    return write_json_file(COUNTRIES_FILE, $countryRecords) && write_json_file(CITIES_FILE, $citiesMap);
}

function find_country(string $code): ?array
{
    foreach (get_all_countries() as $country) {
        if (($country['code'] ?? '') === strtoupper($code)) {
            return $country;
        }
    }
    return null;
}

function validate_country(array $country): array
{
    $errors = [];
    if ($country['code'] === '' || !preg_match('/^[A-Z]{3}$/', $country['code'])) {
        $errors[] = 'Country code must be a 3-letter uppercase code.';
    }
    if ($country['name'] === '') {
        $errors[] = 'Country name is required.';
    }
    if (empty($country['cities'])) {
        $errors[] = 'At least one city is required.';
    }
    return $errors;
}

function upsert_country(array $country, string $originalCode = ''): array
{
    $country = normalise_country($country);
    $errors = validate_country($country);
    if ($errors) {
        return $errors;
    }

    $countries = get_all_countries();
    $matchCode = strtoupper(trim($originalCode)) !== '' ? strtoupper(trim($originalCode)) : $country['code'];
    $found = false;
    foreach ($countries as &$existing) {
        if ($existing['code'] === $matchCode) {
            $existing = $country;
            $found = true;
            break;
        }
    }
    unset($existing);

    if (!$found) {
        $countries[] = $country;
    }

    save_countries($countries);
    return [];
}

function toggle_country(string $code): bool
{
    $countries = get_all_countries();
    foreach ($countries as &$country) {
        if ($country['code'] === strtoupper($code)) {
            $country['enabled'] = !($country['enabled'] ?? true);
            return save_countries($countries);
        }
    }
    return false;
}

function country_from_post(): array
{
    $country = blank_country();
    $country['code'] = strtoupper(trim($_POST['code'] ?? ''));
    $country['name'] = trim($_POST['name'] ?? '');
    $country['enabled'] = isset($_POST['enabled']);
    $country['cities'] = csv_or_lines_to_list((string)($_POST['cities_text'] ?? ''));
    return ['country' => $country, 'errors' => validate_country($country)];
}

function blank_profession(): array
{
    return [
        'id' => generate_uuid(),
        'name' => '',
        'category' => '',
        'education' => '',
        'enabled' => true,
        'levels' => [blank_profession_level()],
    ];
}

function blank_profession_level(): array
{
    return ['title' => '', 'minWage' => 0, 'maxWage' => 0, 'education' => ''];
}

function normalise_profession_level(array $level): array
{
    return [
        'title' => trim((string)($level['title'] ?? '')),
        'minWage' => (int)($level['minWage'] ?? 0),
        'maxWage' => (int)($level['maxWage'] ?? 0),
        'education' => trim((string)($level['education'] ?? '')),
    ];
}

function normalise_profession(array $profession): array
{
    $levels = array_values(array_filter(array_map('normalise_profession_level', array_values(array_filter($profession['levels'] ?? [], 'is_array'))), static fn(array $level): bool => $level['title'] !== ''));
    return [
        'id' => trim((string)($profession['id'] ?? slugify_identifier((string)($profession['name'] ?? '')))),
        'name' => trim((string)($profession['name'] ?? '')),
        'category' => trim((string)($profession['category'] ?? '')),
        'education' => trim((string)($profession['education'] ?? '')),
        'enabled' => normalise_enabled($profession),
        'levels' => $levels ?: [blank_profession_level()],
    ];
}

function get_all_professions(): array
{
    $professions = read_json_file(PROFESSIONS_FILE, []);
    return array_map('normalise_profession', array_values(array_filter(is_array($professions) ? $professions : [], 'is_array')));
}

function save_professions(array $professions): bool
{
    return write_json_file(PROFESSIONS_FILE, array_values(array_map('normalise_profession', $professions)));
}

function find_profession(string $id): ?array
{
    foreach (get_all_professions() as $profession) {
        if (($profession['id'] ?? '') === $id) {
            return $profession;
        }
    }
    return null;
}

function validate_profession(array $profession): array
{
    $errors = [];
    if ($profession['name'] === '') {
        $errors[] = 'Profession name is required.';
    }
    if ($profession['category'] === '') {
        $errors[] = 'Profession category is required.';
    }
    $validLevels = array_values(array_filter($profession['levels'], static fn(array $level): bool => trim($level['title']) !== ''));
    if (!$validLevels) {
        $errors[] = 'At least one profession level is required.';
    }
    foreach ($validLevels as $index => $level) {
        if ($level['maxWage'] < $level['minWage']) {
            $errors[] = 'Profession level #' . ($index + 1) . ' cannot have a max wage below its min wage.';
        }
    }
    return $errors;
}

function upsert_profession(array $profession, string $originalId = ''): array
{
    $profession = normalise_profession($profession);
    $errors = validate_profession($profession);
    if ($errors) {
        return $errors;
    }

    $professions = get_all_professions();
    $matchId = trim($originalId) !== '' ? trim($originalId) : $profession['id'];
    $found = false;
    foreach ($professions as &$existing) {
        if ($existing['id'] === $matchId) {
            $existing = $profession;
            $found = true;
            break;
        }
    }
    unset($existing);

    if (!$found) {
        $professions[] = $profession;
    }

    save_professions($professions);
    return [];
}

function toggle_profession(string $id): bool
{
    $professions = get_all_professions();
    foreach ($professions as &$profession) {
        if ($profession['id'] === $id) {
            $profession['enabled'] = !($profession['enabled'] ?? true);
            return save_professions($professions);
        }
    }
    return false;
}

function profession_from_post(string $id = ''): array
{
    $profession = blank_profession();
    if ($id !== '') {
        $profession['id'] = $id;
    }

    $profession['name'] = trim($_POST['name'] ?? '');
    $profession['id'] = trim($_POST['identifier'] ?? $profession['id']);
    $profession['category'] = trim($_POST['category'] ?? '');
    $profession['education'] = trim($_POST['education'] ?? '');
    $profession['enabled'] = isset($_POST['enabled']);

    $titles = $_POST['level_title'] ?? [];
    $mins = $_POST['level_min_wage'] ?? [];
    $maxes = $_POST['level_max_wage'] ?? [];
    $educations = $_POST['level_education'] ?? [];
    $levels = [];

    foreach ($titles as $index => $title) {
        $levels[] = [
            'title' => trim((string)$title),
            'minWage' => (int)($mins[$index] ?? 0),
            'maxWage' => (int)($maxes[$index] ?? 0),
            'education' => trim((string)($educations[$index] ?? '')),
        ];
    }
    $profession['levels'] = $levels;
    $profession = normalise_profession($profession);

    return ['profession' => $profession, 'errors' => validate_profession($profession)];
}

function blank_world_event(): array
{
    return [
        'id' => generate_uuid(),
        'name' => '',
        'description' => '',
        'regions' => [],
        'enabled' => true,
    ];
}

function normalise_world_event(array $event): array
{
    return [
        'id' => trim((string)($event['id'] ?? slugify_identifier((string)($event['name'] ?? '')))),
        'name' => trim((string)($event['name'] ?? '')),
        'description' => trim((string)($event['description'] ?? '')),
        'regions' => array_values(array_filter(array_map('trim', $event['regions'] ?? []), static fn(string $region): bool => $region !== '')),
        'enabled' => normalise_enabled($event),
    ];
}

function get_all_world_events(): array
{
    $events = read_json_file(WORLD_EVENTS_FILE, []);
    return array_map('normalise_world_event', array_values(array_filter(is_array($events) ? $events : [], 'is_array')));
}

function save_world_events(array $events): bool
{
    return write_json_file(WORLD_EVENTS_FILE, array_values(array_map('normalise_world_event', $events)));
}

function find_world_event(string $id): ?array
{
    foreach (get_all_world_events() as $event) {
        if (($event['id'] ?? '') === $id) {
            return $event;
        }
    }
    return null;
}

function validate_world_event(array $event): array
{
    $errors = [];
    if ($event['name'] === '') {
        $errors[] = 'World event name is required.';
    }
    if ($event['description'] === '') {
        $errors[] = 'World event description is required.';
    }
    return $errors;
}

function upsert_world_event(array $event): array
{
    $event = normalise_world_event($event);
    $errors = validate_world_event($event);
    if ($errors) {
        return $errors;
    }

    $events = get_all_world_events();
    $found = false;
    foreach ($events as &$existing) {
        if ($existing['id'] === $event['id']) {
            $existing = $event;
            $found = true;
            break;
        }
    }
    unset($existing);

    if (!$found) {
        $events[] = $event;
    }

    save_world_events($events);
    return [];
}

function toggle_world_event(string $id): bool
{
    $events = get_all_world_events();
    foreach ($events as &$event) {
        if ($event['id'] === $id) {
            $event['enabled'] = !($event['enabled'] ?? true);
            return save_world_events($events);
        }
    }
    return false;
}

function world_event_from_post(string $id = ''): array
{
    $event = blank_world_event();
    if ($id !== '') {
        $event['id'] = $id;
    }
    $event['name'] = trim($_POST['name'] ?? '');
    $event['description'] = trim($_POST['description'] ?? '');
    $event['regions'] = csv_or_lines_to_list((string)($_POST['regions_text'] ?? ''));
    $event['enabled'] = isset($_POST['enabled']);
    $event = normalise_world_event($event);
    return ['world_event' => $event, 'errors' => validate_world_event($event)];
}

// ── Education types ──────────────────────────────────────────────────────────

function blank_education_type(): array
{
    return ['id' => '', 'label' => '', 'enabled' => true];
}

function normalise_education_type(array $type): array
{
    return [
        'id'      => trim((string)($type['id'] ?? '')),
        'label'   => trim((string)($type['label'] ?? '')),
        'enabled' => normalise_enabled($type),
    ];
}

function get_all_education_types(): array
{
    $types = read_json_file(EDUCATION_TYPES_FILE, []);
    return array_map('normalise_education_type', array_values(array_filter(is_array($types) ? $types : [], 'is_array')));
}

function get_enabled_education_types(): array
{
    return array_values(array_filter(get_all_education_types(), static fn(array $type): bool => !empty($type['enabled'])));
}

function save_education_types(array $types): bool
{
    return write_json_file(EDUCATION_TYPES_FILE, array_values(array_map('normalise_education_type', $types)));
}

function find_education_type(string $id): ?array
{
    foreach (get_all_education_types() as $type) {
        if (($type['id'] ?? '') === $id) {
            return $type;
        }
    }
    return null;
}

function validate_education_type(array $type): array
{
    $errors = [];
    if ($type['id'] === '') {
        $errors[] = 'Education type identifier is required.';
    }
    if ($type['label'] === '') {
        $errors[] = 'Education type label is required.';
    }
    return $errors;
}

function upsert_education_type(array $type, string $originalId = ''): array
{
    $type = normalise_education_type($type);
    $errors = validate_education_type($type);
    if ($errors) {
        return $errors;
    }

    $types = get_all_education_types();
    $matchId = trim($originalId) !== '' ? trim($originalId) : $type['id'];
    $found = false;
    foreach ($types as &$existing) {
        if ($existing['id'] === $matchId) {
            $existing = $type;
            $found = true;
            break;
        }
    }
    unset($existing);

    if (!$found) {
        // Check for duplicate id
        foreach ($types as $existing) {
            if ($existing['id'] === $type['id']) {
                return ['An education type with that identifier already exists.'];
            }
        }
        $types[] = $type;
    }

    save_education_types($types);
    return [];
}

function toggle_education_type(string $id): bool
{
    $types = get_all_education_types();
    foreach ($types as &$type) {
        if ($type['id'] === $id) {
            $type['enabled'] = !($type['enabled'] ?? true);
            return save_education_types($types);
        }
    }
    return false;
}

function delete_education_type(string $id): bool
{
    $types = get_all_education_types();
    $filtered = array_values(array_filter($types, static fn(array $t): bool => $t['id'] !== $id));
    if (count($filtered) === count($types)) {
        return false;
    }
    return save_education_types($filtered);
}

function education_type_from_post(string $originalId = ''): array
{
    $type = blank_education_type();
    $type['id'] = trim($_POST['id'] ?? '');
    $type['label'] = trim($_POST['label'] ?? '');
    $type['enabled'] = isset($_POST['enabled']);
    return ['type' => $type, 'errors' => validate_education_type($type), 'original_id' => $originalId];
}
