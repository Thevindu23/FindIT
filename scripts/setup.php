<?php
// Run in a terminal only: php scripts/setup.php
if (PHP_SAPI !== 'cli') exit('Run this script from a terminal.');
require_once dirname(__DIR__) . '/includes/database.php';

function setup_database(): void
{
    $config = require dirname(__DIR__) . '/config/database.php';
    $db = connect_database(false);
    $db->exec('CREATE DATABASE IF NOT EXISTS `' . $config['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('USE `' . $config['name'] . '`');
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    // Back up existing records before changing an existing database.
    if (in_array('users', $tables, true) && !in_array('login_attempts', $tables, true)) {
        $backup = [];
        foreach (['users', 'items', 'claims', 'sessions'] as $table) {
            if (in_array($table, $tables, true)) $backup[$table] = $db->query("SELECT * FROM `$table`")->fetchAll();
        }
        $folder = dirname(__DIR__) . '/backups';
        if (!is_dir($folder)) mkdir($folder, 0700, true);
        $file = $folder . '/before-php-database-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
        if (file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Could not save the database backup. Setup stopped.');
        }
        echo "Existing records backed up outside public/.\n";
    }
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    foreach (explode(';', $schema) as $sql) {
        if (trim($sql) !== '') $db->exec($sql);
    }
    // These additions also support databases from the original prototype.
    $additions = [
        'items' => [
            'user_id' => 'VARCHAR(64) NULL',
            'original_status' => "ENUM('Lost','Found') NOT NULL DEFAULT 'Lost'",
            'reward_status' => "ENUM('Unpaid','Paid (simulated)') NOT NULL DEFAULT 'Unpaid'",
            'request_key' => 'VARCHAR(64) NULL',
        ],
        'claims' => ['user_id' => 'VARCHAR(64) NULL'],
    ];
    foreach ($additions as $table => $fields) {
        $existing = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fields as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
                if ($name === 'original_status') $db->exec("UPDATE items SET original_status = CASE WHEN status = 'Found' THEN 'Found' ELSE 'Lost' END");
            }
        }
    }
    $imageColumn = $db->query("SHOW COLUMNS FROM items LIKE 'image_url'")->fetch();
    if ($imageColumn['Type'] !== 'mediumtext') $db->exec('ALTER TABLE items MODIFY image_url MEDIUMTEXT');
    $indexes = $db->query('SHOW INDEX FROM items')->fetchAll();
    if (!in_array('unique_report_request', array_column($indexes, 'Key_name'), true)) {
        $db->exec('CREATE UNIQUE INDEX unique_report_request ON items (user_id, request_key)');
    }
    foreach ($db->query('SELECT id, password FROM users')->fetchAll() as $account) {
        $stored = $account['password'];
        // Scrypt remains readable by PHP sodium; plain prototype passwords are hashed now.
        if (!str_starts_with($stored, 'scrypt:') && password_get_info($stored)['algo'] === null) {
            if (strlen($stored) > 72 || str_contains($stored, "\0")) throw new RuntimeException('A legacy password needs manual migration. Existing account was not modified.');
            query($db, 'UPDATE users SET password = ? WHERE id = ?', [password_hash($stored, PASSWORD_BCRYPT), $account['id']]);
        }
    }
    echo "MySQL is ready. Existing reports and accounts were preserved.\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try { setup_database(); }
    catch (Throwable $error) { fwrite(STDERR, "Setup failed: " . $error->getMessage() . "\n"); exit(1); }
}
