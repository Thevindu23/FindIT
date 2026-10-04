<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_login();
$body = read_body();
$item = valid_item($body);
$image = valid_image($body['imageData'] ?? null);
$key = required_text($body['requestKey'] ?? null, 'Submission key', 64);
$existing = query($db, 'SELECT * FROM items WHERE user_id = ? AND request_key = ?', [$user['id'], $key])->fetch();
if ($existing) reply(item_response($existing, true));
$id = new_id();
try {
    query($db, 'INSERT INTO items (id, title, category, status, original_status, location, date_reported, reward_amount, description, image_url, reporter_name, reporter_contact, user_id, request_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $id, $item['title'], $item['category'], $item['status'], $item['status'], $item['location'],
        $item['dateReported'], $item['rewardAmount'], $item['description'], $image, $user['name'],
        $item['reporterContact'], $user['id'], $key,
    ]);
} catch (PDOException $error) {
    if (($error->errorInfo[1] ?? 0) != 1062) throw $error;
    $existing = query($db, 'SELECT * FROM items WHERE user_id = ? AND request_key = ?', [$user['id'], $key])->fetch();
    if (!$existing) throw $error;
    reply(item_response($existing, true));
}
reply(item_response(find_item($db, $id), true), 201);
