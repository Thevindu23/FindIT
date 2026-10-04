<?php
// Every API file starts here: JSON responses, security, database and session.
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

set_exception_handler(function (Throwable $error): void {
    global $db;
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('FindIT: ' . $error->getMessage());
    if ($error instanceof PDOException) {
        $code = (int) ($error->errorInfo[1] ?? 0);
        if ($code === 1062) reply(['error' => 'This email or record already exists.'], 409);
        reply(['error' => 'Database unavailable or not set up. Check MySQL and run php scripts/setup.php.'], 503);
    }
    reply(['error' => 'The request could not be completed. Please try again.'], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) problem('Send JSON data.', 415);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' || ($origin !== '' && $origin !== $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? ''))) {
        problem('Use this website to submit changes.', 403);
    }
}
$db = connect_database();
start_user_session();
$user = current_user($db);
