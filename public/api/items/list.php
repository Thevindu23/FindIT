<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');
$sql = 'SELECT * FROM items WHERE 1 = 1';
$values = [];
$status = $_GET['status'] ?? 'All';
if ($status !== 'All') {
    if (!in_array($status, ['Lost', 'Found', 'Claim Pending', 'Resolved'], true)) problem('Invalid status.');
    $sql .= ' AND status = ?';
    $values[] = $status;
}
if (isset($_GET['category']) && $_GET['category'] !== 'All') {
    $sql .= ' AND category = ?';
    $values[] = required_text($_GET['category'], 'Category', 100);
}
if (!empty($_GET['search'])) {
    $sql .= ' AND (title LIKE ? OR description LIKE ? OR location LIKE ?)';
    $term = '%' . required_text($_GET['search'], 'Search', 150) . '%';
    array_push($values, $term, $term, $term);
}
if (($_GET['bounty'] ?? '') === 'true') $sql .= ' AND reward_amount > 0';
if (($_GET['mine'] ?? '') === 'true') {
    require_login();
    $sql .= ' AND user_id = ?';
    $values[] = $user['id'];
}
$orders = ['newest' => 'created_at DESC, id DESC', 'oldest' => 'created_at ASC, id ASC', 'reward' => 'reward_amount DESC, created_at DESC'];
$sort = required_text($_GET['sort'] ?? 'newest', 'Sort', 20);
if (!isset($orders[$sort])) problem('Invalid sort order.');
$rows = query($db, $sql . ' ORDER BY ' . $orders[$sort], $values)->fetchAll();
$items = [];
foreach ($rows as $row) {
    $private = $user && ($user['role'] === 'admin' || $user['id'] === $row['user_id']);
    $items[] = item_response($row, $private);
}
reply($items);
