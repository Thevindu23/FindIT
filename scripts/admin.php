<?php
// Promote only an account already registered through the website.
if (PHP_SAPI !== 'cli') exit('Run this script from a terminal.');
require dirname(__DIR__) . '/includes/database.php';
$email = strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/admin.php your-email@example.com\n");
    exit(1);
}
try {
    $db = connect_database();
    $user = query($db, 'SELECT id FROM users WHERE email = ?', [$email])->fetch();
    if (!$user) throw new RuntimeException('Register this email on the website first.');
    query($db, "UPDATE users SET role = 'admin' WHERE id = ?", [$user['id']]);
    echo "Administrator access granted. Log in and open the dashboard.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
