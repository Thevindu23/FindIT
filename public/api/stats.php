<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_method('GET');
require_admin();
$stats = query($db, "SELECT COUNT(*) totalItems,
    COALESCE(SUM(status IN ('Lost', 'Found', 'Claim Pending')), 0) activeItems,
    COALESCE(SUM(status = 'Resolved'), 0) resolvedItems,
    COALESCE(SUM(CASE WHEN reward_status = 'Unpaid' THEN reward_amount ELSE 0 END), 0) unpaidRewards,
    COALESCE(SUM(CASE WHEN reward_status = 'Paid (simulated)' THEN reward_amount ELSE 0 END), 0) simulatedPaid
    FROM items")->fetch();
$stats['pendingClaims'] = query($db, "SELECT COUNT(*) FROM claims WHERE status = 'Pending'")->fetchColumn();
foreach ($stats as $key => $value) $stats[$key] = (float) $value;
reply($stats);
