<?php
// Run: php -d extension=sodium tests/integration.php
// Uses a separate temporary database, never deletes the normal project database.
if (PHP_SAPI !== 'cli') exit('Run from a terminal.');
require dirname(__DIR__) . '/scripts/setup.php';
require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/auth.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function request(string $path, string $method = 'GET', ?array $body = null, string $cookie = '', array $headers = []): array
{
    global $base;
    $curl = curl_init($base . $path);
    $responseCookie = '';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Cookie: ' . $cookie], $headers),
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseCookie) {
            if (stripos($header, 'Set-Cookie:') === 0) $responseCookie = trim(explode(';', substr($header, 11))[0]);
            return strlen($header);
        },
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    $text = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $status, 'data' => json_decode($text ?: '', true), 'text' => $text, 'cookie' => $responseCookie];
}

function account(string $name, string $email): array
{
    $response = request('/api/auth/register.php', 'POST', ['name' => $name, 'email' => $email, 'password' => 'StudentTest!2026', 'role' => 'admin']);
    check($response['status'] === 201 && $response['data']['user']['role'] === 'user', 'Registration or role protection failed.');
    return $response;
}

function passed(string $name): void { echo "PASS: $name\n"; }

$database = 'findit_php_test_' . bin2hex(random_bytes(8));
putenv('DB_NAME=' . $database);
$process = null;
$db = null;
$exitCode = 0;
try {
    setup_database();
    $db = connect_database();
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    if (!$socket) throw new RuntimeException('Could not choose a test port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $logFolder = dirname(__DIR__) . '/storage';
    if (!is_dir($logFolder)) mkdir($logFolder, 0700, true);
    $command = [PHP_BINARY];
    if (php_ini_loaded_file()) array_push($command, '-c', php_ini_loaded_file());
    // The WAMP CLI has sodium installed but disabled by default.
    if (extension_loaded('sodium')) array_push($command, '-d', 'extension=sodium');
    array_push($command, '-S', $address, '-t', dirname(__DIR__) . '/public');
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $logFolder . '/php-test.log', 'a'], 2 => ['file', $logFolder . '/php-test.log', 'a']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start PHP test server.');
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 40; $attempt++) {
        if (request('/api/health.php')['status'] === 200) break;
        usleep(100000);
    }
    check(request('/api/health.php')['data']['dbConnected'] === true, 'PHP server did not become ready.');
    foreach (['/', '/report.html', '/item.html', '/adminhome.html', '/css/common.css', '/js/common.js'] as $page) {
        check(request($page)['status'] === 200, "Missing page or asset: $page");
    }
    foreach (['/.env', '/config/database.php', '/includes/auth.php', '/database/schema.sql'] as $private) {
        check(request($private)['status'] === 404, "Private file is accessible: $private");
    }
    passed('Pages, assets, MySQL connection, private-file protection');

    $owner = account('Report Owner', 'owner@test.example');
    $finder = account('Item Finder', 'finder@test.example');
    $admin = account('Test Admin', 'admin@test.example');
    query($db, "UPDATE users SET role='admin' WHERE id=?", [$admin['data']['user']['id']]);
    check(request('/api/auth/register.php', 'POST', ['name' => 'Duplicate', 'email' => 'OWNER@test.example', 'password' => 'StudentTest!2026'])['status'] === 409, 'Duplicate email accepted.');
    $stored = query($db, 'SELECT password FROM users WHERE email=?', ['owner@test.example'])->fetchColumn();
    check(password_verify('StudentTest!2026', $stored), 'Password hash invalid.');
    check(request('/api/auth/login.php', 'POST', ['email' => 'owner@test.example', 'password' => 'wrong'])['status'] === 401, 'Wrong password accepted.');
    passed('Registration, password hashing, duplicate emails, roles and invalid login');

    $oldCookie = $owner['cookie'];
    $login = request('/api/auth/login.php', 'POST', ['email' => 'owner@test.example', 'password' => 'StudentTest!2026'], $oldCookie);
    $owner['cookie'] = $login['cookie'];
    check($login['status'] === 200 && $owner['cookie'] !== $oldCookie, 'Login did not rotate the session.');
    check(request('/api/auth/session.php', 'GET', null, $oldCookie)['data']['user'] === null, 'Old session still valid.');
    check(request('/api/stats.php', 'GET', null, $owner['cookie'])['status'] === 403, 'Normal user read admin statistics.');
    check(request('/api/items/create.php', 'POST', [])['status'] === 401, 'Guest can create reports.');
    check(request('/api/items/delete.php?id=missing', 'GET', null, $admin['cookie'])['status'] === 405, 'Delete accepted a GET request.');
    check(request('/api/auth/logout.php', 'POST', [], $owner['cookie'], ['Origin: https://other.example'])['status'] === 403, 'Cross-origin write accepted.');
    passed('Session rotation, permission checks, HTTP methods and cross-origin protection');

    $legacy = 'scrypt:0123456789abcdef0123456789abcdef:9aa2fbf23f2bf8af92cb5fb44108f4e71f653789abcfccaa6371b820ca412d595bcebf889c3888db10f1e8dc50d23cec58a34e3e38c117c4bf1cab7d06c76e66';
    check(check_password('CompatibilityTest!', $legacy), 'Node.js password compatibility failed.');
    check(!check_password('wrong', $legacy), 'Wrong legacy password accepted.');
    query($db, "INSERT INTO users (id,name,email,password) VALUES ('legacy','Legacy User','legacy@test.example',?)", [$legacy]);
    check(request('/api/auth/login.php', 'POST', ['email' => 'legacy@test.example', 'password' => 'CompatibilityTest!'])['status'] === 200, 'Legacy login failed.');
    check(password_verify('CompatibilityTest!', query($db, "SELECT password FROM users WHERE id='legacy'")->fetchColumn()), 'Legacy password was not upgraded.');
    passed('Existing Node.js passwords work and are upgraded after login');

    $item = ['title' => 'Blue backpack', 'category' => 'Bags', 'status' => 'Lost', 'location' => 'Colombo library', 'dateReported' => '2026-01-10', 'rewardAmount' => 2500, 'description' => 'Blue canvas bag with a silver star.', 'reporterContact' => '0771234567', 'requestKey' => 'report-one'];
    foreach ([['rewardAmount' => -1], ['rewardAmount' => 1.234], ['dateReported' => '2026-02-30'], ['dateReported' => '2999-01-01'], ['category' => 'Invalid'], ['description' => 'short'], ['imageData' => 'data:image/svg+xml;base64,PHN2Zz4=']] as $invalid) {
        check(request('/api/items/create.php', 'POST', array_replace($item, $invalid), $owner['cookie'])['status'] === 400, 'Invalid report accepted.');
    }
    $created = request('/api/items/create.php', 'POST', $item, $owner['cookie']);
    check($created['status'] === 201, 'Report creation failed.');
    $id = $created['data']['id'];
    check(request('/api/items/create.php', 'POST', $item, $owner['cookie'])['data']['id'] === $id, 'Duplicate report created.');
    $guest = request('/api/items/details.php?id=' . $id)['data'];
    check(!isset($guest['reporterContact']) && $guest['claims'] === [], 'Private data leaked to guest.');
    check(count(request('/api/items/list.php?search=backpack')['data']) === 1, 'Search failed.');
    check(count(request('/api/items/list.php?search=' . rawurlencode("' OR 1=1 --"))['data']) === 0, 'Search query escaped SQL placeholders.');
    passed('Report validation, database persistence, deduplication, search and privacy');

    $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF1sAAAAASUVORK5CYII=';
    $edited = array_replace($item, ['title' => 'Updated backpack', 'imageData' => $image]);
    check(request('/api/items/update.php?id=' . $id, 'POST', $edited, $finder['cookie'])['status'] === 403, 'User edited report.');
    check(request('/api/items/update.php?id=' . $id, 'POST', $edited, $admin['cookie'])['status'] === 200, 'Admin edit failed.');
    check(request('/api/items/details.php?id=' . $id)['data']['imageUrl'] === $image, 'Image did not persist.');
    passed('Admin editing and raster image storage');

    $claim = ['finderContact' => '0712345678', 'note' => 'I found the bag near the library desk.'];
    check(request('/api/claims/create.php?id=' . $id, 'POST', $claim, $owner['cookie'])['status'] === 409, 'Self claim allowed.');
    $first = request('/api/claims/create.php?id=' . $id, 'POST', $claim, $finder['cookie']);
    check($first['status'] === 201, 'Claim creation failed.');
    $claimId = $first['data']['id'];
    check(request('/api/claims/create.php?id=' . $id, 'POST', $claim, $finder['cookie'])['data']['id'] === $claimId, 'Duplicate claim created.');
    check(request('/api/items/resolve.php?id=' . $id, 'POST', [], $admin['cookie'])['status'] === 409, 'Resolved before claim review.');
    check(request('/api/claims/review.php?id=' . $claimId, 'POST', ['status' => 'Rejected'], $admin['cookie'])['status'] === 200, 'Rejection failed.');
    check(request('/api/items/details.php?id=' . $id)['data']['status'] === 'Lost', 'Rejected item did not return to Lost.');
    passed('Claim submission, duplicate protection, rejection and status restoration');

    $first = request('/api/claims/create.php?id=' . $id, 'POST', $claim, $finder['cookie']);
    $other = request('/api/claims/create.php?id=' . $id, 'POST', $claim, $admin['cookie']);
    check(request('/api/claims/review.php?id=' . $first['data']['id'], 'POST', ['status' => 'Approved'], $finder['cookie'])['status'] === 403, 'Claimant approved own claim.');
    check(request('/api/claims/review.php?id=' . $first['data']['id'], 'POST', ['status' => 'Approved'], $admin['cookie'])['status'] === 200, 'Approval failed.');
    check(request('/api/items/details.php?id=' . $id)['data']['status'] === 'Resolved', 'Approved item is not resolved.');
    check(query($db, 'SELECT status FROM claims WHERE id=?', [$other['data']['id']])->fetchColumn() === 'Rejected', 'Competing claim not rejected.');
    for ($i = 0; $i < 2; $i++) check(request('/api/items/reward.php?id=' . $id, 'POST', [], $admin['cookie'])['status'] === 200, 'Simulated payout failed.');
    $stats = request('/api/stats.php', 'GET', null, $admin['cookie'])['data'];
    check($stats['simulatedPaid'] == 2500 && $stats['pendingClaims'] == 0, 'Reward totals incorrect.');
    check(request('/api/items/delete.php?id=' . $id, 'POST', [], $admin['cookie'])['status'] === 409, 'Paid record was deleted.');
    passed('Approval, competing claims, simulated payout and dashboard totals');

    $found = request('/api/items/create.php', 'POST', array_replace($item, ['status' => 'Found', 'requestKey' => 'report-two']), $owner['cookie'])['data'];
    $foundClaim = request('/api/claims/create.php?id=' . $found['id'], 'POST', $claim, $finder['cookie'])['data'];
    request('/api/claims/review.php?id=' . $foundClaim['id'], 'POST', ['status' => 'Rejected'], $admin['cookie']);
    check(request('/api/items/details.php?id=' . $found['id'])['data']['status'] === 'Found', 'Found report did not return to Found.');
    check(request('/api/items/delete.php?id=' . $found['id'], 'POST', [], $admin['cookie'])['status'] === 200, 'Deletion failed.');
    check(query($db, 'SELECT COUNT(*) FROM claims WHERE item_id=?', [$found['id']])->fetchColumn() == 0, 'Claims not deleted with item.');
    request('/api/auth/logout.php', 'POST', [], $owner['cookie']);
    check(request('/api/auth/session.php', 'GET', null, $owner['cookie'])['data']['user'] === null, 'Logout did not invalidate the session.');
    passed('Found status restoration, deletion and logout');
    echo "All PHP integration checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if ($db) {
        // The name is generated above and validated before this destructive test cleanup.
        if (!preg_match('/^findit_php_test_[a-f0-9]{16}$/', $database)) throw new RuntimeException('Unsafe test database name.');
        $db->exec("DROP DATABASE IF EXISTS `$database`");
    }
}
exit($exitCode);
