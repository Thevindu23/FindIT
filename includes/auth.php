<?php
function start_user_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    // Store session files outside the public website folder.
    $directory = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($directory)) mkdir($directory, 0700, true);
    session_save_path($directory);
    session_name('findit_php');
    session_set_cookie_params([
        'lifetime' => 28800, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    if (isset($_SESSION['expires']) && $_SESSION['expires'] < time()) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

function current_user(PDO $db): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    return query($db, 'SELECT id, name, email, role FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch() ?: null;
}

function require_login(): array
{
    global $user;
    if (!$user) problem('Please log in to continue.', 401);
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') problem('Administrator access required.', 403);
    return $user;
}

function login_user(string $id): void
{
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $id, 'expires' => time() + 28800];
}

function check_password(string $password, string $stored): bool
{
    // Compatibility with the old Node.js version; rehash after successful login.
    if (preg_match('/^scrypt:([a-f0-9]{32}):([a-f0-9]{128})$/', $stored, $parts)) {
        if (!function_exists('sodium_crypto_pwhash_scryptsalsa208sha256')) {
            problem('Enable the PHP sodium extension to sign in to an existing account.', 503);
        }
        $key = sodium_crypto_pwhash_scryptsalsa208sha256(64, $password, $parts[1], 524288, 16777216);
        return hash_equals($parts[2], bin2hex($key));
    }
    return password_verify($password, $stored);
}

function limit_login_attempts(PDO $db): void
{
    $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    query($db, 'DELETE FROM login_attempts WHERE expires_at < UTC_TIMESTAMP()');
    query($db, 'INSERT INTO login_attempts (address_hash, attempts, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE attempts = attempts + 1', [$key]);
    $row = query($db, 'SELECT attempts FROM login_attempts WHERE address_hash = ?', [$key])->fetch();
    if ($row['attempts'] > 30) problem('Too many login attempts. Try again in 15 minutes.', 429);
}
