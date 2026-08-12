<?php
/**
 * LifeSim — admin authentication and session helpers
 *
 * Call admin_session_start() at the top of every admin page.
 * Call require_admin_login() on pages that need an active session.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// ---------------------------------------------------------------------------
// Session management
// ---------------------------------------------------------------------------

function admin_session_start(): void
{
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_admin_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function admin_login(string $username, string $password): bool
{
    $creds = get_admin_credentials();
    if ($username !== $creds['username']) {
        return false;
    }
    if (!password_verify($password, $creds['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username']  = $username;
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Credentials management
// ---------------------------------------------------------------------------

function get_admin_credentials(): array
{
    $creds = read_json_file(ADMIN_CREDENTIALS_FILE);
    if (empty($creds)) {
        // Seed default credentials on first run
        $creds = [
            'username'         => ADMIN_DEFAULT_USERNAME,
            'password_hash'    => password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_BCRYPT),
            'password_changed' => false,
        ];
        write_json_file(ADMIN_CREDENTIALS_FILE, $creds);
    }
    return $creds;
}

function admin_password_is_default(): bool
{
    $creds = get_admin_credentials();
    return empty($creds['password_changed']);
}

function change_admin_password(string $new_password): bool
{
    $creds = get_admin_credentials();
    $creds['password_hash']    = password_hash($new_password, PASSWORD_BCRYPT);
    $creds['password_changed'] = true;
    $ok = write_json_file(ADMIN_CREDENTIALS_FILE, $creds);
    if ($ok) {
        admin_log('password_changed', ['username' => $creds['username']]);
    }
    return $ok;
}

// ---------------------------------------------------------------------------
// Admin change log
// ---------------------------------------------------------------------------

function admin_log(string $action, array $details = []): void
{
    $log     = read_json_file(ADMIN_LOG_FILE, []);
    $log[]   = [
        'timestamp' => now_iso(),
        'username'  => $_SESSION['admin_username'] ?? 'system',
        'action'    => $action,
        'details'   => $details,
    ];
    write_json_file(ADMIN_LOG_FILE, $log);
}
