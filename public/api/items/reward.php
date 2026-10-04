<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_admin();
$id = required_text($_GET['id'] ?? null, 'Item ID', 64);
$db->beginTransaction();
$item = find_item($db, $id, true);
$claim = query($db, "SELECT id FROM claims WHERE item_id = ? AND status = 'Approved'", [$id])->fetch();
if ($item['status'] !== 'Resolved' || $item['reward_amount'] <= 0 || !$claim) problem('A positive reward and an approved claim are required before recording a simulated payout.', 409);
query($db, "UPDATE items SET reward_status = 'Paid (simulated)' WHERE id = ?", [$id]);
$db->commit();
reply(['success' => true]);
