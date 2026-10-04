<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_login();
$body = read_body();
$id = required_text($_GET['id'] ?? null, 'Item ID', 64);
$contact = valid_contact($body['finderContact'] ?? null);
$note = required_text($body['note'] ?? null, 'Claim details', 2000, 10);
$db->beginTransaction();
$item = find_item($db, $id, true);
if ($item['status'] === 'Resolved') problem('This item is already resolved.', 409);
if ($item['user_id'] === $user['id']) problem('You cannot claim your own report.', 409);
$existing = query($db, "SELECT id FROM claims WHERE item_id = ? AND user_id = ? AND status = 'Pending'", [$id, $user['id']])->fetch();
if ($existing) {
    $db->commit();
    reply(['id' => $existing['id'], 'existing' => true]);
}
$claimId = new_id();
query($db, 'INSERT INTO claims (id, item_id, finder_name, finder_contact, note, user_id) VALUES (?, ?, ?, ?, ?, ?)', [$claimId, $id, $user['name'], $contact, $note, $user['id']]);
query($db, "UPDATE items SET status = 'Claim Pending' WHERE id = ?", [$id]);
$db->commit();
reply(['id' => $claimId, 'existing' => false], 201);
