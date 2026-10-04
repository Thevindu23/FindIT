<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');
$item = find_item($db, required_text($_GET['id'] ?? null, 'Item ID', 64));
$result = item_response($item, $user !== null);
$result['isOwner'] = $user && $user['id'] === $item['user_id'];
$result['claims'] = [];
if ($user) {
    $sql = 'SELECT * FROM claims WHERE item_id = ?';
    $values = [$item['id']];
    if ($user['role'] !== 'admin' && !$result['isOwner']) {
        $sql .= ' AND user_id = ?';
        $values[] = $user['id'];
    }
    foreach (query($db, $sql . ' ORDER BY created_at DESC', $values)->fetchAll() as $claim) {
        $result['claims'][] = claim_response($claim);
    }
}
reply($result);
