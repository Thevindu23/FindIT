<?php
require dirname(__DIR__, 2) . '/includes/database.php';
require dirname(__DIR__, 2) . '/includes/helpers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_method('GET');
try {
    $db = connect_database();
    // Check the tables needed by the PHP version, not just the connection.
    $db->query('SELECT id, user_id, original_status, reward_status, request_key FROM items LIMIT 0');
    $db->query('SELECT address_hash FROM login_attempts LIMIT 0');
    reply(['status' => 'ok', 'dbConnected' => true]);
} catch (Throwable $error) {
    reply(['status' => 'unavailable', 'dbConnected' => false], 503);
}
