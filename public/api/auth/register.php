<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
limit_login_attempts($db);
$body = read_body();
$name = required_text($body['name'] ?? null, 'Name', 100, 2);
$email = valid_email($body['email'] ?? null);
$password = $body['password'] ?? null;
// Bcrypt supports up to 72 bytes. Never silently truncate a password.
if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
    problem('Password must contain 8 to 72 bytes and no null characters.');
}
$id = new_id();
query($db, "INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, 'user')", [
    $id, $name, $email, password_hash($password, PASSWORD_BCRYPT),
]);
login_user($id);
reply(['user' => current_user($db)], 201);
