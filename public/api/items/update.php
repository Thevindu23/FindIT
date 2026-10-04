<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
require_admin();
$body = read_body();
$input = valid_item($body);
$id = required_text($_GET['id'] ?? null, 'Item ID', 64);
$db->beginTransaction();
$item = find_item($db, $id, true);
if ($item['status'] === 'Resolved') problem('Resolved items cannot be edited.', 409);
if ($item['status'] === 'Claim Pending' && $input['status'] !== $item['original_status']) problem('Review pending claims before changing the report type.', 409);
$image = array_key_exists('imageData', $body) ? valid_image($body['imageData']) : $item['image_url'];
$status = $item['status'] === 'Claim Pending' ? 'Claim Pending' : $input['status'];
query($db, 'UPDATE items SET title=?, category=?, location=?, date_reported=?, reward_amount=?, description=?, reporter_contact=?, image_url=?, original_status=?, status=? WHERE id=?', [
    $input['title'], $input['category'], $input['location'], $input['dateReported'], $input['rewardAmount'],
    $input['description'], $input['reporterContact'], $image, $input['status'], $status, $id,
]);
$db->commit();
reply(['success' => true]);
