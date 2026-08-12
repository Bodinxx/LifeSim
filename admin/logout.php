<?php
/**
 * LifeSim — Admin logout handler
 */

require_once __DIR__ . '/auth.php';
admin_session_start();

if (admin_is_logged_in()) {
    admin_log('logout');
    admin_logout();
}

header('Location: index.php');
exit;
