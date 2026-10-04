<?php
function reply(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function problem(string $message, int $status = 400): never
{
    // Roll back incomplete claim/item changes before sending an error.
    global $db;
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    reply(['error' => $message], $status);
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        problem('This request method is not allowed.', 405);
    }
}

function read_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024 + 1);
    if (strlen($raw) > 2 * 1024 * 1024) problem('Request is too large.', 413);
    $body = json_decode($raw);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($body)) problem('Send a valid JSON object.');
    return (array) $body;
}

function required_text(mixed $value, string $label, int $max = 255, int $min = 1): string
{
    if (!is_string($value)) problem("$label is required.");
    $value = trim($value);
    if (mb_strlen($value) < $min || mb_strlen($value) > $max) problem("$label must contain $min to $max characters.");
    return $value;
}

function valid_email(mixed $value): string
{
    $email = strtolower(required_text($value, 'Email', 254));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) problem('Enter a valid email address.');
    return $email;
}

function valid_contact(mixed $value): string
{
    $contact = required_text($value, 'Contact', 254);
    if (!filter_var($contact, FILTER_VALIDATE_EMAIL) && !preg_match('/^\+?[0-9 ()-]{7,25}$/', $contact)) {
        problem('Enter a valid phone number or email address.');
    }
    return $contact;
}

function new_id(): string
{
    return bin2hex(random_bytes(16));
}

function categories(): array
{
    return ['Electronics', 'Wallets & Keys', 'Documents', 'Bags', 'Clothing', 'Pets', 'Other', 'Personal', 'Apparel'];
}

function valid_item(array $body): array
{
    $item = [
        'title' => required_text($body['title'] ?? null, 'Item name', 150),
        'category' => required_text($body['category'] ?? null, 'Category', 100),
        'location' => required_text($body['location'] ?? null, 'Location'),
        'description' => required_text($body['description'] ?? null, 'Description', 4000, 10),
        'reporterContact' => valid_contact($body['reporterContact'] ?? null),
        'dateReported' => required_text($body['dateReported'] ?? null, 'Date', 10),
        'status' => $body['status'] ?? '',
    ];
    if (!in_array($item['category'], categories(), true)) problem('Select a valid category.');
    if (!in_array($item['status'], ['Lost', 'Found'], true)) problem('Select Lost or Found.');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $item['dateReported']);
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
    if (!$date || $date->format('Y-m-d') !== $item['dateReported'] || $item['dateReported'] < '1900-01-01' || $item['dateReported'] > $today) {
        problem('Choose a valid date that is not in the future.');
    }
    $reward = $body['rewardAmount'] ?? 0;
    if ($reward === '') $reward = 0;
    if (!is_numeric($reward) || !is_finite((float) $reward) || $reward < 0 || $reward > 99999999.99 || abs($reward * 100 - round($reward * 100)) > 0.00001) {
        problem('Reward must be a non-negative LKR amount with at most two decimal places.');
    }
    $item['rewardAmount'] = (float) $reward;
    return $item;
}

function valid_image(mixed $value): ?string
{
    if ($value === null || $value === '') return null;
    if (!is_string($value) || strlen($value) > 1450000 || !preg_match('~^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/]+={0,2})$~', $value, $match)) {
        problem('Choose a PNG, JPEG or WebP image no larger than 1 MB.');
    }
    $bytes = base64_decode($match[2], true);
    $info = $bytes === false ? false : @getimagesizefromstring($bytes);
    if (!$info || strlen($bytes) > 1048576 || $info['mime'] !== 'image/' . $match[1]) problem('Invalid image or image exceeds 1 MB.');
    return $value;
}

function item_response(array $row, bool $private = false): array
{
    $item = [
        'id' => $row['id'], 'title' => $row['title'], 'category' => $row['category'],
        'status' => $row['status'], 'originalStatus' => $row['original_status'],
        'location' => $row['location'], 'dateReported' => $row['date_reported'],
        'rewardAmount' => (float) $row['reward_amount'], 'rewardStatus' => $row['reward_status'],
        'description' => $row['description'] ?? '', 'imageUrl' => $row['image_url'],
        'reporterName' => $row['reporter_name'],
    ];
    if ($private) {
        $item['reporterContact'] = $row['reporter_contact'];
        $item['userId'] = $row['user_id'];
    }
    return $item;
}

function claim_response(array $row): array
{
    return [
        'id' => $row['id'], 'itemId' => $row['item_id'], 'finderName' => $row['finder_name'],
        'finderContact' => $row['finder_contact'], 'note' => $row['note'],
        'status' => $row['status'], 'date' => $row['created_at'],
    ];
}

function find_item(PDO $db, string $id, bool $lock = false): array
{
    $item = query($db, 'SELECT * FROM items WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch();
    if (!$item) problem('Item not found.', 404);
    return $item;
}
