<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_admin();
$id = required_text($_GET['id'] ?? null, 'Claim ID', 64);
$body = read_body();
$decision = $body['status'] ?? '';
if (!in_array($decision, ['Approved', 'Rejected'], true)) problem('Choose Approved or Rejected.');
$db->beginTransaction();
$reference = query($db, 'SELECT item_id FROM claims WHERE id = ?', [$id])->fetch();
if (!$reference) problem('Claim not found.', 404);
// Always lock the item first so two administrators cannot approve competing claims.
$item = find_item($db, $reference['item_id'], true);
$claim = query($db, 'SELECT * FROM claims WHERE id = ? FOR UPDATE', [$id])->fetch();
if (!$claim) problem('Claim not found.', 404);
if ($claim['status'] === $decision) {
    $db->commit();
    reply(['success' => true]);
}
if ($claim['status'] !== 'Pending' || $item['status'] === 'Resolved') problem('This claim has already been reviewed.', 409);
query($db, 'UPDATE claims SET status = ? WHERE id = ?', [$decision, $id]);
if ($decision === 'Approved') {
    query($db, "UPDATE claims SET status = 'Rejected' WHERE item_id = ? AND id <> ? AND status = 'Pending'", [$item['id'], $id]);
    query($db, "UPDATE items SET status = 'Resolved' WHERE id = ?", [$item['id']]);
} else {
    $pending = query($db, "SELECT COUNT(*) FROM claims WHERE item_id = ? AND status = 'Pending'", [$item['id']])->fetchColumn();
    if (!$pending) query($db, 'UPDATE items SET status = original_status WHERE id = ?', [$item['id']]);
}
$db->commit();
reply(['success' => true]);
