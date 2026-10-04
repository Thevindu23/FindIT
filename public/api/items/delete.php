<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_admin();
$id = required_text($_GET['id'] ?? null, 'Item ID', 64);
$db->beginTransaction();
$item = find_item($db, $id, true);
if ($item['reward_status'] === 'Paid (simulated)') problem('Keep paid reward records for the project history.', 409);
query($db, 'DELETE FROM items WHERE id = ?', [$id]);
$db->commit();
reply(['success' => true]);
