<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
limit_login_attempts($db);
$body = read_body();
$email = valid_email($body['email'] ?? null);
$password = $body['password'] ?? null;
if (!is_string($password) || strlen($password) < 1 || strlen($password) > 128 || str_contains($password, "\0")) problem('Enter a valid password.');
$account = query($db, 'SELECT * FROM users WHERE email = ?', [$email])->fetch();
if (!$account || !check_password($password, $account['password'])) problem('Invalid email or password.', 401);
// Keep long legacy passwords in their original format instead of truncating them.
if (strlen($password) <= 72 && password_needs_rehash($account['password'], PASSWORD_BCRYPT)) {
    query($db, 'UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_BCRYPT), $account['id']]);
}
login_user($account['id']);
reply(['user' => current_user($db)]);
