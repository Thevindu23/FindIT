<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');
require_admin();
$rows = query($db, 'SELECT c.*, i.title FROM claims c JOIN items i ON i.id = c.item_id ORDER BY c.created_at DESC')->fetchAll();
$claims = [];
foreach ($rows as $row) {
    $claims[] = claim_response($row) + ['itemTitle' => $row['title']];
}
reply($claims);
