<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_admin();
$id = required_text($_GET['id'] ?? null, 'Item ID', 64);
$db->beginTransaction();
find_item($db, $id, true);
$pending = query($db, "SELECT COUNT(*) FROM claims WHERE item_id = ? AND status = 'Pending'", [$id])->fetchColumn();
if ($pending) problem('Approve or reject pending claims first.', 409);
query($db, "UPDATE items SET status = 'Resolved' WHERE id = ?", [$id]);
$db->commit();
reply(['success' => true]);
