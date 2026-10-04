<?php
// PDO is PHP's built-in database interface. Placeholders keep input out of SQL.
function connect_database(bool $selectDatabase = true): PDO
{
    $config = require dirname(__DIR__) . '/config/database.php';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['name'])) {
        throw new RuntimeException('DB_NAME may contain only letters, digits and underscores.');
    }
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
    if ($selectDatabase) $dsn .= ";dbname={$config['name']}";
    $db = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}

function query(PDO $db, string $sql, array $values = []): PDOStatement
{
    $statement = $db->prepare($sql);
    $statement->execute($values);
    return $statement;
}
