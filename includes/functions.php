<?php
/**
 * LifeSim — shared PHP helper functions
 */

require_once __DIR__ . '/config.php';

/**
 * Read a JSON data file and return its decoded value.
 * Returns a default value if the file does not exist.
 */
function read_json_file(string $path, $default = [])
{
    if (!file_exists($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return ($decoded !== null) ? $decoded : $default;
}

/**
 * Write a value to a JSON data file atomically.
 * Returns true on success, false on failure.
 */
function write_json_file(string $path, $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $path);
}

/**
 * Escape a string for safe HTML output.
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate a cryptographically random UUID v4.
 */
function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Return the current UTC timestamp as an ISO-8601 string.
 */
function now_iso(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
}
